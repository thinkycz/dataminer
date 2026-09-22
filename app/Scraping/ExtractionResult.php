<?php

declare(strict_types=1);

namespace App\Scraping;

/**
 * Canonical rows and bounded execution statistics.
 */
final class ExtractionResult
{
    /**
     * @param list<array<string, bool|float|int|string|null>> $rows
     * @param list<string> $diagnostics
     */
    public function __construct(
        public readonly array $rows,
        public readonly bool $complete,
        public readonly array $diagnostics,
        public readonly int $bytes,
        public readonly int $requests,
        public readonly int $pages,
    ) {}
}
