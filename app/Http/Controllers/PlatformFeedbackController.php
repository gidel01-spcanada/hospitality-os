<?php

namespace App\Http\Controllers;

use App\Mail\PlatformFeedbackReceived;
use App\Models\PlatformFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PlatformFeedbackController extends Controller
{
    public function create(): View
    {
        return view('feedback.create', ['deliveryConfigured' => filled(config('services.sprintpay.feedback_email'))]);
    }

    public function store(Request $request): RedirectResponse
    {
        $recipient = config('services.sprintpay.feedback_email');
        if (! filled($recipient)) {
            return back()->withErrors(['feedback' => __('messages.feedback.unavailable')]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'category' => ['required', 'in:suggestion,issue,other'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $feedback = PlatformFeedback::query()->create($validated + ['user_id' => $request->user()?->id]);
        Mail::to($recipient)->send(new PlatformFeedbackReceived($feedback));

        return redirect()->route('feedback.create')->with('status', __('messages.feedback.sent'));
    }
}