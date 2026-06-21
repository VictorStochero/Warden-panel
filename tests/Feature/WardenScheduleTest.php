<?php

use Illuminate\Console\Scheduling\Schedule;

it('auto-registers the warden parent pipeline on the scheduler', function () {
    $events = app(Schedule::class)->events();
    $commands = collect($events)->map(fn ($e) => $e->command ?? '')->implode(' ');

    expect($commands)->toContain('warden:aggregate');
    expect($commands)->toContain('warden:evaluate');
});
