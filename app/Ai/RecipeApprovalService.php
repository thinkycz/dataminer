<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\RecipeGenerationAgent;
use App\Models\Recipe;
use App\Models\User;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeApprovalService
{
    /**
     * Return the recipe's one unresolved approval call for display.
     *
     * @return array{id: string, tool: string, reason: string|null, arguments: array<string, mixed>}|null
     */
    public function pending(Recipe $recipe): array|null
    {
        $conversationId = $recipe->getAiConversationId();
        if ($conversationId === null) {
            return null;
        }

        $message = ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->whereNotNull('approval_state')
            ->latest('id')
            ->first();

        if (!$message instanceof ConversationMessage) {
            return null;
        }

        $state = $message->getAttribute('approval_state');
        $calls = $message->getAttribute('tool_calls');
        if (!\is_array($state) || !\is_array($state['pending'] ?? null) || !\is_array($calls)) {
            return null;
        }

        $ids = \array_keys($state['pending']);
        if (\count($ids) !== 1) {
            return null;
        }

        $id = Typer::assertString($ids[0]);
        foreach ($calls as $call) {
            if (\is_array($call) && ($call['id'] ?? null) === $id) {
                return [
                    'id' => $id,
                    'tool' => Typer::assertString($call['name'] ?? ''),
                    'reason' => Typer::assertNullableString($state['pending'][$id]),
                    'arguments' => Typer::assertStringKeyArray(Typer::assertArray($call['arguments'] ?? [])),
                ];
            }
        }

        return null;
    }

    /**
     * Resolve the exact current approval and queue continuation of the same conversation.
     */
    public function decide(Recipe $recipe, User $user, string $callId, bool $approve): void
    {
        $pending = $this->pending($recipe);

        if ($pending === null || !\hash_equals($pending['id'], $callId)) {
            Thrower::default()->message('approval', Typer::assertString(\__('This approval is missing, stale, or already resolved.')))->throw();
        }

        $conversationId = Typer::assertString($recipe->getAiConversationId());
        $decision = $approve ? Decision::approve() : Decision::reject('The user rejected this recipe candidate.');
        RecipeGenerationAgent::make()
            ->continue($conversationId, $user)
            ->queue(Decisions::from([$callId => $decision]))
            ->onQueue('ai');
    }
}
