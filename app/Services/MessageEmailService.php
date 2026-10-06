<?php

namespace App\Services;

use App\Models\EmailOutbox;
use App\Models\MessageThread;
use App\Models\User;
use App\Support\ReservationSummary;

class MessageEmailService
{
    public function queueForMessage(MessageThread $thread, User $sender, string $body): void
    {
        $thread->loadMissing(['customer', 'establishment.translations']);
        $reservation = $thread->messages()->whereNotNull('reservation_id')->with('reservation.property')->latest('id')->first()?->reservation;
        if ($reservation && ($reservation->property?->establishment_id !== $thread->establishment_id
            || ($reservation->user_id !== $thread->customer_id && strtolower((string) $reservation->email) !== strtolower($thread->customer->email)))) {
            $reservation = null;
        }

        $recipients = $sender->id === $thread->customer_id
            ? User::query()
                ->where('tenant_id', $thread->establishment->tenant_id)
                ->whereIn('role', ['admin', 'concierge', 'host'])
                ->where(fn ($query) => $query->where('role', '!=', 'host')->orWhereHas('establishments', fn ($establishmentQuery) => $establishmentQuery->where('establishments.id', $thread->establishment_id)))
                ->where('email_message_updates', true)
                ->where('id', '!=', $sender->id)
                ->get()
            : User::query()
                ->whereKey($thread->customer_id)
                ->where('email_message_updates', true)
                ->where('id', '!=', $sender->id)
                ->get();

        foreach ($recipients as $recipient) {
            EmailOutbox::query()->create([
                'template' => 'message_received',
                'recipient_email' => $recipient->email,
                'status' => 'queued',
                'payload' => [
                    'subject' => $sender->id !== $thread->customer_id
                        ? __('messages.transactional.message_subject_establishment', ['establishment' => $thread->establishment->localized('name', $recipient->locale ?? 'fr'), 'brand' => \App\Support\PlatformBrand::name()], $recipient->locale ?? 'fr')
                        : __('messages.messages.email_subject', [], $recipient->locale ?? 'fr'),
                    'locale' => $recipient->locale ?? 'fr',
                    'recipient_name' => $recipient->name,
                    'from_establishment' => $sender->id !== $thread->customer_id,
                    'reservation_context' => $reservation ? array_intersect_key(ReservationSummary::make($reservation, $recipient->locale ?? 'fr'), array_flip(['reference', 'check_in', 'check_out', 'establishment', 'property'])) : null,
                    'reservation_url' => $reservation ? route($sender->id === $thread->customer_id ? 'admin.reservations.show' : 'dashboard.reservations.show', $reservation) : null,
                    'sender_name' => $sender->name,
                    'establishment_name' => $thread->establishment->localized('name', $recipient->locale ?? 'fr'),
                    'message' => $body,
                    'thread_url' => $sender->id === $thread->customer_id
                        ? route('admin.messages.show', $thread)
                        : route('messages.show', $thread),
                ],
            ]);
        }
    }
}