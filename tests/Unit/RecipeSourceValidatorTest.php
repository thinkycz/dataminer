<?php

declare(strict_types=1);

use App\Scraping\NetworkGuard;
use App\Scraping\RecipeSourceValidator;

\test('valid application recipe contract is accepted', function (): void {
    (new RecipeSourceValidator())->validate('export async function scrape(context) { context.emit({ name: "ok" }); }');
    \expect(true)->toBeTrue();
});

\test('missing interface forbidden capabilities and invalid syntax are rejected', function (string $source): void {
    \expect(fn() => (new RecipeSourceValidator())->validate($source))->toThrow(InvalidArgumentException::class);
})->with([
    'missing interface' => 'export const value = 1;',
    'filesystem escape' => 'export async function scrape(context) { process.env.SECRET; }',
    'dynamic code' => 'export async function scrape(context) { eval("1"); }',
    'syntax' => 'export async function scrape(context) {',
]);

\test('network guard blocks local private reserved and non-http targets', function (string $url): void {
    \expect(fn() => (new NetworkGuard())->assertPublicHttpUrl($url))->toThrow(InvalidArgumentException::class);
})->with([
    'http://localhost',
    'http://127.0.0.1',
    'http://10.0.0.1',
    'file:///etc/passwd',
    'http://169.254.169.254/latest/meta-data',
]);
