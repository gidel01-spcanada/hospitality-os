<?php

namespace App\Support;

/**
 * Single source of truth for the brand name shown on public pages. Cloud mode is one
 * unified "Hospitality OS" brand across every tenant; on-premise keeps the operator's
 * own brand, editable from the admin Settings page.
 */
class PlatformBrand
{
    public static function name(): string
    {
        return config('platform.mode') === 'cloud'
            ? (string) config('platform.brand_name', 'Hospitality OS')
            : BrandSettings::siteName();
    }
}
