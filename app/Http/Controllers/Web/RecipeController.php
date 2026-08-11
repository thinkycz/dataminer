<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Ai\RecipeApprovalService;
use App\Ai\RecipeGenerationService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\RecipeValidity;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Scraping\RecipeRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;

class RecipeController
{
    use ValidatesWebRequests;

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
            'instructions' => $validity->instructions()->required()->toArray(),
        ]);

        $recipe = Recipe::create([
            'user_id' => $user->getKey(),
            'name' => $validated->assertString('name'),
            'start_url' => $validated->assertString('start_url'),
            'instructions' => $validated->assertString('instructions'),
            'status' => Recipe::STATUS_DRAFT,
        ]);

        Inertia::flash('success', \__('Recipe created.'));

        return Resolver::resolveRedirector()->to('/recipes/' . $recipe->getKey());
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
            'checksum' => $version->getChecksum(),
            'status' => $version->getStatus(),
            'summary' => $version->getGenerationSummary(),
            'reason' => $version->getGenerationReason(),
            'columns' => $version->getProposedColumns(),
            'test_summary' => $version->getTestSummary(),
            'approved_at' => $version->getApprovedAt()?->toJSON(),
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
            'pendingApproval' => (new RecipeApprovalService())->pending($owned),
            'versions' => $versions,
            'runs' => $runs,
        ]);
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
