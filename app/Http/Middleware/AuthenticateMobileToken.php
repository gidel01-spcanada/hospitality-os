<?php

namespace App\Http\Middleware;

use App\Models\MobileAccessToken;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        $token = $plainToken ? MobileAccessToken::query()
            ->where('token', hash('sha256', $plainToken))
            ->with('user.tenant')
            ->first() : null;

        if (! $token || ! $token->user || ! $token->user->is_active || ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($token->user->tenant?->status === 'suspended') {
            return response()->json(['message' => 'This workspace has been suspended.'], 403);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        $request->setUserResolver(fn () => $token->user);
        app(CurrentTenant::class)->set($token->user->tenant_id);

        return $next($request);
    }
}