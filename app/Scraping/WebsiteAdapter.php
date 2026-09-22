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
     * Extract in a fresh isolated browser context; always close it.
     */
    public function execute(RecipeDefinition $definition, CollectorConnection|null $connection): ExtractionResult
    {
        $browser = new BrowserService();
        $payload = ['limits' => $definition->toArray()['limits']];
        if ($connection !== null) {
            $payload['storageState'] = $connection->getCredentials();
        }
        $session = $browser->request('POST', '/sessions', $payload);
        $path = '/sessions/' . \rawurlencode(Typer::assertString($session['sessionId']));
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
                $browser->request('DELETE', $path);
            } catch (RuntimeException) {
                // A timed-out service operation already closes its context.
            }
        }
    }
}
