<dialog class="confirm-dialog" data-confirm-dialog data-default-message="{{ __('messages.dialog.delete_item') }}" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message">
    <form method="dialog" class="confirm-dialog-card">
        <div class="confirm-dialog-icon" aria-hidden="true">!</div>
        <div>
            <h2 id="confirm-dialog-title">{{ __('messages.dialog.confirm_title') }}</h2>
            <p id="confirm-dialog-message" data-confirm-dialog-message></p>
        </div>
        <div class="confirm-dialog-actions">
            <button type="button" class="btn btn-ghost" data-confirm-dialog-cancel>{{ __('messages.dialog.cancel') }}</button>
            <button type="button" class="btn btn-danger" data-confirm-dialog-accept>{{ __('messages.dialog.confirm') }}</button>
        </div>
    </form>
</dialog>
