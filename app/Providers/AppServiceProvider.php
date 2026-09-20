<?php

namespace App\Providers;

use App\Support\BrandSettings;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentTenant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Event::listen('eloquent.created: *', function (string $event, array $payload): void {
            $model = $payload[0] ?? null;
            if ($model instanceof Model) {
                $this->recordAudit($model, 'created', [], $model->getAttributes());
            }
        });
        Event::listen('eloquent.updated: *', function (string $event, array $payload): void {
            $model = $payload[0] ?? null;
            if ($model instanceof Model) {
                $changes = $model->getChanges();
                $oldValues = collect($changes)
                    ->keys()
                    ->mapWithKeys(fn (string $key) => [$key => $model->getRawOriginal($key)])
                    ->all();
                $this->recordAudit($model, 'updated', $oldValues, $changes);
            }
        });
        Event::listen('eloquent.deleted: *', function (string $event, array $payload): void {
            $model = $payload[0] ?? null;
            if ($model instanceof Model) {
                $this->recordAudit($model, 'deleted', $model->getOriginal(), []);
            }
        });

        // Cloud mode is one unified "Hospitality OS" brand regardless of any tenant's own settings;
        // on-premise keeps the operator's own brand, editable from the admin settings page.
        config()->set('app.name', \App\Support\PlatformBrand::name());

        $locale = auth()->user()?->locale
            ?? session('locale')
            ?? BrandSettings::defaultLocale()
            ?? config('app.locale');
        app()->setLocale($locale);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    private function recordAudit(Model $model, string $event, array $oldValues, array $newValues): void
    {
        if ($model instanceof \App\Models\AuditLog || ! Schema::hasTable('audit_logs')) {
            return;
        }

        $redact = static function (array $values): array {
            return collect($values)
                ->except(['password', 'remember_token', 'api_token', 'token'])
                ->map(fn ($value) => is_object($value) ? (string) $value : $value)
                ->all();
        };
        $user = auth()->user();
        $attributes = $model->getAttributes();
        \App\Models\AuditLog::query()->create([
            'user_id' => $user?->id,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'action' => $event,
            'details' => json_encode([
                'old' => $redact($oldValues),
                'new' => $redact($newValues),
                'url' => request()->fullUrl(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }
}
