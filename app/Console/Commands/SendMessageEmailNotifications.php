<?php

namespace App\Console\Commands;

use App\Models\EmailOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendMessageEmailNotifications extends Command
{
    protected $signature = 'messages:send-email-notifications {--limit=50 : Maximum number of notifications to send}';

    protected $description = 'Send queued email notifications';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $notifications = EmailOutbox::query()
            ->whereIn('template', ['message_received', 'payment_link'])
            ->where('status', 'queued')
            ->oldest()
            ->limit($limit)
            ->get();

        foreach ($notifications as $notification) {
            try {
                $locale = $notification->payload['locale'] ?? config('app.locale');
                $previousLocale = app()->getLocale();
                app()->setLocale($locale);

                $view = $notification->template === 'payment_link'
                    ? 'emails.payment-link'
                    : 'emails.message-received';

                Mail::send($view, $notification->payload ?? [], function ($mail) use ($notification): void {
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