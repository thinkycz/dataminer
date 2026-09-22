<?php

declare(strict_types=1);

namespace App\Scraping;

use InvalidArgumentException;

class DatasetComparisonService
{
    /** Compare normalized records independently of their ordering.
     * @param iterable<array<string, mixed>> $previous
     * @param iterable<array<string, mixed>> $current
     * @param list<string> $identity
     * @param list<string> $fields
     *
     * @return array{status:string,added:int,changed:int,missing:int}
     */
    public function compare(iterable $previous, iterable $current, array $identity, array $fields): array
    {
        if ($identity === []) {
            return ['status' => 'disabled', 'added' => 0, 'changed' => 0, 'missing' => 0];
        }
        try {
            $before = $this->index($previous, $identity, $fields);
            $after = $this->index($current, $identity, $fields);
        } catch (InvalidArgumentException) {
            return ['status' => 'invalid_identity', 'added' => 0, 'changed' => 0, 'missing' => 0];
        }
        $changed = 0;
        foreach ($after as $key => $value) {
            if (isset($before[$key]) && $before[$key] !== $value) {
                ++$changed;
            }
        }

        return ['status' => 'compared', 'added' => \count(\array_diff_key($after, $before)), 'changed' => $changed, 'missing' => \count(\array_diff_key($before, $after))];
    }

    /** Index typed scalar composite identities and comparison values.
     * @param iterable<array<string, mixed>> $rows
     * @param list<string> $identity
     * @param list<string> $fields
     *
     * @return array<string, string>
     */
    private function index(iterable $rows, array $identity, array $fields): array
    {
        $result = [];
        foreach ($rows as $row) {
            $parts = [];
            foreach ($identity as $key) {
                if (!isset($row[$key]) || $row[$key] === '' || !\is_scalar($row[$key])) {
                    throw new InvalidArgumentException('Missing identity.');
                }
                $parts[] = $row[$key];
            }
            $key = \json_encode($parts, \JSON_THROW_ON_ERROR);
            if (isset($result[$key])) {
                throw new InvalidArgumentException('Duplicate identity.');
            }
            $values = [];
            foreach ($fields as $field) {
                $values[$field] = $row[$field] ?? null;
            }
            $result[$key] = \json_encode($values, \JSON_THROW_ON_ERROR);
        }

        return $result;
    }
}
