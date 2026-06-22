<?php

use App\Models\User;
use VictorStochero\Warden\Models\Project;

it('translates section content (KPI + table headers) to Portuguese', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    test()->artisan('warden:project', ['name' => 'Loc App'])->assertSuccessful();
    $slug = Project::where('slug', 'loc-app')->firstOrFail()->slug;

    $html = $this->actingAs($admin)
        ->withSession(['locale' => 'pt'])
        ->get("/projects/{$slug}/requests")
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Taxa de erro')   // KPI label PT
        ->and($html)->toContain('Top rotas')   // section heading PT
        ->and($html)->toContain('Rota');        // table column PT
});

it('keeps English content by default', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    test()->artisan('warden:project', ['name' => 'Loc App'])->assertSuccessful();
    $slug = Project::where('slug', 'loc-app')->firstOrFail()->slug;

    $this->actingAs($admin)
        ->get("/projects/{$slug}/requests")
        ->assertOk()
        ->assertSee('Error rate')
        ->assertSee('Top routes');
});
