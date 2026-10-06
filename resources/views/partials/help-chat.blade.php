<details class="help-chat" data-help-chat>
    <summary class="help-chat-launcher" aria-label="{{ __('messages.help_chat.open') }}" title="{{ __('messages.help_chat.open') }}">
        <span class="help-chat-launcher-compact" aria-hidden="true">?</span>
    </summary>
    <section class="help-chat-panel" aria-label="{{ __('messages.help_chat.title') }}">
        <header class="help-chat-header">
            <h2>{{ __('messages.help_chat.title') }}</h2>
            <button type="button" class="btn btn-ghost btn-small" data-help-chat-minimize aria-label="{{ __('messages.help_chat.minimize') }}">−</button>
            <button type="button" class="btn btn-ghost btn-small" data-help-chat-close aria-label="{{ __('messages.help_chat.close') }}">×</button>
        </header>
        <p class="help-chat-privacy">{{ __('messages.help_chat.privacy') }}</p>
        <div class="help-chat-messages" data-help-chat-messages aria-live="polite">
            <p class="help-chat-answer">{{ __('messages.help_chat.greeting') }}</p>
        </div>
        <form class="help-chat-form" data-help-chat-form data-error-message="{{ __('messages.help_chat.failed') }}" action="{{ route('help-chat.ask') }}" method="POST">
            @csrf
            <label class="sr-only" for="help-chat-message">{{ __('messages.help_chat.message') }}</label>
            <textarea id="help-chat-message" name="message" rows="2" maxlength="1200" required></textarea>
            <div class="help-chat-form-actions">
                <p data-help-chat-status role="status" aria-live="polite"></p>
                <button type="submit" class="btn btn-primary">{{ __('messages.help_chat.send') }}</button>
            </div>
        </form>
    </section>
</details>