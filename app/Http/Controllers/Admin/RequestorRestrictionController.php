<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RequestorRestriction;
use App\Services\RequestorCancellationReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestorRestrictionController extends Controller
{
    public function index(Request $request, RequestorCancellationReviewService $review)
    {
        $validated = $request->validate(['days' => ['nullable', 'integer', 'in:30,90,180,365']]);
        return view('admin.requestor-restrictions.index', $review->report((int) ($validated['days'] ?? 90)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:255'],
            'reason' => ['required', 'string', 'max:2000'],
            'expires_at' => ['required', 'date_format:Y-m-d', 'after:today', 'before_or_equal:'.now()->addYear()->toDateString()]]);
        DB::transaction(function () use ($request, $validated): void {
            $restriction = RequestorRestriction::updateOrCreate(['email' => strtolower(trim($validated['email']))],
                ['reason' => $validated['reason'], 'expires_at' => \Carbon\Carbon::parse($validated['expires_at'])->endOfDay(),
                    'created_by' => $request->user()->id, 'revoked_at' => null, 'revoked_by' => null, 'revocation_reason' => null]);
            AuditLog::record('requestor.restricted', $restriction, ['email' => $restriction->email,
                'reason' => $restriction->reason, 'expires_at' => $restriction->expires_at->toIso8601String()]);
        });
        return back()->with('success', 'Temporary restriction saved. Existing reservations remain unchanged.');
    }

    public function destroy(Request $request, RequestorRestriction $restriction)
    {
        $validated = $request->validate(['revocation_reason' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $restriction, $validated): void {
            $restriction = RequestorRestriction::whereKey($restriction->id)->lockForUpdate()->firstOrFail();
            $restriction->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id,
                'revocation_reason' => $validated['revocation_reason']]);
            AuditLog::record('requestor.restriction_released', $restriction, ['reason' => $validated['revocation_reason']]);
        });
        return back()->with('success', 'Restriction released.');
    }
}
