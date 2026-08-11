<?php

declare(strict_types=1);

namespace App\Observers;

use LogicException;

class ScrapeRowObserver
{
    /**
     * Prevent row changes.
     */
    public function updating(): void
    {
        throw new LogicException('Scrape rows are immutable.');
    }

    /**
     * Prevent row deletion.
     */
    public function deleting(): void
    {
        throw new LogicException('Scrape rows are immutable.');
    }
}
