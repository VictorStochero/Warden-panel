<?php

use App\Models\User;
use App\Livewire\Admin\ApiTokens;
use Livewire\Livewire;
use VictorStochero\Warden\Models\ApiToken;
use VictorStochero\Warden\Models\AuditLog;

it('mints a token, shows the plaintext once, and audits it', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $component = Livewire::actingAs($admin)->test(ApiTokens::class)
        ->set('name', 'CI reader')
        ->call('createToken')
        ->assertSee('Copy this now');

    expect(ApiToken::where('name', 'CI reader')->exists())->toBeTrue();
    expect(AuditLog::query()->latest('id')->first()->action)->toBe('panel.token.create');

    // Flash consumed — a refresh must not re-show the one-time plaintext callout.
    $component->call('$refresh')->assertDontSee('Copy this now');
});

it('revokes a token and audits it', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    [$model] = ApiToken::mint('Old');

    Livewire::actingAs($admin)->test(ApiTokens::class)
        ->call('revoke', $model->id);

    expect(ApiToken::whereKey($model->id)->exists())->toBeFalse();
    expect(AuditLog::query()->latest('id')->first()->action)->toBe('panel.token.delete');
});

it('forbids non-admins from API tokens', function () {
    $viewer = User::factory()->create(['is_admin' => false]);
    $this->actingAs($viewer)->get('/admin/api-tokens')->assertForbidden();
});
