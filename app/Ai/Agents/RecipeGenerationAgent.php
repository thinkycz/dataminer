<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\TestRecipeCandidateTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\RemembersConversations as RemembersConversationsContract;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

class RecipeGenerationAgent implements Agent, HasStructuredOutput, HasTools, RemembersConversationsContract
{
    use Promptable;
    use RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return <<<'PROMPT'
            You generate a single reusable JavaScript Playwright scraping recipe for an internal application.
            The source must export exactly `async function scrape(context)` (an exported function declaration is required).
            Use only context.page, context.input, context.emit(row), context.log(level, message), and context.signal.
            Do not import modules, create a browser, access process/environment/filesystem/network APIs directly, launch commands,
            install dependencies, use eval/Function/WebAssembly, or write files. Every emitted row must be a flat object whose
            values are string, number, boolean, or null. Prefer stable selectors and bounded pagination.

            After preparing the source, you MUST call TestRecipeCandidateTool exactly once with every required argument. The call
            is deliberately human-approved. Never claim the recipe was tested before that approval. After the tool returns, give
            a short structured summary. Do not call any other tool.
            PROMPT;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return iterable<int, Tool>
     */
    public function tools(): iterable
    {
        yield new TestRecipeCandidateTool();
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'status' => $schema->string()->enum(['awaiting_approval', 'test_dispatched', 'rejected'])->required(),
        ];
    }
}
