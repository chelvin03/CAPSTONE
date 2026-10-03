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
        abort_unless($request->user()?->status === 'approved'
            && in_array($request->method(), ['GET', 'HEAD'], true)
            && $request->routeIs(
                'staff.dashboard', 'staff.reservations.index', 'staff.reservations.show',
                'staff.schedules.index', 'staff.facilities.index', 'staff.equipment.index'
            ), 403);

        return $next($request);
    }
}
