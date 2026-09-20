{{-- resources/views/admin/preferences.blade.php --}}
@extends('layouts.admin')

@section('title', __('messages.admin.user_preferences'))

@section('content')
<!-- Page Header -->
<div class="admin-page-header">
    <h1 class="admin-page-title">{{ __('messages.admin.user_preferences') }}</h1>
    <p class="admin-page-description">{{ __('messages.admin.user_preferences_description') }}</p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: var(--space-6);">
    <!-- Profile Settings -->
    <x-card>
        <div class="card-header">
            <h3>{{ __('messages.profile.personal') }}</h3>
        </div>
        <form method="POST" action="/admin/preferences/profile" class="card-body">
            @csrf
            @method('PUT')

            <x-input 
                name="name" 
                label="{{ __('messages.profile.full_name') }}"
                value="{{ auth()->user()->name }}"
                required
            />

            <x-input 
                name="email" 
                label="{{ __('messages.profile.email') }}"
                type="email"
                value="{{ auth()->user()->email }}"
                required
            />

            <x-input 
                name="current_password" 
                label="{{ __('messages.admin.current_password_for_changes') }}"
                type="password"
                required
            />

            <div style="display: flex; gap: var(--space-2); margin-top: var(--space-4);">
                <x-button type="submit" variant="primary">{{ __('messages.profile.save') }}</x-button>
            </div>
        </form>
    </x-card>

    <!-- Language & Locale -->
    <x-card>
        <div class="card-header">
            <h3>{{ __('messages.admin.language_locale') }}</h3>
        </div>
        <form method="POST" action="/admin/preferences/locale" class="card-body">
            @csrf
            @method('PUT')

            <div>
                <label for="locale" style="display: block; font-size: var(--font-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-2);">{{ __('messages.profile.language') }}</label>
                <select name="locale" id="locale" class="input-md">
                    <option value="en" {{ auth()->user()->locale === 'en' ? 'selected' : '' }}>English</option>
                    <option value="fr" {{ auth()->user()->locale === 'fr' ? 'selected' : '' }}>Français</option>
                </select>
            </div>

            <div>
                <label for="timezone" style="display: block; font-size: var(--font-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-2); margin-top: var(--space-4);">{{ __('messages.admin.timezone') }}</label>
                <select name="timezone" id="timezone" class="input-md">
                    <option value="UTC" {{ auth()->user()->timezone === 'UTC' ? 'selected' : '' }}>UTC</option>
                    <option value="Africa/Lagos" {{ auth()->user()->timezone === 'Africa/Lagos' ? 'selected' : '' }}>West Africa Time (Lagos)</option>
                    <option value="Europe/Paris" {{ auth()->user()->timezone === 'Europe/Paris' ? 'selected' : '' }}>Central European Time</option>
                </select>
            </div>

            <div style="display: flex; gap: var(--space-2); margin-top: var(--space-4);">
                <x-button type="submit" variant="primary">{{ __('messages.profile.save') }}</x-button>
            </div>
        </form>
    </x-card>
</div>

<!-- Theme Selector -->
<div class="admin-preferences-section">
    <x-card>
        <div class="card-header">
            <h3>{{ __('messages.admin.theme_selection') }}</h3>
            <p class="theme-description">
                {{ __('messages.admin.theme_description') }}
            </p>
        </div>

        <form method="POST" action="/admin/preferences/theme" class="card-body">
            @csrf
            @method('PUT')

            <div class="theme-option-grid">
                @foreach ([
                    'light-admin' => ['class' => '', 'title' => __('messages.admin.theme_light'), 'copy' => __('messages.admin.theme_light_description'), 'swatches' => ['light-ink', 'light-surface']],
                    'dark-admin' => ['class' => 'theme-option-dark', 'title' => __('messages.admin.theme_dark'), 'copy' => __('messages.admin.theme_dark_description'), 'swatches' => ['dark-text', 'dark-surface']],
                    'emerald' => ['class' => '', 'title' => __('messages.admin.theme_emerald'), 'copy' => __('messages.admin.theme_emerald_description'), 'swatches' => ['emerald', 'emerald-light']],
                    'gold' => ['class' => '', 'title' => __('messages.admin.theme_gold'), 'copy' => __('messages.admin.theme_gold_description'), 'swatches' => ['gold', 'gold-light']],
                ] as $themeValue => $themeOption)
                    <label class="theme-option {{ $themeOption['class'] }}">
                        <input type="radio" name="theme" value="{{ $themeValue }}" @checked((auth()->user()->theme ?? 'light-admin') === $themeValue)>
                        <div class="theme-option-card">
                            <div class="theme-swatches" aria-hidden="true">
                                @foreach ($themeOption['swatches'] as $swatch)
                                    <span class="theme-swatch theme-swatch-{{ $swatch }}"></span>
                                @endforeach
                            </div>
                            <div class="theme-option-title">{{ $themeOption['title'] }}</div>
                            <div class="theme-option-copy">{{ $themeOption['copy'] }}</div>
                        </div>
                    </label>
                @endforeach
            </div>

            <div style="display: flex; gap: var(--space-2);">
                <x-button type="submit" variant="primary">{{ __('messages.admin.apply_theme') }}</x-button>
            </div>
        </form>
    </x-card>
</div>

<!-- Security -->
<div style="margin-top: var(--space-8);">
    <x-card>
        <div class="card-header">
            <h3>{{ __('messages.profile.tabs.security') }}</h3>
        </div>

        <form method="POST" action="/admin/preferences/password" class="card-body">
            @csrf
            @method('PUT')

            <x-input 
                name="current_password" 
                label="{{ __('messages.security.current_password') }}"
                type="password"
                required
            />

            <x-input 
                name="password" 
                label="{{ __('messages.security.new_password') }}"
                type="password"
                required
            />

            <x-input 
                name="password_confirmation" 
                label="{{ __('messages.security.confirm_password') }}"
                type="password"
                required
            />

            <div style="display: flex; gap: var(--space-2); margin-top: var(--space-4);">
                <x-button type="submit" variant="primary">{{ __('messages.security.save') }}</x-button>
            </div>
        </form>
    </x-card>
</div>
@endsection
