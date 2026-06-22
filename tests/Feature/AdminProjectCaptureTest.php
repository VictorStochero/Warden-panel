<?php

use App\Models\User;
use App\Livewire\Admin\Project as AdminProject;
use Livewire\Livewire;
use VictorStochero\Warden\Models\AuditLog;
use VictorStochero\Warden\Models\Project as ProjectModel;
use VictorStochero\Warden\Projects\ProjectManager;

function seedCaptureProject(): ProjectModel
{
    return app(ProjectManager::class)->create('Capture App')['project'];
}

it('applies the lean capture profile and audits it', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $project = seedCaptureProject();

    Livewire::actingAs($admin)->test(AdminProject::class, ['slug' => $project->slug])
        ->set('captureProfile', 'lean')
        ->call('saveCapture');

    expect($project->fresh()->capture_profile)->toBe('lean');
    expect(AuditLog::query()->latest('id')->first()->action)->toBe('panel.project.capture');
});

it('stores a sparse type-gate for a custom profile', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $project = seedCaptureProject();

    Livewire::actingAs($admin)->test(AdminProject::class, ['slug' => $project->slug])
        ->set('captureProfile', 'custom')
        ->set('typeGates.cache', false)
        ->set('typeGates.mail', false)
        ->call('saveCapture');

    $fresh = $project->fresh();
    expect($fresh->capture_profile)->toBe('custom')
        ->and($fresh->config['sample']['type_gate']['cache'] ?? null)->toBeFalse()
        ->and($fresh->config['sample']['type_gate']['mail'] ?? null)->toBeFalse();
});

it('resets to full capture, clearing the config', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $project = seedCaptureProject();
    $project->forceFill(['capture_profile' => 'custom', 'config' => ['sample' => ['type_gate' => ['cache' => false]]]])->save();

    Livewire::actingAs($admin)->test(AdminProject::class, ['slug' => $project->slug])
        ->set('captureProfile', 'full')
        ->call('saveCapture');

    $fresh = $project->fresh();
    expect($fresh->capture_profile)->toBe('full')
        ->and($fresh->config)->toBeNull();
});
