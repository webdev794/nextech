<?php

namespace Tests\Feature;

use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportChatRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_rates_a_conversation_once_staff_have_replied(): void
    {
        $user = User::factory()->create();
        $thread = $this->thread($user);
        $thread->post(User::factory()->create(['is_admin' => true]), 'On it.', isStaff: true);

        Sanctum::actingAs($user);
        $this->postJson("/api/support/threads/{$thread->id}/rating", [
            'rating' => 5,
            'comment' => 'Quick and friendly.',
        ])->assertOk()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.rating_comment', 'Quick and friendly.');

        $this->assertDatabaseHas('support_threads', ['id' => $thread->id, 'rating' => 5]);
        $this->assertNotNull($thread->fresh()->rated_at);
    }

    public function test_a_second_submission_edits_the_rating(): void
    {
        $user = User::factory()->create();
        $thread = $this->thread($user);
        $thread->post(User::factory()->create(['is_admin' => true]), 'Hello', isStaff: true);
        Sanctum::actingAs($user);

        $this->postJson("/api/support/threads/{$thread->id}/rating", ['rating' => 2])->assertOk();
        $this->postJson("/api/support/threads/{$thread->id}/rating", ['rating' => 4, 'comment' => 'Better than I first thought.'])->assertOk();

        $fresh = $thread->fresh();
        $this->assertSame(4, $fresh->rating);
        $this->assertSame('Better than I first thought.', $fresh->rating_comment);
    }

    public function test_cannot_rate_before_any_staff_reply(): void
    {
        $user = User::factory()->create();
        $thread = $this->thread($user);

        Sanctum::actingAs($user);
        $this->postJson("/api/support/threads/{$thread->id}/rating", ['rating' => 5])->assertStatus(422);
    }

    public function test_cannot_rate_someone_elses_thread(): void
    {
        $owner = User::factory()->create();
        $thread = $this->thread($owner);
        $thread->post(User::factory()->create(['is_admin' => true]), 'Hi', isStaff: true);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/support/threads/{$thread->id}/rating", ['rating' => 5])->assertNotFound();
    }

    public function test_rating_is_bounded_to_one_through_five(): void
    {
        $user = User::factory()->create();
        $thread = $this->thread($user);
        $thread->post(User::factory()->create(['is_admin' => true]), 'Hi', isStaff: true);
        Sanctum::actingAs($user);

        $this->postJson("/api/support/threads/{$thread->id}/rating", ['rating' => 0])->assertStatus(422);
        $this->postJson("/api/support/threads/{$thread->id}/rating", ['rating' => 6])->assertStatus(422);
    }

    public function test_admin_sees_the_chat_rating_and_comment(): void
    {
        $user = User::factory()->create();
        $thread = $this->thread($user);
        $thread->post(User::factory()->create(['is_admin' => true]), 'Sorted.', isStaff: true);

        Sanctum::actingAs($user);
        $this->postJson("/api/support/threads/{$thread->id}/rating", ['rating' => 3, 'comment' => 'ok-ish'])->assertOk();

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->getJson("/api/admin/support/threads/{$thread->id}")
            ->assertOk()
            ->assertJsonPath('data.rating', 3)
            ->assertJsonPath('data.rating_comment', 'ok-ish');

        $this->getJson('/api/admin/support/threads')
            ->assertOk()
            ->assertJsonPath('data.0.rating', 3);
    }

    private function thread(User $user): SupportThread
    {
        $thread = $user->supportThreads()->create(['issue_type' => 'other', 'status' => 'open']);
        $thread->post($user, 'I have a question.');

        return $thread;
    }
}
