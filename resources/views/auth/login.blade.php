@extends('layouts.app')

@section('title', __('messages.auth.login_title'))

@section('content')
    <section class="auth-page">
        <div class="auth-card">
            <div class="auth-header">
                <span class="badge badge-emerald">{{ __('messages.auth.welcome') }}</span>
                <h1>{{ __('messages.auth.login_title') }}</h1>
                <p>{{ __('messages.auth.login_subtitle', ['brand' => \App\Support\PlatformBrand::name()]) }}</p>
            </div>

            @if ($errors->any())
                <div class="form-alert form-alert-error" role="alert">
                    @foreach ($errors->all() as $error)
                        <span>{{ $error }}</span>
                    @endforeach
                </div>
            @endif

            <form class="auth-form" method="POST" action="{{ route('login') }}">
                @csrf

                <label>
                    <span>{{ __('messages.auth.email') }}</span>
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                </label>

                <label>
                    <span>{{ __('messages.common.password') }}</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>

                @include('partials.recaptcha')

                <a class="inline-link" href="{{ route('password.request') }}">{{ __('messages.auth.forgot_password') }}</a>

                <label class="checkbox-row" for="remember">
                    <input id="remember" type="checkbox" name="remember" value="1">
                    <span>{{ __('messages.auth.remember') }}</span>
                </label>

                <button class="btn btn-primary auth-submit" type="submit">{{ __('messages.auth.sign_in') }}</button>
            </form>

            <p class="auth-switch">
                {{ __('messages.auth.no_account') }}
                <a href="{{ route('register') }}">{{ __('messages.nav.sign_up') }}</a>
            </p>
        </div>
    </section>
@endsection
