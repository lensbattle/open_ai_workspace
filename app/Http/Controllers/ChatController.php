<?php

namespace App\Http\Controllers;

use App\Contracts\ChatProvider;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'conversations' => $request->user()->conversations()
                ->with('messages')
                ->latest('updated_at')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['title' => ['nullable', 'string', 'max:160']]);
        $conversation = $request->user()->conversations()->create([
            'title' => $data['title'] ?? 'New conversation',
        ]);

        return response()->json(['conversation' => $conversation->load('messages')], 201);
    }

    public function message(Request $request, Conversation $conversation, ChatProvider $provider): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        $data = $request->validate(['content' => ['required', 'string', 'max:12000']]);
        $conversation->messages()->create(['role' => 'user', 'content' => $data['content']]);

        try {
            $reply = $provider->reply($conversation->messages()->oldest()->get(['role', 'content'])->toArray());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'The AI provider is unavailable right now.'], 503);
        }

        $assistantMessage = $conversation->messages()->create(['role' => 'assistant', 'content' => $reply]);

        return response()->json(['message' => $assistantMessage]);
    }
}
