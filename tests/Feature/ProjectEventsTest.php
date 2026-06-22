<?php

use App\Models\User;
use App\Livewire\Project\Events;
use Livewire\Livewire;

it('renders the events page and validates the type', function () {
    $this->artisan('warden:project', ['name' => 'Event App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Events::class, ['slug' => 'event-app'])
        ->assertViewHas('events')
        ->assertViewHas('types')
        ->assertSet('type', 'mail')
        ->set('type', 'cache')
        ->assertSet('type', 'cache')
        ->set('type', 'evil-injection')
        ->assertSet('type', 'mail');
});

it('requires auth for the events page', function () {
    $this->get('/projects/event-app/events')->assertRedirect('/login');
});
