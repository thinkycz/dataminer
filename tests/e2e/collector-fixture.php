<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRow;
use App\Models\ScrapeRun;
use App\Models\ScrapeRunColumn;
use App\Models\User;
use Database\Factories\ScrapeRunFactory;
use Illuminate\Contracts\Console\Kernel;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

require \dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require \dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = Config::inject()->assertString('database.connections.sqlite.database');
if (Config::inject()->assertString('app.env') !== 'testing' ||
    Config::inject()->assertString('database.default') !== 'sqlite' ||
    \preg_match('/^\\/private\\/tmp\\/dataminer-e2e-[0-9a-f-]+\\.sqlite$/', $database) !== 1 ||
    $database !== \realpath($database) ||
    $database !== \getenv('DATAMINER_E2E_DATABASE')) {
    throw new RuntimeException('Collector fixtures require the isolated SQLite browser test database.');
}
$user = User::query()->where('email', $argv[1])->firstOrFail();
$recipe = Recipe::query()->where('user_id', $user->getKey())->latest()->firstOrFail();
$mode = $argv[2];
$version = $recipe->versions()->getQuery()->latest('version')->firstOrFail();

if ($mode === 'sample') {
    $run = $version->runs()->getQuery()->where('kind', ScrapeRun::KIND_TEST)->latest()->firstOrFail();
    $version->update(['status' => RecipeVersion::STATUS_TESTED, 'test_summary' => ['row_count' => 1]]);
    $run->update(['status' => ScrapeRun::STATUS_COMPLETED, 'progress' => 100, 'row_count' => 1, 'complete' => true, 'finished_at' => \now()]);
} elseif ($mode === 'complete') {
    $run = $recipe->runs()->getQuery()->where('kind', ScrapeRun::KIND_FULL)->latest()->firstOrFail();
    $run->update(['status' => ScrapeRun::STATUS_COMPLETED, 'progress' => 100, 'row_count' => 1, 'complete' => true, 'finished_at' => \now()]);
} elseif (\in_array($mode, ['failed', 'cancelled', 'empty'], true)) {
    $run = ScrapeRunFactory::new()->createOne([
        'recipe_id' => $recipe->getKey(), 'recipe_version_id' => $version->getKey(), 'user_id' => $user->getKey(),
        'kind' => ScrapeRun::KIND_FULL, 'status' => $mode === 'empty' ? ScrapeRun::STATUS_COMPLETED : $mode,
        'complete' => $mode === 'empty', 'finished_at' => \now(),
    ]);
} elseif ($mode === 'long') {
    $recipe->update(['name' => \str_repeat('Long collector name ', 10), 'start_url' => 'https://example.com/' . \str_repeat('long-path-', 35)]);
    exit;
} else {
    throw new RuntimeException('Unsupported fixture mode.');
}

if (\in_array($mode, ['sample', 'complete'], true)) {
    ScrapeRunColumn::query()->create(['run_id' => $run->getId(), 'key' => 'name', 'label' => 'Name', 'type' => 'string', 'position' => 0]);
    ScrapeRunColumn::query()->create(['run_id' => $run->getId(), 'key' => 'price', 'label' => 'Price', 'type' => 'number', 'position' => 1]);
    ScrapeRow::query()->create(['run_id' => $run->getId(), 'sequence' => 1, 'payload' => ['name' => 'Notebook', 'price' => 12]]);
    $path = 'redesign-fixtures/' . $run->getId();
    Resolver::resolveFilesystemManager()->disk('local')->put($path . '.csv', "name,price\nNotebook,12\n");
    Resolver::resolveFilesystemManager()->disk('local')->put($path . '.json', '[{"name":"Notebook","price":12}]');
    $run->update(['artifact_disk' => 'local', 'csv_path' => $path . '.csv', 'json_path' => $path . '.json']);
}
echo $run->getId();
