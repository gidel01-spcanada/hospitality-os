<?php

namespace App\Services;

use App\Models\EmailOutbox;
use App\Models\User;
use Illuminate\Support\Facades\Password;

class AccountInvitationService
{
    public function send(User $user): void
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw new \RuntimeException(__($status));
        }

        EmailOutbox::query()->create([
            'template' => 'staff_account_invitation',
            'recipient_email' => $user->email,
            'status' => 'queued',
            'payload' => [
                'subject' => __('messages.account_invitation.subject'),
                'name' => $user->name,
                'role' => $user->role,
            ],
        ]);
    }
}
