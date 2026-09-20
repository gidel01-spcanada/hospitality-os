<?php

use App\Services\CalendarFeedSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $feeds = \App\Models\ExternalCalendarFeed::query()
        ->where('is_enabled', true)
        ->get();

    foreach ($feeds as $feed) {
        if ($feed->last_sync_at && $feed->last_sync_at->gt(now()->subMinutes((int) $feed->sync_interval_minutes)) ) {
            continue;
        }
        app(CalendarFeedSyncService::class)->sync($feed);
    }
})->hourly();

Schedule::job(new \App\Jobs\CompletePastReservations())->hourly();

Schedule::call(function (): void {
    \App\Models\Establishment::query()
        ->whereNotNull('google_reviews_import_url')
        ->where(function ($query): void {
            $query->whereNull('google_reviews_last_sync_at')
                ->orWhere('google_reviews_last_sync_at', '<=', now()->subHours(6));
        })
        ->each(function ($establishment): void {
            try {
                app(\App\Services\GoogleReviewSyncService::class)->sync($establishment);
            } catch (\Throwable) {
                // The service persists the error; the next schedule run retries it.
            }
        });
})->everySixHours();

Artisan::command('reservations:complete-past', function (): void {
    dispatch_sync(new \App\Jobs\CompletePastReservations());
    $this->info('Past reservations completed.');
})->purpose('Complete reservations after the checkout date');
