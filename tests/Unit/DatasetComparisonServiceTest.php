<?php

declare(strict_types=1);

use App\Scraping\DatasetComparisonService;

\test('comparisons ignore order and unselected fields but count actual changes', function (): void {
    $service = new DatasetComparisonService();
    $before = [['id' => 'a', 'v' => 1], ['id' => 'b', 'v' => 2]];
    \expect($service->compare($before, [['id' => 'b', 'v' => 2, 'ignored' => 4], ['id' => 'a', 'v' => 1]], ['id'], ['v']))->toBe(['status' => 'compared', 'added' => 0, 'changed' => 0, 'missing' => 0]);
    \expect($service->compare($before, [['id' => 'b', 'v' => 3], ['id' => 'c', 'v' => 4]], ['id'], ['v']))->toBe(['status' => 'compared', 'added' => 1, 'changed' => 1, 'missing' => 1]);
});

\test('ambiguous identity never creates false missing alerts', function (): void {
    $service = new DatasetComparisonService();
    foreach ([[['id' => 'a'], ['id' => 'a']], [['id' => null]], [[]]] as $rows) {
        \expect($service->compare([['id' => 'b']], $rows, ['id'], []))->toBe(['status' => 'invalid_identity', 'added' => 0, 'changed' => 0, 'missing' => 0]);
    }
    \expect($service->compare([], [], [], [])['status'])->toBe('disabled');
});
