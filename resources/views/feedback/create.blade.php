@extends('layouts.app')

@section('title', __('messages.feedback.title'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-emerald">{{ __('messages.feedback.badge') }}</span>
            <h1>{{ __('messages.feedback.title') }}</h1>
        </div>
    </section>
    <section class="container page-content">
        <div class="summary-card full-width feedback-form-shell">
            @if (session('status'))
                <div class="alert success" role="status">{{ session('status') }}</div>
            @endif
            @if (! $deliveryConfigured)
                <p class="form-help">{{ __('messages.feedback.unavailable') }}</p>
            @else
                @if ($errors->any())
                    <div class="form-alert form-alert-error" role="alert">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('feedback.store') }}" class="admin-form-grid">
                    @csrf
                    <div>
                        <label for="feedback-name">{{ __('messages.auth.full_name') }}</label>
                        <input id="feedback-name" name="name" value="{{ old('name', auth()->user()?->name) }}" required maxlength="255">
                    </div>
                    <div>
                        <label for="feedback-email">{{ __('messages.auth.email') }}</label>
                        <input id="feedback-email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" required maxlength="255">
                    </div>
                    <div>
                        <label for="feedback-category">{{ __('messages.feedback.category') }}</label>
                        <select id="feedback-category" name="category" required>
                            <option value="suggestion" @selected(old('category') === 'suggestion')>{{ __('messages.feedback.suggestion') }}</option>
                            <option value="issue" @selected(old('category') === 'issue')>{{ __('messages.feedback.issue') }}</option>
                            <option value="other" @selected(old('category') === 'other')>{{ __('messages.feedback.other') }}</option>
                        </select>
                    </div>
                    <div class="feedback-message-field">
                        <label for="feedback-message">{{ __('messages.feedback.message') }}</label>
                        <textarea id="feedback-message" name="message" rows="6" maxlength="5000" required>{{ old('message') }}</textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">{{ __('messages.feedback.submit') }}</button>
                    </div>
                </form>
            @endif
        </div>
    </section>
@endsection