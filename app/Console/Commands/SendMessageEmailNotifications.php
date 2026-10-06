<?php

namespace App\Console\Commands;

use App\Models\EmailOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Models\Reservation;
use App\Support\ReservationSummary;

class SendMessageEmailNotifications extends Command
{
    protected $signature = 'messages:send-email-notifications {--limit=50 : Maximum number of notifications to send}';

    protected $description = 'Send queued email notifications';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $notifications = EmailOutbox::query()
            ->whereIn('template', ['message_received', 'payment_link', 'reservation_created', 'reservation_received', 'reservation_status_updated', 'receipt_issued', 'payment_proof_submitted'])
            ->where('status', 'queued')
            ->oldest()
            ->limit($limit)
            ->get();

        foreach ($notifications as $notification) {
            try {
                $locale = $notification->payload['locale'] ?? config('app.locale');
                $previousLocale = app()->getLocale();
                app()->setLocale($locale);

                $payload = $notification->payload ?? [];
                if (isset($payload['reservation_summary'], $payload['reservation_id'])) {
                    $reservation = Reservation::query()->find($payload['reservation_id']);
                    if ($reservation && strtolower((string) $reservation->email) === strtolower($notification->recipient_email)) {
                        $payload['reservation_summary'] = ReservationSummary::make($reservation, $locale);
                        $payload['property_name'] = $payload['reservation_summary']['property']['name'];
                        $payload['status'] = $reservation->status;
                        if ($notification->template === 'receipt_issued' && isset($payload['receipt_id'])) {
                            $receipt = $reservation->receipts()->find($payload['receipt_id']);
                            $payload['transaction'] = $receipt ? app(\App\Services\ReservationEmailService::class)->receiptTransaction($receipt) : null;
                        }
                    }
                }

                // Mail::send() overwrites the `$message` view variable with the mail object.
                if (isset($payload['message']) && is_string($payload['message'])) {
                    $payload['message_body'] = $payload['message'];
                }

                $view = match ($notification->template) {
                    'payment_link' => 'emails.payment-link',
                    'reservation_created' => 'emails.reservation-created',
                    'reservation_received' => 'emails.reservation-received',
                    'reservation_status_updated' => 'emails.reservation-status-updated',
                    'receipt_issued' => 'emails.receipt-issued',
                    'payment_proof_submitted' => 'emails.payment-proof-submitted',
                    default => 'emails.message-received',
                };

                Mail::send($view, $payload, function ($mail) use ($notification): void {
                    $mail->to($notification->recipient_email)
                        ->subject($notification->payload['subject'] ?? __('messages.messages.email_subject'));
                });

                app()->setLocale($previousLocale);
                $notification->update(['status' => 'sent']);
                $this->line("Sent {$notification->template} notification to {$notification->recipient_email}.");
            } catch (\Throwable $exception) {
                if (isset($previousLocale)) {
                    app()->setLocale($previousLocale);
                }
                $notification->update([
                    'status' => 'failed',
                    'payload' => array_merge((array) $notification->payload, ['error' => $exception->getMessage()]),
                ]);
                $this->error("Could not send {$notification->template} notification to {$notification->recipient_email}: {$exception->getMessage()}");
            }
        }

        $this->info("Processed {$notifications->count()} email notification(s).");

        return self::SUCCESS;
    }
}