# Warden Panel — Phase 6a: Admin Completeness — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the panel's Admin surface — token rotation, activate/deactivate, full project-detail editing, a danger zone (reset metrics / purge a type / delete), and a read-only audit log — all via package reuse.

**Architecture:** Three Livewire components under `App\Livewire\Admin`: enrich the existing `Projects` list (rotate + toggle + a "Manage" link), a new per-project `Project` page (settings form + danger zone), and a new `Audit` page (`DashboardRepository::auditLog`). A shared `App\Support\WritesAudit` trait records every management action into `wdn_audit_log` via the package `AuditLog` model. No package modification.

**Tech Stack:** Laravel 12.62, Livewire 4, Flux 2.15, Tailwind v4 (Warden DS), Pest, DDEV.

## Global Constraints

- **Do NOT modify `vendor/victorstochero/warden`.** Reuse only.
- All project mutations go through `VictorStochero\Warden\Projects\ProjectManager`; audit reads through `VictorStochero\Warden\Dashboard\DashboardRepository::auditLog`; audit writes through `VictorStochero\Warden\Models\AuditLog`. No direct `wdn_*` queries in panel code beyond the `AuditLog` model.
- **DDEV runtime:** all commands via `ddev` — `ddev artisan test`, `ddev npm run build`. Bare `php`/`npm` fail on the host.
- Every admin screen is gated by `panel.manage` (defined `app/Providers/AppServiceProvider.php:24` as `fn (User $u) => $u->is_admin === true`): `@can('panel.manage')` in nav, `$this->authorize('panel.manage')` in `mount()` AND in every write action.
- Every management action records an audit entry via the `WritesAudit` trait; actions are prefixed `panel.`; **never** put a token/secret in `meta`.
- The minted/rotated child secret is shown **once** via `session()->flash('warden_new_credentials', …)`; it must never persist in client/component state.
- Warden DS theme classes (`bg-ink-850`, `font-mono`); real-time via `wire:poll.{{ config('panel.poll_seconds') }}s`.
- Tests use Pest; test DB SQLite `:memory:`; seed projects with `ddev artisan warden:project` or `ProjectManager::create`. Admins via `User::factory()->create(['is_admin' => true])`; non-admins with `'is_admin' => false`. Do NOT use `warden:demo`.
- **Every new authenticated page MUST be added to `tests/Feature/PanelLayoutRendersTest.php`'s dataset.** Use only valid Flux icons (verify under `vendor/livewire/flux/stubs/resources/views/flux/icon/<name>.blade.php`).

## Read-layer & write-layer reference (exact signatures — consume verbatim)

- `ProjectManager::rotate(Project $project): array` → `['token' => string, 'secret' => string]`.
- `ProjectManager::setActive(Project $project, bool $active): void`.
- `ProjectManager::updateDetails(Project $project, array $data): void` — keys: `name`, `client`, `contact`, `group` (name string; resolved/created), `tags` (CSV string or list; resolved/created + synced; empty clears).
- `ProjectManager::resetMetrics(Project $project): array<string,int>` — deletes the project's rows from `wdn_events/aggregates/issues/incidents/heartbeats/cursors`; project row kept.
- `ProjectManager::purgeType(Project $project, string $type): array<string,int>` — deletes `wdn_events`+`wdn_aggregates` of that `type`.
- `ProjectManager::delete(Project $project): array<string,int>` — `resetMetrics` + detach tags + delete project row.
- `ProjectManager::envSnippet(string $slug, string $token, string $secret, string $url): string`.
- `DashboardRepository::auditLog(int $limit = 200): Collection<int, \stdClass>` of `wdn_audit_log` ordered by `id` desc. Columns: `{id, actor, action, target, method, ip, meta, created_at}`.
- `VictorStochero\Warden\Models\AuditLog` — `$guarded = []`, `timestamps = false`, casts `meta => array`, `created_at => datetime`.
- `VictorStochero\Warden\Models\Project` — `$project->group?->name`, `$project->tags` (collection of `Tag` with `name`), `$project->active` (bool), `$project->slug`, `$project->name`, `$project->client`, `$project->contact`.

---

### Task 1: `WritesAudit` trait

**Files:**
- Create: `app/Support/WritesAudit.php`
- Test: `tests/Feature/WritesAuditTest.php`

**Interfaces:**
- Produces: trait `App\Support\WritesAudit` with `protected function audit(string $action, ?string $target, array $meta = []): void` — inserts a `wdn_audit_log` row (`actor` = auth email or `'local'`, `method` = `'PANEL'`, `ip` = `request()->ip()`, `meta` null when empty). Best-effort: swallows `Throwable`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/WritesAuditTest.php
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
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=WritesAuditTest`
Expected: FAIL (`Class "App\Support\WritesAudit" not found`).

- [ ] **Step 3: Implement the trait**

```php
// app/Support/WritesAudit.php
<?php

namespace App\Support;

use Throwable;
use VictorStochero\Warden\Models\AuditLog;

trait WritesAudit
{
    /**
     * Record a panel management action into wdn_audit_log. Best-effort: the
     * audit trail must never break the action it describes. Never pass a
     * token/secret in $meta.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function audit(string $action, ?string $target, array $meta = []): void
    {
        try {
            AuditLog::create([
                'actor' => auth()->user()?->email ?? 'local',
                'action' => $action,
                'target' => $target,
                'method' => 'PANEL',
                'ip' => request()->ip(),
                'meta' => $meta !== [] ? $meta : null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Audit is best-effort — never break the action.
        }
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `ddev artisan test --filter=WritesAuditTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/WritesAudit.php tests/Feature/WritesAuditTest.php
git commit -m "feat(admin): add WritesAudit trait for panel audit entries"
```

---

### Task 2: Projects list — rotate, activate/deactivate, Manage link

**Files:**
- Modify: `app/Livewire/Admin/Projects.php`
- Modify: `resources/views/livewire/admin/projects.blade.php`
- Modify: `tests/Feature/AdminProjectsTest.php`

**Interfaces:**
- Consumes: `WritesAudit::audit`, `ProjectManager::{create,rotate,setActive,envSnippet}`.
- Produces: actions `rotateToken(string $slug)`, `toggleActive(string $slug)` on `Admin\Projects`; a per-row "Manage" link to `route('admin.project', $slug)` (route added in Task 3).

- [ ] **Step 1: Write the failing test** (append to `tests/Feature/AdminProjectsTest.php`)

```php
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
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=AdminProjectsTest`
Expected: FAIL (`Method rotateToken does not exist`).

- [ ] **Step 3: Implement the component** (rewrite `app/Livewire/Admin/Projects.php`)

```php
<?php

namespace App\Livewire\Admin;

use App\Support\WritesAudit;
use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Projects\ProjectManager;

#[Layout('components.layouts.app')]
class Projects extends Component
{
    use WritesAudit;

    public string $name = '';

    public function mount(): void
    {
        $this->authorize('panel.manage');
    }

    public function createProject(ProjectManager $projects): void
    {
        $this->authorize('panel.manage');
        $this->validate(['name' => 'required|string|max:120']);

        $result = $projects->create($this->name);
        $this->flashCredentials($projects, $result['project'], $result['token'], $result['secret']);
        $this->audit('panel.project.create', $result['project']->slug);

        $this->name = '';
    }

    public function rotateToken(ProjectManager $projects, string $slug): void
    {
        $this->authorize('panel.manage');
        $project = Project::where('slug', $slug)->firstOrFail();

        $creds = $projects->rotate($project);
        $this->flashCredentials($projects, $project, $creds['token'], $creds['secret']);
        $this->audit('panel.project.rotate', $project->slug);
    }

    public function toggleActive(ProjectManager $projects, string $slug): void
    {
        $this->authorize('panel.manage');
        $project = Project::where('slug', $slug)->firstOrFail();

        $next = ! $project->active;
        $projects->setActive($project, $next);
        $this->audit($next ? 'panel.project.activate' : 'panel.project.deactivate', $project->slug);
    }

    private function flashCredentials(ProjectManager $projects, Project $project, string $token, string $secret): void
    {
        $snippet = $projects->envSnippet($project->slug, $token, $secret, rtrim(config('app.url'), '/'));

        session()->flash('warden_new_credentials', [
            'token' => $token,
            'secret' => $secret,
            'snippet' => $snippet,
        ]);
    }

    public function render()
    {
        return view('livewire.admin.projects', [
            'projects' => Project::query()->orderBy('name')->get(),
        ]);
    }
}
```

- [ ] **Step 4: Implement the view** (rewrite `resources/views/livewire/admin/projects.blade.php`)

```blade
<div class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">Projects</flux:heading>

    <form wire:submit="createProject" class="flex items-end gap-3">
        <flux:input wire:model="name" label="New project name" class="max-w-sm" />
        <flux:button type="submit" variant="primary">Create + mint credentials</flux:button>
    </form>

    @if (session('warden_new_credentials'))
        <flux:callout variant="warning">
            <flux:heading>Copy this now — the secret is shown only once.</flux:heading>
            <pre class="font-mono text-sm whitespace-pre-wrap">{{ session('warden_new_credentials')['snippet'] }}</pre>
        </flux:callout>
    @endif

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Slug</flux:table.column>
                <flux:table.column>Active</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($projects as $project)
                    <flux:table.row wire:key="proj-{{ $project->id }}">
                        <flux:table.cell>{{ $project->name }}</flux:table.cell>
                        <flux:table.cell class="font-mono">{{ $project->slug }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$project->active ? 'lime' : 'zinc'" size="sm">{{ $project->active ? 'active' : 'inactive' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-2">
                                <flux:button size="xs" wire:click="rotateToken('{{ $project->slug }}')">Rotate</flux:button>
                                <flux:button size="xs" wire:click="toggleActive('{{ $project->slug }}')">{{ $project->active ? 'Deactivate' : 'Activate' }}</flux:button>
                                <flux:button size="xs" variant="primary" :href="route('admin.project', $project->slug)" wire:navigate>Manage</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</div>
```

- [ ] **Step 5: Run to verify it passes**

Run: `ddev artisan test --filter=AdminProjectsTest`
Expected: PASS (all 4: mint, forbid, rotate, toggle). The `route('admin.project', …)` is added in Task 3 — until then the view's `Manage` link will error in a full render. **Test via the Livewire unit test only at this step** (the unit test renders the component without resolving named routes for hrefs is NOT guaranteed; if the render fails on `route('admin.project')`, do Task 3's route step first, then return). To keep the cycle green, add the route now:

In `routes/web.php`, add the import and route (final component arrives in Task 3, but registering the name first keeps renders valid). Add near the other admin route:

```php
use App\Livewire\Admin\Project as AdminProject;
// ...
Route::get('/admin/projects/{slug}/manage', AdminProject::class)->middleware('can:panel.manage')->name('admin.project');
```

Create a minimal placeholder so the route resolves (replaced fully in Task 3):

```php
// app/Livewire/Admin/Project.php
<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Project extends Component
{
    public string $slug;

    public function mount(string $slug): void
    {
        $this->authorize('panel.manage');
        $this->slug = $slug;
    }

    public function render()
    {
        return view('livewire.admin.project');
    }
}
```

```blade
{{-- resources/views/livewire/admin/project.blade.php --}}
<div class="space-y-6"><flux:heading size="xl">Manage</flux:heading></div>
```

Re-run: `ddev artisan test --filter=AdminProjectsTest` → PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Admin/Projects.php resources/views/livewire/admin/projects.blade.php tests/Feature/AdminProjectsTest.php app/Livewire/Admin/Project.php resources/views/livewire/admin/project.blade.php routes/web.php
git commit -m "feat(admin): rotate token + toggle active on projects list"
```

---

### Task 3: Per-project admin page — settings (updateDetails)

**Files:**
- Modify: `app/Livewire/Admin/Project.php`
- Modify: `resources/views/livewire/admin/project.blade.php`
- Modify: `tests/Feature/PanelLayoutRendersTest.php`
- Test: `tests/Feature/AdminProjectTest.php`

**Interfaces:**
- Consumes: `WritesAudit::audit`, `ProjectManager::updateDetails`, `Project` relations (`group`, `tags`).
- Produces: action `save()` on `Admin\Project`; public props `name, client, contact, group, tags` (CSV).

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/AdminProjectTest.php
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
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=AdminProjectTest`
Expected: FAIL (`Method save does not exist` / props unset).

- [ ] **Step 3: Implement the component** (rewrite `app/Livewire/Admin/Project.php`)

```php
<?php

namespace App\Livewire\Admin;

use App\Support\WritesAudit;
use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Models\Project as ProjectModel;
use VictorStochero\Warden\Projects\ProjectManager;

#[Layout('components.layouts.app')]
class Project extends Component
{
    use WritesAudit;

    public string $slug;
    public string $name = '';
    public string $client = '';
    public string $contact = '';
    public string $group = '';
    public string $tags = '';

    public function mount(string $slug): void
    {
        $this->authorize('panel.manage');
        $this->slug = $slug;

        $project = $this->project();
        $this->name = $project->name;
        $this->client = (string) $project->client;
        $this->contact = (string) $project->contact;
        $this->group = (string) ($project->group?->name ?? '');
        $this->tags = $project->tags->pluck('name')->implode(', ');
    }

    private function project(): ProjectModel
    {
        return ProjectModel::where('slug', $this->slug)->firstOrFail();
    }

    public function save(ProjectManager $projects): void
    {
        $this->authorize('panel.manage');
        $this->validate(['name' => 'required|string|max:120']);

        $projects->updateDetails($this->project(), [
            'name' => $this->name,
            'client' => $this->client,
            'contact' => $this->contact,
            'group' => $this->group,
            'tags' => $this->tags,
        ]);
        $this->audit('panel.project.update', $this->slug);

        session()->flash('admin_project_saved', true);
    }

    public function render()
    {
        return view('livewire.admin.project', ['project' => $this->project()]);
    }
}
```

- [ ] **Step 4: Implement the view** (rewrite `resources/views/livewire/admin/project.blade.php`)

```blade
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Manage</flux:heading>
        <flux:button size="sm" :href="route('admin.projects')" wire:navigate>← Projects</flux:button>
    </div>

    @if (session('admin_project_saved'))
        <flux:callout variant="success">Saved.</flux:callout>
    @endif

    <div class="rounded-xl bg-ink-850 p-6">
        <flux:heading size="lg" class="mb-4">Settings</flux:heading>
        <form wire:submit="save" class="grid max-w-xl gap-4">
            <flux:input wire:model="name" label="Name" />
            <flux:input wire:model="client" label="Client" />
            <flux:input wire:model="contact" label="Contact" />
            <flux:input wire:model="group" label="Group" description="Resolved/created by name; empty clears." />
            <flux:input wire:model="tags" label="Tags" description="Comma-separated; empty clears." />
            <div><flux:button type="submit" variant="primary">Save</flux:button></div>
        </form>
    </div>
</div>
```

- [ ] **Step 5: Add the page to the render test** (`tests/Feature/PanelLayoutRendersTest.php`)

Add to the `->with([...])` dataset, after `'/admin/projects'`:

```php
    '/admin/projects/{slug}/manage',
```

- [ ] **Step 6: Run to verify it passes**

Run: `ddev artisan test --filter=AdminProjectTest && ddev artisan test --filter=PanelLayoutRendersTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Admin/Project.php resources/views/livewire/admin/project.blade.php tests/Feature/AdminProjectTest.php tests/Feature/PanelLayoutRendersTest.php
git commit -m "feat(admin): per-project settings page (updateDetails)"
```

---

### Task 4: Danger zone — reset metrics / purge type / delete

**Files:**
- Modify: `app/Livewire/Admin/Project.php`
- Modify: `resources/views/livewire/admin/project.blade.php`
- Modify: `tests/Feature/AdminProjectTest.php`

**Interfaces:**
- Consumes: `ProjectManager::{resetMetrics,purgeType,delete}`.
- Produces: actions `resetMetrics()`, `purge()`, `deleteProject()`; props `purgeTypeChoice` (default `'cache'`), `confirmSlug`; static `purgeTypes(): list<string>`.

- [ ] **Step 1: Write the failing test** (append to `tests/Feature/AdminProjectTest.php`)

```php
use VictorStochero\Warden\Models\Project as ProjectModel;

it('resets metrics, keeping the project row', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $project = seedAdminProject();
    \Illuminate\Support\Facades\DB::table('wdn_events')->insert([
        'project_id' => $project->id, 'type' => 'cache', 'trace_id' => 't1',
        'occurred_at' => now(), 'occurred_date' => now()->toDateString(), 'payload' => '{}',
    ]);

    Livewire::actingAs($admin)->test(AdminProject::class, ['slug' => $project->slug])
        ->call('resetMetrics');

    expect(\Illuminate\Support\Facades\DB::table('wdn_events')->where('project_id', $project->id)->count())->toBe(0)
        ->and(ProjectModel::where('slug', $project->slug)->exists())->toBeTrue();
    expect(AuditLog::query()->latest('id')->first()->action)->toBe('panel.project.reset');
});

it('purges a single event type', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $project = seedAdminProject();
    foreach (['cache', 'mail'] as $t) {
        \Illuminate\Support\Facades\DB::table('wdn_events')->insert([
            'project_id' => $project->id, 'type' => $t, 'trace_id' => "t-$t",
            'occurred_at' => now(), 'occurred_date' => now()->toDateString(), 'payload' => '{}',
        ]);
    }

    Livewire::actingAs($admin)->test(AdminProject::class, ['slug' => $project->slug])
        ->set('purgeTypeChoice', 'cache')
        ->call('purge');

    expect(\Illuminate\Support\Facades\DB::table('wdn_events')->where('project_id', $project->id)->where('type', 'cache')->count())->toBe(0)
        ->and(\Illuminate\Support\Facades\DB::table('wdn_events')->where('project_id', $project->id)->where('type', 'mail')->count())->toBe(1);
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
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=AdminProjectTest`
Expected: FAIL (`Method resetMetrics does not exist`).

- [ ] **Step 3: Extend the component** — add to `app/Livewire/Admin/Project.php`:

Add the imports/props near the existing props:

```php
    public string $purgeTypeChoice = 'cache';
    public string $confirmSlug = '';

    /** @return list<string> */
    public static function purgeTypes(): array
    {
        return ['query', 'exception', 'log', 'job', 'mail', 'notification', 'cache', 'command', 'schedule', 'http', 'request'];
    }
```

Add the three actions (inside the class, after `save()`):

```php
    public function resetMetrics(ProjectManager $projects): void
    {
        $this->authorize('panel.manage');
        $projects->resetMetrics($this->project());
        $this->audit('panel.project.reset', $this->slug);
        session()->flash('admin_project_saved', true);
    }

    public function purge(ProjectManager $projects): void
    {
        $this->authorize('panel.manage');
        if (! in_array($this->purgeTypeChoice, self::purgeTypes(), true)) {
            $this->purgeTypeChoice = 'cache';
        }
        $projects->purgeType($this->project(), $this->purgeTypeChoice);
        $this->audit('panel.project.purge', $this->slug, ['type' => $this->purgeTypeChoice]);
        session()->flash('admin_project_saved', true);
    }

    public function deleteProject(ProjectManager $projects)
    {
        $this->authorize('panel.manage');
        if ($this->confirmSlug !== $this->slug) {
            $this->addError('confirmSlug', 'Type the exact slug to confirm deletion.');

            return null;
        }
        $projects->delete($this->project());
        $this->audit('panel.project.delete', $this->slug);

        return $this->redirect(route('admin.projects'), navigate: true);
    }
```

Update `render()` to pass the purge types:

```php
    public function render()
    {
        return view('livewire.admin.project', [
            'project' => $this->project(),
            'purgeTypes' => self::purgeTypes(),
        ]);
    }
```

- [ ] **Step 4: Add the danger zone to the view** — append before the closing `</div>` of `resources/views/livewire/admin/project.blade.php`:

```blade
    <div class="rounded-xl border border-rose-500/40 bg-ink-850 p-6 space-y-4">
        <flux:heading size="lg" class="text-rose-400">Danger zone</flux:heading>

        <div class="flex flex-wrap items-end gap-3">
            <flux:button wire:click="resetMetrics" wire:confirm="Delete all metrics for this project? Issues/incidents go too.">Reset metrics</flux:button>

            <div class="flex items-end gap-2">
                <flux:select wire:model="purgeTypeChoice" class="max-w-40">
                    @foreach ($purgeTypes as $t)<flux:select.option value="{{ $t }}">{{ ucfirst($t) }}</flux:select.option>@endforeach
                </flux:select>
                <flux:button wire:click="purge" wire:confirm="Purge all stored events of this type?">Purge type</flux:button>
            </div>
        </div>

        <flux:separator />

        <div class="space-y-2">
            <flux:subheading>Delete this project and all its data. Type <span class="font-mono">{{ $project->slug }}</span> to confirm.</flux:subheading>
            <div class="flex items-end gap-2">
                <flux:input wire:model="confirmSlug" class="max-w-xs" />
                <flux:button variant="danger" wire:click="deleteProject">Delete project</flux:button>
            </div>
            <flux:error name="confirmSlug" />
        </div>
    </div>
```

- [ ] **Step 5: Run to verify it passes**

Run: `ddev artisan test --filter=AdminProjectTest`
Expected: PASS (all 6 cases).

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Admin/Project.php resources/views/livewire/admin/project.blade.php tests/Feature/AdminProjectTest.php
git commit -m "feat(admin): per-project danger zone (reset/purge/delete)"
```

---

### Task 5: Audit log page

**Files:**
- Create: `app/Livewire/Admin/Audit.php`
- Create: `resources/views/livewire/admin/audit.blade.php`
- Modify: `routes/web.php` (`/admin/audit`)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (Audit nav item)
- Modify: `tests/Feature/PanelLayoutRendersTest.php`
- Test: `tests/Feature/AdminAuditTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::auditLog`.
- Produces: route `admin.audit`; view data key `entries`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/AdminAuditTest.php
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
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=AdminAuditTest`
Expected: FAIL (`Class "App\Livewire\Admin\Audit" not found`).

- [ ] **Step 3: Implement the component**

```php
// app/Livewire/Admin/Audit.php
<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Audit extends Component
{
    public function mount(): void
    {
        $this->authorize('panel.manage');
    }

    public function render(DashboardRepository $dashboard)
    {
        return view('livewire.admin.audit', [
            'entries' => $dashboard->auditLog(200),
        ]);
    }
}
```

- [ ] **Step 4: Implement the view**

```blade
{{-- resources/views/livewire/admin/audit.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">Audit log</flux:heading>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Time</flux:table.column>
                <flux:table.column>Actor</flux:table.column>
                <flux:table.column>Action</flux:table.column>
                <flux:table.column>Target</flux:table.column>
                <flux:table.column>Method</flux:table.column>
                <flux:table.column>IP</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($entries as $entry)
                    <flux:table.row wire:key="audit-{{ $entry->id }}">
                        <flux:table.cell class="font-mono text-xs text-slate-400">{{ $entry->created_at }}</flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $entry->actor }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $entry->action }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $entry->target }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $entry->method }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs text-slate-400">{{ $entry->ip }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No audit entries yet.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
```

- [ ] **Step 5: Register the route** — in `routes/web.php`, add the import and route in the `auth` group near `admin.projects`:

```php
use App\Livewire\Admin\Audit as AdminAudit;
// ...
Route::get('/admin/audit', AdminAudit::class)->middleware('can:panel.manage')->name('admin.audit');
```

- [ ] **Step 6: Add the nav item** — in `resources/views/components/layouts/app/sidebar.blade.php`, inside the `@can('panel.manage')` block in the "Warden" group, after the Projects item:

```blade
                        <flux:navlist.item icon="clipboard-document-list" :href="route('admin.audit')" :current="request()->routeIs('admin.audit')" wire:navigate>Audit</flux:navlist.item>
```

Verify the icon exists: `ls vendor/livewire/flux/stubs/resources/views/flux/icon/clipboard-document-list.blade.php` (if absent, fall back to `document-text`).

- [ ] **Step 7: Add the page to the render test** (`tests/Feature/PanelLayoutRendersTest.php`)

Add to the dataset, after `'/admin/projects/{slug}/manage'`:

```php
    '/admin/audit',
```

- [ ] **Step 8: Run to verify it passes**

Run: `ddev artisan test --filter=AdminAuditTest && ddev artisan test --filter=PanelLayoutRendersTest`
Expected: PASS.

- [ ] **Step 9: Full suite + build + commit**

```bash
ddev artisan test
ddev npm run build
git add app/Livewire/Admin/Audit.php resources/views/livewire/admin/audit.blade.php routes/web.php resources/views/components/layouts/app/sidebar.blade.php tests/Feature/PanelLayoutRendersTest.php tests/Feature/AdminAuditTest.php
git commit -m "feat(admin): read-only audit log page"
```

---

## Phase 6a Done — Definition of Done

- `ddev artisan test` green; `ddev npm run build` succeeds.
- Admin can create, **rotate**, **activate/deactivate**, **edit full details**, **reset metrics**, **purge a type**, and **delete** projects — each action audited and destructive ones confirmed; the **Audit log** page lists entries with `wire:poll`. All gated by `panel.manage`; new pages (`/admin/projects/{slug}/manage`, `/admin/audit`) covered by `PanelLayoutRendersTest`.
- All access via `ProjectManager` / `DashboardRepository` / `AuditLog` model; package unmodified; no secret leaks to logs or client state.
- **Chrome DevTools local validation** performed on the Projects list (rotate/toggle), the per-project page (settings + danger zone), and the Audit log — zero console errors — before the final commit.

## Validation note (manual, before final commit)

After Task 5, validate live via Chrome DevTools against DDEV (`https://warden-panel.ddev.site`), logged in as an admin:
1. `/admin/projects` → Rotate (snippet re-shows), Deactivate/Activate (badge flips).
2. Manage → edit name/group/tags → Save (success callout); danger zone: Reset metrics, Purge type, Delete (type-to-confirm → redirects).
3. `/admin/audit` → the actions above appear as `panel.*` rows.
Confirm zero console errors at each step.
