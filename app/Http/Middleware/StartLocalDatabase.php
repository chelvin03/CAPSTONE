<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class StartLocalDatabase
{
    public function handle(Request $request, Closure $next)
    {
        $connection = config('database.connections.'.config('database.default'));

        // Only manage local XAMPP, never a deployed or test database.
        if (! app()->environment('local') || PHP_OS_FAMILY !== 'Windows'
            || ($connection['driver'] ?? null) !== 'mysql'
            || ! empty($connection['url'])
            || ! in_array($connection['host'] ?? null, ['127.0.0.1', 'localhost'], true)
            || (int) ($connection['port'] ?? 3306) !== 3306) {
            return $next($request);
        }

        if (! $this->isListening()) {
            $lock = fopen(storage_path('framework/local-mysql.lock'), 'c');
            if ($lock === false) {
                return response('Unable to prepare the local database. Please try again.', 503);
            }

            try {
                flock($lock, LOCK_EX);
                if (! $this->isListening()) {
                    $process = new Process([
                        'powershell.exe', '-NoProfile', '-NonInteractive',
                        '-ExecutionPolicy', 'Bypass', '-File',
                        base_path('scripts/start-local-mysql.ps1'),
                    ]);
                    // PHP's development server can omit these from its child environment.
                    $process->setEnv([
                        'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
                        'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
                        'SystemDrive' => getenv('SystemDrive') ?: 'C:',
                    ]);
                    $process->setTimeout(25);
                    $process->mustRun();
                }
            } catch (\Throwable $exception) {
                report($exception);

                return response('The local database could not start. Please check MySQL in XAMPP and refresh this page.', 503);
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        return $next($request);
    }

    private function isListening(): bool
    {
        $socket = @fsockopen('127.0.0.1', 3306, $errorCode, $errorMessage, 0.2);
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    }
}
