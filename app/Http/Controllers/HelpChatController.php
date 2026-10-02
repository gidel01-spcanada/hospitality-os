<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HelpChatController extends Controller
{
    public function ask(Request $request): JsonResponse
    {
        $apiKey = config('services.help_chat.api_key');
        if (! filled($apiKey)) {
            return response()->json(['message' => __('messages.help_chat.unavailable')], 503);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1200'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:1200'],
        ]);

        $topics = ['booking', 'payment', 'cancellation', 'checkin', 'amenities', 'support'];
        $faqContext = collect($topics)->map(fn (string $topic): string => implode("\n", [
            'Q: ' . __('messages.faq.' . $topic . '_question'),
            'A: ' . __('messages.faq.' . $topic . '_answer'),
        ]))->implode("\n\n");

        $messages = [[
            'role' => 'system',
            'content' => 'You are Afrik Appart product support. Answer in the user\'s language using only the following public FAQ. If the FAQ does not answer the question, say so and direct them to the Contact page. Never request or repeat passwords, payment details, verification codes, or private reservation information.\n\n' . $faqContext,
        ]];
        foreach ($validated['history'] ?? [] as $message) {
            $messages[] = ['role' => $message['role'], 'content' => $message['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $validated['message']];

        try {
            $response = Http::acceptJson()
                ->withToken($apiKey)
                ->timeout(25)
                ->post(rtrim((string) config('services.help_chat.base_url'), '/') . '/chat/completions', [
                    'model' => config('services.help_chat.model'),
                    'messages' => $messages,
                    'temperature' => 0.2,
                    'max_tokens' => 500,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Help chat provider request failed.', ['exception' => $exception]);

            return response()->json(['message' => __('messages.help_chat.failed')], 502);
        }

        if (! $response->successful()) {
            Log::warning('Help chat provider returned an unsuccessful response.', ['status' => $response->status()]);

            return response()->json(['message' => __('messages.help_chat.failed')], 502);
        }

        $answer = trim((string) $response->json('choices.0.message.content'));
        if ($answer === '') {
            return response()->json(['message' => __('messages.help_chat.failed')], 502);
        }

        return response()->json(['answer' => $answer]);
    }
}