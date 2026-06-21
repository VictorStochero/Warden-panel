<?php

use App\Models\User;
use App\Livewire\Project\Show;
use Livewire\Livewire;

it('exposes request series and top routes for the project', function () {
    $this->artisan('warden:project', ['name' => 'Billing'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Show::class, ['slug' => 'billing'])
        ->assertViewHas('series')
        ->assertViewHas('routes');
});
