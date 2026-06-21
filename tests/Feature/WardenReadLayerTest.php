<?php

use VictorStochero\Warden\Dashboard\DashboardRepository;

it('returns the documented overview shape after demo seeding', function () {
    // Seed a project into wdn_projects so the read layer has fleet data to return.
    // warden:project runs in parent mode (the current test mode) and is the
    // canonical way to mint a project row before child telemetry arrives.
    $this->artisan('warden:project', ['name' => 'Demo Fleet'])->assertSuccessful();

    // Switch to child mode with a dummy parent URL + token so warden:demo can
    // exercise the capture pipeline (buffer → outbox flush) without failing its
    // mode/config guards. The outbox write is harmless in :memory: SQLite.
    config([
        'warden.mode' => 'child',
        'warden.child.parent_url' => 'http://localhost',
        'warden.child.token' => 'demo-token',
    ]);

    $this->artisan('warden:demo')->assertSuccessful();

    $overview = app(DashboardRepository::class)->overview();

    expect($overview)->toHaveKeys(['projects', 'open_issues', 'open_incidents', 'throughput']);
    expect($overview['projects'])->not->toBeEmpty();
});
