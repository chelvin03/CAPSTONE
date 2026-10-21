<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $arAction = $request->isMethod('POST') && $request->routeIs('staff.reservations.receipt.prepare', 'staff.reservations.receipt.send', 'staff.reservations.receipt.start', 'staff.reservations.receipt.complete');
        $outcomeAction = $request->isMethod('POST') && $request->routeIs('staff.reservations.outcome', 'staff.reservations.equipment.return');
        abort_unless($request->user()?->status === 'approved' && ($arAction || $outcomeAction || (
            in_array($request->method(), ['GET', 'HEAD'], true)
            && $request->routeIs(
                'staff.dashboard', 'staff.reservations.index', 'staff.reservations.show',
                'staff.schedules.index', 'staff.equipment.index', 'staff.reservations.receipt', 'staff.reservations.receipt.print',
                'staff.ar.index', 'staff.reservations.receipt.pdf'
            ))), 403);

        return $next($request);
    }
}
