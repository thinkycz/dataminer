<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;
use Database\Factories\RecipeFactory;
use Database\Factories\RecipeVersionFactory;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('owner can approve a tested version and make it active', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);
    $version = Typer::assertInstance(RecipeVersionFactory::new()->for($recipe)->createOne([
        'status' => RecipeVersion::STATUS_TESTED,
    ]), RecipeVersion::class);

    $this->be($user, 'users')->from('/recipes/' . $recipe->getKey())
        ->post('/recipes/' . $recipe->getKey() . '/versions/' . $version->getKey() . '/approve', [], $this->inertiaHeaders())
        ->assertRedirect();

    static::assertSame(RecipeVersion::STATUS_APPROVED, $version->refresh()->getStatus());
    static::assertSame($version->getKey(), $recipe->refresh()->getActiveVersionId());
    static::assertSame(Recipe::STATUS_READY, $recipe->getStatus());
});

\test('owner can reject a non-active tested version', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);
    $version = Typer::assertInstance(RecipeVersionFactory::new()->for($recipe)->createOne([
        'status' => RecipeVersion::STATUS_TESTED,
    ]), RecipeVersion::class);

    $this->be($user, 'users')->post('/recipes/' . $recipe->getKey() . '/versions/' . $version->getKey() . '/reject')
        ->assertRedirect();
    static::assertSame(RecipeVersion::STATUS_REJECTED, $version->refresh()->getStatus());
});

\test('another user cannot approve a version', function (): void {
    $owner = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $other = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($owner)->createOne(), Recipe::class);
    $version = Typer::assertInstance(RecipeVersionFactory::new()->for($recipe)->createOne(['status' => RecipeVersion::STATUS_TESTED]), RecipeVersion::class);
    $this->be($other, 'users')->post('/recipes/' . $recipe->getKey() . '/versions/' . $version->getKey() . '/approve')->assertNotFound();
});
