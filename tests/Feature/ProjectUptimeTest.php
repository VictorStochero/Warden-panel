<?php

use App\Models\User;
use App\Livewire\Project\Uptime;
use Livewire\Livewire;

it('renders the uptime section for a project', function () {
    $this->artisan('warden:project', ['name' => 'Status App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Uptime::class, ['slug' => 'status-app'])
        ->assertViewHas('uptime')
        ->assertViewHas('windows')
        ->assertViewHas('incidents')
        ->assertViewHas('project');
});
