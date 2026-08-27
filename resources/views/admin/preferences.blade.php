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
<div style="margin-top: var(--space-8);">
    <x-card>
        <div class="card-header">
            <h3>{{ __('messages.admin.theme_selection') }}</h3>
            <p style="margin: var(--space-2) 0 0 0; color: var(--text-secondary); font-size: var(--font-sm);">
                {{ __('messages.admin.theme_description') }}
            </p>
        </div>

        <form method="POST" action="/admin/preferences/theme" class="card-body">
            @csrf
            @method('PUT')

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
                <!-- Light Admin Theme -->
                <label style="cursor: pointer;">
                    <input 
                        type="radio" 
                        name="theme" 
                        value="light-admin"
                        {{ (auth()->user()->theme ?? 'light-admin') === 'light-admin' ? 'checked' : '' }}
                        style="display: none;"
                    />
                    <div style="
                        border: 3px solid {{ (auth()->user()->theme ?? 'light-admin') === 'light-admin' ? 'var(--theme-primary)' : 'var(--border-default)' }};
                        border-radius: var(--radius-lg);
                        padding: var(--space-4);
                        background: white;
                        transition: all 200ms ease;
                    ">
                        <div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-3);">
                            <div style="width: 24px; height: 24px; background: #1F2937; border-radius: var(--radius-md);"></div>
                            <div style="width: 24px; height: 24px; background: #F3F4F6; border-radius: var(--radius-md); border: 1px solid #D1D5DB;"></div>
                        </div>
                        <div style="font-size: var(--font-sm); font-weight: var(--font-semibold);">{{ __('messages.admin.theme_light') }}</div>
                        <div style="font-size: var(--font-xs); color: var(--text-secondary); margin-top: var(--space-1);">{{ __('messages.admin.theme_light_description') }}</div>
                    </div>
                </label>

                <!-- Dark Admin Theme -->
                <label style="cursor: pointer;">
                    <input 
                        type="radio" 
                        name="theme" 
                        value="dark-admin"
                        {{ (auth()->user()->theme ?? 'light-admin') === 'dark-admin' ? 'checked' : '' }}
                        style="display: none;"
                    />
                    <div style="
                        border: 3px solid {{ (auth()->user()->theme ?? 'light-admin') === 'dark-admin' ? 'var(--theme-primary)' : 'var(--border-default)' }};
                        border-radius: var(--radius-lg);
                        padding: var(--space-4);
                        background: #111827;
                        color: white;
                        transition: all 200ms ease;
                    ">
                        <div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-3);">
                            <div style="width: 24px; height: 24px; background: #F3F4F6; border-radius: var(--radius-md);"></div>
                            <div style="width: 24px; height: 24px; background: #1F2937; border-radius: var(--radius-md); border: 1px solid #374151;"></div>
                        </div>
                        <div style="font-size: var(--font-sm); font-weight: var(--font-semibold);">{{ __('messages.admin.theme_dark') }}</div>
                        <div style="font-size: var(--font-xs); color: #9CA3AF; margin-top: var(--space-1);">{{ __('messages.admin.theme_dark_description') }}</div>
                    </div>
                </label>

                <!-- Emerald Theme (Public Inspiration) -->
                <label style="cursor: pointer;">
                    <input 
                        type="radio" 
                        name="theme" 
                        value="emerald"
                        {{ (auth()->user()->theme ?? 'light-admin') === 'emerald' ? 'checked' : '' }}
                        style="display: none;"
                    />
                    <div style="
                        border: 3px solid {{ (auth()->user()->theme ?? 'light-admin') === 'emerald' ? 'var(--theme-primary)' : 'var(--border-default)' }};
                        border-radius: var(--radius-lg);
                        padding: var(--space-4);
                        background: white;
                        transition: all 200ms ease;
                    ">
                        <div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-3);">
                            <div style="width: 24px; height: 24px; background: #0f5b4c; border-radius: var(--radius-md);"></div>
                            <div style="width: 24px; height: 24px; background: #d1fae5; border-radius: var(--radius-md);"></div>
                        </div>
                        <div style="font-size: var(--font-sm); font-weight: var(--font-semibold);">{{ __('messages.admin.theme_emerald') }}</div>
                        <div style="font-size: var(--font-xs); color: var(--text-secondary); margin-top: var(--space-1);">{{ __('messages.admin.theme_emerald_description') }}</div>
                    </div>
                </label>

                <!-- Gold Theme (Public Inspiration) -->
                <label style="cursor: pointer;">
                    <input 
                        type="radio" 
                        name="theme" 
                        value="gold"
                        {{ (auth()->user()->theme ?? 'light-admin') === 'gold' ? 'checked' : '' }}
                        style="display: none;"
                    />
                    <div style="
                        border: 3px solid {{ (auth()->user()->theme ?? 'light-admin') === 'gold' ? 'var(--theme-primary)' : 'var(--border-default)' }};
                        border-radius: var(--radius-lg);
                        padding: var(--space-4);
                        background: white;
                        transition: all 200ms ease;
                    ">
                        <div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-3);">
                            <div style="width: 24px; height: 24px; background: #c89b3c; border-radius: var(--radius-md);"></div>
                            <div style="width: 24px; height: 24px; background: #fef3c7; border-radius: var(--radius-md);"></div>
                        </div>
                        <div style="font-size: var(--font-sm); font-weight: var(--font-semibold);">{{ __('messages.admin.theme_gold') }}</div>
                        <div style="font-size: var(--font-xs); color: var(--text-secondary); margin-top: var(--space-1);">{{ __('messages.admin.theme_gold_description') }}</div>
                    </div>
                </label>
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
