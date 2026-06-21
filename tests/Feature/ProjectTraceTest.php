<?php

use App\Models\User;
use App\Livewire\Project\Trace;
use Livewire\Livewire;

it('renders a trace waterfall page', function () {
    $this->artisan('warden:project', ['name' => 'Span App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Trace::class, ['slug' => 'span-app', 'traceId' => 'abc123'])
        ->assertViewHas('rows')
        ->assertViewHas('project')
        ->assertSet('traceId', 'abc123');
});

it('requires auth for the trace page', function () {
    $this->get('/projects/span-app/traces/abc123')->assertRedirect('/login');
});
