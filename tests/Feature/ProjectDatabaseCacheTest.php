<?php

use App\Models\User;
use App\Livewire\Project\Database;
use Livewire\Livewire;

it('exposes the cache stores breakdown on the database section', function () {
    test()->artisan('warden:project', ['name' => 'Cache App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Database::class, ['slug' => 'cache-app'])
        ->assertViewHas('cacheStores')
        ->assertSee('Cache stores');
});
