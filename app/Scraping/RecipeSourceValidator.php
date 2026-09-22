<?php

declare(strict_types=1);

namespace App\Scraping;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Process\Process;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

class RecipeSourceValidator
{
    /**
     * Validate the application-owned recipe contract before execution.
     */
    public function validate(string $source): void
    {
        if (\preg_match('/export\\s+async\\s+function\\s+scrape\\s*\\(\\s*context\\s*\\)/', $source) !== 1) {
            throw new InvalidArgumentException('Recipe must export async function scrape(context).');
        }

        $forbidden = [
            '/\\bimport\\s*(?:\\(|[\\s{*])/',
            '/\\brequire\\s*\\(/',
            '/\\bprocess\\b/',
            '/\\bchild_process\\b/',
            '/\\b(?:fs|path|os|net|tls|dns|http|https)\\s*\\./',
            '/(?<![\\w$])(?:eval|Function|WebAssembly)\\s*\\(/',
            '/\\b(?:fetch|XMLHttpRequest|WebSocket)\\s*\\(/',
            '/\\b(?:chromium|firefox|webkit)\\s*\\./',
        ];

        foreach ($forbidden as $pattern) {
            if (\preg_match($pattern, $source) === 1) {
                throw new InvalidArgumentException('Recipe contains a forbidden runtime capability.');
            }
        }

        $path = \sys_get_temp_dir() . '/recipe-check-' . Str::uuid()->toString() . '.mjs';
        Resolver::resolveFilesystem()->put($path, $source);

        try {
            $process = new Process([Config::inject()->assertString('scraping.node_binary'), '--check', $path], null, [
                'PATH' => '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin',
            ], null, 10);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new InvalidArgumentException('Recipe contains invalid JavaScript syntax.');
            }
        } finally {
            Resolver::resolveFilesystem()->delete($path);
        }
    }
}
