<?php

use App\Models\User;
use VictorStochero\Warden\Models\Project;

it('uses the Warden ink palette for the page background, not starter-kit zinc', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    test()->artisan('warden:project', ['name' => 'Theme App'])->assertSuccessful();
    $slug = Project::where('slug', 'theme-app')->firstOrFail()->slug;

    $html = $this->actingAs($admin)->get("/projects/{$slug}")->assertOk()->getContent();

    expect($html)->toContain('bg-ink-950')
        ->and($html)->not->toContain('dark:bg-zinc-800');
});

it('renders the throughput KPI value in white, not brand blue', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    test()->artisan('warden:project', ['name' => 'Theme App'])->assertSuccessful();
    $slug = Project::where('slug', 'theme-app')->firstOrFail()->slug;

    $html = $this->actingAs($admin)->get("/projects/{$slug}")->assertOk()->getContent();

    // The Throughput card (zero traffic) should carry the white tone class.
    expect($html)->toContain('text-white');
});
