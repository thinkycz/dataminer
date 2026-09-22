<?php

declare(strict_types=1);

namespace App\Scraping;

/**
 * Execute a validated extraction definition.
 */
interface SourceAdapterInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function execute(RecipeDefinition $definition, array $headers = []): ExtractionResult;
}
