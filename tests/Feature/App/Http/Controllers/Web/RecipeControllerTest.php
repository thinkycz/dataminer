<?php

declare(strict_types=1);

use App\Ai\Agents\RecipeGenerationAgent;
use App\Models\Recipe;
use App\Models\User;
use Database\Factories\RecipeFactory;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest cannot access recipes', function (): void {
    $this->get('/recipes')->assertRedirect('/login');
});

\test('user can create and view an owned recipe', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $response = $this->be($user, 'users')->post('/recipes', [
        'name' => 'Product catalog',
        'start_url' => 'https://example.com/products',
        'instructions' => 'Extract each product name and public price.',
    ], $this->inertiaHeaders());

    $recipe = Recipe::query()->firstOrFail();
    $response->assertRedirect('/recipes/' . $recipe->getKey());
    static::assertSame($user->getKey(), $recipe->user()->getResults()?->getKey());

    $this->be($user, 'users')->get('/recipes/' . $recipe->getKey(), $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'recipes/Show');
});

\test('recipe index only contains recipes owned by the current user', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    RecipeFactory::new()->for($user)->createOne(['name' => 'Mine']);
    RecipeFactory::new()->createOne(['name' => 'Not mine']);

    $this->be($user, 'users')->get('/recipes', $this->inertiaHeaders())
        ->assertOk()->assertJsonCount(1, 'props.recipes.data')->assertJsonPath('props.recipes.data.0.name', 'Mine');
});

\test('generation is queued without running a browser', function (): void {
    RecipeGenerationAgent::fake();
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);

    $this->be($user, 'users')->from('/recipes/' . $recipe->getKey())
        ->post('/recipes/' . $recipe->getKey() . '/generate', [], $this->inertiaHeaders())->assertRedirect();

    RecipeGenerationAgent::assertQueued(fn($prompt): bool => \str_contains($prompt->prompt, 'recipe_id: ' . $recipe->getKey()));
    static::assertSame(Recipe::STATUS_GENERATING, $recipe->refresh()->getStatus());
});

\test('user cannot view another users recipe', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->createOne(), Recipe::class);
    $this->be($user, 'users')->get('/recipes/' . $recipe->getKey())->assertNotFound();
});
