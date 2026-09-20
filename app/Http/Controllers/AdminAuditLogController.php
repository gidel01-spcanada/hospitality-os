<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'event' => ['nullable', 'in:created,updated,deleted'],
            'type' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $event = (string) ($filters['event'] ?? '');
        $type = (string) ($filters['type'] ?? '');
        $dateFrom = $filters['date_from'] ?? '';
        $dateTo = $filters['date_to'] ?? '';
        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when(in_array($event, ['created', 'updated', 'deleted'], true), fn ($query) => $query->where('action', $event))
            ->when($type !== '', fn ($query) => $query->where('model_type', $type))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $types = AuditLog::query()->select('model_type')->distinct()->orderBy('model_type')->pluck('model_type');

        return view('admin.audit-logs', compact('logs', 'types', 'event', 'type', 'dateFrom', 'dateTo'));
    }
}