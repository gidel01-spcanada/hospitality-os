<?php

return [
    // on_premise: single tenant, current behaviour, operator's own branding.
    // cloud: multi-tenant "Hospitality OS", self-serve signup, unified public branding.
    'mode' => env('PLATFORM_MODE', 'on_premise'),

    'brand_name' => env('PLATFORM_MODE') === 'cloud'
        ? 'Hospitality OS'
        : env('APP_BRAND_NAME'),
];
