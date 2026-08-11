<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Ai\RecipeApprovalService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use App\Scraping\RecipeRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class RecipeApprovalController
{
    use ValidatesWebRequests;

    /**
     * Resolve an exact pending SDK tool call.
     */
    public function decide(Request $request, int $recipe, string $approval): RedirectResponse
    {
        $user = User::mustAuth();
        $owned = (new RecipeRepository())->findOwned($recipe, $user);
        $validated = $this->validateRequest($request, ['decision' => 'required|in:approve,reject']);
        (new RecipeApprovalService())->decide($owned, $user, $approval, $validated->assertString('decision') === 'approve');
        Inertia::flash('success', \__('Approval decision queued.'));

        return Resolver::resolveRedirector()->back();
    }
}
