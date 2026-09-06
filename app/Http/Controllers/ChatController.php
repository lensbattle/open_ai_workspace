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
            'title' => $data['title'] ?? Conversation::DEFAULT_TITLE,
        ]);

        return response()->json(['conversation' => $conversation->load('messages')], 201);
    }

    public function message(Request $request, Conversation $conversation, ChatProvider $provider): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        $data = $request->validate(['content' => ['required', 'string', 'max:12000']]);
        $needsTitle = $conversation->messages()->doesntExist() || $conversation->title === Conversation::DEFAULT_TITLE;
        $conversation->messages()->create(['role' => 'user', 'content' => $data['content']]);

        if ($needsTitle) {
            $conversation->update(['title' => Conversation::titleFromPrompt($data['content'])]);
        }

        try {
            $reply = $provider->reply($conversation->messages()->oldest()->get(['role', 'content'])->toArray());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'The AI provider is unavailable right now.'], 503);
        }

        $assistantMessage = $conversation->messages()->create(['role' => 'assistant', 'content' => $reply]);

        if ($needsTitle) {
            $conversation->update(['title' => $this->nameConversation($provider, $conversation, $data['content'], $reply)]);
        }

        return response()->json([
            'message' => $assistantMessage,
            'conversation' => $conversation->only('id', 'title'),
        ]);
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        $conversation->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Ask the provider to name the conversation, falling back to the trimmed prompt.
     */
    private function nameConversation(ChatProvider $provider, Conversation $conversation, string $prompt, string $reply): string
    {
        try {
            return $provider->title([
                ['role' => 'user', 'content' => $prompt],
                ['role' => 'assistant', 'content' => $reply],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return $conversation->title;
        }
    }
}
