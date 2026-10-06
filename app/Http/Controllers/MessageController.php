<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use App\Models\MessageThread;
use App\Models\Reservation;
use App\Services\MessageEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeCustomer($request);
        $customer = $request->user();
        $threads = MessageThread::query()
            ->where('customer_id', $customer->id)
            ->with(['establishment', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->latest('updated_at')
            ->get();

        $establishments = Establishment::query()->orderBy('name')->get();

        return view('messages.index', compact('threads', 'establishments'));
    }

    public function show(Request $request, MessageThread $thread): View
    {
        $this->authorizeCustomer($request);
        abort_unless($thread->customer_id === $request->user()->id, 403);

        $thread->load(['customer', 'establishment', 'messages.sender', 'messages.reservation']);
        $reservation = $thread->messages
            ->filter(fn ($message) => $message->reservation !== null)
            ->sortByDesc('created_at')
            ->first()?->reservation;
        $reservation ??= Reservation::query()
            ->where(fn ($query) => $query->where('user_id', $thread->customer_id)->orWhere('email', $thread->customer->email))
            ->whereHas('property', fn ($query) => $query->where('establishment_id', $thread->establishment_id))
            ->latest()
            ->first();

        return view('messages.thread', ['thread' => $thread, 'isStaff' => false, 'reservation' => $reservation]);
    }

    public function store(Request $request, MessageEmailService $emailService): RedirectResponse
    {
        $this->authorizeCustomer($request);
        $validated = $request->validate([
            'establishment_id' => ['required', 'integer', 'exists:establishments,id'],
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $establishment = Establishment::query()->findOrFail($validated['establishment_id']);
        $reservation = null;
        if (! empty($validated['reservation_id'])) {
            $reservation = Reservation::query()
                ->whereKey($validated['reservation_id'])
                ->where(fn ($query) => $query->where('user_id', $request->user()->id)->orWhere('email', $request->user()->email))
                ->whereHas('property', fn ($query) => $query->where('establishment_id', $establishment->id))
                ->firstOrFail();
        }
        $thread = MessageThread::query()->firstOrCreate([
            'customer_id' => $request->user()->id,
            'establishment_id' => $establishment->id,
        ]);

        $thread->messages()->create([
            'sender_id' => $request->user()->id,
            'reservation_id' => $reservation?->id,
            'body' => $validated['body'],
        ]);
        $thread->touch();
        $emailService->queueForMessage($thread, $request->user(), $validated['body']);

        return $reservation
            ? redirect()->route('messages.show', $thread)->with('status', __('messages.messages.sent'))
            : redirect()->route('messages.index')->with('status', __('messages.messages.sent'));
    }

    public function reply(Request $request, MessageThread $thread, MessageEmailService $emailService): RedirectResponse
    {
        $this->authorizeCustomer($request);
        abort_unless($thread->customer_id === $request->user()->id, 403);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $thread->messages()->create(['sender_id' => $request->user()->id, 'body' => $validated['body']]);
        $thread->touch();
        $emailService->queueForMessage($thread, $request->user(), $validated['body']);

        return redirect()->route('messages.show', $thread)->with('status', __('messages.messages.sent'));
    }

    private function authorizeCustomer(Request $request): void
    {
        abort_unless($request->user()?->role === 'customer', 403, __('messages.errors.customer_messaging_required'));
    }
}