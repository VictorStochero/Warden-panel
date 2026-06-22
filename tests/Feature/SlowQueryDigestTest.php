<?php

use Illuminate\Support\Facades\Http;

it('runs the slow-query digest command without error', function () {
    Http::fake();
    test()->artisan('warden:project', ['name' => 'Digest App'])->assertSuccessful();

    $this->artisan('panel:slow-query-digest')->assertExitCode(0);
});
