<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsEstablishmentManager
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isEstablishmentManager()) {
            abort(403, __('messages.errors.establishment_manager_required'));
        }

        return $next($request);
    }
}
