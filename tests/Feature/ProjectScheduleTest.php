<?php

use App\Models\User;
use App\Livewire\Project\Schedule;
use Livewire\Livewire;

it('renders the schedule section for a project', function () {
    $this->artisan('warden:project', ['name' => 'Cron App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Schedule::class, ['slug' => 'cron-app'])
        ->assertViewHas('tasks')
        ->assertViewHas('project');
});
