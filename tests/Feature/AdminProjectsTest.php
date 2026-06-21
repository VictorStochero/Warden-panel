<?php

use App\Models\User;
use Livewire\Livewire;
use App\Livewire\Admin\Projects;

it('mints a project and shows the child snippet once', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test(Projects::class)
        ->set('name', 'Checkout API')
        ->call('createProject')
        ->assertSet('newToken', fn ($t) => is_string($t) && strlen($t) === 40)
        ->assertSet('newSecret', fn ($s) => is_string($s) && strlen($s) === 64)
        ->assertSee('WARDEN_MODE=child')
        ->assertSee('WARDEN_PROJECT=checkout-api');

    expect(\VictorStochero\Warden\Models\Project::where('slug', 'checkout-api')->exists())->toBeTrue();
});

it('forbids non-admins from the projects screen', function () {
    $viewer = User::factory()->create(['is_admin' => false]);
    $this->actingAs($viewer)->get('/admin/projects')->assertForbidden();
});
