<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\AssistanceGate;
use App\Ai\LegacyRecipeCandidateService;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class TestRecipeCandidateTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Persist an immutable scraping recipe candidate and dispatch its bounded browser test. This always requires human approval.';
    }

    /**
     * Execute the approved tool exactly once.
     */
    public function handle(Request $request): string
    {
        (new AssistanceGate())->assertAvailable(User::auth());

        return (new LegacyRecipeCandidateService())->acceptApprovedToolCall($request);
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'recipe_id' => $schema->integer()->required(),
            'source' => $schema->string()->required(),
            'proposed_columns' => $schema->array()->items(
                $schema->object([
                    'key' => $schema->string()->required(),
                    'label' => $schema->string()->required(),
                    'type' => $schema->string()->enum(['string', 'number', 'boolean', 'null'])->required(),
                ]),
            )->required(),
            'generation_summary' => $schema->string()->required(),
            'generation_reason' => $schema->string()->enum(['initial', 'repair'])->required(),
        ];
    }
}
