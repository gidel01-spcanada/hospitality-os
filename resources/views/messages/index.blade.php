@php($isStaff = $isStaff ?? false)
@extends($isStaff ? 'layouts.admin' : 'layouts.app')

@section('title', __('messages.messages.title'))

@section('content')
    <div class="{{ $isStaff ? 'admin-content' : 'container section portal-inbox-index' }}">
        <div class="{{ $isStaff ? 'admin-page-header' : 'portal-page-heading' }}">
            <h1 class="admin-page-title">{{ __('messages.messages.title') }}</h1>
            <p class="admin-page-description">{{ __('messages.messages.description') }}</p>
        </div>

        @if ($threads->isNotEmpty())
            <section class="message-inbox {{ $isStaff ? '' : 'portal-inbox-list' }}" aria-labelledby="message-inbox-title">
                <h2 id="message-inbox-title">{{ __('messages.messages.conversations') }}</h2>
                <div class="message-thread-list">
                    @foreach ($threads as $thread)
                        <a class="message-thread-preview" href="{{ route($isStaff ? 'admin.messages.show' : 'messages.show', $thread) }}">
                            <strong>{{ $isStaff ? $thread->customer->name : $thread->establishment->name }}</strong>
                            <span>{{ $thread->messages->first()?->body ?? __('messages.messages.no_messages') }}</span>
                            @if ($thread->updated_at)<time datetime="{{ $thread->updated_at->toIso8601String() }}">{{ $thread->updated_at->translatedFormat('d M Y') }}</time>@endif
                        </a>
                    @endforeach
                </div>
            </section>
        @else
            <p class="empty-state portal-empty-state">{{ __('messages.messages.no_threads') }}</p>
        @endif

        @if (!$isStaff)
            <details class="message-new-panel portal-new-conversation" @if($threads->isEmpty() || $errors->any()) open @endif>
                <summary>{{ __('messages.messages.start_thread') }}</summary>
                <form method="POST" action="{{ route('messages.store') }}" class="message-form">
                    @csrf
                    <label for="establishment_id">{{ __('messages.messages.send_to') }}</label>
                    <select id="establishment_id" name="establishment_id" required>
                        <option value="">{{ __('messages.messages.select_establishment') }}</option>
                        @foreach ($establishments as $establishment)
                            <option value="{{ $establishment->id }}">{{ $establishment->name }}</option>
                        @endforeach
                    </select>
                    <label for="body">{{ __('messages.messages.message') }}</label>
                    <textarea id="body" name="body" rows="4" maxlength="5000" required @error('body') aria-invalid="true" aria-describedby="new-message-error" @enderror>{{ old('body') }}</textarea>
                    @error('body')<p class="portal-field-error" id="new-message-error" role="alert">{{ $message }}</p>@enderror
                    <button type="submit" class="btn btn-primary">{{ __('messages.messages.send') }}</button>
                </form>
            </details>
        @endif
    </div>
@endsection