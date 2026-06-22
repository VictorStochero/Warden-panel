<?php

use App\Alerting\AlertComposer;

it('picks the first non-vendor frame as where-to-fix', function () {
    $stack = [
        ['class' => null, 'function' => null, 'file' => 'vendor/laravel/framework/src/X.php', 'line' => 10],
        ['class' => 'App\\Foo', 'function' => 'bar', 'file' => 'app/Services/Foo.php', 'line' => 42],
    ];

    expect(AlertComposer::topAppFrame($stack))->toBe('app/Services/Foo.php:42');
});

it('falls back to the throw site when every frame is in vendor', function () {
    $stack = [
        ['file' => 'vendor/laravel/framework/src/A.php', 'line' => 5],
        ['file' => 'vendor/symfony/console/B.php', 'line' => 9],
    ];

    expect(AlertComposer::topAppFrame($stack))->toBe('vendor/laravel/framework/src/A.php:5');
});

it('returns null for an empty stack', function () {
    expect(AlertComposer::topAppFrame(null))->toBeNull()
        ->and(AlertComposer::topAppFrame([]))->toBeNull();
});
