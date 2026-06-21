<?php

use App\Support\Waterfall;
use Illuminate\Support\Collection;

it('positions spans proportionally across the trace window', function () {
    $spans = new Collection([
        ['type' => 'request', 'occurred_at' => '2026-06-21 12:00:00.000000', 'duration_us' => 1_000_000, 'payload' => ['method' => 'GET', 'path' => '/x']],
        ['type' => 'query',   'occurred_at' => '2026-06-21 12:00:00.500000', 'duration_us' => 500_000,   'payload' => ['sql' => 'select 1']],
    ]);

    $rows = Waterfall::rows($spans);

    expect($rows)->toHaveCount(2);
    // request spans the full window: left 0, width 100
    expect(round($rows[0]['_left']))->toBe(0.0);
    expect(round($rows[0]['_width']))->toBe(100.0);
    // query starts at 50% and runs to the end: left 50, width 50
    expect(round($rows[1]['_left']))->toBe(50.0);
    expect(round($rows[1]['_width']))->toBe(50.0);
});

it('returns an empty array for no spans', function () {
    expect(Waterfall::rows(new Collection()))->toBe([]);
});

it('labels and colors spans by type', function () {
    expect(Waterfall::label(['type' => 'query', 'payload' => ['sql' => 'select 1']]))->toBe('select 1');
    expect(Waterfall::label(['type' => 'request', 'payload' => ['method' => 'GET', 'path' => '/x']]))->toBe('GET /x');
    expect(Waterfall::color('query'))->toBeString();
});
