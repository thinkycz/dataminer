<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\User;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

class AssistanceGate
{
    /**
     * Require both the optional extension and provider to be enabled for an entitled user.
     */
    public function available(User|null $user): bool
    {
        if ($user === null || !Config::inject()->assertBool('assistance.enabled') || !Config::inject()->assertBool('assistance.provider_available')) {
            return false;
        }

        foreach (Config::inject()->assertArray('assistance.entitled_emails') as $email) {
            if (\hash_equals(\mb_strtolower(Typer::assertString($email)), \mb_strtolower($user->getEmail()))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stop AI work at every entry point, including a worker processing old jobs.
     */
    public function assertAvailable(User|null $user): void
    {
        if (!$this->available($user)) {
            \abort(403);
        }
    }
}
