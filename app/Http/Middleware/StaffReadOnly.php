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
        $arAction = $request->isMethod('POST') && $request->routeIs('staff.reservations.receipt.prepare', 'staff.reservations.receipt.send');
        abort_unless($request->user()?->status === 'approved' && ($arAction || (
            in_array($request->method(), ['GET', 'HEAD'], true)
            && $request->routeIs(
                'staff.dashboard', 'staff.reservations.index', 'staff.reservations.show',
                'staff.schedules.index', 'staff.equipment.index', 'staff.reservations.receipt', 'staff.reservations.receipt.print'
            ))), 403);

        return $next($request);
    }
}
