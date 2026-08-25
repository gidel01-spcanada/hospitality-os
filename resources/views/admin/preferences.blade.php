{{-- resources/views/admin/preferences.blade.php --}}
@extends('layouts.admin')

@section('title', 'Preferences')

@section('content')
<!-- Page Header -->
<div class="admin-page-header">
    <h1 class="admin-page-title">User Preferences</h1>
    <p class="admin-page-description">Customize your admin experience and personal settings</p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: var(--space-6);">
    <!-- Profile Settings -->
    <x-card>
        <div class="card-header">
            <h3>Profile Information</h3>
        </div>
        <form method="POST" action="/admin/preferences/profile" class="card-body">
            @csrf
            @method('PUT')

            <x-input 
                name="name" 
                label="Full Name"
                value="{{ auth()->user()->name }}"
                required
            />

            <x-input 
                name="email" 
                label="Email Address"
                type="email"
                value="{{ auth()->user()->email }}"
                required
            />

            <x-input 
                name="current_password" 
                label="Current Password (to make changes)"
                type="password"
                required
            />

            <div style="display: flex; gap: var(--space-2); margin-top: var(--space-4);">
                <x-button type="submit" variant="primary">Save Profile</x-button>
            </div>
        </form>
    </x-card>

    <!-- Language & Locale -->
    <x-card>
        <div class="card-header">
            <h3>Language & Locale</h3>
        </div>
        <form method="POST" action="/admin/preferences/locale" class="card-body">
            @csrf
            @method('PUT')

            <div>
                <label for="locale" style="display: block; font-size: var(--font-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-2);">Preferred Language</label>
                <select name="locale" id="locale" class="input-md">
                    <option value="en" {{ auth()->user()->locale === 'en' ? 'selected' : '' }}>English</option>
                    <option value="fr" {{ auth()->user()->locale === 'fr' ? 'selected' : '' }}>Français</option>
                </select>
            </div>

            <div>
                <label for="timezone" style="display: block; font-size: var(--font-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-2); margin-top: var(--space-4);">Timezone</label>
                <select name="timezone" id="timezone" class="input-md">
                    <option value="UTC" {{ auth()->user()->timezone === 'UTC' ? 'selected' : '' }}>UTC</option>
                    <option value="Africa/Lagos" {{ auth()->user()->timezone === 'Africa/Lagos' ? 'selected' : '' }}>West Africa Time (Lagos)</option>
                    <option value="Europe/Paris" {{ auth()->user()->timezone === 'Europe/Paris' ? 'selected' : '' }}>Central European Time</option>
                </select>
            </div>

            <div style="display: flex; gap: var(--space-2); margin-top: var(--space-4);">
                <x-button type="submit" variant="primary">Save Preferences</x-button>
            </div>
        </form>
    </x-card>
</div>

<!-- Theme Selector -->
<div style="margin-top: var(--space-8);">
    <x-card>
        <div class="card-header">
            <h3>Theme Selection</h3>
            <p style="margin: var(--space-2) 0 0 0; color: var(--text-secondary); font-size: var(--font-sm);">
                Choose your preferred color theme for the admin panel
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
                        <div style="font-size: var(--font-sm); font-weight: var(--font-semibold);">Light Admin</div>
                        <div style="font-size: var(--font-xs); color: var(--text-secondary); margin-top: var(--space-1);">Professional & Clean</div>
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
                        <div style="font-size: var(--font-sm); font-weight: var(--font-semibold);">Dark Admin</div>
                        <div style="font-size: var(--font-xs); color: #9CA3AF; margin-top: var(--space-1);">Easy on the Eyes</div>
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
                        <div style="font-size: var(--font-sm); font-weight: var(--font-semibold);">Emerald</div>
                        <div style="font-size: var(--font-xs); color: var(--text-secondary); margin-top: var(--space-1);">Nature-Inspired</div>
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
                        <div style="font-size: var(--font-sm); font-weight: var(--font-semibold);">Gold</div>
                        <div style="font-size: var(--font-xs); color: var(--text-secondary); margin-top: var(--space-1);">Luxury Feel</div>
                    </div>
                </label>
            </div>

            <div style="display: flex; gap: var(--space-2);">
                <x-button type="submit" variant="primary">Apply Theme</x-button>
            </div>
        </form>
    </x-card>
</div>

<!-- Security -->
<div style="margin-top: var(--space-8);">
    <x-card>
        <div class="card-header">
            <h3>Security</h3>
        </div>

        <form method="POST" action="/admin/preferences/password" class="card-body">
            @csrf
            @method('PUT')

            <x-input 
                name="current_password" 
                label="Current Password"
                type="password"
                required
            />

            <x-input 
                name="password" 
                label="New Password"
                type="password"
                required
            />

            <x-input 
                name="password_confirmation" 
                label="Confirm New Password"
                type="password"
                required
            />

            <div style="display: flex; gap: var(--space-2); margin-top: var(--space-4);">
                <x-button type="submit" variant="primary">Update Password</x-button>
            </div>
        </form>
    </x-card>
</div>
@endsection
