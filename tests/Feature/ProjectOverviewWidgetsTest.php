<?php

use App\Models\User;
use App\Livewire\Project\Show;
use Livewire\Livewire;

it('exposes the rich overview widget data', function () {
    test()->artisan('warden:project', ['name' => 'Widget App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Show::class, ['slug' => 'widget-app'])
        ->assertViewHas('recentIssues')
        ->assertViewHas('incidents')
        ->assertViewHas('heartbeats')
        ->assertViewHas('recentTraces')
        ->assertViewHas('slowQueries')
        ->assertViewHas('queues')
        ->assertSee('Recent issues')
        ->assertSee('Heartbeats')
        ->assertSee('Recent traces');
});
