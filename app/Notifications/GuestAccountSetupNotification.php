<?php

namespace App\Notifications;

use App\Support\BrandSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuestAccountSetupNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $reservationReference,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = (string) BrandSettings::get('guest_account_setup_subject');
        $message = strtr((string) BrandSettings::get('guest_account_setup_message'), [
            ':name' => $notifiable->name,
            ':reservation_ref' => $this->reservationReference,
        ]);

        return (new MailMessage)
            ->subject($subject)
            ->greeting(__('messages.account_setup.greeting', ['name' => $notifiable->name]))
            ->line($message)
            ->action(__('messages.account_setup.set_password'), route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]))
            ->line(__('messages.account_setup.expiry'));
    }
}