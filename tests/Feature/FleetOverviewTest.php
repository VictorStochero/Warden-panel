<?php

use App\Models\User;
use App\Livewire\Overview;
use Livewire\Livewire;

it('renders the fleet overview with project cards and filter data', function () {
    test()->artisan('warden:project', ['name' => 'Alpha App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Overview::class)
        ->assertViewHas('groups')
        ->assertViewHas('tags')
        ->assertSee('Alpha App')
        ->assertSee('Fleet overview');
});

it('coerces an unknown group filter to empty', function () {
    test()->artisan('warden:project', ['name' => 'Alpha App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Overview::class)
        ->set('group', 'no-such-group')
        ->assertSet('group', '');
});
