@extends('layouts.admin')

@section('title', __('messages.cleaning.title'))

@section('content')
<div class="admin-page-header cleaning-page-header">
    <div>
        <h1 class="admin-page-title">{{ __('messages.cleaning.title') }}</h1>
        <p class="admin-page-description">{{ __('messages.cleaning.description') }}</p>
    </div>
    <div class="cleaning-page-actions">
        <a class="btn btn-ghost" href="{{ route('admin.cleaning.generate.create', array_filter(['mode' => $mode, 'date' => $reference->toDateString(), 'property' => $propertyId, 'assignee' => $assignee])) }}">{{ __('messages.cleaning.generate_from_departures') }}</a>
        <a class="btn btn-primary" href="{{ route('admin.cleaning.create', array_filter(['mode' => $mode, 'date' => $reference->toDateString(), 'property' => $propertyId, 'assignee' => $assignee])) }}">{{ __('messages.cleaning.add_visit') }}</a>
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
        <label><span>{{ __('messages.admin.status') }}</span><select name="status"><option value="">{{ __('messages.admin.all_status') }}</option>@foreach(['scheduled','in_progress','completed','cancelled'] as $visitStatus)<option value="{{ $visitStatus }}" @selected($status === $visitStatus)>{{ __('messages.cleaning.status_'.$visitStatus) }}</option>@endforeach</select></label>
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

<div class="cleaning-workspace">
    <section class="cleaning-list admin-table-wrap" aria-label="{{ __('messages.cleaning.schedule') }}">
        <table class="admin-table cleaning-table">
            <thead><tr><th>Date et heure</th><th>{{ __('messages.admin.property') }}</th><th>{{ __('messages.cleaning.assignee') }}</th><th>{{ __('messages.cleaning.duration') }}</th><th>{{ __('messages.admin.status') }}</th><th style="text-align: right;">{{ __('messages.admin.actions') }}</th></tr></thead>
            <tbody>
        @forelse($visits as $visit)
            <tr class="cleaning-visit cleaning-status-{{ $visit->status }}">
                <td>
                    <time datetime="{{ $visit->scheduled_at->toIso8601String() }}">{{ $visit->scheduled_at->translatedFormat('D d M · H:i') }}</time>
                    @if($visit->started_at || $visit->completed_at)
                        <small>{{ $visit->started_at ? __('messages.cleaning.started_at', ['time' => $visit->started_at->format('H:i')]) : '' }}{{ $visit->completed_at ? ' · '.__('messages.cleaning.completed_at', ['time' => $visit->completed_at->format('H:i')]) : '' }}</small>
                    @endif
                </td>
                <td>{{ $visit->property->name }}</td>
                <td>{{ $visit->assignee_name }}</td>
                <td>{{ $visit->duration_minutes }} min</td>
                <td><span class="{{ $visit->isOverdue() ? 'cleaning-overdue-badge' : '' }}">{{ $visit->isOverdue() ? __('messages.cleaning.overdue') : __('messages.cleaning.status_'.$visit->status) }}</span></td>
                <td style="text-align: right;">
                    <a class="btn btn-ghost btn-small" href="{{ route('admin.cleaning.edit', $visit) }}">{{ __('messages.admin.edit') }}</a>
                    <form method="POST" action="{{ route('admin.cleaning.destroy', $visit) }}" data-confirm-message="{{ __('messages.cleaning.delete_confirmation') }}" style="display:inline-flex">@csrf @method('DELETE')<input type="hidden" name="mode" value="{{ $mode }}"><input type="hidden" name="date" value="{{ $reference->toDateString() }}">@if($assignee)<input type="hidden" name="assignee" value="{{ $assignee }}">@endif<button class="btn btn-danger btn-small" type="submit">{{ __('messages.admin.delete') }}</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="admin-table-empty"><p>{{ __('messages.cleaning.empty') }}</p></td></tr>
        @endforelse
            </tbody>
        </table>
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
