<?php

use App\Models\User;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Models\Setting;

it('shows the new-version banner when a newer release is cached', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    test()->artisan('warden:project', ['name' => 'Ver App'])->assertSuccessful();
    $slug = Project::where('slug', 'ver-app')->firstOrFail()->slug;

    Setting::write('version_check', ['current' => '0.3.5', 'latest' => '9.9.9']);

    $this->actingAs($admin)->get("/projects/{$slug}")
        ->assertOk()
        ->assertSee('is available')
        ->assertSee('9.9.9');
});

it('does not show the banner without a newer release', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    test()->artisan('warden:project', ['name' => 'Ver App'])->assertSuccessful();
    $slug = Project::where('slug', 'ver-app')->firstOrFail()->slug;

    $this->actingAs($admin)->get("/projects/{$slug}")
        ->assertOk()
        ->assertDontSee('is available');
});
