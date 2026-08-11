<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\RecipeGenerationAgent;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\User;
use Throwable;

class RecipeGenerationService
{
    /**
     * Queue initial generation or an explicit repair in the persisted conversation.
     */
    public function queue(Recipe $recipe, User $user, string $reason, RecipeVersion|null $version = null, string|null $error = null): void
    {
        $recipe->update(['status' => Recipe::STATUS_GENERATING]);
        $agent = RecipeGenerationAgent::make();

        if ($recipe->getAiConversationId() === null) {
            $agent->forParticipant($user);
        } else {
            $agent->continue($recipe->getAiConversationId(), $user);
        }

        $prompt = $reason === 'repair'
            ? $this->repairPrompt($recipe, $version, $error)
            : $this->initialPrompt($recipe);

        $queued = $agent->queue($prompt);
        $queued->catch(static function (Throwable $throwable) use ($recipe): void {
            $recipe->refresh();
            if ($recipe->getStatus() === Recipe::STATUS_GENERATING) {
                $recipe->update(['status' => Recipe::STATUS_FAILED]);
            }
        });
        $queued->onQueue('ai');
    }

    /**
     * Build a bounded initial prompt.
     */
    private function initialPrompt(Recipe $recipe): string
    {
        return "Create a scraping recipe candidate.\n"
            . 'recipe_id: ' . $recipe->getKey() . "\n"
            . 'start_url: ' . $recipe->getStartUrl() . "\n"
            . "generation_reason: initial\n"
            . 'instructions: ' . $recipe->getInstructions();
    }

    /**
     * Build a sanitized, bounded repair prompt.
     */
    private function repairPrompt(Recipe $recipe, RecipeVersion|null $version, string|null $error): string
    {
        return "Generate a replacement scraping recipe candidate. Do not reuse the previous approval call.\n"
            . 'recipe_id: ' . $recipe->getKey() . "\n"
            . 'start_url: ' . $recipe->getStartUrl() . "\n"
            . "generation_reason: repair\n"
            . 'instructions: ' . $recipe->getInstructions() . "\n"
            . 'sanitized_failure: ' . \mb_substr($this->sanitize($error ?? 'No diagnostic was recorded.'), 0, 2_000) . "\n"
            . 'previous_source: ' . \mb_substr($version?->getSource() ?? '', 0, 30_000);
    }

    /**
     * Remove control characters and likely secret-bearing header values.
     */
    private function sanitize(string $value): string
    {
        $value = \preg_replace('/(?i)(authorization|cookie|set-cookie|api[-_ ]?key)\\s*[:=]\\s*[^\\s]+/', '$1: [redacted]', $value) ?? '';

        return \preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/u', '', $value) ?? '';
    }
}
