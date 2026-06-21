<?php

use App\Models\User;
use App\Livewire\Project\Http;
use Livewire\Livewire;

it('renders the http section for a project', function () {
    $this->artisan('warden:project', ['name' => 'Gateway'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Http::class, ['slug' => 'gateway'])
        ->assertViewHas('hosts')
        ->assertViewHas('project');
});
