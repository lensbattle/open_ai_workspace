<?php

namespace Tests\Feature;

use App\Contracts\ChatProvider;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
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

        $this->mock(ChatProvider::class, function ($mock) {
            $mock->shouldReceive('reply')
                ->once()
                ->with([['role' => 'user', 'content' => 'Hello Gemini']])
                ->andReturn('Hello there');
            $mock->shouldReceive('title')->andReturn('Greeting Gemini');
        });

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

    public function test_a_user_can_delete_their_conversation_with_its_messages(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Ideas']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Hello Gemini']);

        $this->actingAs($user)
            ->deleteJson("/api/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id]);
    }

    public function test_a_user_cannot_delete_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $owner->id, 'title' => 'Private']);

        $this->actingAs($intruder)
            ->deleteJson("/api/conversations/{$conversation->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
    }

    public function test_the_provider_names_the_conversation_from_the_opening_exchange(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => Conversation::DEFAULT_TITLE]);

        $this->mock(ChatProvider::class, function ($mock) {
            $mock->shouldReceive('reply')->andReturn('Day one: Fushimi Inari.');
            $mock->shouldReceive('title')
                ->once()
                ->with([
                    ['role' => 'user', 'content' => 'Plan a three day trip to Kyoto'],
                    ['role' => 'assistant', 'content' => 'Day one: Fushimi Inari.'],
                ])
                ->andReturn('Three Day Kyoto Itinerary');
        });

        $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['content' => 'Plan a three day trip to Kyoto'])
            ->assertOk()
            ->assertJsonPath('conversation.title', 'Three Day Kyoto Itinerary');

        $this->assertDatabaseHas('conversations', ['id' => $conversation->id, 'title' => 'Three Day Kyoto Itinerary']);
    }

    public function test_the_prompt_names_the_conversation_when_the_provider_cannot(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => Conversation::DEFAULT_TITLE]);

        $this->mock(ChatProvider::class, function ($mock) {
            $mock->shouldReceive('reply')->andReturn('Sure thing');
            $mock->shouldReceive('title')->andThrow(new RuntimeException('Provider down'));
        });

        $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['content' => "  Plan a   three day\n trip to Kyoto  "])
            ->assertOk()
            ->assertJsonPath('conversation.title', 'Plan a three day trip to Kyoto');
    }

    public function test_an_older_untitled_conversation_is_named_on_its_next_message(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => Conversation::DEFAULT_TITLE]);
        $conversation->messages()->create(['role' => 'user', 'content' => 'hello']);
        $conversation->messages()->create(['role' => 'assistant', 'content' => 'Hi there']);

        $this->mock(ChatProvider::class, function ($mock) {
            $mock->shouldReceive('reply')->andReturn('Not much!');
            $mock->shouldReceive('title')->once()->andReturn('Friendly Small Talk');
        });

        $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['content' => 'what are you doing?'])
            ->assertOk()
            ->assertJsonPath('conversation.title', 'Friendly Small Talk');
    }

    public function test_a_named_conversation_keeps_its_name(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Kyoto Itinerary']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Day one?']);

        $this->mock(ChatProvider::class, function ($mock) {
            $mock->shouldReceive('reply')->andReturn('Fushimi Inari.');
            $mock->shouldNotReceive('title');
        });

        $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['content' => 'And day two?'])
            ->assertOk()
            ->assertJsonPath('conversation.title', 'Kyoto Itinerary');
    }
}
