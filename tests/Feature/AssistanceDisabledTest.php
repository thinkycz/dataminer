<?php

declare(strict_types=1);

use App\Ai\AgentConversationContext;
use App\Ai\AgentRunService;
use App\Ai\Agents\RecipeGenerationAgent;
use App\Ai\RecipeApprovalService;
use App\Ai\RecipeGenerationService;
use App\Jobs\RunChatAgentJob;
use App\Models\AgentRun;
use App\Models\Recipe;
use App\Models\User;
use Database\Factories\RecipeFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Jobs\InvokeAgent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('disabled generation and approval do not queue an AI call', function (): void {
    RecipeGenerationAgent::fake();
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);

    try {
        (new RecipeGenerationService())->queue($recipe, $user, 'initial');
        static::fail('Generation was not blocked.');
    } catch (HttpException $exception) {
        static::assertSame(403, $exception->getStatusCode());
    }

    try {
        (new RecipeApprovalService())->decide($recipe, $user, 'old-call', true);
        static::fail('Approval continuation was not blocked.');
    } catch (HttpException $exception) {
        static::assertSame(403, $exception->getStatusCode());
    }

    RecipeGenerationAgent::assertNeverQueued();
    static::assertSame(Recipe::STATUS_DRAFT, $recipe->refresh()->getStatus());
});

\test('disabled queued chat job cancels before a provider request', function (): void {
    Http::preventStrayRequests();
    Http::fake();
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid7(), 'title' => 'Old chat']);
    $run = Typer::assertInstance(AgentRun::query()->create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->getKey(),
        'user_id' => $user->getKey(),
        'status' => AgentRun::STATUS_QUEUED,
        'prompt' => 'Continue old chat',
        'user_message_id' => (string) Str::uuid7(),
    ]), AgentRun::class);

    (new RunChatAgentJob($run->getId()))->handle(
        Resolver::resolve(AgentRunService::class),
        Resolver::resolve(AgentConversationContext::class),
    );

    static::assertSame(AgentRun::STATUS_CANCELLED, $run->refresh()->getStatus());
    Http::assertNothingSent();
});

\test('old queued SDK generation is blocked before a provider request', function (): void {
    Http::preventStrayRequests();
    Http::fake();

    try {
        (new InvokeAgent(new RecipeGenerationAgent(), 'Old queued recipe generation'))->handle();
        static::fail('Queued SDK work was not blocked.');
    } catch (HttpException $exception) {
        static::assertSame(403, $exception->getStatusCode());
    }

    Http::assertNothingSent();
});
