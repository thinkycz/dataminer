<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Thinkycz\LaravelCore\Support\Config;

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$request = Request::capture();
$app->booted(static function () use ($request): void {
    if (Config::inject()->assertString('app.env') !== 'testing' ||
        Config::inject()->assertString('database.default') !== 'sqlite' ||
        Config::inject()->assertString('session.driver') !== 'cookie') {
        throw new RuntimeException('The browser test server requires testing mode, SQLite, and persistent cookie sessions.');
    }

    $database = Config::inject()->assertString('database.connections.sqlite.database');
    if (\preg_match('#^/private/tmp/dataminer-e2e-[0-9a-f-]+\\.sqlite$#', $database) !== 1 || $database !== \realpath($database)) {
        throw new RuntimeException('The browser test server requires a dedicated temporary SQLite database.');
    }

    if ($request->isMethod('POST') && $request->path() === 'register') {
        $email = $request->input('email');
        $fixtureEmail = $request->header('X-E2E-Pilot-Email');
        if (\is_string($email) && \is_string($fixtureEmail) && \hash_equals($email, $fixtureEmail)) {
            Config::inject()->assign('pilot.registration_emails', [$email]);
        }
    }

    Queue::fake();
});
$path = \parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);
if (\is_string($path) && $path !== '/' && \is_file(__DIR__ . '/../../public' . $path)) {
    return false;
}
$app->handleRequest($request);
