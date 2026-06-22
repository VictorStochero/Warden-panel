<?php

use Illuminate\Support\Facades\Blade;

it('renders the bars component with data and an empty state', function () {
    $withData = Blade::render('<x-panel.bars :values="$v" />', ['v' => [1, 4, 2]]);
    expect($withData)->toContain('background:');

    $empty = Blade::render('<x-panel.bars :values="$v" />', ['v' => []]);
    expect($empty)->toContain('No data');
});

it('renders the chart component as an svg polyline with data', function () {
    $withData = Blade::render('<x-panel.chart :values="$v" />', ['v' => [3, 1, 5, 2]]);
    expect($withData)->toContain('<svg')->toContain('<polyline');

    $empty = Blade::render('<x-panel.chart :values="$v" />', ['v' => []]);
    expect($empty)->toContain('No data');
});
