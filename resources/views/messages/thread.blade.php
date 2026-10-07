@php($isStaff = $isStaff ?? false)
@extends($isStaff ? 'layouts.admin' : 'layouts.app')

@section('title', __('messages.messages.thread_title', ['establishment' => $thread->establishment->name]))

@section('content')
    <div class="{{ $isStaff ? 'admin-content' : 'container section portal-thread-page' }}">
        <div class="{{ $isStaff ? 'admin-page-header' : 'portal-page-heading' }}">
            @if ($isStaff)
                <a class="btn btn-ghost btn-small" href="{{ route('admin.messages.index') }}">{{ __('messages.admin.back_list') }}</a>
            @endif
            <h1 class="admin-page-title">{{ $thread->establishment->name }}</h1>
            <p class="admin-page-description">{{ $isStaff ? $thread->customer->name : __('messages.messages.description') }}</p>
        </div>

        @if (!$isStaff)
            <div class="portal-thread-layout">
                <aside class="portal-conversation-list" aria-label="{{ __('messages.messages.conversations') }}">
                    <a class="portal-back-inbox" href="{{ route('messages.index') }}">← {{ __('messages.messages.title') }}</a>
                    @foreach ($threads as $conversation)
                        <a class="portal-conversation-link" href="{{ route('messages.show', $conversation) }}" @if ($conversation->id === $thread->id) aria-current="page" @endif>
                            <strong>{{ $conversation->establishment->name }}</strong>
                            <span>{{ $conversation->messages->first()?->body ?? __('messages.messages.no_messages') }}</span>
                            @if ($conversation->updated_at)<time datetime="{{ $conversation->updated_at->toIso8601String() }}">{{ $conversation->updated_at->translatedFormat('d M') }}</time>@endif
                        </a>
                    @endforeach
                </aside>
                <div class="portal-thread-content">
        @endif

        @if ($reservation)
            <p class="message-reservation-link">
                <a class="inline-link" href="{{ route($isStaff ? 'admin.reservations.show' : 'dashboard.reservations.show', $reservation) }}">{{ __('messages.messages.return_to_reservation', ['reference' => $reservation->reservation_ref]) }}</a>
            </p>
        @endif

        @if (session('status'))
            <div class="reservation-success" role="status" aria-live="polite">{{ session('status') }}</div>
        @endif

        <div class="message-thread">
            @php($lastMessageDate = null)
            @forelse ($thread->messages as $message)
                @if ($lastMessageDate !== $message->created_at->toDateString())
                    <div class="portal-message-date-separator"><span>{{ $message->created_at->translatedFormat('l d M Y') }}</span></div>
                    @php($lastMessageDate = $message->created_at->toDateString())
                @endif
                <article class="message-bubble {{ $message->sender_id === auth()->id() ? 'message-bubble-own' : '' }}">
                    <header>
                        <span class="portal-message-author">
                            <strong>{{ $message->sender->name }}</strong>
                            <small>{{ __('messages.admin.' . ($message->sender->role === 'admin' ? 'administrator' : $message->sender->role)) }}</small>
                        </span>
                        <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->translatedFormat('d M Y H:i') }}</time>
                    </header>
                    <p>{{ $message->body }}</p>
                </article>
            @empty
                <p class="empty-state">{{ __('messages.messages.no_messages') }}</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route($isStaff ? 'admin.messages.reply' : 'messages.reply', $thread) }}" class="message-form message-reply-form portal-message-composer">
            @csrf
            <label for="body">{{ __('messages.messages.message') }}</label>
            <textarea id="body" name="body" rows="3" maxlength="5000" required @error('body') aria-invalid="true" aria-describedby="reply-message-error" @enderror>{{ old('body') }}</textarea>
            @error('body')<p class="portal-field-error" id="reply-message-error" role="alert">{{ $message }}</p>@enderror
            <button type="submit" class="btn btn-primary">{{ __('messages.messages.send') }}</button>
        </form>
        @if (!$isStaff)
                </div>
            </div>
        @endif
    </div>
@endsection