<?php

declare(strict_types=1);

use App\Enums\FilesystemDiskEnum;
use Thinkycz\LaravelCore\Support\Env;

$env = Env::inject();

return [
    'node_binary' => $env->parseNullableString('SCRAPING_NODE_BINARY') ?? 'node',
    'artifact_disk' => $env->parseNullableString('SCRAPING_ARTIFACT_DISK') ?? FilesystemDiskEnum::Private->value,
    'test' => [
        'rows' => 100,
        'bytes' => 5_000_000,
        'requests' => 100,
        'pages' => 10,
        'seconds' => 120,
    ],
    'full' => [
        'rows' => 100_000,
        'bytes' => 250_000_000,
        'requests' => 10_000,
        'pages' => 1_000,
        'seconds' => 3_600,
    ],
];
