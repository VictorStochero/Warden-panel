<?php

use App\Models\User;
use App\Livewire\Project\Jobs;
use Livewire\Livewire;

it('renders the jobs section for a project', function () {
    $this->artisan('warden:project', ['name' => 'Worker'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Jobs::class, ['slug' => 'worker'])
        ->assertViewHas('queues')
        ->assertViewHas('project');
});
