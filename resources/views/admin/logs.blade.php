@extends('layouts.admin')

@section('title', __('messages.admin.logs'))

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">{{ __('messages.admin.logs') }}</h1>
        <p class="admin-page-description">{{ __('messages.admin.logs_description') }}</p>
    </div>
    <div class="admin-page-actions">
        <a class="btn btn-ghost" href="{{ route('admin.audit-logs') }}">Journal des modifications</a>
    </div>
</div>

<x-card class="audit-log-card">
    <form method="GET" action="{{ route('admin.logs') }}" class="admin-form-grid compact log-filter-form">
        <label><span>{{ __('messages.admin.log_file') }}</span><select name="file">@forelse($files as $file)<option value="{{ $file->getFilename() }}" @selected($selected?->getFilename() === $file->getFilename())>{{ $file->getFilename() }}</option>@empty<option>{{ __('messages.admin.no_log_files') }}</option>@endforelse</select></label>
        <label><span>{{ __('messages.admin.log_lines') }}</span><input type="number" name="lines" min="50" max="1000" value="{{ $lineLimit }}"></label>
        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.refresh_logs') }}</button></div>
    </form>
    @if($selected)
        <div class="admin-log-toolbar">
            <p class="form-help">{{ __('messages.admin.showing_log_file', ['file' => $selected->getFilename()]) }}</p>
            <div class="admin-log-copy-action">
                <button type="button" class="btn btn-ghost" data-copy-text="{{ $content }}" data-copy-success="{{ __('messages.admin.log_copied') }}" data-copy-error="{{ __('messages.admin.log_copy_failed') }}">{{ __('messages.admin.copy_log') }}</button>
                <span data-copy-text-status role="status" aria-live="polite" hidden></span>
            </div>
        </div>
        <pre class="admin-log-output" tabindex="0"><code>{{ $content }}</code></pre>
    @else
        <p class="empty-note">{{ __('messages.admin.no_log_files') }}</p>
    @endif
</x-card>
@endsection
