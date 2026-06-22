<?php

use App\Support\Window;

it('maps a custom minute span to the nearest preset range', function () {
    expect(Window::nearestPreset(10))->toBe('15m')
        ->and(Window::nearestPreset(50))->toBe('1h')
        ->and(Window::nearestPreset(300))->toBe('6h')
        ->and(Window::nearestPreset(1500))->toBe('24h')
        ->and(Window::nearestPreset(9000))->toBe('7d')
        ->and(Window::nearestPreset(50000))->toBe('30d');
});
