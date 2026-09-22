<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\AssistancePromptMiddleware;
use App\Ai\Tools\AskClarifyingQuestionsTool;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Thinkycz\LaravelCore\Support\Config;

class ChatAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable;
    use RemembersConversations;

    /**
     * Enforce the provider gate for any prompt path.
     *
     * @return array<int, class-string>
     */
    public function middleware(): array
    {
        return [AssistancePromptMiddleware::class];
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return 'You are a helpful AI assistant built into the application boilerplate.';
    }

    /**
     * Get the model for the agent.
     */
    public function model(): string
    {
        return Config::inject()->assertString('ai.providers.openrouter.model');
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        yield new AskClarifyingQuestionsTool();
    }
}
