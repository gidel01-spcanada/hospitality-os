<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditRecorder
{
    public function action(string $action, ?Model $model = null, array $details = []): void
    {
        if (! $model && $details === []) {
            return;
        }

        $redacted = collect($details)
            ->except(['password', 'password_confirmation', 'remember_token', 'api_token', 'token'])
            ->all();

        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'model_type' => $model ? $model::class : null,
            'model_id' => $model?->getKey(),
            'action' => $action,
            'details' => json_encode([
                ...$redacted,
                'url' => request()->fullUrl(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }
}
