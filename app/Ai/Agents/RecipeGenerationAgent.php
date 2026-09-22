<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\AssistancePromptMiddleware;
use App\Ai\Tools\TestRecipeCandidateTool;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\RemembersConversations as RemembersConversationsContract;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Laravel\Ai\ToolChoice;

class RecipeGenerationAgent implements Agent, HasMiddleware, HasTools, RemembersConversationsContract
{
    use Promptable;
    use RemembersConversations;

    /**
     * Require candidate creation only for generation, not approval continuation.
     */
    public bool $requireCandidate = false;

    /**
     * Enforce the provider gate inside queued SDK work.
     *
     * @return array<int, class-string>
     */
    public function middleware(): array
    {
        return [AssistancePromptMiddleware::class];
    }

    /**
     * Prevent a summary from replacing the required candidate tool call.
     */
    public function toolChoice(): ToolChoice
    {
        return new ToolChoice($this->requireCandidate ? ToolChoice::required : ToolChoice::auto);
    }

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
            requests human approval; calling it does not run the browser test. You must call it to present the candidate for
            approval. Never claim the recipe was tested before that approval. After the tool returns, give a short summary.
            Do not call any other tool.
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
}
