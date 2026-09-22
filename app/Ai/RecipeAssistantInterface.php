<?php

declare(strict_types=1);

namespace App\Ai;

use App\Scraping\RecipeDefinition;

interface RecipeAssistantInterface
{
    /**
     * Propose a configuration-only definition for human review.
     */
    public function propose(RecipeDefinition $current, string $instructions): RecipeAssistantProposal;
}
