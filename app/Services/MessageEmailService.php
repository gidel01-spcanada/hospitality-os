<?php

namespace App\Services;

use App\Models\EmailOutbox;
use App\Models\MessageThread;
use App\Models\User;

class MessageEmailService
{
    public function queueForMessage(MessageThread $thread, User $sender, string $body): void
    {
        $thread->loadMissing(['customer', 'establishment']);

        $recipients = $sender->id === $thread->customer_id
            ? User::query()
                ->where('tenant_id', $thread->establishment->tenant_id)
                ->whereIn('role', ['admin', 'concierge'])
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
                    'subject' => __('messages.messages.email_subject', [], $recipient->locale ?? 'fr'),
                    'locale' => $recipient->locale ?? 'fr',
                    'sender_name' => $sender->name,
                    'establishment_name' => $thread->establishment->name,
                    'message' => $body,
                    'thread_url' => $sender->id === $thread->customer_id
                        ? route('admin.messages.show', $thread)
                        : route('messages.show', $thread),
                ],
            ]);
        }
    }
}