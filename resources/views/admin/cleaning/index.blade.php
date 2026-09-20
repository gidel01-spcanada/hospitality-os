@extends('layouts.admin')

@section('title', __('messages.cleaning.title'))

@section('content')
<div class="admin-page-header cleaning-page-header">
    <div>
        <h1 class="admin-page-title">{{ __('messages.cleaning.title') }}</h1>
        <p class="admin-page-description">{{ __('messages.cleaning.description') }}</p>
    </div>
</div>

@if (session('cleaning_share_url'))
    @php $sharedUrl = session('cleaning_share_url'); @endphp
    <div class="cleaning-share-result" role="status">
        <strong>{{ __('messages.cleaning.share_ready') }}</strong>
        <input value="{{ $sharedUrl }}" readonly aria-label="{{ __('messages.cleaning.share_url') }}">
        <button type="button" class="btn btn-ghost" data-copy-text="{{ $sharedUrl }}">{{ __('messages.cleaning.copy_link') }}</button>
        <a class="btn btn-ghost" href="https://wa.me/?text={{ urlencode(__('messages.cleaning.whatsapp_message', ['url' => $sharedUrl])) }}" target="_blank" rel="noopener">{{ __('messages.cleaning.share_whatsapp') }}</a>
        <a class="btn btn-ghost" href="{{ $sharedUrl }}" target="_blank" rel="noopener">{{ __('messages.cleaning.open') }}</a>
    </div>
@endif

<x-card class="cleaning-filter-card">
    <form method="GET" action="{{ route('admin.cleaning.index') }}" class="cleaning-filters">
        <label><span>{{ __('messages.cleaning.view') }}</span><select name="mode"><option value="week" @selected($mode === 'week')>{{ __('messages.cleaning.week') }}</option><option value="month" @selected($mode === 'month')>{{ __('messages.cleaning.month') }}</option></select></label>
        <label><span>{{ __('messages.cleaning.reference_date') }}</span><input type="date" name="date" value="{{ $reference->toDateString() }}"></label>
        <label><span>{{ __('messages.admin.property') }}</span><select name="property"><option value="">{{ __('messages.reports.all_properties') }}</option>@foreach($properties as $property)<option value="{{ $property->id }}" @selected($propertyId === $property->id)>{{ $property->name }}</option>@endforeach</select></label>
        <label><span>{{ __('messages.cleaning.assignee') }}</span><select name="assignee"><option value="">{{ __('messages.cleaning.all_assignees') }}</option>@foreach($assignees as $name)<option value="{{ $name }}" @selected($assignee === $name)>{{ $name }}</option>@endforeach</select></label>
        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.reports.apply') }}</button><a class="btn btn-ghost" href="{{ route('admin.cleaning.index') }}">{{ __('messages.reports.reset') }}</a></div>
    </form>
</x-card>

<div class="cleaning-period-bar">
    <div class="cleaning-period-summary"><strong>{{ $from->translatedFormat('d M Y') }} - {{ $to->translatedFormat('d M Y') }}</strong><span>{{ trans_choice('messages.cleaning.visit_count', $visits->count(), ['count' => $visits->count()]) }}</span></div>
    <nav class="cleaning-period-nav" aria-label="{{ __('messages.cleaning.period_navigation') }}">
        <a class="btn btn-ghost" href="{{ route('admin.cleaning.index', array_filter(['mode' => $mode, 'date' => $from->subDay()->toDateString(), 'property' => $propertyId, 'assignee' => $assignee])) }}" aria-label="{{ __('messages.cleaning.previous_period') }}">&larr;</a>
        <a class="btn btn-ghost" href="{{ route('admin.cleaning.index', array_filter(['mode' => $mode, 'date' => now()->toDateString(), 'property' => $propertyId, 'assignee' => $assignee])) }}">{{ __('messages.cleaning.today') }}</a>
        <a class="btn btn-ghost" href="{{ route('admin.cleaning.index', array_filter(['mode' => $mode, 'date' => $to->addDay()->toDateString(), 'property' => $propertyId, 'assignee' => $assignee])) }}" aria-label="{{ __('messages.cleaning.next_period') }}">&rarr;</a>
    </nav>
    <form method="POST" action="{{ route('admin.cleaning.share') }}" class="cleaning-share-form">@csrf<input type="hidden" name="mode" value="{{ $mode }}"><input type="hidden" name="date" value="{{ $reference->toDateString() }}">@if($propertyId)<input type="hidden" name="property" value="{{ $propertyId }}">@endif @if($assignee)<input type="hidden" name="assignee" value="{{ $assignee }}">@endif<label><span>{{ __('messages.cleaning.link_duration') }}</span><select name="expires_in_days"><option value="7">{{ __('messages.cleaning.days', ['count' => 7]) }}</option><option value="30" selected>{{ __('messages.cleaning.days', ['count' => 30]) }}</option><option value="90">{{ __('messages.cleaning.days', ['count' => 90]) }}</option></select></label><button class="btn btn-ghost" type="submit">{{ __('messages.cleaning.create_share') }}</button></form>
</div>

<details class="cleaning-generator">
    <summary>{{ __('messages.cleaning.generate_from_departures') }}</summary>
    <p class="form-help">{{ __('messages.cleaning.generate_help') }}</p>
    <form method="POST" action="{{ route('admin.cleaning.generate') }}" class="cleaning-generator-form">
        @csrf
        <input type="hidden" name="mode" value="{{ $mode }}"><input type="hidden" name="date" value="{{ $reference->toDateString() }}">@if($propertyId)<input type="hidden" name="property" value="{{ $propertyId }}">@endif @if($assignee)<input type="hidden" name="assignee" value="{{ $assignee }}">@endif
        <label><span>{{ __('messages.cleaning.assignee') }}</span><input name="assignee_name" required placeholder="{{ __('messages.cleaning.assignee_placeholder') }}"></label>
        <label><span>{{ __('messages.cleaning.start_time') }}</span><input type="time" name="scheduled_time" value="11:00" required></label>
        <label><span>{{ __('messages.cleaning.duration') }}</span><input type="number" name="duration_minutes" min="15" max="1440" step="15" value="120" required></label>
        <label><span>{{ __('messages.cleaning.instructions') }}</span><input name="instructions" placeholder="{{ __('messages.cleaning.default_instructions_placeholder') }}"></label>
        <button class="btn btn-primary" type="submit">{{ __('messages.cleaning.generate') }}</button>
    </form>
</details>

<div class="cleaning-workspace">
    <div class="cleaning-create-action">
        <button type="button" class="btn btn-primary" data-admin-modal-open="cleaning-visit-modal">{{ __('messages.cleaning.add_visit') }}</button>
    </div>
    <dialog class="admin-modal admin-modal--side" data-admin-modal="cleaning-visit-modal" aria-labelledby="cleaning-visit-modal-title">
        <div class="admin-modal-card">
            <header class="admin-modal-header">
                <h2 id="cleaning-visit-modal-title">{{ __('messages.cleaning.add_visit') }}</h2>
                <button type="button" class="admin-modal-close" data-admin-modal-close aria-label="Fermer">&times;</button>
            </header>
            <div class="admin-modal-body">
        <form method="POST" action="{{ route('admin.cleaning.store') }}">@csrf
            <input type="hidden" name="mode" value="{{ $mode }}"><input type="hidden" name="date" value="{{ $reference->toDateString() }}">@if($propertyId)<input type="hidden" name="property" value="{{ $propertyId }}">@endif @if($assignee)<input type="hidden" name="assignee" value="{{ $assignee }}">@endif
            <div class="admin-form-grid">
                <label><span>{{ __('messages.admin.property') }}</span><select name="property_id" required><option value="">{{ __('messages.admin.select_property') }}</option>@foreach($properties as $property)<option value="{{ $property->id }}" @selected(old('property_id', $propertyId) === $property->id)>{{ $property->name }}</option>@endforeach</select></label>
                <label><span>{{ __('messages.cleaning.assignee') }}</span><input name="assignee_name" value="{{ old('assignee_name') }}" required placeholder="{{ __('messages.cleaning.assignee_placeholder') }}"></label>
                <label><span>{{ __('messages.cleaning.scheduled_at') }}</span><input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $reference->setTime(10, 0)->format('Y-m-d\TH:i')) }}" required></label>
                <label><span>{{ __('messages.cleaning.duration') }}</span><input type="number" name="duration_minutes" min="15" max="1440" step="15" value="{{ old('duration_minutes', 120) }}" required></label>
                <label><span>{{ __('messages.cleaning.reservation_optional') }}</span><select name="reservation_id"><option value="">{{ __('messages.cleaning.no_reservation') }}</option>@foreach($reservations as $reservation)<option value="{{ $reservation->id }}">{{ $reservation->property->name }} - {{ $reservation->check_out->format('d/m/Y') }} ({{ $reservation->reservation_ref }})</option>@endforeach</select></label>
                <label><span>{{ __('messages.admin.status') }}</span><select name="status">@foreach(['scheduled','in_progress','completed','cancelled'] as $status)<option value="{{ $status }}">{{ __('messages.cleaning.status_'.$status) }}</option>@endforeach</select></label>
                <label class="full-width"><span>{{ __('messages.cleaning.instructions') }}</span><textarea name="instructions" rows="3">{{ old('instructions') }}</textarea></label>
            </div>
            <div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.cleaning.add_visit') }}</button></div>
        </form>
            </div>
        </div>
    </dialog>

    <section class="cleaning-list" aria-label="{{ __('messages.cleaning.schedule') }}">
        @forelse($visits as $visit)
            <article class="cleaning-visit cleaning-visit-row cleaning-status-{{ $visit->status }}" data-cleaning-edit-label="{{ __('messages.admin.edit') }}" data-cleaning-edit-title="{{ $visit->property->name }}">
                <form method="POST" action="{{ route('admin.cleaning.update', $visit) }}">@csrf @method('PUT')
                    <input type="hidden" name="mode" value="{{ $mode }}"><input type="hidden" name="date" value="{{ $reference->toDateString() }}">@if($propertyId)<input type="hidden" name="property" value="{{ $propertyId }}">@endif @if($assignee)<input type="hidden" name="assignee" value="{{ $assignee }}">@endif
                    <div class="cleaning-visit-heading"><div><time datetime="{{ $visit->scheduled_at->toIso8601String() }}">{{ $visit->scheduled_at->translatedFormat('D d M · H:i') }}</time><strong>{{ $visit->property->name }}</strong>@if($visit->started_at || $visit->completed_at)<small>{{ $visit->started_at ? __('messages.cleaning.started_at', ['time' => $visit->started_at->format('H:i')]) : '' }}{{ $visit->completed_at ? ' · '.__('messages.cleaning.completed_at', ['time' => $visit->completed_at->format('H:i')]) : '' }}</small>@endif</div><span class="{{ $visit->isOverdue() ? 'cleaning-overdue-badge' : '' }}">{{ $visit->isOverdue() ? __('messages.cleaning.overdue') : __('messages.cleaning.status_'.$visit->status) }}</span></div>
                    <div class="admin-form-grid compact">
                        <label><span>{{ __('messages.admin.property') }}</span><select name="property_id" required>@foreach($properties as $property)<option value="{{ $property->id }}" @selected($visit->property_id === $property->id)>{{ $property->name }}</option>@endforeach</select></label>
                        <label><span>{{ __('messages.cleaning.assignee') }}</span><input name="assignee_name" value="{{ $visit->assignee_name }}" required></label>
                        <label><span>{{ __('messages.cleaning.scheduled_at') }}</span><input type="datetime-local" name="scheduled_at" value="{{ $visit->scheduled_at->format('Y-m-d\TH:i') }}" required></label>
                        <label><span>{{ __('messages.cleaning.duration') }}</span><input type="number" name="duration_minutes" min="15" max="1440" step="15" value="{{ $visit->duration_minutes }}" required></label>
                        <label><span>{{ __('messages.cleaning.reservation_optional') }}</span><select name="reservation_id"><option value="">{{ __('messages.cleaning.no_reservation') }}</option>@foreach($reservations as $reservation)<option value="{{ $reservation->id }}" @selected($visit->reservation_id === $reservation->id)>{{ $reservation->property->name }} - {{ $reservation->check_out->format('d/m/Y') }}</option>@endforeach</select></label>
                        <label><span>{{ __('messages.admin.status') }}</span><select name="status">@foreach(['scheduled','in_progress','completed','cancelled'] as $status)<option value="{{ $status }}" @selected($visit->status === $status)>{{ __('messages.cleaning.status_'.$status) }}</option>@endforeach</select></label>
                        <label class="full-width"><span>{{ __('messages.cleaning.instructions') }}</span><textarea name="instructions" rows="2">{{ $visit->instructions }}</textarea></label>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.save_changes') }}</button></div>
                </form>
                <form method="POST" action="{{ route('admin.cleaning.destroy', $visit) }}" data-confirm-message="{{ __('messages.cleaning.delete_confirmation') }}">@csrf @method('DELETE')<input type="hidden" name="mode" value="{{ $mode }}"><input type="hidden" name="date" value="{{ $reference->toDateString() }}">@if($assignee)<input type="hidden" name="assignee" value="{{ $assignee }}">@endif<button class="btn btn-danger" type="submit">{{ __('messages.admin.delete') }}</button></form>
            </article>
        @empty
            <div class="admin-empty-state"><p>{{ __('messages.cleaning.empty') }}</p></div>
        @endforelse
    </section>
</div>

@if($shares->isNotEmpty())
<section class="cleaning-shares">
    <h2>{{ __('messages.cleaning.active_shares') }}</h2>
    @foreach($shares as $share)
        @php $shareUrl = route('cleaning.public', $share->token); @endphp
        <div class="cleaning-share-row"><div><strong>{{ $share->period_start->format('d/m/Y') }} - {{ $share->period_end->format('d/m/Y') }}</strong><span>{{ $share->assignee_name ? __('messages.cleaning.for_assignee', ['name' => $share->assignee_name]) : __('messages.cleaning.all_assignees') }} · {{ __('messages.cleaning.expires', ['date' => $share->expires_at?->format('d/m/Y')]) }}</span></div><div class="cleaning-share-actions"><a href="https://wa.me/?text={{ urlencode(__('messages.cleaning.whatsapp_message', ['url' => $shareUrl])) }}" target="_blank" rel="noopener">{{ __('messages.cleaning.share_whatsapp') }}</a><a href="{{ route('cleaning.public.pdf', $share->token) }}">PDF</a><a href="{{ $shareUrl }}" target="_blank" rel="noopener">{{ __('messages.cleaning.open') }}</a></div><form method="POST" action="{{ route('admin.cleaning.shares.revoke', $share) }}" data-confirm-message="{{ __('messages.cleaning.revoke_confirmation') }}">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">{{ __('messages.cleaning.revoke') }}</button></form></div>
    @endforeach
</section>
@endif
@endsection
