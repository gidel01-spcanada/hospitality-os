@php($isStaff = $isStaff ?? false)
@extends($isStaff ? 'layouts.admin' : 'layouts.app')

@section('title', __('messages.messages.title'))

@section('content')
    <div class="{{ $isStaff ? 'admin-content' : 'container section' }}">
        <div class="admin-page-header">
            <h1 class="admin-page-title">{{ __('messages.messages.title') }}</h1>
            <p class="admin-page-description">{{ __('messages.messages.description') }}</p>
        </div>

        @if (!$isStaff)
            <div class="message-new-panel">
                <h2>{{ __('messages.messages.start_thread') }}</h2>
                <form method="POST" action="{{ route('messages.store') }}" class="message-form">
                    @csrf
                    <label for="establishment_id">{{ __('messages.messages.establishment') }}</label>
                    <select id="establishment_id" name="establishment_id" required>
                        <option value="">{{ __('messages.messages.select_establishment') }}</option>
                        @foreach ($establishments as $establishment)
                            <option value="{{ $establishment->id }}">{{ $establishment->name }}</option>
                        @endforeach
                    </select>
                    <label for="body">{{ __('messages.messages.message') }}</label>
                    <textarea id="body" name="body" rows="4" maxlength="5000" required></textarea>
                    <button type="submit" class="btn btn-primary">{{ __('messages.messages.send') }}</button>
                </form>
            </div>
        @endif

        <div class="message-thread-list">
            @forelse ($threads as $thread)
                <a class="message-thread-preview" href="{{ route($isStaff ? 'admin.messages.show' : 'messages.show', $thread) }}">
                    <strong>{{ $isStaff ? $thread->customer->name : $thread->establishment->name }}</strong>
                    <span>{{ $thread->messages->first()?->body ?? __('messages.messages.no_messages') }}</span>
                </a>
            @empty
                <p class="empty-state">{{ __('messages.messages.no_threads') }}</p>
            @endforelse
        </div>
    </div>
@endsection