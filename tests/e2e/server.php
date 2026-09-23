<?php

declare(strict_types=1);

use App\Jobs\ExecuteScrapeRunJob;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Thinkycz\LaravelCore\Support\Config;

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$request = Request::capture();
$app->booted(static function () use ($request): void {
    if (Config::inject()->assertString('app.env') !== 'testing' ||
        Config::inject()->assertString('database.default') !== 'sqlite' ||
        Config::inject()->assertString('session.driver') !== 'cookie' ||
        Config::inject()->assertString('mail.default') !== 'array') {
        throw new RuntimeException('The browser test server requires testing mode, SQLite, cookie sessions, and an in-memory mail transport.');
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

    $sources = [
        'https://1.1.1.1/e2e/catalog.json' => '{"data":{"items":[{"details":{"title":"Notebook"},"price":12},{"details":{"title":"Pencil"},"price":2}]}}',
        'https://1.1.1.1/e2e/catalog.csv' => "\"Product name\",price\nNotebook,12\nPencil,2\n",
        'https://1.1.1.1/e2e/catalog.xml' => '<catalog xmlns="urn:catalog"><product code="A"><details><title>Notebook</title></details><price>12</price></product><product code="B"><details><title>Pencil</title></details><price>2</price></product></catalog>',
    ];
    Config::inject()->assign('queue.default', 'sync');
    $realJobs = [SendQueuedNotifications::class];
    if ($request->isMethod('POST') && \preg_match('#^collectors/([0-9]+)/(?:sample|preview|runs/start)$#', $request->path(), $matches) === 1) {
        Http::fake(\array_map(static fn(string $source) => Http::response($source), $sources));
        Http::preventStrayRequests();
        $collector = Recipe::query()->find((int) $matches[1]);
        if ($collector instanceof Recipe) {
            $websiteFixture = $collector->getStartUrl() === 'https://1.1.1.1/e2e/website';
            if ($websiteFixture) {
                Http::allowStrayRequests([Config::inject()->assertString('scraping.browser_service_url') . '/*']);
            }
            if ($websiteFixture || \array_key_exists($collector->getStartUrl(), $sources)) {
                $realJobs[] = ExecuteScrapeRunJob::class;
            }
        }
    }
    Queue::fake()->except($realJobs);
    Event::listen(MessageSent::class, static function (MessageSent $event) use ($database): void {
        $message = $event->sent->getOriginalMessage();
        if (!$message instanceof Email) {
            throw new RuntimeException('Expected a rendered email message.');
        }
        \file_put_contents($database . '.mail.jsonl', \json_encode([
            'to' => \array_map(static fn(Address $recipient): string => $recipient->getAddress(), $message->getTo()),
            'subject' => $message->getSubject(),
            'html' => $message->getHtmlBody(),
        ], \JSON_THROW_ON_ERROR) . "\n", \FILE_APPEND | \LOCK_EX);
        \chmod($database . '.mail.jsonl', 0o600);
    });
});
$path = \parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);
if (\is_string($path) && $path !== '/' && \is_file(__DIR__ . '/../../public' . $path)) {
    return false;
}
$app->handleRequest($request);
