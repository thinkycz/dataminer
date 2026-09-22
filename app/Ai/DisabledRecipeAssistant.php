<?php

declare(strict_types=1);

namespace App\Ai;

use App\Scraping\RecipeDefinition;

class DisabledRecipeAssistant implements RecipeAssistantInterface
{
    /**
     * Keep the optional extension inert until an implementation is installed.
     */
    public function propose(RecipeDefinition $current, string $instructions): RecipeAssistantProposal
    {
        \abort(403);
    }
}
