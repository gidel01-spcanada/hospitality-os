<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleRecaptchaVerifier
{
    public function enabled(): bool
    {
        return (bool) config('services.recaptcha.enabled');
    }

    public function verify(?string $token, ?string $ipAddress = null, string $expectedAction = 'submit'): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (! filled($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(
                'https://www.google.com/recaptcha/api/siteverify',
                array_filter([
                    'secret' => config('services.recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $ipAddress,
                ])
            );
        } catch (\Throwable) {
            return false;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            return false;
        }

        return (float) $response->json('score', 0) >= (float) config('services.recaptcha.minimum_score', 0.5)
            && ($response->json('action') === null || $response->json('action') === $expectedAction);
    }
}
