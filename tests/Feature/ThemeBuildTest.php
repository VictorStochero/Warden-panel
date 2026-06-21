<?php

it('builds the themed stylesheet', function () {
    expect(file_exists(public_path('build/manifest.json')))->toBeTrue();
    expect(glob(public_path('fonts/archivo-*.woff2')))->not->toBeEmpty();
});
