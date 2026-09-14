@extends('layouts.app')

@section('title', __('messages.auth.host_register_title'))

@section('content')
    <section class="auth-page">
        <div class="auth-card">
            <div class="auth-header">
                <span class="badge badge-gold">{{ __('messages.auth.host_register_badge') }}</span>
                <h1>{{ __('messages.auth.host_register_title') }}</h1>
                <p>{{ __('messages.auth.host_register_subtitle') }}</p>
            </div>

            @if ($errors->any())
                <div class="form-alert form-alert-error" role="alert">
                    @foreach ($errors->all() as $error)
                        <span>{{ $error }}</span>
                    @endforeach
                </div>
            @endif

            <form class="auth-form" method="POST" action="{{ route('host.register.store') }}">
                @csrf

                <label>
                    <span>{{ __('messages.auth.company_name') }}</span>
                    <input type="text" name="company_name" value="{{ old('company_name') }}" required>
                </label>

                <label>
                    <span>{{ __('messages.auth.company_contact_email') }}</span>
                    <input type="email" name="contact_email" value="{{ old('contact_email') }}" required>
                </label>

                <label>
                    <span>{{ __('messages.auth.full_name') }}</span>
                    <input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required>
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                </label>

                <label>
                    <span>{{ __('messages.auth.language') }}</span>
                    <select name="locale" required>
                        <option value="fr" @selected(old('locale', app()->getLocale()) === 'fr')>Français</option>
                        <option value="en" @selected(old('locale', app()->getLocale()) === 'en')>English</option>
                    </select>
                </label>

                <label>
                    <span>{{ __('messages.auth.password') }}</span>
                    <input type="password" name="password" autocomplete="new-password" required>
                </label>

                <label>
                    <span>{{ __('messages.auth.confirm_password') }}</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password" required>
                </label>

                @include('partials.recaptcha')

                <button class="btn btn-primary auth-submit" type="submit">{{ __('messages.auth.host_create_workspace') }}</button>
            </form>

            <p class="auth-switch">
                {{ __('messages.auth.already_member') }}
                <a href="{{ route('login') }}">{{ __('messages.auth.sign_in') }}</a>
            </p>
        </div>
    </section>
@endsection
