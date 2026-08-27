<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use App\Models\MessageThread;
use App\Services\MessageEmailService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminMessageController extends Controller
{
    public function index(): View
    {
        $threads = $this->tenantThreads()->with(['customer', 'establishment', 'messages' => fn ($query) => $query->latest()->limit(1)])->latest('updated_at')->get();

        return view('messages.index', ['threads' => $threads, 'establishments' => collect(), 'isStaff' => true]);
    }

    public function show(MessageThread $thread): View
    {
        abort_unless($this->belongsToCurrentTenant($thread), 403);

        $thread->load(['customer', 'establishment', 'messages.sender']);

        return view('messages.thread', ['thread' => $thread, 'isStaff' => true]);
    }

    public function reply(Request $request, MessageThread $thread, MessageEmailService $emailService): RedirectResponse
    {
        abort_unless($this->belongsToCurrentTenant($thread), 403);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $thread->messages()->create(['sender_id' => $request->user()->id, 'body' => $validated['body']]);
        $thread->touch();
        $emailService->queueForMessage($thread, $request->user(), $validated['body']);

        return back()->with('status', __('messages.messages.sent'));
    }

    private function tenantThreads()
    {
        return MessageThread::query()->whereHas('establishment', fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()));
    }

    private function belongsToCurrentTenant(MessageThread $thread): bool
    {
        return $thread->establishment()->where('tenant_id', app(CurrentTenant::class)->id())->exists();
    }
}