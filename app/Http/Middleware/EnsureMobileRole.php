<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        $authorized = match ($role) {
            'customer' => $user?->role === 'customer',
            'staff' => $user?->canManageReservations(),
            'admin' => $user?->isAdmin(),
            default => false,
        };

        if (! $authorized) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}