<?php

declare(strict_types=1);

use App\Enums\FilesystemDiskEnum;
use Thinkycz\LaravelCore\Support\Env;

$env = Env::inject();

return [
    'browser_service_url' => $env->parseNullableString('BROWSER_SERVICE_URL') ?? 'http://127.0.0.1:3210',
    'browser_service_secret' => $env->parseNullableString('BROWSER_SERVICE_SECRET') ?? '',
    'node_binary' => $env->parseNullableString('SCRAPING_NODE_BINARY') ?? 'node',
    'headless' => $env->parseNullableBool('SCRAPING_HEADLESS') ?? true,
    'browser_channel' => $env->parseNullableString('SCRAPING_BROWSER_CHANNEL'),
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
