<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body style="font-family: Arial, sans-serif; color: #1d2a2d; line-height: 1.6;">
    <h1>{{ __('messages.messages.email_heading') }}</h1>
    <p>{{ __('messages.messages.email_intro', ['sender' => $sender_name ?? '', 'establishment' => $establishment_name ?? '']) }}</p>
    <blockquote style="margin: 1rem 0; padding: 0.8rem 1rem; border-left: 4px solid #0f5b4c; background: #f6f3ec; white-space: pre-wrap;">{{ $message ?? '' }}</blockquote>
    @if (!empty($thread_url))
        <p><a href="{{ $thread_url }}">{{ __('messages.messages.email_action') }}</a></p>
    @endif
</body>
</html>