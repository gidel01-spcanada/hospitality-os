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
        private readonly ?string $checkoutReturn = null,
        private readonly bool $isReservationReference = true,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = (string) BrandSettings::get('guest_account_setup_subject');
        if ($subject === BrandSettings::DEFAULTS['guest_account_setup_subject']) {
            $subject = __('messages.transactional.account_subject');
            if ($this->isReservationReference) {
                $subject .= ' – ' . __('messages.account_setup.reservation_number') . ' ' . $this->reservationReference;
            }
        }
        $configuredMessage = (string) BrandSettings::get('guest_account_setup_message');
        if ($configuredMessage === BrandSettings::DEFAULTS['guest_account_setup_message']) {
            $configuredMessage = __('messages.transactional.account_default_message');
        }
        $message = strtr($configuredMessage, [
            ':name' => $notifiable->name,
            ':reservation_ref' => $this->reservationReference,
        ]);

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting(__('messages.account_setup.greeting', ['name' => $notifiable->name]))
            ->line($message)
            ->action(__('messages.account_setup.set_password'), route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
                'checkout_return' => $this->checkoutReturn,
            ]))
            ->line(__('messages.account_setup.expiry'));

        return $mail->view('emails.account-setup', [
            'recipient_name' => $notifiable->name,
            'account_message' => $message,
            'reservation_reference' => $this->reservationReference,
            'reference_label' => $this->isReservationReference ? __('messages.account_setup.reservation_number') : __('messages.checkout.property'),
            'action_url' => $mail->actionUrl,
        ]);
    }
}