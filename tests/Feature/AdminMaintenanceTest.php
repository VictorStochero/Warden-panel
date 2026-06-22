<?php

use App\Models\User;
use App\Livewire\Admin\Maintenance;
use Livewire\Livewire;
use VictorStochero\Warden\Models\AuditLog;
use VictorStochero\Warden\Models\CommandRun;

it('lists the maintenance commands', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test(Maintenance::class)
        ->assertViewHas('commands')
        ->assertSee('warden:aggregate')
        ->assertSee('warden:prune');
});

it('queues an allowed command via a CommandRun and audits it', function () {
    Illuminate\Support\Facades\Queue::fake();
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test(Maintenance::class)
        ->call('run', 'aggregate');

    expect(CommandRun::where('command', 'aggregate')->exists())->toBeTrue();

    $audit = AuditLog::query()->latest('id')->firstOrFail();
    expect($audit->action)->toBe('panel.maintenance.run')
        ->and($audit->target)->toBe('aggregate')
        ->and($audit->meta)->toBe(['command' => 'aggregate']);
});

it('ignores a command outside the allow-list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test(Maintenance::class)
        ->call('run', 'rm-rf');

    expect(AuditLog::query()->count())->toBe(0);
});

it('forbids non-admins from maintenance', function () {
    $viewer = User::factory()->create(['is_admin' => false]);
    $this->actingAs($viewer)->get('/admin/maintenance')->assertForbidden();
});
