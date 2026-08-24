<?php

namespace App\Providers;

use App\Support\BrandSettings;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        config()->set('app.name', BrandSettings::siteName());

        $locale = auth()->user()?->locale
            ?? session('locale')
            ?? BrandSettings::defaultLocale()
            ?? config('app.locale');
        app()->setLocale($locale);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
