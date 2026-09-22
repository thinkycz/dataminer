<?php

declare(strict_types=1);

namespace App\Ai;

use App\Scraping\RecipeDefinition;

class RecipeAssistantProposal
{
    /**
     * Carry the proposed configuration and a reviewable account of changes.
     *
     * @param array<int, string> $fieldChanges
     */
    public function __construct(
        public readonly RecipeDefinition $definition,
        public readonly string $explanation,
        public readonly array $fieldChanges,
    ) {}
}
