<?php

use App\Models\User;
use App\Livewire\Project\Logs;
use Livewire\Livewire;

it('renders the logs page for a project', function () {
    $this->artisan('warden:project', ['name' => 'Log App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Logs::class, ['slug' => 'log-app'])
        ->assertViewHas('logs')
        ->assertViewHas('project')
        ->assertSet('range', '1h')
        ->set('range', 'bogus')
        ->assertSet('range', '1h');
});

it('requires auth for the logs page', function () {
    $this->get('/projects/log-app/logs')->assertRedirect('/login');
});
