@php($isStaff = $isStaff ?? false)
@extends($isStaff ? 'layouts.admin' : 'layouts.app')

@section('title', __('messages.messages.thread_title', ['establishment' => $thread->establishment->name]))

@section('content')
    <div class="{{ $isStaff ? 'admin-content' : 'container section' }}">
        <div class="admin-page-header">
            <h1 class="admin-page-title">{{ $thread->establishment->name }}</h1>
            <p class="admin-page-description">{{ $isStaff ? $thread->customer->name : __('messages.messages.description') }}</p>
        </div>

        @if (session('status'))
            <div class="reservation-success">{{ session('status') }}</div>
        @endif

        <div class="message-thread">
            @forelse ($thread->messages as $message)
                <article class="message-bubble {{ $message->sender_id === auth()->id() ? 'message-bubble-own' : '' }}">
                    <header><strong>{{ $message->sender->name }}</strong><time>{{ $message->created_at->translatedFormat('d M Y H:i') }}</time></header>
                    <p>{{ $message->body }}</p>
                </article>
            @empty
                <p class="empty-state">{{ __('messages.messages.no_messages') }}</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route($isStaff ? 'admin.messages.reply' : 'messages.reply', $thread) }}" class="message-form message-reply-form">
            @csrf
            <label for="body">{{ __('messages.messages.message') }}</label>
            <textarea id="body" name="body" rows="4" maxlength="5000" required></textarea>
            <button type="submit" class="btn btn-primary">{{ __('messages.messages.send') }}</button>
        </form>
    </div>
@endsection