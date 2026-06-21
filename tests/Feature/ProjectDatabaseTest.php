<?php

use App\Models\User;
use App\Livewire\Project\Database;
use Livewire\Livewire;

it('renders the database section for a project', function () {
    $this->artisan('warden:project', ['name' => 'Shop'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Database::class, ['slug' => 'shop'])
        ->assertViewHas('slowQueries')
        ->assertViewHas('frequentQueries')
        ->assertViewHas('queryHealth')
        ->assertViewHas('project');
});

it('requires auth for the database section', function () {
    $this->get('/projects/shop/database')->assertRedirect('/login');
});
