<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Ai\RecipeApprovalService;
use App\Ai\RecipeGenerationService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\RecipeValidity;
use App\Models\CollectorConnection;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Scraping\BrowserService;
use App\Scraping\CollectorConnectionService;
use App\Scraping\GuardedHttpTransport;
use App\Scraping\RecipeCandidateService;
use App\Scraping\RecipeDefinition;
use App\Scraping\RecipeRepository;
use App\Scraping\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeController
{
    use ValidatesWebRequests;

    /**
     * Display the independent collector builder.
     */
    public function setup(int $recipe): Response
    {
        $owned = (new RecipeRepository())->findOwned($recipe, User::mustAuth());

        return Inertia::render('recipes/Setup', [
            'recipe' => ['id' => $owned->getKey(), 'name' => $owned->getName(), 'start_url' => $owned->getStartUrl()],
            'definition' => $owned->getSetupDraft(),
            'connections' => CollectorConnection::query()->where('user_id', User::mustAuth()->getKey())->get()->map(static fn(CollectorConnection $connection): array => ['id' => $connection->getKey(), 'name' => $connection->getName(), 'origin' => $connection->getOrigin(), 'kind' => $connection->getKind(), 'status' => $connection->getStatus()])->all(),
        ]);
    }

    /**
     * Show owned recipes.
     */
    public function index(Request $request): Response
    {
        $user = User::mustAuth();
        $query = (new RecipeRepository())->ownedQuery($user)->with('activeVersion')->latest();
        $search = $request->string('search')->trim()->toString();
        if ($search !== '') {
            Recipe::scopeSearch($query, $search);
        }

        return Inertia::render('recipes/Index', [
            'recipes' => $query->paginate(20)->withQueryString()->through(static function (Recipe $recipe): array {
                $activeVersion = $recipe->activeVersion()->getResults();
                $lastRun = $recipe->runs()->getQuery()->latest()->first();

                return [
                    'id' => $recipe->getKey(),
                    'name' => $recipe->getName(),
                    'start_url' => $recipe->getStartUrl(),
                    'status' => $recipe->getStatus(),
                    'active_version' => $activeVersion instanceof RecipeVersion ? $activeVersion->getVersion() : null,
                    'last_run' => $lastRun instanceof ScrapeRun ? ['id' => $lastRun->getId(), 'status' => $lastRun->getStatus()] : null,
                ];
            }),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the recipe creation form.
     */
    public function create(): Response
    {
        return Inertia::render('recipes/Create');
    }

    /**
     * Create a user-owned recipe.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = User::mustAuth();
        $validity = new RecipeValidity();
        $validated = $this->validateRequest($request, [
            'name' => $validity->name()->required()->toArray(),
            'start_url' => $validity->startUrl()->required()->toArray(),
            'source_type' => $validity->sourceType()->nullable()->toArray(),
            'instructions' => $validity->instructions()->nullable()->toArray(),
        ]);

        $sourceType = $validated->assertNullableString('source_type') ?? 'json';
        $recipe = Recipe::create([
            'user_id' => $user->getKey(),
            'name' => $validated->assertString('name'),
            'start_url' => $validated->assertString('start_url'),
            'instructions' => $validated->assertNullableString('instructions') ?? '',
            'status' => Recipe::STATUS_DRAFT,
            'setup_draft' => [
                'schema_version' => 1,
                'source_type' => $sourceType,
                'url' => $validated->assertString('start_url'),
                'connection_id' => null,
                'records_path' => '',
                'fields' => [['name' => 'name', 'path' => 'name', 'type' => 'string', 'required' => true, 'transforms' => [['op' => 'trim']]]],
                'pagination' => ['mode' => 'none'],
                'limits' => ['rows' => 10000, 'bytes' => 50000000, 'requests' => 100, 'pages' => 100, 'seconds' => 600],
                'validation' => ['allow_empty' => false],
                'comparison' => ['identity' => [], 'fields' => []],
                ...($sourceType === 'website' ? ['website' => ['record_selector' => 'body', 'detail_fields' => []]] : []),
            ],
        ]);

        Inertia::flash('success', \__('Recipe created.'));

        return Resolver::resolveRedirector()->to('/recipes/' . $recipe->getKey() . '/setup');
    }

    /**
     * Show recipe lifecycle, versions, runs, and pending approval.
     */
    public function show(int $recipe): Response
    {
        $owned = (new RecipeRepository())->findOwned($recipe, User::mustAuth());
        $versions = $owned->versions()->getQuery()->latest('version')->get()->map(static fn(RecipeVersion $version): array => [
            'id' => $version->getKey(),
            'version' => $version->getVersion(),
            'source' => $version->getSource(),
            'definition_format' => $version->getDefinitionFormat(),
            'definition' => $version->getDefinition()?->toArray(),
            'checksum' => $version->getChecksum(),
            'status' => $version->getStatus(),
            'summary' => $version->getGenerationSummary(),
            'reason' => $version->getGenerationReason(),
            'columns' => $version->getProposedColumns(),
            'test_summary' => $version->getTestSummary(),
            'approved_at' => $version->getApprovedAt()?->toJSON(),
            'sample_run_id' => $version->runs()->getQuery()->where('kind', ScrapeRun::KIND_TEST)->where('status', ScrapeRun::STATUS_COMPLETED)->latest()->first()?->getId(),
        ])->all();
        $runs = $owned->runs()->getQuery()->latest()->limit(20)->get()->map(static fn(ScrapeRun $run): array => [
            'id' => $run->getId(),
            'kind' => $run->getKind(),
            'status' => $run->getStatus(),
            'progress' => $run->getProgress(),
            'rows' => $run->getRowCount(),
        ])->all();

        return Inertia::render('recipes/Show', [
            'recipe' => [
                'id' => $owned->getKey(),
                'name' => $owned->getName(),
                'start_url' => $owned->getStartUrl(),
                'instructions' => $owned->getInstructions(),
                'status' => $owned->getStatus(),
                'active_version_id' => $owned->getActiveVersionId(),
            ],
            'setupDraft' => $owned->getSetupDraft(),
            'assistanceAvailable' => false,
            'schedule' => (new ScheduleService())->forRecipe($owned, User::mustAuth()),
            'events' => Resolver::resolveDatabaseManager()->table('collector_events')->where('recipe_id', $owned->getKey())->latest('id')->limit(20)->get()->map(static fn(object $event): array => ['kind' => $event->kind, 'created_at' => $event->created_at, 'run_id' => $event->run_id])->all(),
            'emailNotifications' => $owned->wantsEmailNotifications(),
            'pendingApproval' => (new RecipeApprovalService())->pending($owned),
            'versions' => $versions,
            'runs' => $runs,
        ]);
    }

    /**
     * Save corrections without changing the approved collector.
     */
    public function updateSetup(Request $request, int $recipe): RedirectResponse
    {
        $user = User::mustAuth();
        $owned = (new RecipeRepository())->findOwned($recipe, $user);
        try {
            $input = $request->input('definition');
            if (!\is_array($input)) {
                throw new InvalidArgumentException('A structured definition is required.');
            }
            foreach (\array_keys($input) as $key) {
                if (!\is_string($key)) {
                    throw new InvalidArgumentException('Definition keys must be named properties.');
                }
            }
            $definition = RecipeDefinition::fromArray(Typer::assertStringKeyArray($input));
            (new RecipeCandidateService())->saveDraft($owned, $user, $definition);
        } catch (InvalidArgumentException $error) {
            Thrower::default()->message('definition', $error->getMessage())->throw();
        }

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Test an immutable snapshot of the current setup draft.
     */
    public function preview(int $recipe): RedirectResponse
    {
        $user = User::mustAuth();
        $owned = (new RecipeRepository())->findOwned($recipe, $user);
        try {
            $run = (new RecipeCandidateService())->preview($owned, $user);
        } catch (InvalidArgumentException $error) {
            Thrower::default()->message('definition', $error->getMessage())->throw();
        }

        return Resolver::resolveRedirector()->to('/scrape-runs/' . $run->getId());
    }

    /**
     * Fetch a small private sample for mapping without invoking AI.
     */
    public function sample(Request $request, int $recipe): JsonResponse
    {
        $owned = (new RecipeRepository())->findOwned($recipe, User::mustAuth());
        $draft = $owned->getSetupDraft();
        $url = $request->string('url')->toString();
        if ($url === '') {
            $url = $owned->getStartUrl();
        }
        $headers = [];
        if ($draft !== null) {
            $definition = RecipeDefinition::fromArray($draft);
            \abort_unless((new CollectorConnectionService())->origin($url) === (new CollectorConnectionService())->origin($definition->getUrl()), 422);
            $connections = new CollectorConnectionService();
            $headers = $connections->headers($connections->forDefinition($definition, User::mustAuth()));
        }
        $response = (new GuardedHttpTransport())->get($url, $headers, 1_000_000, 20, 5);
        $decoded = \json_decode($response['body'], true, 32);

        return new JsonResponse(['sample' => $decoded ?? \mb_substr($response['body'], 0, 50_000)]);
    }

    /**
     * Opt in to queued email alerts independently of in-app events.
     */
    public function notifications(Request $request, int $recipe): RedirectResponse
    {
        $owned = (new RecipeRepository())->findOwned($recipe, User::mustAuth());
        $owned->update(['email_notifications' => $request->boolean('enabled')]);

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Save feed authentication without disclosing stored credentials.
     */
    public function connection(Request $request, int $recipe): RedirectResponse
    {
        $user = User::mustAuth();
        $owned = (new RecipeRepository())->findOwned($recipe, $user);
        $kind = $request->string('kind')->toString();
        \abort_unless(\in_array($kind, ['bearer', 'api_key', 'basic'], true), 422);
        $credentials = [];
        foreach ($kind === 'basic' ? ['username', 'password'] : ($kind === 'api_key' ? ['header', 'token'] : ['token']) as $key) {
            $value = $request->input($key);
            \abort_unless(\is_string($value) && $value !== '' && \mb_strlen($value) <= 8192 && !\str_contains($value, "\r") && !\str_contains($value, "\n"), 422);
            $credentials[$key] = $value;
        }
        if ($kind === 'api_key') {
            \abort_unless(\preg_match('/^[A-Za-z][A-Za-z0-9-]{0,100}$/', $credentials['header'] ?? '') === 1 && !\in_array(\mb_strtolower($credentials['header']), ['host', 'connection', 'content-length', 'transfer-encoding', 'proxy-authorization'], true), 422);
        }
        CollectorConnection::create([
            'user_id' => $user->getKey(), 'name' => $owned->getName(),
            'origin' => (new CollectorConnectionService())->origin($request->string('origin')->toString() !== '' ? $request->string('origin')->toString() : Typer::assertString($owned->getSetupDraft()['url'] ?? $owned->getStartUrl())),
            'kind' => $kind, 'status' => 'ready', 'credentials' => $credentials,
        ]);

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Revoke reusable state immediately.
     */
    public function revokeConnection(int $recipe, int $connection): RedirectResponse
    {
        $user = User::mustAuth();
        (new RecipeRepository())->findOwned($recipe, $user);
        Resolver::resolveDatabaseManager()->transaction(static function () use ($user, $connection): void {
            $owned = CollectorConnection::query()->where('user_id', $user->getKey())->lockForUpdate()->findOrFail($connection);
            (new CollectorConnectionService())->expire($connection);
            $owned->update(['status' => 'revoked', 'credentials' => [], 'verified_at' => null, 'state_revision' => $owned->getStateRevision() + 1]);
        }, 3);

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Relay browser commands after authorizing both collector and session.
     */
    public function browser(Request $request, int $recipe): JsonResponse
    {
        $user = User::mustAuth();
        $owned = (new RecipeRepository())->findOwned($recipe, $user);
        $action = $request->string('action')->toString();
        $key = 'collector_browser.' . $owned->getKey();
        $browser = new BrowserService();
        if ($action === 'open') {
            $old = $request->session()->get($key);
            if (\is_string($old)) {
                try {
                    $browser->request('DELETE', '/sessions/' . \rawurlencode($old));
                } catch (RuntimeException) {
                    $request->session()->forget($key);
                }
            }
            $definition = $owned->getSetupDraft() === null ? null : RecipeDefinition::fromArray($owned->getSetupDraft());
            $connection = $definition?->getConnectionId() === null ? null : CollectorConnection::query()->where('user_id', $user->getKey())->findOrFail($definition->getConnectionId());
            $payload = ['url' => $definition?->getUrl() ?? $owned->getStartUrl()];
            \abort_unless($connection === null || (new CollectorConnectionService())->origin($payload['url']) === $connection->getOrigin(), 422);
            if ($connection !== null && $connection->getKind() === 'browser' && $connection->getStatus() === 'ready') {
                $payload['storageState'] = $connection->getCredentials();
            }
            $result = $browser->request('POST', '/sessions', $payload);
            $request->session()->put($key, Typer::assertString($result['sessionId']));
            $request->session()->put($key . '_context', ['origin' => (new CollectorConnectionService())->origin($payload['url']), 'connection_id' => $definition?->getConnectionId()]);

            return new JsonResponse(['opened' => true]);
        }
        $session = $request->session()->get($key);
        \abort_unless(\is_string($session), 409);
        $path = '/sessions/' . \rawurlencode($session);
        if ($action === 'snapshot') {
            return new JsonResponse($browser->request('POST', $path . '/snapshot', ['selector' => $request->string('selector')->toString()]));
        }
        if ($action === 'inspect') {
            $input = $request->input('input');

            return new JsonResponse(\is_array($input) ? $browser->request('POST', $path . '/inspect', Typer::assertStringKeyArray($input)) : $browser->request('GET', $path . '/inspect'));
        }
        if ($action === 'act') {
            $payload = $request->input('input');
            \abort_unless(\is_array($payload), 422);

            return new JsonResponse($browser->request('POST', $path . '/act', Typer::assertStringKeyArray($payload)));
        }
        if ($action === 'save') {
            $context = $request->session()->get($key . '_context');
            \abort_unless(\is_array($context), 409);
            $state = $browser->request('GET', $path . '/state');
            Resolver::resolveDatabaseManager()->transaction(static function () use ($owned, $user, $context, $state): void {
                $connectionId = $context['connection_id'] ?? null;
                \abort_unless($connectionId === null || \is_int($connectionId), 409);
                $connection = $connectionId === null ? new CollectorConnection() : CollectorConnection::query()->where('user_id', $user->getKey())->lockForUpdate()->findOrFail($connectionId);
                $locked = Recipe::query()->whereKey($owned->getKey())->lockForUpdate()->firstOrFail();
                $draft = $locked->getSetupDraft();
                $definition = $draft === null ? null : RecipeDefinition::fromArray($draft);
                \abort_unless(($context['origin'] ?? null) === (new CollectorConnectionService())->origin($definition?->getUrl() ?? $locked->getStartUrl()) && $connectionId === $definition?->getConnectionId(), 409);
                \abort_unless(!$connection->exists || $connection->getKind() === 'browser', 422);
                $connection->fill([
                    'user_id' => $user->getKey(), 'name' => $locked->getName(),
                    'origin' => $context['origin'], 'kind' => 'browser', 'status' => 'ready',
                    'credentials' => $state['storageState'], 'verified_at' => null,
                    'state_revision' => $connection->exists ? $connection->getStateRevision() + 1 : 1,
                ])->save();
                if ($draft !== null) {
                    $locked->update(['setup_draft' => [...$draft, 'connection_id' => $connection->getKey()]]);
                }
            });
            $browser->request('DELETE', $path);
            $request->session()->forget([$key, $key . '_context']);

            return new JsonResponse(['saved' => true]);
        }
        \abort(422);
    }

    /**
     * Queue initial candidate generation.
     */
    public function generate(int $recipe): RedirectResponse
    {
        $user = User::mustAuth();
        $owned = (new RecipeRepository())->findOwned($recipe, $user);
        (new RecipeGenerationService())->queue($owned, $user, 'initial');
        Inertia::flash('success', \__('Recipe generation queued.'));

        return Resolver::resolveRedirector()->back();
    }
}
