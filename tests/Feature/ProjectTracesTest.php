<?php

use App\Models\User;
use App\Livewire\Project\Traces;
use Livewire\Livewire;

it('renders the traces list for a project', function () {
    $this->artisan('warden:project', ['name' => 'Trace App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Traces::class, ['slug' => 'trace-app'])
        ->assertViewHas('traces')
        ->assertViewHas('project');
});

it('requires auth for the traces list', function () {
    $this->get('/projects/trace-app/traces')->assertRedirect('/login');
});
