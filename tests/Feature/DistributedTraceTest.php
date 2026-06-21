<?php

use App\Models\User;
use App\Livewire\Project\Trace;
use Livewire\Livewire;

it('exposes distributed-trace context', function () {
    $this->artisan('warden:project', ['name' => 'Edge App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Trace::class, ['slug' => 'edge-app', 'traceId' => 'xyz789'])
        ->assertViewHas('projects')
        ->assertViewHas('distributed')
        ->assertViewHas('rows');
});
