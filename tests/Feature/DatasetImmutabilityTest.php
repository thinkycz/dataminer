<?php

declare(strict_types=1);

use App\Models\ScrapeRow;
use App\Models\ScrapeRun;
use App\Models\ScrapeRunColumn;
use Database\Factories\ScrapeRunFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('stored dataset rows and columns are immutable', function (): void {
    $run = Typer::assertInstance(ScrapeRunFactory::new()->createOne(), ScrapeRun::class);
    $row = Typer::assertInstance(ScrapeRow::query()->create(['run_id' => $run->getId(), 'sequence' => 1, 'payload' => ['name' => 'A']]), ScrapeRow::class);
    $column = Typer::assertInstance(ScrapeRunColumn::query()->create(['run_id' => $run->getId(), 'key' => 'name', 'label' => 'Name', 'type' => 'string', 'position' => 0]), ScrapeRunColumn::class);

    \expect(fn() => $row->update(['payload' => ['name' => 'B']]))->toThrow(LogicException::class);
    \expect(fn() => $column->update(['label' => 'Changed']))->toThrow(LogicException::class);
});
