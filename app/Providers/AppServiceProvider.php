<?php

namespace App\Providers;

use App\Models\AnalyticsReport;
use App\Models\AuditLog;
use App\Models\EmailTemplate;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ReservationDocument;
use App\Models\ReservationStatusHistory;
use App\Models\ScheduleBlock;
use App\Models\SystemSetting;
use App\Models\User;
use App\Observers\AuditObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            User::class, Reservation::class, ReservationDocument::class,
            ReservationStatusHistory::class, Facility::class, Equipment::class,
            ScheduleBlock::class, SystemSetting::class, EmailTemplate::class,
            AnalyticsReport::class,
        ] as $model) {
            $model::observe(AuditObserver::class);
        }

        Event::listen(Login::class, fn (Login $event) => AuditLog::recordEvent('authentication.login', $event->user, null, ['guard' => $event->guard], actor: $event->user));
        Event::listen(Logout::class, fn (Logout $event) => AuditLog::recordEvent('authentication.logout', $event->user, null, ['guard' => $event->guard], actor: $event->user));
        Event::listen(Failed::class, fn (Failed $event) => AuditLog::recordEvent('authentication.failed', $event->user, null, ['email' => $event->credentials['email'] ?? null], actor: $event->user));
    }
}
