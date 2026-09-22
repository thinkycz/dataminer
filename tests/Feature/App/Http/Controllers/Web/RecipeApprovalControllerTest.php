<?php

declare(strict_types=1);

use App\Ai\Agents\RecipeGenerationAgent;
use App\Models\Recipe;
use App\Models\User;
use Database\Factories\RecipeFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Str;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Typer;

\test('disabled owner cannot continue a pending tool approval', function (): void {
    RecipeGenerationAgent::fake();
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid7(), 'title' => 'Recipe']);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne([
        'ai_conversation_id' => $conversation->getKey(),
        'status' => Recipe::STATUS_PENDING_APPROVAL,
    ]), Recipe::class);
    ConversationMessage::query()->create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->getKey(),
        'participant_type' => User::class,
        'participant_id' => $user->getKey(),
        'agent' => RecipeGenerationAgent::class,
        'role' => 'assistant',
        'content' => '',
        'attachments' => [],
        'tool_calls' => [[
            'id' => 'call-1',
            'name' => 'TestRecipeCandidateTool',
            'arguments' => ['recipe_id' => $recipe->getKey(), 'source' => 'export async function scrape(context) {}'],
        ]],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
        'approval_state' => ['pending' => ['call-1' => 'Test generated source']],
    ]);

    $this->be($user, 'users')->from('/recipes/' . $recipe->getKey())
        ->post('/recipes/' . $recipe->getKey() . '/approvals/call-1/decide', ['decision' => 'approve'], $this->inertiaHeaders())
        ->assertForbidden();
    RecipeGenerationAgent::assertNeverQueued();
    static::assertSame(Recipe::STATUS_PENDING_APPROVAL, $recipe->refresh()->getStatus());
});

\test('stale and cross-user approval attempts are rejected', function (): void {
    $owner = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $other = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($owner)->createOne(), Recipe::class);

    $this->be($other, 'users')->post('/recipes/' . $recipe->getKey() . '/approvals/missing/decide', ['decision' => 'approve'])->assertNotFound();
    $this->be($owner, 'users')->post('/recipes/' . $recipe->getKey() . '/approvals/missing/decide', ['decision' => 'approve'])->assertForbidden();
});
