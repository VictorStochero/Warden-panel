<?php

use App\Models\User;
use App\Livewire\Admin\Project as AdminProject;
use Livewire\Livewire;
use VictorStochero\Warden\Models\AuditLog;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Models\Project as ProjectModel;
use Illuminate\Support\Facades\DB;

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

it('resets metrics, keeping the project row', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $project = seedAdminProject();
    DB::table('wdn_events')->insert([
        'project_id' => $project->id, 'type' => 'cache', 'trace_id' => 't1',
        'occurred_at' => now(), 'occurred_date' => now()->toDateString(), 'payload' => '{}',
    ]);

    Livewire::actingAs($admin)->test(AdminProject::class, ['slug' => $project->slug])
        ->call('resetMetrics');

    expect(DB::table('wdn_events')->where('project_id', $project->id)->count())->toBe(0)
        ->and(ProjectModel::where('slug', $project->slug)->exists())->toBeTrue();
    expect(AuditLog::query()->latest('id')->first()->action)->toBe('panel.project.reset');
});

it('purges a single event type', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $project = seedAdminProject();
    foreach (['cache', 'mail'] as $t) {
        DB::table('wdn_events')->insert([
            'project_id' => $project->id, 'type' => $t, 'trace_id' => "t-$t",
            'occurred_at' => now(), 'occurred_date' => now()->toDateString(), 'payload' => '{}',
        ]);
    }

    Livewire::actingAs($admin)->test(AdminProject::class, ['slug' => $project->slug])
        ->set('purgeTypeChoice', 'cache')
        ->call('purge');

    expect(DB::table('wdn_events')->where('project_id', $project->id)->where('type', 'cache')->count())->toBe(0)
        ->and(DB::table('wdn_events')->where('project_id', $project->id)->where('type', 'mail')->count())->toBe(1);
    $audit = AuditLog::query()->latest('id')->firstOrFail();
    expect($audit->action)->toBe('panel.project.purge')->and($audit->meta)->toBe(['type' => 'cache']);
});

it('deletes a project only when the slug confirmation matches, then redirects', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $project = seedAdminProject();

    // Wrong confirmation: no-op.
    Livewire::actingAs($admin)->test(AdminProject::class, ['slug' => $project->slug])
        ->set('confirmSlug', 'wrong')
        ->call('deleteProject')
        ->assertHasErrors('confirmSlug');
    expect(ProjectModel::where('slug', $project->slug)->exists())->toBeTrue();

    // Correct confirmation: deletes + redirects to the list.
    Livewire::actingAs($admin)->test(AdminProject::class, ['slug' => $project->slug])
        ->set('confirmSlug', $project->slug)
        ->call('deleteProject')
        ->assertRedirect(route('admin.projects'));
    expect(ProjectModel::where('slug', $project->slug)->exists())->toBeFalse();
    expect(AuditLog::query()->latest('id')->first()->action)->toBe('panel.project.delete');
});
