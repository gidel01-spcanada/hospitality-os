@extends('layouts.admin')

@section('title', 'Journal des modifications')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Journal des modifications</h1>
        <p class="admin-page-description">Historique des créations, modifications et suppressions. Cette page est réservée aux administrateurs.</p>
    </div>
    <div class="admin-page-actions">
        <a class="btn btn-ghost" href="{{ route('admin.logs') }}">Journaux système</a>
    </div>
</div>

<x-card class="audit-log-card">
    <form method="GET" action="{{ route('admin.audit-logs') }}" class="admin-form-grid compact">
        <label><span>Action</span><select name="event"><option value="">Toutes</option><option value="created" @selected($event === 'created')>Création</option><option value="updated" @selected($event === 'updated')>Modification</option><option value="deleted" @selected($event === 'deleted')>Suppression</option></select></label>
        <label><span>Objet</span><select name="type"><option value="">Tous les objets</option>@foreach($types as $objectType)<option value="{{ $objectType }}" @selected($type === $objectType)>{{ class_basename($objectType) }}</option>@endforeach</select></label>
        <div class="date-range-picker admin-date-range-picker" data-date-range-picker data-incomplete-message="Sélectionnez les deux dates" data-past-message="La date ne peut pas être passée" data-start-label="Au" data-end-label="Au" data-placeholder="Du / Au">
            <label for="audit-date-range-trigger"><span>Du / Au</span><button type="button" id="audit-date-range-trigger" class="date-range-trigger" data-date-range-trigger aria-expanded="false"><span data-date-range-label>{{ $dateFrom && $dateTo ? $dateFrom . ' → ' . $dateTo : 'Du / Au' }}</span></button></label>
            <input type="hidden" name="date_from" value="{{ $dateFrom }}" data-date-range-start>
            <input type="hidden" name="date_to" value="{{ $dateTo }}" data-date-range-end>
            <div class="date-range-popover" data-date-range-popover hidden></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Filtrer</button></div>
    </form>
    <div class="admin-log-list-section">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Administrateur</th><th>Action</th><th>Objet</th><th>Changements</th><th>Adresse IP</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? 'Système' }}@if($log->user?->email)<small>{{ $log->user->email }}</small>@endif</td>
                    <td><span class="audit-event audit-event-{{ $log->action }}">{{ ucfirst($log->action) }}</span></td>
                    <td>{{ class_basename($log->model_type) }} #{{ $log->model_id ?? 'n/a' }}</td>
                    <td><details><summary>Voir les données</summary><pre class="audit-values">{{ json_encode(json_decode($log->details, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details></td>
                    <td>{{ $log->ip_address ?: 'n/a' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Aucune modification enregistrée.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
    </div>
</x-card>
@endsection
