<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\Reservation;
use App\Mail\FeedbackInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class FeedbackController extends Controller
{
    private function eligible(Reservation $reservation): bool
    {
        return in_array($reservation->status, ['approved', 'completed'], true)
            && CarbonImmutable::parse($reservation->reservation_date->toDateString().' '.$reservation->end_time)->lte(now());
    }

    public function index()
    {
        return view('public.feedback.index');
    }

    public function requestLink(Request $request)
    {
        $data = $request->validate(['reference_number' => ['required', 'string', 'max:50'], 'email' => ['required', 'email', 'max:255']]);
        $reservation = Reservation::where('reference_number', trim($data['reference_number']))->first();
        if ($reservation && $this->eligible($reservation)
            && strcasecmp(trim((string)$reservation->contact_email), trim($data['email'])) === 0
            && ! Feedback::where('reservation_id', $reservation->id)->exists()) {
            try {
                Mail::mailer('smtp')->to($reservation->contact_email)->send(new FeedbackInvitation(
                    URL::temporarySignedRoute('public.feedback.form', now()->addHour(), ['reservation' => $reservation->id])
                ));
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::error('Feedback invitation could not be sent.', ['exception_class' => get_class($exception)]);
                return back()->withErrors(['email' => 'Unable to send the feedback link. Please try again later.']);
            }
        }
        return back()->with('status', 'If the reference and email match an eligible reservation, a feedback link has been sent. Feedback is available after an approved gym session ends, once per reservation.');
    }

    public function form(Request $request, Reservation $reservation)
    {
        if (! $this->eligible($reservation) || Feedback::where('reservation_id', $reservation->id)->exists()) {
            return redirect()->route('public.feedback')->with('status', 'Feedback has already been submitted or this reservation is not eligible.');
        }
        return view('public.feedback.form', ['reservation' => $reservation, 'submitUrl' => $request->fullUrl()]);
    }

    public function store(Request $request, Reservation $reservation)
    {
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comments' => ['nullable', 'string', 'max:3000']]);
        DB::transaction(function () use ($data, $reservation) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if (! $this->eligible($reservation) || Feedback::where('reservation_id', $reservation->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['rating' => 'Feedback has already been submitted or this reservation is not eligible.']);
            }
            Feedback::create(['reservation_id' => $reservation->id, 'user_id' => $reservation->user_id, ...$data]);
        });
        return redirect()->route('public.feedback')->with('status', 'Thank you! Your feedback has been received by the gym administrator.');
    }
}
