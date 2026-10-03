<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Models\SystemSetting;
use App\Models\AuditLog;
use App\Services\BackupService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reservations:auto-cancel-pending', function () {
    $cutoff = now()->subHours((int) SystemSetting::getValue('pending_auto_cancel_hours', 72));
    $reservations = Reservation::whereIn('status', ['new', 'pending'])->where('created_at', '<=', $cutoff)->get();
    foreach ($reservations as $reservation) {
        $previous = $reservation->status;
        $reservation->update(['status' => 'cancelled', 'cancellation_reason' => 'Automatically cancelled after the pending timeout.', 'cancelled_at' => now()]);
        ReservationStatusHistory::create(['reservation_id' => $reservation->id, 'changed_by' => null, 'previous_status' => $previous, 'new_status' => 'cancelled', 'remarks' => 'Automatically cancelled after the configured pending timeout.']);
    }
    $this->info($reservations->count().' pending reservation(s) cancelled.');
})->purpose('Cancel reservations that exceed the configured pending timeout');

Schedule::command('reservations:auto-cancel-pending')->hourly();

Artisan::command('backup:create {--scheduled}', function (BackupService $backups) {
    $record = $backups->create(null, $this->option('scheduled') ? 'scheduled' : 'command');
    $this->info('Backup created and verified: '.$record->filename);
})->purpose('Create an encrypted full database and file backup');

Schedule::command('backup:create --scheduled')
    ->dailyAt((string) config('gym.backup.schedule_time', '02:00'))
    ->withoutOverlapping()
    ->onOneServer();

Artisan::command('audit:verify', function () {
    $result = AuditLog::verifyChain();
    if (! $result['valid']) {
        $this->error('Audit integrity failed at record #'.$result['failed_id'].'.');
        return self::FAILURE;
    }
    $this->info('Audit integrity verified: '.$result['checked'].' record(s) checked.');
    return self::SUCCESS;
})->purpose('Verify the tamper-evident audit log hash chain');
