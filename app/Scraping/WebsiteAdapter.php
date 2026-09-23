<?php

declare(strict_types=1);

namespace App\Scraping;

use App\Models\CollectorConnection;
use InvalidArgumentException;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Typer;

class WebsiteAdapter
{
    /**
     * Reuse an owned live browser when available; close only disposable contexts.
     */
    public function execute(RecipeDefinition $definition, CollectorConnection|null $connection): ExtractionResult
    {
        $browser = new BrowserService();
        $payload = ['limits' => $definition->toArray()['limits']];
        if ($connection !== null) {
            $payload['storageState'] = $connection->getCredentials();
        }
        $liveSessionId = $payload['storageState']['liveSessionId'] ?? null;
        $sessionId = $liveSessionId === null
            ? Typer::assertString($browser->request('POST', '/sessions', $payload)['sessionId'])
            : Typer::assertString($liveSessionId);
        $path = '/sessions/' . \rawurlencode($sessionId);
        try {
            $result = $browser->request('POST', $path . '/extract', ['definition' => $definition->toArray()]);
            $data = $definition->toArray();
            $details = Typer::assertArray($data['website']['detail_fields'] ?? []);
            $data['fields'] = [...$data['fields'], ...$details];
            $data['website']['detail_fields'] = [];
            $fields = RecipeDefinition::fromArray($data)->toArray()['fields'];
            $complete = Typer::assertBool($result['complete']);
            $diagnostics = \array_values(\array_map(Typer::assertString(...), Typer::assertArray($result['diagnostics'])));
            $rows = [];
            foreach (Typer::assertArray($result['rows']) as $row) {
                $canonical = [];
                foreach (Typer::assertStringKeyArray(Typer::assertArray($row)) as $key => $value) {
                    if ($value !== null && !\is_scalar($value)) {
                        throw new RuntimeException('Browser returned a non-scalar value.');
                    }
                    $canonical[$key] = $value;
                }
                $input = [];
                $mappings = [];
                foreach ($fields as $index => $field) {
                    $input['field_' . $index] = $canonical[$field['name']] ?? null;
                    $mappings[] = [...$field, 'path' => 'field_' . $index];
                }
                try {
                    $rows[] = (new DefinitionRowMapper())->map($input, $mappings, $definition->getUrl());
                } catch (InvalidArgumentException $error) {
                    $complete = false;
                    $diagnostics[] = $error->getMessage();
                }
            }

            return new ExtractionResult($rows, $complete, $diagnostics, Typer::assertInt($result['bytes']), Typer::assertInt($result['requests']), Typer::assertInt($result['pages']));
        } finally {
            try {
                if ($liveSessionId === null) {
                    $browser->request('DELETE', $path);
                }
            } catch (RuntimeException) {
                // A timed-out service operation already closes its context.
            }
        }
    }
}
