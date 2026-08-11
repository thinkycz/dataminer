<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Ai\Agents\RecipeGenerationAgent;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\ToolApprovalRequested;
use Laravel\Ai\Events\ToolApprovalResolved;
use Laravel\Ai\Responses\Data\ToolResult;

class RecipeAiEventListener
{
    /**
     * Associate the SDK conversation and expose its pending approval.
     */
    public function requested(ToolApprovalRequested $event): void
    {
        if (!$event->agent instanceof RecipeGenerationAgent || $event->conversationId === null) {
            return;
        }

        $approval = $event->pendingApprovals->first();
        $recipeId = $approval?->arguments['recipe_id'] ?? null;
        if (!\is_int($recipeId)) {
            return;
        }

        $recipe = Recipe::query()->find($recipeId);
        if ($recipe instanceof Recipe) {
            $recipe->update([
                'ai_conversation_id' => $event->conversationId,
                'status' => Recipe::STATUS_PENDING_APPROVAL,
            ]);
        }
    }

    /**
     * Reflect a rejected approval; approved tools update their own lifecycle atomically.
     */
    public function resolved(ToolApprovalResolved $event): void
    {
        if (!$event->agent instanceof RecipeGenerationAgent || $event->conversationId === null) {
            return;
        }

        $recipe = Recipe::query()->where('ai_conversation_id', $event->conversationId)->first();
        $denied = $event->toolResults->contains(static fn(ToolResult $result): bool => $result->denied);

        if ($recipe instanceof Recipe && $denied) {
            $recipe->update(['status' => Recipe::STATUS_DRAFT]);
        }
    }

    /**
     * Persist current provider/model/usage metadata on the created version.
     */
    public function prompted(AgentPrompted $event): void
    {
        if (!$event->prompt->agent instanceof RecipeGenerationAgent || $event->response->conversationId === null) {
            return;
        }

        $recipe = Recipe::query()->where('ai_conversation_id', $event->response->conversationId)->first();
        if (!$recipe instanceof Recipe) {
            return;
        }

        $version = $recipe->versions()->getQuery()->latest('version')->first();
        if ($version instanceof RecipeVersion && $version->getStatus() === RecipeVersion::STATUS_TESTING) {
            $version->update([
                'provider' => $event->response->meta->provider,
                'model' => $event->response->meta->model,
                'usage' => $event->response->usage->toArray(),
            ]);
        }
    }
}
