<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\User;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class AssistancePromptMiddleware
{
    /**
     * Check at execution time so agent prompts queued before disablement are stopped.
     *
     * @param Closure(AgentPrompt): mixed $next
     */
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        (new AssistanceGate())->assertAvailable(User::auth());

        return $next($prompt);
    }
}
