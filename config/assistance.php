<?php

declare(strict_types=1);

use Thinkycz\LaravelCore\Support\Env;

$env = Env::inject();

return [
    'enabled' => $env->parseNullableBool('ASSISTANCE_ENABLED') ?? false,
    'provider_available' => $env->parseNullableBool('ASSISTANCE_PROVIDER_AVAILABLE') ?? false,
    'entitled_emails' => \array_values(\array_filter(\array_map(
        static fn(string $email): string => \mb_strtolower(\mb_trim($email)),
        \explode(',', $env->parseNullableString('ASSISTANCE_ENTITLED_EMAILS') ?? ''),
    ), static fn(string $email): bool => $email !== '')),
];
