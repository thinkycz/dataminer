<?php

declare(strict_types=1);

use Thinkycz\LaravelCore\Support\Env;

$env = Env::inject();

return [
    'registration_emails' => \array_values(\array_filter(\array_map(
        static fn(string $email): string => \mb_strtolower(\mb_trim($email)),
        \explode(',', $env->parseNullableString('PILOT_REGISTRATION_EMAILS') ?? ''),
    ), static fn(string $email): bool => $email !== '')),
];
