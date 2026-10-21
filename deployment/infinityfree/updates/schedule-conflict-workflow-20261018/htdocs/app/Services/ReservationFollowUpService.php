<?php

namespace App\Services;

use App\Models\{AuditLog, Reservation, ReservationReminder, ScheduleProposal, User};
use Illuminate\Support\Facades\{DB, Log, Mail, URL};
use Illuminate\Validation\ValidationException;

class ReservationFollowUpService
{
    public function candidates()
    {
        $rows = collect();
        Reservation::whereIn('status', ['approved', 'new', 'pending', 'validated'])->whereDate('reservation_date', '>=', now()->toDateString())
            ->with('documents')->orderBy('reservation_date')->get()->each(function ($r) use ($rows) {
                foreach (['event', 'requirements'] as $kind) if ($row = $this->candidate($kind, $r->id, $r)) $rows->push($row);
            });
        ScheduleProposal::where('status', 'pending')->where('expires_at', '>', now())->where('created_at', '<=', now()->subHours(12))
            ->with('reservation')->get()->each(function ($proposal) use ($rows) {
                if ($row = $this->candidate('proposal', $proposal->id, $proposal)) $rows->push($row);
            });
        $delivery = ReservationReminder::whereIn('fingerprint', $rows->pluck('fingerprint'))->get()->keyBy('fingerprint');
        return $rows->map(fn ($row) => $row + ['delivery' => $delivery->get($row['fingerprint'])]);
    }

    public function candidate(string $kind, int $id, $record = null): ?array
    {
        $proposal = $kind === 'proposal' ? ($record ?? ScheduleProposal::find($id)) : null;
        $r = $kind === 'proposal' ? $proposal?->reservation : ($record ?? Reservation::with('documents')->find($id));
        if (!$r || !filter_var($r->contact_email, FILTER_VALIDATE_EMAIL)) return null;
        $start = $r->reservation_date->copy()->startOfDay()->addMinutes((int) substr($r->start_time, 0, 2) * 60 + (int) substr($r->start_time, 3, 2));
        $link = route('reservation.track', ['reference' => $r->reference_number]);
        if ($kind === 'event') {
            if ($r->status !== 'approved' || !$start->isFuture() || $start->gt(now()->addHours(24))) return null;
            $title = 'Upcoming approved event';
            $message = 'Reminder: your approved event starts within 24 hours. Please review the confirmed schedule and equipment arrangements.';
        } elseif ($kind === 'requirements') {
            if (!in_array($r->status, ['new', 'pending', 'validated'], true) || !$r->agreement_accepted || !$start->isFuture()
                || $r->created_at->gt(now()->subHours(24)) || $r->documents->contains(fn ($d) => $d->document_type === 'permit' && filled($d->file_path))) return null;
            $title = 'Supporting document follow-up';
            $message = 'Your request is still awaiting review and no supporting approval letter is recorded. Please contact the gym administrator to provide or verify the required document. Your booking is not yet confirmed.';
        } elseif ($kind === 'proposal') {
            if (!$proposal || $proposal->status !== 'pending' || $proposal->expires_at->lte(now()) || $proposal->created_at->gt(now()->subHours($proposal->kind === 'waiting_list' ? 12 : 24))
                || $proposal->original !== app(ScheduleProposalService::class)->snapshot($r)
                || in_array($r->status, ['cancelled', 'rejected', 'completed', 'no_show'], true)) return null;
            $title = 'Schedule offer awaiting your reply';
            $message = 'Please Accept or Decline the schedule offer before '.$proposal->expires_at->format('F j, Y g:i A').' ('.config('app.timezone').'). The offered slot is not held and availability is checked again on acceptance. Your existing reservation has not changed.';
            $link = URL::temporarySignedRoute('reservation.schedule-proposal', $proposal->expires_at, ['proposal' => $proposal->id]);
        } else return null;
        $fingerprint = hash('sha256', json_encode([$kind, $id, app(ScheduleProposalService::class)->snapshot($r), $proposal?->target]));
        return ['kind' => $kind, 'id' => $id, 'reservation' => $r, 'recipient' => $r->contact_email,
            'title' => $title, 'message' => $message, 'link' => $link, 'fingerprint' => $fingerprint];
    }

    public function send(string $kind, int $id, string $fingerprint, User $admin): void
    {
        abort_unless($admin->role === 'admin' && $admin->status === 'approved', 403);
        $candidate = $this->candidate($kind, $id);
        if (!$candidate || !hash_equals($candidate['fingerprint'], $fingerprint)) throw ValidationException::withMessages(['reminder' => 'This follow-up changed or is no longer due. Refresh the list before sending.']);
        DB::transaction(function () use ($kind, $id, $fingerprint, $candidate, $admin) {
            Reservation::whereKey($candidate['reservation']->id)->lockForUpdate()->firstOrFail();
            $fresh = $this->candidate($kind, $id);
            if (!$fresh || !hash_equals($fresh['fingerprint'], $fingerprint)) throw ValidationException::withMessages(['reminder' => 'The booking changed. Review the current follow-up first.']);
            $existing = ReservationReminder::where('fingerprint', $fingerprint)->lockForUpdate()->first();
            if ($existing && ($existing->status !== 'failed' || $existing->attempted_at->gt(now()->subHour()))) {
                throw ValidationException::withMessages(['reminder' => 'Already sent or being sent. Failed attempts may be retried after one hour.']);
            }
            $record = ReservationReminder::updateOrCreate(['fingerprint' => $fingerprint], ['reservation_id' => $fresh['reservation']->id,
                'kind' => $kind, 'recipient' => $fresh['recipient'], 'status' => 'sending', 'attempted_at' => now(), 'sent_at' => null,
                'attempts' => ($existing?->attempts ?? 0) + 1, 'sent_by' => $admin->id]);
            AuditLog::record('reservation.reminder_requested', $fresh['reservation'], ['kind' => $kind, 'reminder_id' => $record->id]);
            DB::afterCommit(function () use ($record, $kind, $id, $fingerprint) {
                $current = $this->candidate($kind, $id);
                if (!$current || !hash_equals($current['fingerprint'], $fingerprint)) { $record->update(['status' => 'skipped']); return; }
                try {
                    Mail::to($current['recipient'])->send(new \App\Mail\ReservationFollowUp($current));
                    $record->update(['status' => 'sent', 'sent_at' => now()]);
                } catch (\Throwable $exception) {
                    $record->update(['status' => 'failed']);
                    Log::error('Reservation reminder failed.', ['reminder_id' => $record->id, 'exception_type' => get_class($exception)]);
                }
            });
        });
    }
}
