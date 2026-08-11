<?php

declare(strict_types=1);

namespace App\Observers;

use LogicException;

class ScrapeRunColumnObserver
{
    /**
     * Prevent column changes.
     */
    public function updating(): void
    {
        throw new LogicException('Scrape run columns are immutable.');
    }

    /**
     * Prevent column deletion.
     */
    public function deleting(): void
    {
        throw new LogicException('Scrape run columns are immutable.');
    }
}
