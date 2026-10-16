<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\AcknowledgementReceipt;
use App\Services\AcknowledgementReceiptService;
use App\Mail\AcknowledgementReceiptReady;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationReceiptController extends Controller
{
    public function index(Request $request, AcknowledgementReceiptService $service)
    {
        $filters = $request->validate(['status' => ['nullable', 'in:pending,in_progress,completed'], 'history' => ['nullable', 'boolean']]);
        $query = Reservation::whereHas('acknowledgementTask')
            ->when(! $request->boolean('history'), fn ($q) => $q->where('status', 'approved'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->whereHas('acknowledgementTask', fn ($task) => $task->where('status', $status)))
            ->with(['acknowledgementTask', 'acknowledgementReceipt']);
        $reservations = $service->orderedTasks($query)->paginate(15)->withQueryString();
        return view('staff.ar.index', compact('reservations', 'filters'));
    }

    public function start(Request $request, Reservation $reservation, AcknowledgementReceiptService $service)
    {
        $this->authorizeBooking($request, $reservation);
        DB::transaction(function () use ($reservation, $service, $request) {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $this->authorizeBooking($request, $locked);
            $task = $service->ensureTask($locked);
            if ($task->status === 'pending') $task->update(['status' => 'in_progress']);
        });
        return redirect()->route('staff.reservations.receipt', $reservation);
    }

    public function complete(Request $request, Reservation $reservation, AcknowledgementReceiptService $service)
    {
        $this->authorizeBooking($request, $reservation);
        $data = $request->validate(['version' => ['required', 'uuid']]);
        DB::transaction(function () use ($request, $reservation, $service, $data) {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $this->authorizeBooking($request, $locked);
            $receipt = AcknowledgementReceipt::where('reservation_id', $locked->id)->lockForUpdate()->first();
            if (! $receipt || $receipt->version !== $data['version'] || ! $service->current($receipt, $locked)) {
                throw ValidationException::withMessages(['receipt' => 'Generate the current AR before marking it completed.']);
            }
            $task = $service->ensureTask($locked);
            if ($task->status !== 'completed') $task->update(['status' => 'completed', 'completed_by' => $request->user()->id, 'completed_at' => now()]);
        });
        return back()->with('success', 'Acknowledgement receipt marked as completed.');
    }

    public function pdf(Request $request, Reservation $reservation, AcknowledgementReceiptService $service, \App\Services\AcknowledgementPdfService $pdf)
    {
        $this->authorizeBooking($request, $reservation);
        $receipt = AcknowledgementReceipt::where('reservation_id', $reservation->id)->first();
        abort_unless($receipt && $service->current($receipt, $reservation), 409, 'Generate the current AR first.');
        return response($pdf->render($receipt), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="AR-'.$reservation->id.'.pdf"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeBooking(Request $request, Reservation $reservation): void
    {
        abort_unless($request->user()?->status === 'approved', 403);
        abort_unless(in_array($reservation->status, ['approved', 'completed'], true), 403, 'The administrator must approve this reservation before staff prepares the AR.');
    }

    public function show(Request $request, Reservation $reservation, AcknowledgementReceiptService $service)
    {
        $this->authorizeBooking($request, $reservation);
        $receipt = AcknowledgementReceipt::where('reservation_id', $reservation->id)->first();
        return view('reservations.ar-workflow', [
            'reservation' => $reservation, 'receipt' => $receipt,
            'isCurrent' => $receipt && $service->current($receipt, $reservation),
            'isStaff' => $request->user()->role === 'staff',
            'task' => $reservation->acknowledgementTask,
            'previewBooking' => $service->snapshot($reservation),
        ]);
    }

    public function prepare(Request $request, Reservation $reservation, AcknowledgementReceiptService $service)
    {
        $this->authorizeBooking($request, $reservation);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $reservation, $service, $data) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $this->authorizeBooking($request, $reservation);
            $receipt = AcknowledgementReceipt::where('reservation_id', $reservation->id)->first();
            $task = $service->ensureTask($reservation);
            $notes = $data['notes'] ?? null;
            if ($receipt && $service->current($receipt, $reservation) && $receipt->notes === $notes) {
                if ($task->status === 'pending') $task->update(['status' => 'in_progress']);
                return;
            }
            AcknowledgementReceipt::updateOrCreate(['reservation_id' => $reservation->id], [
                'receipt_number' => 'AR-'.$reservation->reference_number,
                'prepared_by' => $request->user()->id,
                'snapshot' => ['booking' => $service->snapshot($reservation), 'prepared_name' => trim($request->user()->first_name.' '.$request->user()->last_name)],
                'version' => (string) Str::uuid(), 'notes' => $notes,
                'prepared_at' => now(), 'sent_at' => null, 'sent_to' => null,
            ]);
            $task->update(['status' => 'in_progress', 'completed_at' => null, 'completed_by' => null]);
        });
        return back()->with('success', 'AR prepared. Review the printable receipt, then send it to the requestor.');
    }

    public function send(Request $request, Reservation $reservation, AcknowledgementReceiptService $service)
    {
        $this->authorizeBooking($request, $reservation);
        $data = $request->validate(['version' => ['required', 'uuid']]);
        try {
            DB::transaction(function () use ($request, $reservation, $service, $data) {
                $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
                $this->authorizeBooking($request, $reservation);
                $receipt = AcknowledgementReceipt::where('reservation_id', $reservation->id)->lockForUpdate()->first();
                if (! $receipt || ! $service->current($receipt, $reservation) || $receipt->version !== $data['version']) {
                    throw ValidationException::withMessages(['receipt' => 'Prepare the current AR before sending. Booking details may have changed.']);
                }
                if (! filter_var($reservation->contact_email, FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::withMessages(['receipt' => 'No valid booking email is saved. Ask the administrator to update the requestor email first.']);
                }
                $signedPath = URL::temporarySignedRoute('public.reservations.receipt', now()->addDays(7), ['receipt' => $receipt->id, 'version' => $receipt->version], absolute: false);
                $printUrl = url($signedPath);
                Mail::mailer('smtp')->to($reservation->contact_email)->send(new AcknowledgementReceiptReady($printUrl, $reservation->reference_number));
                $receipt->update(['sent_at' => now(), 'sent_to' => $reservation->contact_email]);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::error('AR email delivery failed.', ['reservation_id' => $reservation->id, 'exception_class' => get_class($exception)]);
            return back()->withErrors(['receipt' => 'The AR is prepared, but the email could not be sent. Please try again.']);
        }
        return back()->with('success', 'AR sent to the requestor. The email contains a print-ready link valid for 7 days.');
    }

    public function print(Request $request, Reservation $reservation, AcknowledgementReceiptService $service)
    {
        $this->authorizeBooking($request, $reservation);
        $receipt = AcknowledgementReceipt::where('reservation_id', $reservation->id)->first();
        abort_unless($receipt && $service->current($receipt, $reservation), 409, 'Staff must prepare the current AR first.');
        return $this->renderReceipt($receipt, route($request->user()->role === 'staff' ? 'staff.reservations.receipt' : 'admin.reservations.receipt', $reservation));
    }

    public function publicPrint(Request $request, AcknowledgementReceipt $receipt, AcknowledgementReceiptService $service)
    {
        abort_unless($receipt->sent_at && hash_equals($receipt->version, (string)$request->query('version'))
            && $receipt->reservation && $service->current($receipt, $receipt->reservation), 403, 'This AR link is no longer available. Please contact the gym staff.');
        return $this->renderReceipt($receipt, route('home'));
    }

    private function renderReceipt(AcknowledgementReceipt $receipt, string $backUrl)
    {
        return response()->view('reservations.acknowledgement', ['receipt' => $receipt, 'booking' => $receipt->snapshot['booking'],
            'receiptNumber' => $receipt->receipt_number, 'preparedBy' => $receipt->snapshot['prepared_name'], 'backUrl' => $backUrl])
            ->header('Cache-Control', 'private, no-store');
    }
}
