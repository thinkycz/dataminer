<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\RecipeVersion;
use App\Models\ScrapeRow;
use App\Models\ScrapeRun;
use App\Models\ScrapeRunColumn;
use App\Models\User;
use App\Scraping\RecipeRepository;
use App\Scraping\ScrapeRunService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class ScrapeRunController
{
    /**
     * Show all owned scrape runs.
     */
    public function index(): Response
    {
        $user = User::mustAuth();
        $runs = ScrapeRun::query()->where('user_id', $user->getKey())->with('recipe')->latest()->paginate(25)
            ->through(static function (ScrapeRun $run): array {
                $recipe = $run->recipe()->getResults();

                return [
                    'id' => $run->getId(),
                    'recipe_name' => $recipe?->getName(),
                    'kind' => $run->getKind(),
                    'status' => $run->getStatus(),
                    'progress' => $run->getProgress(),
                    'rows' => $run->getRowCount(),
                    'finished_at' => $run->getFinishedAt()?->toJSON(),
                ];
            });

        return Inertia::render('runs/Index', ['runs' => $runs]);
    }

    /**
     * Start the approved recipe version without invoking AI.
     */
    public function start(int $recipe): RedirectResponse
    {
        $user = User::mustAuth();
        $owned = (new RecipeRepository())->findOwned($recipe, $user);
        $version = $owned->activeVersion()->getResults();
        if (!$version instanceof RecipeVersion || $version->getStatus() !== RecipeVersion::STATUS_APPROVED) {
            Thrower::default()->message('version', Typer::assertString(\__('An approved recipe version is required.')))->throw();
        }
        $run = (new ScrapeRunService())->start($owned, $version, $user, ScrapeRun::KIND_FULL);

        return Resolver::resolveRedirector()->to('/runs/' . $run->getId());
    }

    /**
     * Show run details and a server-side dataset page.
     */
    public function show(Request $request, string $scrapeRun): Response
    {
        $run = (new RecipeRepository())->findOwnedRun($scrapeRun, User::mustAuth());
        $recipe = $run->recipe()->getResults();
        $columns = $run->columns()->getQuery()->orderBy('position')->get();
        $allowed = $columns->mapWithKeys(static fn(ScrapeRunColumn $column): array => [$column->getColumnKey() => $column])->all();
        $visible = $this->visibleColumns($request, \array_keys($allowed));
        $query = $run->rows()->getQuery()->orderBy('sequence');
        $this->filterRows($query, $request, $allowed, $visible);
        $this->sortRows($query, $request, $allowed);

        return Inertia::render('runs/Show', [
            'recipe' => $recipe === null ? null : ['id' => $recipe->getKey(), 'name' => $recipe->getName()],
            'run' => [
                'id' => $run->getId(),
                'kind' => $run->getKind(),
                'status' => $run->getStatus(),
                'progress' => $run->getProgress(),
                'row_count' => $run->getRowCount(),
                'complete' => $run->isComplete(),
                'diagnostics' => $run->getDiagnostics(),
                'comparison' => $run->getComparison(),
                'byte_count' => $run->getByteCount(),
                'request_count' => $run->getRequestCount(),
                'logs' => $run->getLogs(),
                'error' => $run->getError(),
                'has_json' => $run->getJsonPath() !== null,
                'has_csv' => $run->getCsvPath() !== null,
            ],
            'columns' => $columns->map(static fn(ScrapeRunColumn $column): array => [
                'key' => $column->getColumnKey(),
                'label' => $column->getLabel(),
                'type' => $column->getType(),
            ])->all(),
            'rows' => $query->paginate(50)->withQueryString()->through(static fn(ScrapeRow $row): array => [
                'sequence' => $row->getSequence(),
                'payload' => $row->getPayload(),
            ]),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'column' => $request->array('filter'),
                'sort' => $request->string('sort')->toString(),
                'direction' => $request->string('direction', 'asc')->toString(),
                'visible' => $visible,
            ],
        ]);
    }

    /**
     * Cancel an active run.
     */
    public function cancel(string $scrapeRun): RedirectResponse
    {
        $run = (new RecipeRepository())->findOwnedRun($scrapeRun, User::mustAuth());
        ScrapeRun::query()->whereKey($run->getId())->whereIn('status', [ScrapeRun::STATUS_QUEUED, ScrapeRun::STATUS_RUNNING])->update(['status' => ScrapeRun::STATUS_CANCELLED, 'finished_at' => \now()]);
        Inertia::flash('success', \__('Run cancellation requested.'));

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Emit an ownership-checked SSE state snapshot; EventSource reconnects while active.
     */
    public function stream(string $scrapeRun): StreamedResponse
    {
        $run = (new RecipeRepository())->findOwnedRun($scrapeRun, User::mustAuth());

        return Resolver::resolveResponseFactory()->stream(static function () use ($run): void {
            $fresh = $run->fresh();
            if (!$fresh instanceof ScrapeRun) {
                return;
            }
            echo 'data: ' . \json_encode([
                'type' => 'run_state',
                'status' => $fresh->getStatus(),
                'progress' => $fresh->getProgress(),
                'rows' => $fresh->getRowCount(),
                'error' => $fresh->getError(),
            ], \JSON_THROW_ON_ERROR) . "\n\n";
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache']);
    }

    /**
     * Stream a private owned artifact download.
     */
    public function download(string $scrapeRun, string $format): StreamedResponse
    {
        $run = (new RecipeRepository())->findOwnedRun($scrapeRun, User::mustAuth());
        if (!\in_array($format, ['json', 'csv'], true)) {
            throw new NotFoundHttpException();
        }
        $path = $format === 'json' ? $run->getJsonPath() : $run->getCsvPath();
        $diskName = $run->getArtifactDisk();
        if ($path === null || $diskName === null) {
            throw new NotFoundHttpException();
        }
        $disk = Resolver::resolveFilesystemManager()->disk($diskName);

        return Resolver::resolveResponseFactory()->streamDownload(static function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            if (!\is_resource($stream)) {
                return;
            }
            \fpassthru($stream);
            \fclose($stream);
        }, 'dataset-' . $run->getId() . '.' . $format, ['Content-Type' => $format === 'json' ? 'application/json' : 'text/csv; charset=UTF-8']);
    }

    /**
     * Resolve visible columns from the stored metadata allow-list.
     *
     * @param array<int, string> $allowed
     *
     * @return array<int, string>
     */
    private function visibleColumns(Request $request, array $allowed): array
    {
        $requested = $request->input('visible');
        if (!\is_array($requested)) {
            return $allowed;
        }

        return \array_values(\array_intersect($allowed, \array_filter($requested, '\\is_string')));
    }

    /**
     * Apply safe global and per-column filters.
     *
     * @param Builder<ScrapeRow> $query
     * @param array<string, ScrapeRunColumn> $allowed
     * @param array<int, string> $visible
     */
    private function filterRows(Builder $query, Request $request, array $allowed, array $visible): void
    {
        $filters = $request->input('filter', []);
        if (\is_array($filters)) {
            foreach ($filters as $key => $value) {
                if (!\is_string($key) || !isset($allowed[$key]) || !\is_scalar($value) || (string) $value === '') {
                    continue;
                }
                $query->whereRaw('CAST(json_extract(payload, ?) AS CHAR) LIKE ?', [$this->jsonPath($key), '%' . (string) $value . '%']);
            }
        }

        $search = $request->string('search')->trim()->toString();
        if ($search !== '' && $visible !== []) {
            $query->where(function (Builder $nested) use ($visible, $search): void {
                foreach ($visible as $key) {
                    $nested->orWhereRaw('CAST(json_extract(payload, ?) AS CHAR) LIKE ?', [$this->jsonPath($key), '%' . $search . '%']);
                }
            });
        }
    }

    /**
     * Apply safe type-aware sorting with stable sequence ordering.
     *
     * @param Builder<ScrapeRow> $query
     * @param array<string, ScrapeRunColumn> $allowed
     */
    private function sortRows(Builder $query, Request $request, array $allowed): void
    {
        $key = $request->string('sort')->toString();
        if (!isset($allowed[$key])) {
            return;
        }
        $direction = $request->string('direction')->toString() === 'desc' ? 'DESC' : 'ASC';
        $expression = $allowed[$key]->getType() === 'number'
            ? 'CAST(json_extract(payload, ?) AS DECIMAL(30,10))'
            : 'CAST(json_extract(payload, ?) AS CHAR)';
        $query->reorder()->orderByRaw($expression . ' ' . $direction, [$this->jsonPath($key)])->orderBy('sequence');
    }

    /**
     * Build a quoted MySQL JSON path for a validated stored column key.
     */
    private function jsonPath(string $key): string
    {
        return '$."' . $key . '"';
    }
}
