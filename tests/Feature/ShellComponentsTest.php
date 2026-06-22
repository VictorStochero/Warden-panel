<?php

use App\Models\User;
use App\Livewire\Project\Show;
use Livewire\Livewire;

function seedShellProject(): string
{
    test()->artisan('warden:project', ['name' => 'Shell App'])->assertSuccessful();
    return 'shell-app';
}

it('renders the page header range pills and KPI strip on the overview', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $slug = seedShellProject();

    Livewire::actingAs($admin)->test(Show::class, ['slug' => $slug])
        ->assertSee('Throughput')      // KPI strip label
        ->assertSee('Open issues')     // KPI strip label
        ->assertSee('6h')              // range pill
        ->set('range', '6h')
        ->assertSet('range', '6h');
});

it('shows the read-only banner to non-admins and hides it from admins', function () {
    $slug = seedShellProject();

    $viewer = User::factory()->create(['is_admin' => false]);
    $this->actingAs($viewer)->get("/projects/{$slug}")->assertSee('read-only');

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get("/projects/{$slug}")->assertDontSee('read-only');
});
