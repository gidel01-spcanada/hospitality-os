<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Services\ReservationEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CompletePastReservations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ReservationEmailService $emailService): void
    {
        $cutoff = now()->subHours(max(0, (int) config('platform.checkout_completion_grace_hours', 6)));

        Reservation::query()
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->whereDate('check_out', '<', $cutoff->toDateString())
            ->with('property')
            ->chunkById(100, function ($reservations) use ($emailService): void {
                foreach ($reservations as $reservation) {
                    $reservation->update([
                        'status' => 'completed',
                        'notes' => trim(($reservation->notes ?? '') . PHP_EOL . 'Stay automatically completed after check-out.'),
                    ]);
                    $emailService->queueStatusUpdate($reservation, 'completed');
                }
            });
    }
}
