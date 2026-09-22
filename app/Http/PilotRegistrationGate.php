<?php

declare(strict_types=1);

namespace App\Http;

use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

class PilotRegistrationGate
{
    /**
     * Admit only addresses explicitly listed for pilot signup.
     */
    public function assertAllowed(string $email): void
    {
        foreach (Config::inject()->assertArray('pilot.registration_emails') as $allowed) {
            if (\hash_equals(\mb_strtolower(Typer::assertString($allowed)), \mb_strtolower(\mb_trim($email)))) {
                return;
            }
        }

        \abort(403);
    }
}
