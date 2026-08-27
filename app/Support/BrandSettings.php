<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BrandSettings
{
    /**
     * Default values used when no brand configuration has been saved yet.
     */
    public const DEFAULTS = [
        'site_name' => 'Afrik Appart',
        'site_tagline' => 'Séjours premium pour des escapades sereines en Afrique de l’Ouest.',
        'contact_email' => 'support@afrikappart.example',
        'support_phone' => '+229 00 00 00 00',
        'default_locale' => 'fr',
        'secondary_locale' => 'en',
        'review_source_booking_url' => '',
        'review_source_google_url' => '',
        'email_sender_name' => 'Afrik Appart',
        'email_sender_email' => 'support@afrikappart.example',
        'customer_confirmation_subject' => 'Confirmation de votre demande',
        'customer_confirmation_message' => 'Merci pour votre demande. Nous vous répondrons rapidement.',
        'pre_arrival_subject' => 'Votre arrivée prochaine',
        'pre_arrival_message' => 'Nous vous souhaitons la bienvenue et nous sommes ravis de vous accueillir très bientôt.',
        'post_stay_subject' => 'Merci pour votre séjour',
        'post_stay_message' => 'Merci d’avoir choisi Afrik Appart. Nous espérons vous revoir bientôt.',
        'footer_copy' => 'Séjours premium pour des escapades sereines en Afrique de l’Ouest.',
    ];

    public static function all(): array
    {
        if (! Schema::hasTable('settings')) {
            return self::DEFAULTS;
        }

        $rows = self::query()->select(['key', 'value'])->get()->keyBy('key');

        $settings = [];
        foreach (self::DEFAULTS as $key => $default) {
            $settings[$key] = $rows->has($key) && $rows[$key]->value !== null ? (string) $rows[$key]->value : $default;
        }

        foreach ($rows as $key => $row) {
            if (! array_key_exists($key, $settings)) {
                $settings[$key] = (string) $row->value;
            }
        }

        return $settings;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            self::query()->updateOrInsert(
                ['key' => $key, 'tenant_id' => app(CurrentTenant::class)->id()],
                [
                    'key' => $key,
                    'value' => (string) $value,
                    'type' => 'string',
                    'tenant_id' => app(CurrentTenant::class)->id(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    /**
     * Scoped to the current tenant when one is resolved (admin requests). Falls back to an
     * unfiltered query otherwise -- today that only ever means the single on-premise tenant;
     * unifying public branding across tenants for cloud mode is a later phase (ADR 0002).
     */
    private static function query(): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('settings');

        if ($tenantId = app(CurrentTenant::class)->id()) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    public static function siteName(): string
    {
        return (string) self::get('site_name', self::DEFAULTS['site_name']);
    }

    public static function siteTagline(): string
    {
        return (string) self::get('site_tagline', self::DEFAULTS['site_tagline']);
    }

    public static function supportEmail(): string
    {
        return (string) self::get('contact_email', self::DEFAULTS['contact_email']);
    }

    public static function supportPhone(): string
    {
        return (string) self::get('support_phone', self::DEFAULTS['support_phone']);
    }

    public static function defaultLocale(): string
    {
        return (string) self::get('default_locale', self::DEFAULTS['default_locale']);
    }
}
