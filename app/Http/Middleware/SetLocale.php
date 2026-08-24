<?php

namespace App\Http\Middleware;

use App\Support\BrandSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? $request->cookie('locale')
            ?? $request->session()->get('locale')
            ?? BrandSettings::defaultLocale()
            ?? config('app.locale');

        app()->setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }
}