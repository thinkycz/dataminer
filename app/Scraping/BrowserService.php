<?php

declare(strict_types=1);

namespace App\Scraping;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

class BrowserService
{
    /** Call the private service; its secret never reaches the client.
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $payload = []): array
    {
        $config = Config::inject();
        $secret = $config->assertString('scraping.browser_service_secret');
        if ($secret === '') {
            throw new RuntimeException('The private browser service is not configured.');
        }
        $definition = Typer::assertArray($payload['definition'] ?? []);
        $limits = Typer::assertArray($definition['limits'] ?? []);
        $seconds = \str_ends_with($path, '/extract') ? \min(300, Typer::assertInt($limits['seconds'] ?? 120)) + 30 : 125;
        $response = Http::withToken($secret)->timeout($seconds)->acceptJson()->send($method, \mb_rtrim($config->assertString('scraping.browser_service_url'), '/') . $path, ['json' => $payload]);
        if (!$response->successful()) {
            throw new RuntimeException('The browser service request failed.');
        }

        return Typer::assertStringKeyArray(Typer::assertArray($response->json()));
    }
}
