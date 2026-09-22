<?php

declare(strict_types=1);

use App\Ai\Tools\TestRecipeCandidateTool;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Scraping\ScrapeRunner;
use Database\Factories\RecipeFactory;
use Database\Factories\RecipeVersionFactory;
use Database\Factories\ScrapeRunFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Typer;

\test('disabled approved tool cannot persist or test an old candidate', function (): void {
    Http::preventStrayRequests();
    Http::fake();
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $recipe = Typer::assertInstance(RecipeFactory::new()->for($user)->createOne(), Recipe::class);
    $request = new Request([
        'recipe_id' => $recipe->getKey(),
        'source' => 'export async function scrape(context) { context.emit({ name: "ok" }); }',
        'proposed_columns' => [['key' => 'name', 'label' => 'Name', 'type' => 'string']],
        'generation_summary' => 'Extract names.',
        'generation_reason' => 'initial',
    ], 'tool-call-1');
    $tool = new TestRecipeCandidateTool();

    $this->be($user, 'users');
    \expect(fn() => $tool->handle($request))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

    static::assertSame(0, RecipeVersion::query()->count());
    static::assertSame(0, ScrapeRun::query()->count());
    Http::assertNothingSent();
});

\test('an empty runner response fails the test and cannot mark its version tested', function (): void {
    $user = UserFactory::new()->createOne();
    $recipe = RecipeFactory::new()->for($user)->createOne(['start_url' => 'https://93.184.216.34', 'status' => Recipe::STATUS_TESTING]);
    $version = RecipeVersionFactory::new()->for($recipe)->createOne(['status' => RecipeVersion::STATUS_TESTING]);
    $run = ScrapeRunFactory::new()->createOne(['recipe_id' => $recipe->getKey(), 'recipe_version_id' => $version->getKey(), 'user_id' => $user->getKey()]);
    $executable = \tempnam(\sys_get_temp_dir(), 'empty-runner-');
    \file_put_contents($executable, <<<'SH'
        #!/bin/sh
        if [ "$1" = "--check" ]; then exit 0; fi
        echo '{"type":"summary","rows":0,"bytes":0,"requests":1,"pages":1}'
        SH);
    \chmod($executable, 0o700);
    \config()->set('scraping.node_binary', $executable);
    try {
        \expect(fn() => (new ScrapeRunner())->execute($run))->toThrow(RuntimeException::class, 'The test collected no rows.');
        \expect($run->refresh()->getStatus())->toBe(ScrapeRun::STATUS_FAILED)
            ->and($version->refresh()->getStatus())->toBe(RecipeVersion::STATUS_DRAFT)
            ->and($recipe->refresh()->getStatus())->toBe(Recipe::STATUS_FAILED);
    } finally {
        \unlink($executable);
    }
});
