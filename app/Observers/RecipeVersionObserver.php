<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RecipeVersion;
use LogicException;

class RecipeVersionObserver
{
    /**
     * Prevent changes to generated source and identity fields.
     */
    public function updating(RecipeVersion $version): void
    {
        if ($version->isDirty(['recipe_id', 'version', 'source', 'checksum', 'proposed_columns', 'generation_reason', 'approval_call_id', 'definition_format', 'schema_version', 'definition'])) {
            throw new LogicException('Recipe version source and identity are immutable.');
        }
    }

    /**
     * Prevent deletion of immutable versions.
     */
    public function deleting(): void
    {
        throw new LogicException('Recipe versions are immutable.');
    }
}
