<?php

use App\Models\User;
use App\Livewire\Search;
use Livewire\Livewire;

it('searches projects globally for a 2+ char term', function () {
    test()->artisan('warden:project', ['name' => 'Zeta App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Search::class)
        ->set('q', 'Ze')
        ->assertViewHas('results')
        ->assertSee('Zeta App');
});

it('does not search for a single-character term', function () {
    test()->artisan('warden:project', ['name' => 'Zeta App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Search::class)
        ->set('q', 'Z')
        ->assertDontSee('Zeta App');
});

it('is available to non-admin users', function () {
    test()->artisan('warden:project', ['name' => 'Zeta App'])->assertSuccessful();
    $viewer = User::factory()->create(['is_admin' => false]);

    Livewire::actingAs($viewer)->test(Search::class)
        ->set('q', 'Zeta')
        ->assertSee('Zeta App');
});
