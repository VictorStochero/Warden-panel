<?php

use App\Models\User;
use App\Support\WritesAudit;
use VictorStochero\Warden\Models\AuditLog;

it('records an audit row with the panel marker and actor', function () {
    $user = User::factory()->create(['email' => 'op@example.com']);
    $this->actingAs($user);

    $recorder = new class {
        use WritesAudit;
        public function go(): void
        {
            $this->audit('panel.project.rotate', 'checkout-api', ['k' => 'v']);
        }
    };

    $recorder->go();

    $row = AuditLog::query()->latest('id')->firstOrFail();
    expect($row->action)->toBe('panel.project.rotate')
        ->and($row->target)->toBe('checkout-api')
        ->and($row->actor)->toBe('op@example.com')
        ->and($row->method)->toBe('PANEL')
        ->and($row->meta)->toBe(['k' => 'v']);
});

it('never throws even if the actor is a guest', function () {
    $recorder = new class {
        use WritesAudit;
        public function go(): void
        {
            $this->audit('panel.project.delete', 'gone', []);
        }
    };

    $recorder->go();

    $row = AuditLog::query()->latest('id')->firstOrFail();
    expect($row->actor)->toBe('local')->and($row->meta)->toBeNull();
});
