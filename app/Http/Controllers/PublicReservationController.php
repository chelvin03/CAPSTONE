<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Models\ScheduleBlock;
use App\Models\SystemSetting;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicReservationController extends Controller
{
    public function availability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'facility_id' => ['required', 'exists:facilities,id'],
            'date' => ['required', 'date'],
        ]);

        $occupied = Reservation::query()
            ->where('facility_id', $validated['facility_id'])
            ->whereDate('reservation_date', $validated['date'])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->orderBy('start_time')
            ->get(['start_time', 'end_time', 'status']);
        $blocks = ScheduleBlock::query()->whereDate('starts_on', '<=', $validated['date'])->whereDate('ends_on', '>=', $validated['date'])
            ->where(fn ($query) => $query->whereNull('facility_id')->orWhere('facility_id', $validated['facility_id']))
            ->get()->map(fn ($block) => ['start_time' => $block->start_time ?? '00:00:00', 'end_time' => $block->end_time ?? '23:59:59', 'status' => 'blackout']);
        return response()->json($occupied->concat($blocks)->values());
    }

    public function create(): View
    {
        $facilities = Facility::query()
            ->where('status', 'available')
            ->orderBy('facility_name')
            ->get();

        $minimumNoticeDays = max(0, (int) SystemSetting::getValue('minimum_notice_days', config('gym.reservation.minimum_notice_days', 3)));

        return view('public.reservations.create', compact('facilities', 'minimumNoticeDays'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'facility_id' => ['required', 'exists:facilities,id'],
            'reservation_type' => ['required', 'in:student,faculty,organization,community'],
            'event_name' => ['required', 'string', 'max:255'],
            'event_type' => ['nullable', 'string', 'max:100'],
            'purpose' => ['required', 'string'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email:rfc', 'max:255'],
            'contact_number' => ['required', 'digits:11'],
            'expected_attendees' => ['required', 'integer', 'min:1'],
            'reservation_date' => ['required', 'date', 'after_or_equal:'.now()->addDays(max(0, (int) SystemSetting::getValue('minimum_notice_days', config('gym.reservation.minimum_notice_days', 3))))->toDateString(), 'before_or_equal:'.now()->addDays((int) SystemSetting::getValue('maximum_advance_days', 365))->toDateString()],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'setup_time' => ['nullable', 'date_format:H:i'],
            'cleanup_time' => ['nullable', 'date_format:H:i'],
            'permit' => [
                'required',
                'file',
                'mimes:'.implode(',', config('gym.documents.allowed_extensions')),
                'max:'.config('gym.documents.max_size_kb'),
            ],
            'agreement' => ['accepted'],
            'requested_equipment' => ['nullable', 'string', 'max:255', 'required_with:requested_equipment_quantity'],
            'requested_equipment_quantity' => ['nullable', 'integer', 'min:1', 'required_with:requested_equipment'],

        ]);

        $durationMinutes = (strtotime($validated['end_time']) - strtotime($validated['start_time'])) / 60;
        if ($durationMinutes > ((int) SystemSetting::getValue('maximum_booking_hours', 8) * 60)) {
            return back()->withInput()->withErrors(['end_time' => 'The booking exceeds the maximum allowed duration.']);
        }

        $hasConflict = Reservation::query()
            ->where('facility_id', $validated['facility_id'])
            ->whereDate('reservation_date', $validated['reservation_date'])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->where(function ($query) use ($validated): void {
                $query
                    ->where('start_time', '<', $validated['end_time'])
                    ->where('end_time', '>', $validated['start_time']);
            })
            ->exists();

        if ($hasConflict) {
            return back()
                ->withInput()
                ->withErrors([
                    'reservation_date' => 'The selected facility is unavailable during the chosen schedule.',
                ]);
        }

        $hasBlackout = ScheduleBlock::query()->whereDate('starts_on', '<=', $validated['reservation_date'])->whereDate('ends_on', '>=', $validated['reservation_date'])
            ->where(fn ($query) => $query->whereNull('facility_id')->orWhere('facility_id', $validated['facility_id']))
            ->where(fn ($query) => $query->whereNull('start_time')->orWhere(fn ($times) => $times->where('start_time', '<', $validated['end_time'])->where('end_time', '>', $validated['start_time'])))->exists();
        if ($hasBlackout) return back()->withInput()->withErrors(['reservation_date' => 'The selected date or time is blocked by the administrator.']);

        $storedPermitPath = null;

        try {
            $reservation = DB::transaction(function () use ($validated, &$storedPermitPath): Reservation {
                $reservation = Reservation::create([
                    'reference_number' => $this->generateReferenceNumber(),
                    'user_id' => null,
                    'facility_id' => $validated['facility_id'],
                    'reservation_type' => $validated['reservation_type'],
                    'event_name' => $validated['event_name'],
                    'event_type' => $validated['event_type'] ?? null,
                    'purpose' => $validated['purpose'],
                    'contact_person' => $validated['contact_person'],
                    'contact_email' => $validated['contact_email'],
                    'contact_number' => $validated['contact_number'],
                    'expected_attendees' => $validated['expected_attendees'],
                    'reservation_date' => $validated['reservation_date'],
                    'start_time' => $validated['start_time'],
                    'end_time' => $validated['end_time'],
                    'setup_time' => $validated['setup_time'] ?? null,
                    'cleanup_time' => $validated['cleanup_time'] ?? null,
                    'requested_equipment' => $validated['requested_equipment'] ?? null,
                    'requested_equipment_quantity' => $validated['requested_equipment_quantity'] ?? null,
                    'status' => 'new',
                ]);

                $permit = $validated['permit'];
                $storedFilename = Str::uuid().'.'.$permit->extension();
                $directory = trim(config('gym.documents.directory'), '/').
                    '/'.$reservation->reference_number;
                $storedPermitPath = $permit->storeAs(
                    $directory,
                    $storedFilename,
                    config('gym.documents.disk')
                );

                $reservation->documents()->create([
                    'uploaded_by' => null,
                    'document_type' => 'permit',
                    'original_filename' => $permit->getClientOriginalName(),
                    'stored_filename' => $storedFilename,
                    'file_path' => $storedPermitPath,
                    'mime_type' => $permit->getMimeType() ?? $permit->getClientMimeType(),
                    'file_size' => $permit->getSize(),
                ]);

                ReservationStatusHistory::create([
                    'reservation_id' => $reservation->id,
                    'changed_by' => null,
                    'previous_status' => null,
                    'new_status' => 'new',
                    'remarks' => 'Reservation submitted by requestor.',
                ]);

                return $reservation;
            });
        } catch (\Throwable $exception) {
            if ($storedPermitPath !== null) {
                Storage::disk(config('gym.documents.disk'))->delete($storedPermitPath);
            }

            throw $exception;
        }

        try {
            $trackingUrl = route('reservation.track', ['reference' => $reservation->reference_number]);
            $template = EmailTemplate::where('key', 'booking_confirmation')->first();
            $body = str_replace(['{reference}', '{tracking_link}'], [$reservation->reference_number, $trackingUrl], $template?->body ?? "Your MCST Gym reservation was submitted successfully.\n\nReference code: {reference}\nTrack your reservation: {tracking_link}");
            Mail::raw(
                $body,
                fn ($message) => $message
                    ->to($reservation->contact_email)
                    ->subject($template?->subject ?? 'MCST Gym Reservation Confirmation')
            );
        } catch (\Throwable $exception) {
            Log::error('Reservation confirmation email delivery failed.', [
                'reservation_id' => $reservation->id,
                'exception' => $exception,
            ]);
        }

        return redirect()
            ->route('reservation.success', $reservation->reference_number);
    }

    public function sendVerificationCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc'],
        ]);

        $email = (string) $validated['email'];
        $code = random_int(100000, 999999);
        $key = 'reservation:email_code:'.sha1($email);
        $verifiedKey = 'reservation:email_verified:'.sha1($email);

        Cache::forget($verifiedKey);
        Cache::put($key, (string) $code, now()->addMinutes(10));

        try {
            Mail::raw(
                "Your MCST Gym reservation verification code is: {$code}",
                fn ($message) => $message->to($email)->subject('MCST Reservation Verification Code')
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send reservation verification code', ['email' => $email, 'exception' => $e]);
            return response()->json(['message' => 'Failed to send verification code. Please try again later.'], 500);
        }

        return response()->json(['message' => 'Verification code sent.']);
    }

    public function verifyVerificationCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc'],
            'code' => ['required', 'string'],
        ]);

        $email = (string) $validated['email'];
        $code = (string) $validated['code'];
        $key = 'reservation:email_code:'.sha1($email);
        $cached = Cache::get($key);

        if ($cached && hash_equals((string) $cached, $code)) {
            Cache::forget($key);
            $verifiedKey = 'reservation:email_verified:'.sha1($email);
            Cache::put($verifiedKey, true, now()->addMinutes(30));
            return response()->json(['verified' => true]);
        }

        return response()->json(['verified' => false], 422);
    }

    public function success(string $referenceNumber): View
    {
        $reservation = Reservation::query()
            ->with('facility')
            ->where('reference_number', $referenceNumber)
            ->firstOrFail();

        return view(
            'public.reservations.success',
            compact('reservation')
        );
    }

    public function track(Request $request): View
    {
        $reference = Str::upper(trim((string) $request->query('reference', '')));
        $reservation = null;

        if ($reference !== '') {
            $request->validate(['reference' => ['string', 'max:50']]);
            $reservation = Reservation::with(['facility', 'statusHistories'])
                ->where('reference_number', $reference)
                ->first();
        }

        return view('public.reservations.track', compact('reference', 'reservation'));
    }

    private function generateReferenceNumber(): string
    {
        do {
            $reference = 'MCST-'.
                now()->format('Ymd').
                '-'.
                Str::upper(Str::random(6));
        } while (
            Reservation::query()
                ->where('reference_number', $reference)
                ->exists()
        );

        return $reference;
    }
}
