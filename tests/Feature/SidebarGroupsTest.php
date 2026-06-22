<?php

use App\Models\User;
use VictorStochero\Warden\Models\Project;

it('groups the project navigation into named sections', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    test()->artisan('warden:project', ['name' => 'Nav App'])->assertSuccessful();
    $slug = Project::where('slug', 'nav-app')->firstOrFail()->slug;

    $this->actingAs($admin)->get("/projects/{$slug}")
        ->assertSee('Performance')
        ->assertSee('Reliability')
        ->assertSee('Diagnostics')
        ->assertSee('Database')
        ->assertSee('Incidents');
});
