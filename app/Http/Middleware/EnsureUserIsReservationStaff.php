<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsReservationStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->canManageReservations()) {
            abort(403, __('messages.errors.reservation_staff_required'));
        }

        return $next($request);
    }
}