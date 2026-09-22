<?php

declare(strict_types=1);

use App\Jobs\RetainCollectorDatasetsJob;
use App\Models\ScrapeRow;
use App\Models\ScrapeRun;
use App\Scraping\RunOutcomeService;
use Database\Factories\RecipeFactory;
use Database\Factories\RecipeVersionFactory;
use Database\Factories\ScrapeRunFactory;
use Thinkycz\LaravelCore\Support\Resolver;

\test('complete runs compare to the same version baseline and suppress repeat failures', function (): void {
    $recipe = RecipeFactory::new()->createOne();
    $definition = ['schema_version' => 1, 'source_type' => 'json', 'url' => 'https://example.com', 'fields' => [['name' => 'id', 'path' => 'id', 'type' => 'string', 'required' => true]], 'comparison' => ['identity' => ['id'], 'fields' => ['id']]];
    $version = RecipeVersionFactory::new()->for($recipe)->createOne(['definition_format' => 'definition', 'definition' => $definition]);
    $runs = [];
    foreach ([['a'], ['a', 'b'], ['a', 'b']] as $i => $ids) {
        $run = ScrapeRunFactory::new()->createOne(['recipe_id' => $recipe->getKey(), 'recipe_version_id' => $version->getKey(), 'user_id' => $recipe->user()->firstOrFail()->getKey(), 'kind' => 'full', 'status' => ScrapeRun::STATUS_COMPLETED, 'complete' => true, 'finished_at' => \now()->addSeconds($i)]);
        foreach ($ids as $j => $id) {
            ScrapeRow::create(['run_id' => $run->getId(), 'sequence' => $j + 1, 'payload' => ['id' => $id]]);
        }
        (new RunOutcomeService())->record($run);
        $runs[] = $run;
    }
    \expect($runs[0]->getComparison()['status'])->toBe('baseline')->and($runs[1]->getComparison()['added'])->toBe(1)->and($runs[2]->getComparison()['added'])->toBe(0);
    \expect(Resolver::resolveDatabaseManager()->table('collector_events')->count())->toBe(1);
    foreach ([1, 2] as $ignored) {
        $failed = ScrapeRunFactory::new()->createOne(['recipe_id' => $recipe->getKey(), 'recipe_version_id' => $version->getKey(), 'user_id' => $recipe->user()->firstOrFail()->getKey(), 'kind' => 'full', 'status' => ScrapeRun::STATUS_FAILED, 'complete' => false]);
        (new RunOutcomeService())->record($failed);
    }
    \expect(Resolver::resolveDatabaseManager()->table('collector_events')->count())->toBe(2);
});

\test('retention cannot delete old legacy history even when a single-run job is delivered', function (): void {
    $run = ScrapeRunFactory::new()->createOne(['status' => ScrapeRun::STATUS_COMPLETED, 'finished_at' => \now()->subDays(40), 'retention_managed' => false]);
    ScrapeRow::create(['run_id' => $run->getId(), 'sequence' => 1, 'payload' => ['id' => 'kept']]);
    (new RetainCollectorDatasetsJob($run->getId()))->handle();
    \expect($run->rows()->count())->toBe(1);
});

\test('retention preserves the last comparable dataset while expiring older managed rows', function (): void {
    $recipe = RecipeFactory::new()->createOne();
    $version = RecipeVersionFactory::new()->for($recipe)->createOne();
    $runs = [];
    foreach ([50, 40] as $days) {
        $run = ScrapeRunFactory::new()->createOne([
            'recipe_id' => $recipe->getKey(), 'recipe_version_id' => $version->getKey(),
            'kind' => 'full', 'status' => ScrapeRun::STATUS_COMPLETED, 'complete' => true,
            'finished_at' => \now()->subDays($days), 'retention_managed' => true,
            'comparison' => ['status' => 'compared', 'added' => 0, 'changed' => 0, 'missing' => 0],
        ]);
        ScrapeRow::create(['run_id' => $run->getId(), 'sequence' => 1, 'payload' => ['id' => 'kept']]);
        $runs[] = $run;
    }
    foreach ($runs as $run) {
        (new RetainCollectorDatasetsJob($run->getId()))->handle();
    }
    \expect($runs[0]->rows()->count())->toBe(0)->and($runs[1]->rows()->count())->toBe(1);
});
