@extends('layouts.app')

@section('title', __('messages.auth.forgot_title'))

@section('content')
<section class="page-hero compact-hero">
    <div class="container narrow">
        <span class="badge badge-emerald">{{ __('messages.auth.forgot_badge') }}</span>
        <h1>{{ __('messages.auth.forgot_title') }}</h1>
        <p>{{ __('messages.auth.forgot_subtitle') }}</p>
    </div>
</section>

<section class="container auth-panel">
    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
        @csrf

        @if (session('status'))
            <div class="alert success">{{ session('status') }}</div>
        @endif

        <div class="field-group">
            <label for="email">{{ __('messages.auth.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus @error('email') class="is-error" aria-invalid="true" aria-describedby="forgot-email-error" @enderror>
            @error('email')
                <span id="forgot-email-error" class="form-error">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary">{{ __('messages.auth.send_link') }}</button>
    </form>
</section>
@endsection
