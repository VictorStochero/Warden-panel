<?php

use App\Models\User;
use VictorStochero\Warden\Models\Project;
use Illuminate\Support\Str;

function seedProjectForRender(string $name): string
{
    test()->artisan('warden:project', ['name' => $name])->assertSuccessful();
    return Project::where('slug', Str::slug($name))->firstOrFail()->slug;
}

it('renders authenticated panel pages with the full layout for an admin', function (string $path) {
    $admin = User::factory()->create(['is_admin' => true]);
    $slug = seedProjectForRender('Render Check');

    $this->actingAs($admin)
        ->get(str_replace('{slug}', $slug, $path))
        ->assertOk();
})->with([
    '/',
    '/admin/projects',
    '/projects/{slug}',
    '/projects/{slug}/database',
    '/projects/{slug}/jobs',
    '/projects/{slug}/http',
    '/projects/{slug}/schedule',
    '/projects/{slug}/uptime',
    '/projects/{slug}/traces',
    '/projects/{slug}/issues',
    '/projects/{slug}/incidents',
    '/projects/{slug}/logs',
    '/projects/{slug}/events',
]);
