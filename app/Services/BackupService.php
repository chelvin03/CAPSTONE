<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BackupRecord;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

class BackupService
{
    private const FORMAT_VERSION = 1;

    private const EXCLUDED_TABLES = [
        'backup_records', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'sessions', 'migrations',
    ];

    public function create(?int $userId = null, string $type = 'manual'): BackupRecord
    {
        $disk = (string) config('gym.backup.disk', 'local');
        $directory = trim((string) config('gym.backup.directory', 'backups'), '/');
        $filename = 'mcst-gym-'.now()->format('Y-m-d_His').'-'.strtolower(bin2hex(random_bytes(3))).'.mcstbak';
        $path = $directory.'/'.$filename;
        $temporaryZip = $this->temporaryPath('mcst-backup-', '.zip');

        try {
            $tables = $this->databaseSnapshot();
            $databaseJson = json_encode($tables, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $files = $this->sourceFiles($directory);

            $manifest = [
                'format' => 'mcst-gym-backup',
                'version' => self::FORMAT_VERSION,
                'created_at' => now()->toIso8601String(),
                'application' => (string) config('app.name'),
                'database' => [
                    'sha256' => hash('sha256', $databaseJson),
                    'tables' => collect($tables)->map(fn (array $rows) => count($rows))->all(),
                ],
                'files' => [],
            ];

            $zip = new ZipArchive;
            if ($zip->open($temporaryZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create the backup archive.');
            }

            $zip->addFromString('database.json', $databaseJson);
            foreach ($files as $file) {
                $contents = Storage::disk('local')->get($file);
                $archivePath = 'files/'.$file;
                $zip->addFromString($archivePath, $contents);
                $manifest['files'][$file] = hash('sha256', $contents);
            }
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            $zip->close();

            $encrypted = Crypt::encryptString(base64_encode((string) file_get_contents($temporaryZip)));
            if (! Storage::disk($disk)->put($path, $encrypted)) {
                throw new RuntimeException('Unable to write the encrypted backup file.');
            }

            $record = BackupRecord::create([
                'filename' => $filename,
                'disk' => $disk,
                'path' => $path,
                'size_bytes' => Storage::disk($disk)->size($path),
                'checksum' => hash('sha256', $encrypted),
                'table_count' => count($tables),
                'row_count' => collect($tables)->sum(fn (array $rows) => count($rows)),
                'file_count' => count($files),
                'status' => 'ready',
                'type' => $type,
                'created_by' => $userId,
                'verified_at' => now(),
            ]);

            $this->pruneExpired();

            return $record;
        } finally {
            @unlink($temporaryZip);
        }
    }

    public function verify(BackupRecord $backup): array
    {
        if (! Storage::disk($backup->disk)->exists($backup->path)) {
            throw new RuntimeException('The backup file is missing from storage.');
        }

        $encrypted = Storage::disk($backup->disk)->get($backup->path);
        if (! hash_equals($backup->checksum, hash('sha256', $encrypted))) {
            throw new RuntimeException('The encrypted archive checksum does not match.');
        }

        $result = $this->inspectEncryptedArchive($encrypted);
        $backup->update(['status' => 'ready', 'verified_at' => now(), 'last_error' => null]);

        return $result;
    }

    public function restore(BackupRecord $backup): array
    {
        $encrypted = Storage::disk($backup->disk)->get($backup->path);
        if (! hash_equals($backup->checksum, hash('sha256', $encrypted))) {
            throw new RuntimeException('Restore stopped because the archive checksum is invalid.');
        }

        $inspected = $this->inspectEncryptedArchive($encrypted, true);
        $temporaryZip = $inspected['temporary_zip'];
        $rollbackZip = $this->temporaryPath('mcst-rollback-', '.zip');
        $directory = trim((string) config('gym.backup.directory', 'backups'), '/');

        try {
            $this->snapshotCurrentFiles($rollbackZip, $directory);
            $this->replaceFilesFromArchive($temporaryZip, array_keys($inspected['manifest']['files']), $directory);

            try {
                $this->restoreDatabase($inspected['database']);
            } catch (Throwable $exception) {
                $this->restoreFilesFromSnapshot($rollbackZip, $directory);
                throw $exception;
            }

            $backup->update(['status' => 'restored', 'restored_at' => now(), 'last_error' => null]);

            return $inspected['manifest'];
        } finally {
            @unlink($temporaryZip);
            @unlink($rollbackZip);
        }
    }

    public function import(string $uploadedPath, ?int $userId = null): BackupRecord
    {
        $encrypted = (string) file_get_contents($uploadedPath);
        $inspected = $this->inspectEncryptedArchive($encrypted);
        $disk = (string) config('gym.backup.disk', 'local');
        $directory = trim((string) config('gym.backup.directory', 'backups'), '/');
        $filename = 'imported-'.now()->format('Y-m-d_His').'-'.strtolower(bin2hex(random_bytes(3))).'.mcstbak';
        $path = $directory.'/'.$filename;
        Storage::disk($disk)->put($path, $encrypted);

        return BackupRecord::create([
            'filename' => $filename,
            'disk' => $disk,
            'path' => $path,
            'size_bytes' => strlen($encrypted),
            'checksum' => hash('sha256', $encrypted),
            'table_count' => count($inspected['manifest']['database']['tables']),
            'row_count' => array_sum($inspected['manifest']['database']['tables']),
            'file_count' => count($inspected['manifest']['files']),
            'status' => 'ready',
            'type' => 'imported',
            'created_by' => $userId,
            'verified_at' => now(),
        ]);
    }

    public function delete(BackupRecord $backup): void
    {
        Storage::disk($backup->disk)->delete($backup->path);
        $backup->delete();
    }

    public function pruneExpired(): int
    {
        $cutoff = now()->subDays(max(1, (int) config('gym.backup.retention_days', 30)));
        $expired = BackupRecord::query()->where('created_at', '<', $cutoff)->get();
        foreach ($expired as $backup) {
            $this->delete($backup);
        }

        return $expired->count();
    }

    private function databaseSnapshot(): array
    {
        $tables = array_values(array_filter(
            Schema::getTableListing(),
            fn (string $table) => ! $this->isExcludedTable($table)
        ));
        sort($tables);
        $snapshot = [];
        foreach ($tables as $table) {
            $snapshot[$table] = DB::table($table)->get()->map(fn (object $row) => (array) $row)->all();
        }

        return $snapshot;
    }

    private function sourceFiles(string $backupDirectory): array
    {
        return collect(Storage::disk('local')->allFiles())
            ->reject(fn (string $path) => $path === $backupDirectory || str_starts_with($path, $backupDirectory.'/'))
            ->values()
            ->all();
    }

    private function inspectEncryptedArchive(string $encrypted, bool $keepTemporary = false): array
    {
        try {
            $decoded = base64_decode(Crypt::decryptString($encrypted), true);
        } catch (Throwable $exception) {
            throw new RuntimeException('The backup cannot be decrypted with this application key.', 0, $exception);
        }
        if ($decoded === false) {
            throw new RuntimeException('The backup payload is invalid.');
        }

        $temporaryZip = $this->temporaryPath('mcst-verify-', '.zip');
        file_put_contents($temporaryZip, $decoded);
        $zip = new ZipArchive;

        try {
            if ($zip->open($temporaryZip) !== true) {
                throw new RuntimeException('The decrypted backup is not a valid ZIP archive.');
            }
            $manifestJson = $zip->getFromName('manifest.json');
            $databaseJson = $zip->getFromName('database.json');
            if ($manifestJson === false || $databaseJson === false) {
                throw new RuntimeException('The backup manifest or database snapshot is missing.');
            }
            $manifest = json_decode($manifestJson, true, flags: JSON_THROW_ON_ERROR);
            $database = json_decode($databaseJson, true, flags: JSON_THROW_ON_ERROR);
            if (($manifest['format'] ?? null) !== 'mcst-gym-backup' || ($manifest['version'] ?? null) !== self::FORMAT_VERSION) {
                throw new RuntimeException('This backup format is not supported.');
            }
            if (! hash_equals((string) $manifest['database']['sha256'], hash('sha256', $databaseJson))) {
                throw new RuntimeException('Database integrity verification failed.');
            }
            foreach ($manifest['files'] as $path => $checksum) {
                if (str_contains((string) $path, '..') || str_starts_with((string) $path, '/') || str_starts_with((string) $path, '\\')) {
                    throw new RuntimeException('The backup contains an unsafe file path.');
                }
                $contents = $zip->getFromName('files/'.$path);
                if ($contents === false || ! hash_equals((string) $checksum, hash('sha256', $contents))) {
                    throw new RuntimeException('File integrity verification failed for '.$path.'.');
                }
            }
            $result = ['manifest' => $manifest, 'database' => $database];
            if ($keepTemporary) {
                $result['temporary_zip'] = $temporaryZip;
            }

            return $result;
        } finally {
            if ($zip->status === ZipArchive::ER_OK) {
                $zip->close();
            }
            if (! $keepTemporary) {
                @unlink($temporaryZip);
            }
        }
    }

    private function restoreDatabase(array $database): void
    {
        $driver = DB::getDriverName();
        $toggle = fn (bool $enabled) => match ($driver) {
            'mysql', 'mariadb' => DB::statement('SET FOREIGN_KEY_CHECKS='.(int) $enabled),
            'sqlite' => DB::statement('PRAGMA foreign_keys = '.($enabled ? 'ON' : 'OFF')),
            default => null,
        };

        $toggle(false);
        try {
            DB::transaction(function () use ($database): void {
                foreach (array_reverse(array_keys($database)) as $table) {
                    if (! $this->isExcludedTable($table) && Schema::hasTable($table)) {
                        DB::table($table)->delete();
                    }
                }
                foreach ($database as $table => $rows) {
                    if ($this->isExcludedTable($table) || ! Schema::hasTable($table)) {
                        continue;
                    }
                    foreach (array_chunk($rows, 250) as $chunk) {
                        if ($chunk !== []) {
                            DB::table($table)->insert($chunk);
                        }
                    }
                }
            }, 3);
        } finally {
            $toggle(true);
        }
    }

    private function snapshotCurrentFiles(string $zipPath, string $backupDirectory): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to prepare the recovery rollback point.');
        }
        foreach ($this->sourceFiles($backupDirectory) as $file) {
            $zip->addFromString('files/'.$file, Storage::disk('local')->get($file));
        }
        $zip->close();
    }

    private function replaceFilesFromArchive(string $zipPath, array $files, string $backupDirectory): void
    {
        foreach ($this->sourceFiles($backupDirectory) as $file) {
            Storage::disk('local')->delete($file);
        }
        $zip = new ZipArchive;
        $zip->open($zipPath);
        foreach ($files as $file) {
            $contents = $zip->getFromName('files/'.$file);
            if ($contents === false || str_contains($file, '..') || str_starts_with($file, '/')) {
                throw new RuntimeException('The backup contains an unsafe or missing file path.');
            }
            Storage::disk('local')->put($file, $contents);
        }
        $zip->close();
    }

    private function restoreFilesFromSnapshot(string $zipPath, string $backupDirectory): void
    {
        foreach ($this->sourceFiles($backupDirectory) as $file) {
            Storage::disk('local')->delete($file);
        }
        $zip = new ZipArchive;
        $zip->open($zipPath);
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if (str_starts_with($name, 'files/')) {
                Storage::disk('local')->put(substr($name, 6), (string) $zip->getFromIndex($index));
            }
        }
        $zip->close();
    }

    private function temporaryPath(string $prefix, string $suffix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false) {
            throw new RuntimeException('Unable to allocate temporary backup storage.');
        }
        $renamed = $path.$suffix;
        rename($path, $renamed);

        return $renamed;
    }

    private function isExcludedTable(string $table): bool
    {
        $name = trim((string) str($table)->afterLast('.'), '`"[]');

        return in_array($name, self::EXCLUDED_TABLES, true);
    }
}
