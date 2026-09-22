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
        $linkLocale = in_array($request->query('lang'), ['fr', 'en'], true) ? $request->query('lang') : null;

        $locale = $request->user()?->locale
            ?? $linkLocale
            ?? $request->cookie('locale')
            ?? $request->session()->get('locale')
            ?? BrandSettings::defaultLocale()
            ?? config('app.locale');

        app()->setLocale($locale);
        $request->session()->put('locale', $locale);

        $response = $next($request);

        // Persist the language chosen via an emailed link (?lang=) so follow-up requests in the same flow keep it.
        if ($linkLocale && method_exists($response, 'withCookie')) {
            $response->withCookie(cookie('locale', $linkLocale, 525600));
        }

        return $response;
    }
}