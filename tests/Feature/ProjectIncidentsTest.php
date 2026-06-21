<?php

use App\Models\User;
use App\Livewire\Project\Incidents;
use Livewire\Livewire;

it('renders the incidents list for a project', function () {
    $this->artisan('warden:project', ['name' => 'Uptime App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Incidents::class, ['slug' => 'uptime-app'])
        ->assertViewHas('incidents')
        ->assertViewHas('project');
});

it('requires auth for the incidents list', function () {
    $this->get('/projects/uptime-app/incidents')->assertRedirect('/login');
});
