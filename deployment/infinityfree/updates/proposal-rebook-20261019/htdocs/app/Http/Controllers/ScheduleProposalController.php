<?php

namespace App\Http\Controllers;

use App\Models\ScheduleProposal;
use App\Services\ScheduleProposalService;
use Illuminate\Http\Request;

class ScheduleProposalController extends Controller
{
    public function show(ScheduleProposal $proposal)
    {
        $proposal->load('reservation');
        $current = $proposal->original === app(ScheduleProposalService::class)->snapshot($proposal->reservation);
        return response()->view('public.reservations.schedule-proposal', compact('proposal', 'current'))
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function respond(Request $request, ScheduleProposal $proposal)
    {
        $data = $request->validate(['decision' => ['required', 'in:accepted,declined']]);
        app(ScheduleProposalService::class)->respond($proposal, $data['decision']);
        return redirect()->to($request->fullUrl())->with('success', $data['decision'] === 'accepted'
            ? ($proposal->kind === 'waiting_list' ? 'Waiting-list offer accepted. Your reservation is now approved for the offered schedule.' : 'Replacement schedule accepted and saved. Your reservation status is unchanged.')
            : 'New schedule declined. Your original booking has not changed. Use Book Again below if you want to submit a separate reservation.');
    }
}
