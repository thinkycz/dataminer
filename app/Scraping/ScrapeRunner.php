<?php

declare(strict_types=1);

namespace App\Scraping;

use App\Models\CollectorConnection;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\ScrapeRow;
use App\Models\ScrapeRun;
use App\Models\ScrapeRunColumn;
use App\Models\User;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

class ScrapeRunner
{
    /**
     * Execute a run and ingest its NDJSON protocol.
     */
    public function execute(ScrapeRun $run): void
    {
        if (ScrapeRun::query()->whereKey($run->getId())->where('status', ScrapeRun::STATUS_QUEUED)->update([
            'status' => ScrapeRun::STATUS_RUNNING, 'started_at' => \now(), 'heartbeat_at' => \now(), 'progress' => 1,
        ]) !== 1) {
            return;
        }

        $version = $run->recipeVersion()->getResults();
        $recipe = $run->recipe()->getResults();

        if (!$version instanceof RecipeVersion || !$recipe instanceof Recipe) {
            throw new RuntimeException('Run relationships are unavailable.');
        }

        if ($version->getDefinitionFormat() !== 'legacy_js') {
            $this->executeDefinition($run, $version, $recipe);

            return;
        }

        $directory = \sys_get_temp_dir() . '/dataminer-' . $run->getId();
        $sourcePath = $directory . '/recipe.mjs';
        $filesystem = Resolver::resolveFilesystem();
        try {
            (new RecipeSourceValidator())->validate($version->getSource());
            if (!\hash_equals($version->getChecksum(), \hash('sha256', $version->getSource()))) {
                throw new RuntimeException('Recipe source checksum mismatch.');
            }
            (new NetworkGuard())->assertPublicHttpUrl($recipe->getStartUrl());
            if ($run->fresh()?->getStatus() !== ScrapeRun::STATUS_RUNNING) {
                return;
            }
            $filesystem->makeDirectory($directory, 0o700, true, true);
            $filesystem->put($sourcePath, $version->getSource());
            $summary = $this->runProcess($run, $recipe, $sourcePath);
            $this->complete($run, $version, $recipe, $summary);
        } catch (Throwable $throwable) {
            $fresh = $run->fresh();
            $cancelled = $fresh instanceof ScrapeRun && $fresh->getStatus() === ScrapeRun::STATUS_CANCELLED;
            $run->update([
                'status' => $cancelled ? ScrapeRun::STATUS_CANCELLED : ScrapeRun::STATUS_FAILED,
                'error' => $cancelled ? null : $this->sanitize($throwable->getMessage()),
                'finished_at' => \now(),
            ]);

            if ($run->getKind() === ScrapeRun::KIND_TEST) {
                $version->update(['status' => RecipeVersion::STATUS_DRAFT]);
                if ($recipe->getActiveVersionId() === null) {
                    $recipe->update(['status' => Recipe::STATUS_FAILED]);
                }
            }

            if (!$cancelled) {
                throw $throwable;
            }
        } finally {
            $filesystem->deleteDirectory($directory);
        }
    }

    /**
     * Execute configuration without loading source code or an AI provider.
     */
    private function executeDefinition(ScrapeRun $run, RecipeVersion $version, Recipe $recipe): void
    {
        try {
            $definition = $version->getDefinition();
            if ($definition === null || !\hash_equals($version->getChecksum(), $definition->checksum())) {
                throw new RuntimeException('Definition checksum mismatch.');
            }
            $user = $recipe->user()->getResults();
            if (!$user instanceof User) {
                throw new RuntimeException('Collector owner is unavailable.');
            }
            $connections = new CollectorConnectionService();
            $connection = $connections->forDefinition($definition, $user);
            if ($connection !== null && $connection->getStateRevision() !== $run->getConnectionRevision()) {
                throw new RuntimeException('Connection changed; preview the current credentials again.');
            }
            $bounded = $definition->toArray();
            $definitionLimits = Typer::assertStringKeyArray(Typer::assertArray($bounded['limits']));
            foreach ($run->getLimits() as $key => $maximum) {
                $definitionLimits[$key] = \min(Typer::assertInt($definitionLimits[$key]), Typer::assertInt($maximum));
            }
            $bounded['limits'] = $definitionLimits;
            $boundedDefinition = RecipeDefinition::fromArray($bounded);
            $result = $definition->getSourceType() === 'website'
                ? (new WebsiteAdapter())->execute($boundedDefinition, $connection)
                : (new FeedAdapter())->execute($boundedDefinition, $connections->headers($connection));
            $fresh = $run->fresh();
            if (!$fresh instanceof ScrapeRun || $fresh->getStatus() === ScrapeRun::STATUS_CANCELLED) {
                return;
            }
            Resolver::resolveDatabaseManager()->transaction(function () use ($run, $result): void {
                foreach ($result->rows as $index => $row) {
                    $this->assertCanonicalRow($row);
                    ScrapeRow::create(['run_id' => $run->getId(), 'sequence' => $index + 1, 'payload' => $row]);
                }
                $run->update(['complete' => $result->complete, 'diagnostics' => $result->diagnostics, 'heartbeat_at' => \now()]);
            });
            $boundedSample = $run->getKind() === ScrapeRun::KIND_TEST && $result->rows !== [] && $result->diagnostics !== [] &&
                \array_diff($result->diagnostics, ['Row limit reached.', 'row_limit', 'page_limit']) === [];
            if (!$result->complete && !$boundedSample) {
                throw new RuntimeException('Extraction is incomplete: ' . \implode('; ', $result->diagnostics));
            }
            $this->complete($run, $version, $recipe, ['rows' => \count($result->rows), 'bytes' => $result->bytes, 'requests' => $result->requests, 'pages' => $result->pages]);
        } catch (Throwable $error) {
            if ($run->fresh()?->getStatus() === ScrapeRun::STATUS_CANCELLED) {
                return;
            }
            $connectionId = $version->getDefinition()?->getConnectionId();
            if ($connectionId !== null && (\str_contains($error->getMessage(), 'auth_expired') || \preg_match('/HTTP (401|403)/', $error->getMessage()) === 1)) {
                (new CollectorConnectionService())->expire($connectionId, $run->getConnectionRevision());
            }
            $run->update(['status' => ScrapeRun::STATUS_FAILED, 'error' => $this->sanitize($error->getMessage()), 'finished_at' => \now(), 'complete' => false]);
            if ($run->getKind() === ScrapeRun::KIND_TEST) {
                $version->update(['status' => RecipeVersion::STATUS_DRAFT]);
            }
            (new RunOutcomeService())->record($run);
            throw $error;
        }
    }

    /**
     * Run the isolated Node harness and consume stdout incrementally.
     *
     * @return array<string, int>
     */
    private function runProcess(ScrapeRun $run, Recipe $recipe, string $sourcePath): array
    {
        $config = Config::inject();
        $limits = $run->getLimits();
        $input = ['start_url' => $recipe->getStartUrl(), 'kind' => $run->getKind()];
        $environment = [];
        foreach ([...\array_keys(\getenv()), ...\array_keys($_ENV)] as $name) {
            if (\is_string($name)) {
                $environment[$name] = false;
            }
        }
        $process = new Process([
            $config->assertString('scraping.node_binary'),
            Resolver::resolveApp()->resourcePath('scraping/runner.mjs'),
            $sourcePath,
            \mb_rtrim(\strtr(\base64_encode(Typer::assertString(\json_encode($input))), '+/', '-_'), '='),
            \mb_rtrim(\strtr(\base64_encode(Typer::assertString(\json_encode($limits))), '+/', '-_'), '='),
            \base64_encode(Typer::assertString(\json_encode([
                'headless' => $config->assertBool('scraping.headless'),
                'channel' => $config->assertNullableString('scraping.browser_channel'),
            ]))),
        ], Resolver::resolveApp()->basePath(), [
            ...$environment,
            'PATH' => '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin',
            'NODE_ENV' => 'production',
        ], null, Typer::assertInt($limits['seconds']) + 5);

        $buffer = '';
        $rows = [];
        $logs = [];
        $summary = null;

        try {
            $process->run(function (string $type, string $data) use (&$buffer, &$rows, &$logs, &$summary, $run): void {
                if ($type === Process::ERR) {
                    $logs[] = $this->sanitize($data);

                    return;
                }

                $buffer .= $data;

                while (($position = \mb_strpos($buffer, "\n")) !== false) {
                    $line = \mb_substr($buffer, 0, $position);
                    $buffer = \mb_substr($buffer, $position + 1);

                    if ($line === '') {
                        continue;
                    }

                    $message = \json_decode($line, true);

                    if (!\is_array($message) || !isset($message['type'])) {
                        throw new RuntimeException('Malformed NDJSON protocol message.');
                    }

                    $fresh = $run->fresh();
                    if ($fresh instanceof ScrapeRun && $fresh->getStatus() === ScrapeRun::STATUS_CANCELLED) {
                        throw new RuntimeException('Run cancelled.');
                    }

                    if ($message['type'] === 'row') {
                        $payload = Typer::assertStringKeyArray(Typer::assertArray($message['payload'] ?? null));
                        $this->assertCanonicalRow($payload);
                        $rows[] = [
                            'run_id' => $run->getId(),
                            'sequence' => Typer::assertInt($message['sequence'] ?? null),
                            'payload' => Typer::assertString(\json_encode($payload)),
                            'created_at' => \now(),
                            'updated_at' => \now(),
                        ];

                        if (\count($rows) >= 250) {
                            ScrapeRow::query()->insert($rows);
                            $rows = [];
                        }
                    } elseif ($message['type'] === 'log') {
                        $logs[] = $this->sanitize(Typer::assertString($message['message'] ?? ''));
                    } elseif ($message['type'] === 'progress') {
                        $run->update(['progress' => \min(99, Typer::assertInt($message['progress'] ?? 1))]);
                    } elseif ($message['type'] === 'summary') {
                        $summary = [
                            'rows' => Typer::assertInt($message['rows'] ?? null),
                            'bytes' => Typer::assertInt($message['bytes'] ?? null),
                            'requests' => Typer::assertInt($message['requests'] ?? null),
                            'pages' => Typer::assertInt($message['pages'] ?? null),
                        ];
                    } else {
                        throw new RuntimeException('Unknown NDJSON protocol message.');
                    }
                }
            });
        } catch (Throwable $throwable) {
            if ($process->isRunning()) {
                $process->stop(1, \SIGKILL);
            }

            throw $throwable;
        }

        if ($rows !== []) {
            ScrapeRow::query()->insert($rows);
        }

        $run->update(['logs' => \implode("\n", \array_slice($logs, -500))]);

        if (!$process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput();
            throw new RuntimeException($this->sanitize($errorOutput === '' ? 'Browser runner failed.' : $errorOutput));
        }

        if ($buffer !== '' || !\is_array($summary)) {
            throw new RuntimeException('Browser runner ended without a valid terminal summary.');
        }

        return $summary;
    }

    /** Atomically publish only a run that has not been cancelled or recovered.
     * @param array<string, int> $summary
     */
    private function complete(ScrapeRun $run, RecipeVersion $version, Recipe $recipe, array $summary): void
    {
        Resolver::resolveDatabaseManager()->transaction(function () use ($run, $version, $recipe, $summary): void {
            $current = ScrapeRun::query()->whereKey($run->getId())->lockForUpdate()->firstOrFail();
            if ($current->getStatus() !== ScrapeRun::STATUS_RUNNING) {
                return;
            }
            $this->publish($run, $version, $recipe, $summary);
        });
    }

    /**
     * Finish ingestion, column detection, and private artifact creation.
     *
     * @param array<string, int> $summary
     */
    private function publish(ScrapeRun $run, RecipeVersion $version, Recipe $recipe, array $summary): void
    {
        if ($run->getKind() === ScrapeRun::KIND_TEST && $summary['rows'] === 0) {
            throw new RuntimeException('The test collected no rows. Check the page access and product selectors before approving this version.');
        }

        $this->detectColumns($run);
        [$jsonPath, $csvPath, $disk] = $this->writeArtifacts($run);

        $run->update([
            'status' => ScrapeRun::STATUS_COMPLETED,
            'progress' => 100,
            'row_count' => $summary['rows'],
            'byte_count' => $summary['bytes'],
            'request_count' => $summary['requests'],
            'artifact_disk' => $disk,
            'json_path' => $jsonPath,
            'csv_path' => $csvPath,
            'finished_at' => \now(),
        ]);

        (new RunOutcomeService())->record($run);

        Recipe::query()->whereKey($recipe->getKey())->lockForUpdate()->firstOrFail();
        $version = RecipeVersion::query()->whereKey($version->getKey())->lockForUpdate()->firstOrFail();
        if ($run->getKind() === ScrapeRun::KIND_TEST && $version->getStatus() === RecipeVersion::STATUS_TESTING) {
            $version->update(['status' => RecipeVersion::STATUS_TESTED, 'test_summary' => $summary]);
            $connectionId = $version->getDefinition()?->getConnectionId();
            if ($connectionId !== null) {
                CollectorConnection::query()->whereKey($connectionId)->where('status', 'ready')->where('state_revision', $run->getConnectionRevision())->update(['verified_at' => \now()]);
            }
            if ($recipe->getActiveVersionId() === null) {
                $recipe->update(['status' => Recipe::STATUS_PENDING_APPROVAL]);
            }
        }
    }

    /**
     * Detect stable column metadata across the ingested dataset.
     */
    private function detectColumns(ScrapeRun $run): void
    {
        $columns = [];
        foreach ($run->rows()->getQuery()->orderBy('sequence')->cursor() as $row) {
            foreach ($row->getPayload() as $key => $value) {
                if (!isset($columns[$key])) {
                    $columns[$key] = $this->scalarType($value);
                } elseif ($columns[$key] !== $this->scalarType($value) && $value !== null) {
                    $columns[$key] = 'string';
                }
            }
        }

        $position = 0;
        foreach ($columns as $key => $type) {
            ScrapeRunColumn::create([
                'run_id' => $run->getId(),
                'key' => $key,
                'label' => \ucfirst(\str_replace(['_', '-'], ' ', $key)),
                'type' => $type,
                'position' => $position++,
            ]);
        }
    }

    /**
     * Write streaming JSON and CSV artifacts.
     *
     * @return array{string, string, string}
     */
    private function writeArtifacts(ScrapeRun $run): array
    {
        $diskName = Config::inject()->assertString('scraping.artifact_disk');
        $disk = Resolver::resolveFilesystemManager()->disk($diskName);
        $directory = 'scrape-runs/' . $run->getId();
        $jsonPath = $directory . '/dataset.json';
        $csvPath = $directory . '/dataset.csv';
        $json = \tmpfile();
        $csv = \tmpfile();

        if ($json === false || $csv === false) {
            throw new RuntimeException('Unable to create artifact streams.');
        }

        $keys = $run->columns()->getQuery()->orderBy('position')->get()
            ->map(static fn(ScrapeRunColumn $column): string => $column->getColumnKey())->all();
        \fwrite($json, '[');
        \fputcsv($csv, $keys, escape: '');
        $first = true;

        foreach ($run->rows()->getQuery()->orderBy('sequence')->cursor() as $row) {
            $payload = $row->getPayload();
            \fwrite($json, ($first ? '' : ',') . Typer::assertString(\json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)));
            $first = false;
            \fputcsv($csv, \array_map(fn(string $key): string => $this->csvValue($payload[$key] ?? null), $keys), escape: '');
        }

        \fwrite($json, ']');
        \rewind($json);
        \rewind($csv);
        $disk->writeStream($jsonPath, $json);
        $disk->writeStream($csvPath, $csv);
        \fclose($json);
        \fclose($csv);

        return [$jsonPath, $csvPath, $diskName];
    }

    /**
     * Assert that all payload values are canonical scalars.
     *
     * @param array<string, mixed> $payload
     */
    private function assertCanonicalRow(array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (\preg_match('/^[A-Za-z0-9_.-]{1,120}$/', $key) !== 1) {
                throw new InvalidArgumentException('Invalid row key.');
            }
            if ($value !== null && !\is_string($value) && !\is_int($value) && !\is_float($value) && !\is_bool($value)) {
                throw new InvalidArgumentException('Row values must be scalar.');
            }
        }
    }

    /**
     * Determine a scalar value type.
     */
    private function scalarType(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            \is_bool($value) => 'boolean',
            \is_int($value), \is_float($value) => 'number',
            default => 'string',
        };
    }

    /**
     * Make a spreadsheet-safe CSV cell without altering canonical JSON.
     */
    private function csvValue(mixed $value): string
    {
        $cell = match (true) {
            $value === null => '',
            $value === true => 'true',
            $value === false => 'false',
            \is_string($value) => $value,
            \is_int($value), \is_float($value) => (string) $value,
            default => throw new InvalidArgumentException('CSV values must be scalar.'),
        };

        return \preg_match('/^(?:[=+\\-@\\t\\r\\n]|\\s+[=+\\-@])/u', $cell) === 1 ? '\'' . $cell : $cell;
    }

    /**
     * Sanitize runtime output for durable logs and repair prompts.
     */
    private function sanitize(string $message): string
    {
        return \mb_substr(\preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/u', '', $message) ?? '', 0, 4_000);
    }
}
