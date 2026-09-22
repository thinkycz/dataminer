<?php

declare(strict_types=1);

namespace App\Scraping;

use App\Models\CollectorConnection;
use App\Models\CollectorSchedule;
use App\Models\RecipeVersion;
use App\Models\User;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class CollectorConnectionService
{
    /**
     * Normalize the exact origin used to scope saved credentials.
     */
    public function origin(string $url): string
    {
        $parts = \parse_url($url);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host']) || !\in_array($parts['scheme'], ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('A valid HTTP origin is required.');
        }
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);

        return \mb_strtolower($parts['scheme'] . '://' . $parts['host']) . ':' . $port;
    }

    /**
     * Load only credentials owned by this user and matching this source origin.
     */
    public function forDefinition(RecipeDefinition $definition, User $user): CollectorConnection|null
    {
        if ($definition->getConnectionId() === null) {
            return null;
        }
        $connection = CollectorConnection::query()->where('user_id', $user->getKey())->findOrFail($definition->getConnectionId());
        if ($connection->getOrigin() !== $this->origin($definition->getUrl()) || $connection->getStatus() !== 'ready') {
            throw new InvalidArgumentException('The connection needs to be reconnected for this origin.');
        }

        return $connection;
    }

    /**
     * Pause all schedules that share an expired session.
     */
    public function expire(int $connectionId, int|null $expectedRevision = null): void
    {
        Resolver::resolveDatabaseManager()->transaction(static function () use ($connectionId, $expectedRevision): void {
            $connection = CollectorConnection::query()->whereKey($connectionId)->lockForUpdate()->first();
            if (!$connection instanceof CollectorConnection || ($expectedRevision !== null && $expectedRevision !== $connection->getStateRevision())) {
                return;
            }
            $connection->update(['status' => 'expired', 'verified_at' => null]);
            foreach (CollectorSchedule::query()->where('status', CollectorSchedule::STATUS_ACTIVE)->cursor() as $schedule) {
                $version = $schedule->recipeVersion()->getResults();
                if ($version instanceof RecipeVersion && $connectionId === $version->getDefinition()?->getConnectionId()) {
                    $schedule->update(['status' => CollectorSchedule::STATUS_PAUSED, 'next_run_at' => null]);
                }
            }
        }, 3);
    }

    /**
     * Construct credentials only for a previously authorized connection.
     *
     * @return array<string, string>
     */
    public function headers(CollectorConnection|null $connection): array
    {
        if ($connection === null || $connection->getKind() === 'browser') {
            return [];
        }
        $credentials = $connection->getCredentials();

        return match ($connection->getKind()) {
            'bearer' => ['Authorization' => 'Bearer ' . Typer::assertString($credentials['token'])],
            'basic' => ['Authorization' => 'Basic ' . \base64_encode(Typer::assertString($credentials['username']) . ':' . Typer::assertString($credentials['password']))],
            'api_key' => [Typer::assertString($credentials['header']) => Typer::assertString($credentials['token'])],
            default => throw new InvalidArgumentException('Unsupported connection kind.'),
        };
    }
}
