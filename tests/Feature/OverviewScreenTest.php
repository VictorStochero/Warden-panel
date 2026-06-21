<?php

use App\Livewire\Overview;
use App\Models\User;
use Livewire\Livewire;

it('renders fleet KPIs from the package read layer', function () {
    $this->artisan('warden:project', ['name' => 'Demo Fleet'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Overview::class)
        ->assertViewHas('openIssues')
        ->assertViewHas('throughput')
        ->assertViewHas('projects');
});

it('requires authentication for the overview route', function () {
    $this->get('/')->assertRedirect('/login');
});
