<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.cleaning.public_title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="cleaning-public-page">
<main class="cleaning-public-shell">
    <header class="cleaning-public-header">
        <div><span class="cleaning-public-brand">{{ config('app.name') }}</span><h1>{{ __('messages.cleaning.public_title') }}</h1><p>{{ $share->period_start->translatedFormat('d M Y') }} - {{ $share->period_end->translatedFormat('d M Y') }}@if($share->assignee_name)<br>{{ __('messages.cleaning.for_assignee', ['name' => $share->assignee_name]) }}@endif</p></div>
        <div class="cleaning-public-actions"><a class="btn btn-primary" href="{{ route('cleaning.public.pdf', $share->token) }}">{{ __('messages.cleaning.download_pdf') }}</a><button class="btn btn-ghost" type="button" data-print-report>{{ __('messages.reports.print') }}</button></div>
    </header>
    <p class="cleaning-public-notice">{{ __('messages.cleaning.public_notice') }}</p>
    @if(session('cleaning_status_updated'))<p class="cleaning-public-success" role="status">{{ session('cleaning_status_updated') }}</p>@endif
    <section class="cleaning-public-list">
        @forelse($visits as $visit)
            <article class="cleaning-public-visit">
                <div class="cleaning-public-date"><strong>{{ $visit->scheduled_at->translatedFormat('D') }}</strong><span>{{ $visit->scheduled_at->format('d/m') }}</span><time>{{ $visit->scheduled_at->format('H:i') }}</time></div>
                <div class="cleaning-public-details"><h2>{{ $visit->property->name }}</h2><p>{{ $visit->property->establishment->name }}</p><dl><div><dt>{{ __('messages.cleaning.assignee') }}</dt><dd>{{ $visit->assignee_name }}</dd></div><div><dt>{{ __('messages.cleaning.duration') }}</dt><dd>{{ $visit->duration_minutes }} min</dd></div><div><dt>{{ __('messages.admin.status') }}</dt><dd>{{ $visit->isOverdue() ? __('messages.cleaning.overdue') : __('messages.cleaning.status_'.$visit->status) }}</dd></div>@if($visit->started_at)<div><dt>{{ __('messages.cleaning.actual_start') }}</dt><dd>{{ $visit->started_at->format('H:i') }}</dd></div>@endif @if($visit->completed_at)<div><dt>{{ __('messages.cleaning.actual_end') }}</dt><dd>{{ $visit->completed_at->format('H:i') }}</dd></div>@endif</dl>@if($visit->instructions)<div class="cleaning-public-instructions"><strong>{{ __('messages.cleaning.instructions') }}</strong><p>{{ $visit->instructions }}</p></div>@endif
                    @if($visit->status === 'scheduled')
                        <form method="POST" action="{{ route('cleaning.public.status', [$share->token, $visit]) }}" class="cleaning-field-actions">@csrf<input type="hidden" name="status" value="in_progress"><button class="btn btn-primary" type="submit">{{ __('messages.cleaning.mark_in_progress') }}</button></form>
                    @elseif($visit->status === 'in_progress')
                        <form method="POST" action="{{ route('cleaning.public.status', [$share->token, $visit]) }}" class="cleaning-field-actions">@csrf<input type="hidden" name="status" value="completed"><button class="btn btn-primary" type="submit">{{ __('messages.cleaning.mark_completed') }}</button></form>
                    @endif
                </div>
            </article>
        @empty
            <p class="cleaning-public-empty">{{ __('messages.cleaning.empty') }}</p>
        @endforelse
    </section>
    <footer>{{ __('messages.cleaning.shared_footer', ['date' => $share->expires_at?->translatedFormat('d M Y')]) }}</footer>
</main>
</body>
</html>
