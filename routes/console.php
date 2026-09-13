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
        app(CalendarFeedSyncService::class)->sync($feed);
    }
})->hourly();

Schedule::job(new \App\Jobs\CompletePastReservations())->hourly();

Artisan::command('reservations:complete-past', function (): void {
    dispatch_sync(new \App\Jobs\CompletePastReservations());
    $this->info('Past reservations completed.');
})->purpose('Complete reservations after the checkout date');
