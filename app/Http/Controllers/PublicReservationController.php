<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicReservationController extends Controller
{
    public function sendVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30'],
            'reservation_type' => ['required', 'string', 'max:50'],
        ]);

        $code = (string) random_int(100000, 999999);

        try {
            Mail::raw(
                "Your MCST Gym reservation verification code is {$code}. It expires in 10 minutes.",
                fn ($message) => $message
                    ->to($validated['contact_email'])
                    ->subject('MCST Gym Reservation Verification Code')
            );
        } catch (\Throwable $exception) {
            Log::error('Reservation verification email delivery failed.', [
                'recipient_domain' => str($validated['contact_email'])->after('@')->toString(),
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => 'We could not send the verification email. Please try again shortly.',
            ], 503);
        }

        $request->session()->put('public_reservation_verification', [
            'email' => mb_strtolower($validated['contact_email']),
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        $response = [
            'message' => 'A 6-digit verification code was sent to your email.',
        ];

        // The log mailer is intended for local development and does not deliver
        // externally. Expose the code only in local mode so the workflow remains
        // testable without weakening production verification.
        if (app()->environment('local') && config('mail.default') === 'log') {
            $response['message'] = 'Development mode: email delivery is disabled.';
            $response['development_code'] = $code;
        }

        return response()->json($response);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_email' => ['required', 'email'],
            'verification_code' => ['required', 'digits:6'],
        ]);
        $verification = $request->session()->get('public_reservation_verification');

        if (! $verification ||
            $verification['email'] !== mb_strtolower($validated['contact_email']) ||
            $verification['expires_at'] < now()->timestamp ||
            ! Hash::check($validated['verification_code'], $verification['code'])) {
            return response()->json(['message' => 'The verification code is invalid or has expired.'], 422);
        }

        $request->session()->put('public_reservation_verified_email', $verification['email']);
        $request->session()->forget('public_reservation_verification');

        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function availability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'facility_id' => ['required', 'exists:facilities,id'],
            'date' => ['required', 'date'],
        ]);

        return response()->json(Reservation::query()
            ->where('facility_id', $validated['facility_id'])
            ->whereDate('reservation_date', $validated['date'])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->orderBy('start_time')
            ->get(['start_time', 'end_time', 'status']));
    }

    public function create(): View
    {
        $facilities = Facility::query()
            ->where('status', 'available')
            ->orderBy('facility_name')
            ->get();

        $equipment = Equipment::query()
            ->where('status', 'available')
            ->orderBy('equipment_name')
            ->get();

        return view('public.reservations.create', compact(
            'facilities',
            'equipment'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'facility_id' => ['required', 'exists:facilities,id'],
            'reservation_type' => ['required', 'string', 'max:50'],
            'event_name' => ['required', 'string', 'max:255'],
            'event_type' => ['nullable', 'string', 'max:100'],
            'purpose' => ['required', 'string'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30'],
            'expected_attendees' => ['required', 'integer', 'min:1'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
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
            'equipment' => ['nullable', 'array'],
            'equipment.*.quantity_requested' => [
                'nullable',
                'integer',
                'min:1',
            ],

        ]);

        if ($request->session()->get('public_reservation_verified_email') !== mb_strtolower($validated['contact_email'])) {
            return back()->withInput()->withErrors([
                'contact_email' => 'Please verify this email address before submitting the reservation.',
            ]);
        }

        $hasConflict = Reservation::query()
            ->where('facility_id', $validated['facility_id'])
            ->where('reservation_date', $validated['reservation_date'])
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

                $equipmentData = [];

                foreach ($validated['equipment'] ?? [] as $equipmentId => $item) {
                    $quantity = $item['quantity_requested'] ?? null;

                    if ($quantity) {
                        $equipmentData[$equipmentId] = [
                            'quantity_requested' => $quantity,
                            'quantity_approved' => null,
                            'remarks' => null,
                        ];
                    }
                }

                if ($equipmentData !== []) {
                    $reservation->equipment()->sync($equipmentData);
                }

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

        $request->session()->forget('public_reservation_verified_email');

        return redirect()
            ->route('reservation.success', $reservation->reference_number);
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
