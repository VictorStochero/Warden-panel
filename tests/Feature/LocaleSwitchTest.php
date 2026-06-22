<?php

use App\Models\User;

it('translates the navigation to Portuguese when the locale is pt', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->withSession(['locale' => 'pt'])
        ->get('/')
        ->assertOk()
        ->assertSee('Manutenção')   // Maintenance → PT
        ->assertSee('Frota');       // Fleet → PT
});

it('persists a chosen locale and falls back to en for an unknown one', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->post('/locale', ['locale' => 'es'])->assertRedirect();
    expect(session('locale'))->toBe('es');

    $this->actingAs($admin)->post('/locale', ['locale' => 'klingon']);
    expect(session('locale'))->toBe('es'); // unchanged — invalid rejected

    // Invalid session locale resolves to en at render time.
    $this->actingAs($admin)->withSession(['locale' => 'klingon'])->get('/')
        ->assertOk()
        ->assertSee('Maintenance');
});
