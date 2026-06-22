<?php

use App\Models\User;
use App\Livewire\Admin\Project as AdminProject;
use Livewire\Livewire;
use VictorStochero\Warden\Models\AuditLog;
use VictorStochero\Warden\Projects\ProjectManager;

function seedAdminProject(string $name = 'Manage Me')
{
    return app(ProjectManager::class)->create($name)['project'];
}

it('forbids non-admins from the per-project admin page', function () {
    $viewer = User::factory()->create(['is_admin' => false]);
    $project = seedAdminProject();
    $this->actingAs($viewer)->get("/admin/projects/{$project->slug}/manage")->assertForbidden();
});

it('edits project details and audits the change', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $project = seedAdminProject();

    Livewire::actingAs($admin)->test(AdminProject::class, ['slug' => $project->slug])
        ->set('name', 'Renamed App')
        ->set('client', 'Acme')
        ->set('contact', 'ops@acme.test')
        ->set('group', 'Production')
        ->set('tags', 'critical, billing')
        ->call('save');

    $fresh = $project->fresh();
    expect($fresh->name)->toBe('Renamed App')
        ->and($fresh->client)->toBe('Acme')
        ->and($fresh->contact)->toBe('ops@acme.test')
        ->and($fresh->group?->name)->toBe('Production')
        ->and($fresh->tags->pluck('name')->sort()->values()->all())->toBe(['billing', 'critical']);

    $audit = AuditLog::query()->latest('id')->firstOrFail();
    expect($audit->action)->toBe('panel.project.update')->and($audit->target)->toBe($project->slug);
});
