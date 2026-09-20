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
                    <input type="text" name="company_name" value="{{ old('company_name') }}" required @error('company_name') class="is-error" aria-invalid="true" aria-describedby="host-company-error" @enderror>
                    @error('company_name')<span id="host-company-error" class="form-error">{{ $message }}</span>@enderror
                </label>

                <label>
                    <span>{{ __('messages.auth.company_contact_email') }}</span>
                    <input type="email" name="contact_email" value="{{ old('contact_email') }}" required @error('contact_email') class="is-error" aria-invalid="true" aria-describedby="host-contact-error" @enderror>
                    @error('contact_email')<span id="host-contact-error" class="form-error">{{ $message }}</span>@enderror
                </label>

                <label>
                    <span>{{ __('messages.auth.full_name') }}</span>
                    <input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required @error('name') class="is-error" aria-invalid="true" aria-describedby="host-name-error" @enderror>
                    @error('name')<span id="host-name-error" class="form-error">{{ $message }}</span>@enderror
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required @error('email') class="is-error" aria-invalid="true" aria-describedby="host-email-error" @enderror>
                    @error('email')<span id="host-email-error" class="form-error">{{ $message }}</span>@enderror
                </label>

                <label>
                    <span>{{ __('messages.auth.language') }}</span>
                    <select name="locale" required @error('locale') class="is-error" aria-invalid="true" aria-describedby="host-locale-error" @enderror>
                        <option value="fr" @selected(old('locale', app()->getLocale()) === 'fr')>Français</option>
                        <option value="en" @selected(old('locale', app()->getLocale()) === 'en')>English</option>
                    </select>
                    @error('locale')<span id="host-locale-error" class="form-error">{{ $message }}</span>@enderror
                </label>

                <label>
                    <span>{{ __('messages.auth.password') }}</span>
                    <input type="password" name="password" autocomplete="new-password" required @error('password') class="is-error" aria-invalid="true" aria-describedby="host-password-error" @enderror>
                    @error('password')<span id="host-password-error" class="form-error">{{ $message }}</span>@enderror
                </label>

                <label>
                    <span>{{ __('messages.auth.confirm_password') }}</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password" required @error('password') class="is-error" aria-invalid="true" aria-describedby="host-password-error" @enderror>
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
