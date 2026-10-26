<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityPreparation;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\Reservation;
use App\Services\PlanningScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanningScheduleController extends Controller
{
    public function index(Request $request, PlanningScheduleService $planning)
    {
        $data = $request->validate(['activity' => ['nullable', 'integer']]);

        return response()->view('admin.planning.index', $planning->summarize(isset($data['activity']) ? (int) $data['activity'] : null))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, Reservation $reservation, PlanningScheduleService $planning)
    {
        $data = $request->validate(['task' => ['required', Rule::in(array_keys(PlanningScheduleService::TASKS))],
            'completed' => ['required', 'boolean'], 'snapshot' => ['required', 'string', 'size:64']]);
        DB::transaction(function () use ($request, $reservation, $planning, $data) {
            $booking = Reservation::whereKey($reservation->id)->lockForUpdate()->with('equipment')->firstOrFail();
            abort_unless((int) $booking->facility_id === (int) Facility::gym()->value('id') && in_array($booking->status, PlanningScheduleService::STATUSES, true)
                && $booking->reservation_date->toDateString() >= now()->toDateString()
                && $booking->reservation_date->toDateString() <= now()->addDays(3)->toDateString(), 403);
            if (! hash_equals($planning->snapshot($booking), $data['snapshot'])) {
                throw ValidationException::withMessages(['preparation' => 'This activity changed. Reload and review its current details before updating preparation.']);
            }
            if ($data['task'] === 'venue' && $request->boolean('completed') && $booking->status !== 'approved') {
                throw ValidationException::withMessages(['preparation' => 'The reservation must be approved before its venue schedule can be confirmed.']);
            }
            $item = ActivityPreparation::updateOrCreate(['reservation_id' => $booking->id, 'task' => $data['task']], [
                'completed' => $request->boolean('completed'), 'snapshot_hash' => $data['snapshot'], 'updated_by' => $request->user()->id,
            ]);
            AuditLog::record('planning.preparation.updated', $item, ['task' => $data['task'], 'completed' => $item->completed, 'reservation_id' => $booking->id]);
        });

        return redirect()->to(route('admin.planning.index', ['activity' => $reservation->id]).'#planning-checklist')->with('success', 'Preparation checklist updated.');
    }
}
