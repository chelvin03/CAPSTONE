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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use App\Mail\PublicRegistrationCode;
use App\Mail\ReservationSubmitted;
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
            'date' => ['required_without:month', 'date'],
            'month' => ['sometimes', 'required', 'date_format:Y-m'],
        ]);

        if (isset($validated['month'])) {
            return response()->json(app(\App\Services\PublicCalendarService::class)->month(
                Facility::findOrFail($validated['facility_id']), $validated['month']
            ));
        }

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

    public function create(Request $request): View|RedirectResponse
    {
        if ((int) $request->query('step') === 1) {
            $request->session()->forget(['public_email_verified', 'public_email_challenge', 'public_reservation_details', '_old_input']);
        }

        $emailVerified = $this->emailIsVerified($request);
        if ((int) $request->query('step') === 3 && ! $emailVerified) {
            return redirect()->route('reservation.create')->withErrors(['contact_email' => 'Please verify your email before continuing to Event Information.']);
        }
        $requestorDetails = $request->session()->get('public_reservation_details', []);
        $initialStep = $emailVerified ? 3 : ($request->session()->has('public_email_challenge') ? 2 : 1);
        $facilities = Facility::query()
            ->orderBy('facility_name')
            ->get();

        $minimumNoticeDays = max(0, (int) SystemSetting::getValue('minimum_notice_days', config('gym.reservation.minimum_notice_days', 3)));
        $equipment = \App\Models\Equipment::where('status', 'available')->orderBy('equipment_name')->get();

        return view('public.reservations.create', compact('facilities', 'equipment', 'minimumNoticeDays', 'emailVerified', 'requestorDetails', 'initialStep'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'facility_id' => ['required', 'exists:facilities,id'],
            'reservation_type' => ['required', 'in:internal,external'],
            'event_name' => ['required', 'string', 'max:255'],
            'event_type' => ['nullable', 'string', 'max:100'],
            'purpose' => ['required', 'string'],
            'organization_department' => ['nullable', 'string', 'max:255'],
            'additional_notes' => ['nullable', 'string', 'max:5000'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email:rfc', 'max:255'],
            'contact_number' => ['required', 'digits:11'],
            'expected_attendees' => \App\Support\GymCapacity::rules(),
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
            'agreement_accepted' => ['accepted'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['array:quantity_requested'],
            'equipment.*.quantity_requested' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'requested_equipment' => ['nullable', 'string', 'max:255', 'required_with:requested_equipment_quantity'],
            'requested_equipment_quantity' => ['nullable', 'integer', 'min:1', 'required_with:requested_equipment'],

        ], [
            'agreement_accepted.accepted' => 'Please read and accept the MCST Gymnasium Use Agreement before submitting your reservation request.',
            'expected_attendees.max' => \App\Support\GymCapacity::ERROR,
            'reservation_type.required' => 'Please select a requestor type.',
        ]);

        if (! $this->emailIsVerified($request, $validated['contact_email'])) {
            $request->session()->forget(['public_email_verified', 'public_email_challenge']);
            return back()->withInput()->withErrors([
                'contact_email' => 'Please verify this email address before submitting the reservation.',
            ]);
        }

        if ($request->session()->has('public_reservation_details.reservation_type') && $request->session()->get('public_reservation_details.reservation_type') !== $validated['reservation_type']) {
            return back()->withInput()->withErrors(['reservation_type' => 'Please verify your requestor details again after changing Requestor Type.']);
        }
        app(\App\Services\EquipmentRequestService::class)->validateRequests($validated['reservation_type'], $validated['equipment'] ?? [], $validated['requested_equipment'] ?? null);

        if (Facility::findOrFail($validated['facility_id'])->status !== 'available') {
            return back()->withInput()->withErrors(['facility_id' => 'The gymnasium is currently unavailable.']);
        }

        if ($validated['start_time'] < '08:00' || $validated['end_time'] > '21:00') {
            return back()->withInput()->withErrors(['start_time' => 'Reservations must be within 8:00 AM - 9:00 PM.']);
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
                    'reservation_date' => 'Selected time overlaps with an existing reservation. Please choose from the remaining available time.',
                ]);
        }

        $hasBlackout = ScheduleBlock::query()->whereDate('starts_on', '<=', $validated['reservation_date'])->whereDate('ends_on', '>=', $validated['reservation_date'])
            ->where(fn ($query) => $query->whereNull('facility_id')->orWhere('facility_id', $validated['facility_id']))
            ->where(fn ($query) => $query->whereNull('start_time')->orWhere(fn ($times) => $times->where('start_time', '<', $validated['end_time'])->where('end_time', '>', $validated['start_time'])))->exists();
        if ($hasBlackout) return back()->withInput()->withErrors(['reservation_date' => 'The selected date or time is blocked by the administrator.']);

        $storedPermitPath = null;

        try {
            $reservation = DB::transaction(function () use ($validated, &$storedPermitPath): Reservation {
                $equipmentService = app(\App\Services\EquipmentRequestService::class);
                $equipmentService->lockInventory();
                $equipmentService->validateRequests($validated['reservation_type'], $validated['equipment'] ?? [], $validated['requested_equipment'] ?? null);
                // Serialize submissions for this facility before the final overlap check.
                $facility = Facility::whereKey($validated['facility_id'])->lockForUpdate()->firstOrFail();
                $unavailable = $facility->status !== 'available'
                    || Reservation::where('facility_id', $facility->id)->whereDate('reservation_date', $validated['reservation_date'])
                        ->whereNotIn('status', ['rejected', 'cancelled'])->where('start_time', '<', $validated['end_time'])
                        ->where('end_time', '>', $validated['start_time'])->exists()
                    || ScheduleBlock::whereDate('starts_on', '<=', $validated['reservation_date'])->whereDate('ends_on', '>=', $validated['reservation_date'])
                        ->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', $facility->id))
                        ->where(fn ($q) => $q->whereNull('start_time')->orWhere(fn ($times) => $times->where('start_time', '<', $validated['end_time'])->where('end_time', '>', $validated['start_time'])))->exists();
                if ($unavailable) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['reservation_date' => 'Selected time overlaps with an existing reservation. Please choose from the remaining available time.']);
                }
                $reservation = Reservation::create([
                    'reference_number' => $this->generateReferenceNumber(),
                    'user_id' => null,
                    'facility_id' => $validated['facility_id'],
                    'reservation_type' => $validated['reservation_type'],
                    'event_name' => $validated['event_name'],
                    'event_type' => $validated['event_type'] ?? null,
                    'purpose' => $validated['purpose'],
                    'organization_department' => $validated['organization_department'] ?? null,
                    'additional_notes' => $validated['additional_notes'] ?? null,
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
                    'agreement_accepted' => true,
                    'agreement_accepted_at' => now(),
                ]);

                $permit = $validated['permit'];
                app(\App\Services\EquipmentRequestService::class)->attach($reservation, $validated['equipment'] ?? []);
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
            Mail::mailer('smtp')->to($reservation->contact_email)->send(new ReservationSubmitted(
                $reservation->load('facility'),
                $trackingUrl,
                $body,
                $template?->subject ?? 'MCST Gymnasium Reservation Request Confirmation',
            ));
            $request->session()->flash('reservation_email_sent', true);
        } catch (\Throwable $exception) {
            $request->session()->flash('reservation_email_sent', false);
            Log::error('Reservation confirmation email delivery failed.', [
                'reservation_id' => $reservation->id,
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);
        }

        $references = $request->session()->get('public_reservation_references', []);
        $request->session()->put('public_reservation_references', array_values(array_unique([...$references, $reservation->reference_number])));
        $request->session()->forget(['public_email_verified', 'public_email_challenge', 'public_reservation_details']);

        return redirect()
            ->route('reservation.track', ['reference' => $reservation->reference_number])
            ->with('reservation_submitted', $reservation->reference_number);
    }

    public function sendVerificationCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'digits:11'],
            'reservation_type' => ['required', 'in:internal,external'],
            'organization_department' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
        ], ['reservation_type.required' => 'Please select a requestor type.']);
        $email = Str::lower(trim($validated['email']));
        $previous = $request->session()->get('public_email_challenge');
        $details = [...$validated, 'contact_email' => $email];
        unset($details['email']);
        $request->session()->put('public_reservation_details', $details);
        // A changed address immediately invalidates any previous proof, even during cooldown.
        $request->session()->forget('public_email_verified');
        if (($previous['email'] ?? null) !== $email) {
            $request->session()->forget('public_email_challenge');
        }
        $key = hash('sha256', $email);
        $cooldown = 'public-code:send:'.$key;
        $sends = 'public-code:sends:'.$key;
        foreach ([[$cooldown, 1], [$sends, 5]] as [$limit, $maximum]) {
            if (RateLimiter::tooManyAttempts($limit, $maximum)) {
                return response()->json([
                    'message' => 'Please wait before requesting another verification code.',
                    'retry_after' => RateLimiter::availableIn($limit),
                ], 429);
            }
        }
        RateLimiter::hit($cooldown, 60);
        RateLimiter::hit($sends, 600);
        $request->session()->forget('public_email_challenge');
        do {
            $code = (string) random_int(100000, 999999);
        } while (isset($previous['hash']) && Hash::check($code, $previous['hash']));
        try {
            Mail::mailer('smtp')->to($email)->send(new PublicRegistrationCode($code));
        } catch (\Throwable $exception) {
            // SMTP exception messages/debug transcripts may contain credentials or the OTP.
            // Log the exception type, numeric code, source and a safe diagnostic category only.
            $message = strtolower($exception->getMessage());
            $reason = match (true) {
                str_contains($message, '535'), str_contains($message, 'authenticate') => 'smtp_authentication_failed',
                str_contains($message, 'certificate') => 'smtp_tls_certificate_failed',
                str_contains($message, 'timed out'), str_contains($message, 'connection') => 'smtp_connection_failed',
                default => 'mail_delivery_failed',
            };
            Log::error('Public reservation verification email failed.', [
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
                'source' => basename($exception->getFile()).':'.$exception->getLine(),
                'reason' => $reason,
            ]);
            return response()->json(['message' => "We couldn't send the verification code. Please try again.", 'retry_after' => 60], 503);
        }
        $request->session()->put('public_email_challenge', [
            'email' => $email,
            'hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'verified' => false,
        ]);
        return response()->json(['message' => 'A new verification code has been sent to your email.', 'retry_after' => 60]);
    }

    public function verifyVerificationCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/'],
        ]);
        $email = Str::lower(trim($validated['email']));
        $limit = 'public-code:attempt:'.hash('sha256', $email.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($limit, 5)) {
            $request->session()->forget('public_email_challenge');
            return response()->json(['message' => 'Too many incorrect codes. Wait ten minutes, then request a new code.'], 429);
        }
        $challenge = $request->session()->get('public_email_challenge');
        if (! $challenge || $challenge['email'] !== $email
            || $request->session()->get('public_reservation_details.contact_email') !== $email) {
            return response()->json(['verified' => false, 'message' => 'Your verification session has expired. Please start again.'], 422);
        }
        if ($challenge['expires_at'] <= now()->timestamp) {
            $request->session()->forget('public_email_challenge');
            return response()->json(['verified' => false, 'message' => 'Your verification code has expired. Please request a new code.'], 422);
        }
        if (! Hash::check($validated['code'], $challenge['hash'])) {
            RateLimiter::hit($limit, 600);
            if (RateLimiter::tooManyAttempts($limit, 5)) {
                $request->session()->forget('public_email_challenge');
            }
            return response()->json(['verified' => false, 'message' => 'Invalid verification code.'], 422);
        }
        $request->session()->forget('public_email_challenge');
        $request->session()->put('public_email_verified', [
            'email' => $email, 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp,
        ]);
        return response()->json(['verified' => true, 'redirect' => route('reservation.create', ['step' => 3])]);
    }

    public function editDetails(Request $request): JsonResponse
    {
        $request->session()->forget(['public_email_verified', 'public_email_challenge']);
        return response()->json(['verified' => false]);
    }

    private function emailIsVerified(Request $request, ?string $email = null): bool
    {
        $proof = $request->session()->get('public_email_verified');
        $email ??= $request->session()->get('public_reservation_details.contact_email');
        return $proof && ($proof['verified'] ?? false) === true
            && $proof['expires_at'] > now()->timestamp
            && $proof['email'] === Str::lower(trim((string) $email));
    }


    public function success(string $referenceNumber): View
    {
        $reservation = Reservation::query()
            ->with(['facility', 'statusHistories'])
            ->where('reference_number', $referenceNumber)
            ->firstOrFail();

        return view(
            'public.reservations.success',
            compact('reservation')
        );
    }

    public function track(Request $request): View|JsonResponse
    {
        $reference = Str::upper(trim((string) $request->query('reference', '')));
        $reservation = null;

        if ($reference !== '') {
            $request->validate(['reference' => ['string', 'max:50']]);
            $reservation = Reservation::with(['facility', 'statusHistories', 'equipment', 'notifications'])
                ->where('reference_number', $reference)
                ->first();
        }

        if ($request->expectsJson()) {
            abort_unless($reservation, 404);
            $html = view('public.reservations.partials.tracking', compact('reservation'))->render();
            return response()->json(['status' => $reservation->status, 'html' => $html, 'version' => hash('sha256', $html)])
                ->header('Cache-Control', 'no-store, private');
        }
        $recentReservations = Reservation::whereIn('reference_number', $request->session()->get('public_reservation_references', []))
            ->latest()->get();
        return view('public.reservations.track', compact('reference', 'reservation', 'recentReservations'));
    }

    private function generateReferenceNumber(): string
    {
        do {
            $reference = 'MCST-GYM-'.
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
