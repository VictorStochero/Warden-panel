<?php

use App\Models\User;
use App\Livewire\Admin\Audit;
use Livewire\Livewire;
use VictorStochero\Warden\Models\AuditLog;

it('renders the audit log with recent entries', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    AuditLog::create([
        'actor' => 'op@example.com', 'action' => 'panel.project.rotate',
        'target' => 'checkout-api', 'method' => 'PANEL', 'ip' => '127.0.0.1',
        'meta' => null, 'created_at' => now(),
    ]);

    Livewire::actingAs($admin)->test(Audit::class)
        ->assertViewHas('entries')
        ->assertSee('panel.project.rotate')
        ->assertSee('checkout-api');
});

it('forbids non-admins from the audit log', function () {
    $viewer = User::factory()->create(['is_admin' => false]);
    $this->actingAs($viewer)->get('/admin/audit')->assertForbidden();
});

it('requires auth for the audit log', function () {
    $this->get('/admin/audit')->assertRedirect('/login');
});
