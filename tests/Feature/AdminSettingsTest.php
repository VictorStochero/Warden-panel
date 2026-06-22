<?php

use App\Models\User;
use App\Livewire\Admin\Settings;
use Livewire\Livewire;
use VictorStochero\Warden\Models\AlertRule;
use VictorStochero\Warden\Models\AlertSetting;
use VictorStochero\Warden\Models\AuditLog;

it('saves alert settings and audits the change', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test(Settings::class)
        ->set('emailEnabled', true)
        ->set('recipients', "ops@acme.test, junk, sre@acme.test")
        ->set('minSeverity', 'critical')
        ->set('cooldown', 600)
        ->call('save');

    $settings = AlertSetting::current();
    expect($settings->email_enabled)->toBeTrue()
        ->and($settings->recipients)->toBe(['ops@acme.test', 'sre@acme.test'])
        ->and($settings->min_severity)->toBe('critical')
        ->and($settings->cooldown)->toBe(600);

    expect(AuditLog::query()->latest('id')->first()->action)->toBe('panel.settings.update');
});

it('coerces an invalid minimum severity', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test(Settings::class)
        ->set('minSeverity', 'bogus')
        ->call('save');

    expect(AlertSetting::current()->min_severity)->toBe('warning');
});

it('adds a valid alert rule and rejects an invalid one', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test(Settings::class)
        ->set('ruleName', 'High error rate')
        ->set('ruleMetric', 'error_rate')
        ->set('ruleOp', '>')
        ->set('ruleThreshold', 5.0)
        ->set('ruleWindow', '1h')
        ->set('ruleSeverity', 'critical')
        ->call('addRule');

    expect(AlertRule::where('name', 'High error rate')->exists())->toBeTrue();

    // Invalid metric → no new rule.
    Livewire::actingAs($admin)->test(Settings::class)
        ->set('ruleName', 'Bad')
        ->set('ruleMetric', 'not-a-metric')
        ->call('addRule');

    expect(AlertRule::where('name', 'Bad')->exists())->toBeFalse();
});

it('forbids non-admins from settings', function () {
    $viewer = User::factory()->create(['is_admin' => false]);
    $this->actingAs($viewer)->get('/admin/settings')->assertForbidden();
});
