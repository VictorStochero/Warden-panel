<?php

use App\Models\User;
use App\Livewire\Project\Requests;
use Livewire\Livewire;

it('exposes release markers on the requests section', function () {
    test()->artisan('warden:project', ['name' => 'Deploy App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Requests::class, ['slug' => 'deploy-app'])
        ->assertViewHas('markers');
});
