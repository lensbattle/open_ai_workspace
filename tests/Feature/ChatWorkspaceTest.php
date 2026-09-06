<?php

namespace Tests\Feature;

use App\Contracts\ChatProvider;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_a_user_can_register_and_is_authenticated(): void
    {
        $response = $this->postJson('/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()->assertJsonPath('user.email', 'ada@example.com');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    public function test_a_user_can_save_a_prompt_and_provider_reply(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Ideas']);

        $this->mock(ChatProvider::class)
            ->shouldReceive('reply')
            ->once()
            ->with([['role' => 'user', 'content' => 'Hello Gemini']])
            ->andReturn('Hello there');

        $response = $this->actingAs($user)->postJson("/api/conversations/{$conversation->id}/messages", [
            'content' => 'Hello Gemini',
        ]);

        $response->assertOk()->assertJsonPath('message.content', 'Hello there');
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Hello Gemini']);
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Hello there']);
    }

    public function test_a_user_cannot_write_to_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $owner->id, 'title' => 'Private']);

        $this->actingAs($intruder)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['content' => 'Not mine'])
            ->assertNotFound();
    }

    public function test_guest_cannot_access_conversations(): void
    {
        $this->getJson('/api/conversations')->assertUnauthorized();
    }
}
