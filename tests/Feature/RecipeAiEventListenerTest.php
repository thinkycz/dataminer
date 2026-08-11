<?php

declare(strict_types=1);

use App\Ai\Agents\RecipeGenerationAgent;
use App\Listeners\RecipeAiEventListener;
use App\Models\Recipe;
use App\Models\User;
use Database\Factories\RecipeFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Events\ToolApprovalRequested;
use Laravel\Ai\Events\ToolApprovalResolved;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolResult;
use Thinkycz\LaravelCore\Support\Typer;

\test('pending approval fake and requested event associate the persisted conversation', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);
    $approval = new PendingApproval('call-1', 'TestRecipeCandidateTool', ['recipe_id' => $recipe->getKey()], 'Review source');
    $response = AgentResponse::fakeWithPendingApprovals([$approval]);
    static::assertTrue($response->hasPendingApprovals());
    $conversationId = (string) Str::uuid7();

    (new RecipeAiEventListener())->requested(new ToolApprovalRequested(
        'invocation',
        new RecipeGenerationAgent(),
        new Collection([$approval]),
        $conversationId,
        $user,
    ));

    static::assertSame($conversationId, $recipe->refresh()->getAiConversationId());
    static::assertSame(Recipe::STATUS_PENDING_APPROVAL, $recipe->getStatus());
});

\test('resolved rejection returns recipe to draft without executing a tool', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversationId = (string) Str::uuid7();
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne([
        'ai_conversation_id' => $conversationId,
        'status' => Recipe::STATUS_PENDING_APPROVAL,
    ]), Recipe::class);
    $result = new ToolResult('call-1', 'TestRecipeCandidateTool', ['recipe_id' => $recipe->getKey()], 'Rejected', denied: true);

    (new RecipeAiEventListener())->resolved(new ToolApprovalResolved(
        'invocation',
        new RecipeGenerationAgent(),
        new Collection([$result]),
        $conversationId,
        $user,
    ));

    static::assertSame(Recipe::STATUS_DRAFT, $recipe->refresh()->getStatus());
    static::assertSame(0, $recipe->versions()->getQuery()->count());
});
