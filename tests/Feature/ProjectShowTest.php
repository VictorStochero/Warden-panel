<?php

use App\Models\User;
use App\Livewire\Project\Show;
use App\Support\Ranges;
use Livewire\Livewire;

it('sanitizes ranges to the allow-list', function () {
    expect(Ranges::sanitize('6h'))->toBe('6h');
    expect(Ranges::sanitize('bogus'))->toBe('1h');
    expect(Ranges::sanitize(null))->toBe('1h');
    expect(Ranges::all())->toBe(['15m','1h','6h','24h','7d','30d']);
});

it('renders project KPIs and supports range switching', function () {
    $this->artisan('warden:project', ['name' => 'Checkout API'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Show::class, ['slug' => 'checkout-api'])
        ->assertViewHas('kpis')
        ->assertViewHas('project')
        ->assertSet('range', '1h')
        ->set('range', '24h')
        ->assertSet('range', '24h')
        ->set('range', 'bogus')
        ->assertSet('range', '1h');
});

it('404s for an unknown project', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/projects/does-not-exist')->assertNotFound();
});

it('requires auth for project pages', function () {
    $this->get('/projects/checkout-api')->assertRedirect('/login');
});
