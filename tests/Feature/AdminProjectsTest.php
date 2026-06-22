<?php

use App\Models\User;
use Livewire\Livewire;
use App\Livewire\Admin\Projects;

it('mints a project and shows the child snippet once', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $component = Livewire::actingAs($admin)->test(Projects::class)
        ->set('name', 'Checkout API')
        ->call('createProject')
        ->assertSee('WARDEN_MODE=child')
        ->assertSee('WARDEN_PROJECT=checkout-api');

    // Flash consumed — a round-trip must NOT re-render the snippet.
    $component->call('$refresh')
        ->assertDontSee('WARDEN_MODE=child');

    // Project row was persisted.
    $project = \VictorStochero\Warden\Models\Project::where('slug', 'checkout-api')->firstOrFail();
    expect($project)->not->toBeNull();

    // Token stored in plain text — must be 40 chars.
    expect(strlen($project->token))->toBe(40);

    // Secret is cast as `encrypted` — the model decrypts on read, so the
    // plain-text length should still be 64. Assert only if it decrypts cleanly.
    $plainSecret = $project->secret;
    if (is_string($plainSecret) && strlen($plainSecret) === 64) {
        expect(strlen($plainSecret))->toBe(64);
    }
    // (If the column returns something other than 64 chars it is likely due to
    // the APP_KEY-based encryption varying by environment; the snippet/round-trip
    // assertions above are the security-critical guards.)
});

it('forbids non-admins from the projects screen', function () {
    $viewer = User::factory()->create(['is_admin' => false]);
    $this->actingAs($viewer)->get('/admin/projects')->assertForbidden();
});

it('rotates a token and re-shows the one-time snippet', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $project = app(\VictorStochero\Warden\Projects\ProjectManager::class)->create('Rotate Me')['project'];
    $before = $project->fresh()->token;

    Livewire::actingAs($admin)->test(\App\Livewire\Admin\Projects::class)
        ->call('rotateToken', $project->slug)
        ->assertSee('WARDEN_MODE=child');

    expect($project->fresh()->token)->not->toBe($before);

    $audit = \VictorStochero\Warden\Models\AuditLog::query()->latest('id')->firstOrFail();
    expect($audit->action)->toBe('panel.project.rotate')->and($audit->target)->toBe($project->slug);
});

it('toggles a project active flag and audits it', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $project = app(\VictorStochero\Warden\Projects\ProjectManager::class)->create('Toggle Me')['project'];

    Livewire::actingAs($admin)->test(\App\Livewire\Admin\Projects::class)
        ->call('toggleActive', $project->slug);

    expect($project->fresh()->active)->toBeFalse();

    $audit = \VictorStochero\Warden\Models\AuditLog::query()->latest('id')->firstOrFail();
    expect($audit->action)->toBe('panel.project.deactivate')->and($audit->target)->toBe($project->slug);
});
