# Warden Panel — Phase 4: Issues + Incidents — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the reliability surface — an issues list (filterable by status), an issue detail page with the full lifecycle (resolve / ignore / reopen / assign / snooze / comment) gated behind `panel.manage`, and an incidents list — reading the Warden read layer and mutating only through the package's `IssueWorkflow` service.

**Architecture:** Three Livewire components under `App\Livewire\Project`. Reads go through `DashboardRepository`; lifecycle mutations go through `VictorStochero\Warden\Issues\IssueWorkflow` operating on a `VictorStochero\Warden\Models\Issue` loaded by id. Management actions authorize `panel.manage` (admin-only); viewing is auth-only. No package modification.

**Tech Stack:** Laravel 12.62, Livewire 4, Flux 2.15, Tailwind v4 (Warden DS theme), Pest, DDEV.

## Global Constraints

- **Do NOT modify `vendor/victorstochero/warden`.** Reuse only.
- Read issue/incident data ONLY through `DashboardRepository`. Mutate issues ONLY through `IssueWorkflow`. No direct `wdn_*` writes from panel code (loading a `Models\Issue` by id to hand to `IssueWorkflow` is allowed — it is the package's own model). No direct `wdn_*` read queries in panel code beyond what `DashboardRepository` exposes.
- **DDEV runtime:** all commands via `ddev` — `ddev artisan test`, `ddev npm run build`. Bare `php`/`npm` fail on the host.
- Real-time = Livewire `wire:poll` from `config('panel.poll_seconds')` (default 3) on the list pages. The issue detail page does NOT poll (it carries action state).
- Viewing issues/incidents is auth-gated (view-only). Lifecycle MUTATIONS (resolve/ignore/reopen/assign/snooze/comment) require `panel.manage` — authorize in EVERY action method (Livewire actions are client-invocable; a guard only in `mount()` is insufficient).
- Unknown project slug → 404 (`DashboardRepository::project()` `firstOrFail()`). Unknown issue id → 404 (`DashboardRepository::issue()` returns null → abort 404).
- Warden DS theme classes (`bg-ink-850`, `text-brand-400`, `text-rose-400`, `shadow-glow`, `font-mono`).
- Tests use Pest; test DB SQLite `:memory:`; seed projects with `ddev artisan warden:project` (parent-mode). Seed issues by `VictorStochero\Warden\Models\Issue::create([...])` (the model is `guarded = []`). Do NOT use `warden:demo`.
- Append nav items to the existing contextual "Project" sidebar group; keep all existing items.

## Read/service-layer reference (exact signatures — consume verbatim)

- `DashboardRepository::project(string $idOrSlug): Project`
- `DashboardRepository::issues(int $projectId, array $filters): Collection` — filters: `status` (e.g. `'open'|'resolved'|'ignored'`), `assignee`, `priority`, `order` (default `'last_seen_at'`), `limit` (default 100). Rows are `\stdClass` from `wdn_issues` with columns incl. `id, status, class, message, priority, count, users_affected, last_seen_at, assignee, fingerprint, snoozed_until`.
- `DashboardRepository::issue(int $projectId, int $issueId): ?\stdClass` — null when not found; `->stack` is decoded to an array.
- `DashboardRepository::comments(int $issueId): Collection` of `\stdClass {author, body, created_at}` (oldest first).
- `DashboardRepository::incidents(int $projectId, int $limit = 20): Collection<int,\stdClass>` (open first, then by `started_at` desc).
- `IssueWorkflow::resolve(Issue $issue): void`, `ignore(Issue): void`, `reopen(Issue): void`, `assign(Issue, ?string $assignee): void`, `snooze(Issue, int $minutes): void`, `comment(Issue, string $author, string $body): ?IssueComment`.
- `VictorStochero\Warden\Models\Issue` — load with `Issue::query()->where('project_id', $pid)->where('id', $id)->firstOrFail()`.

---

### Task 1: Issues list page (filterable by status)

**Files:**
- Create: `app/Livewire/Project/Issues.php`
- Create: `resources/views/livewire/project/issues.blade.php`
- Modify: `routes/web.php` (`/projects/{slug}/issues`)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (Issues nav item)
- Test: `tests/Feature/ProjectIssuesTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::project(string)`, `::issues(int,array)`.
- Produces: route `project.issues`; view data key `issues`; the issue-detail route name `project.issue` (params `slug`,`issueId`) used for row links (created in Task 2). A public `string $status = 'open'` filter property (allowed values `'open'`,`'resolved'`,`'ignored'`,`''`=all).

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ProjectIssuesTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Issues;
use Livewire\Livewire;
use VictorStochero\Warden\Models\Issue;

function seedProject(string $name): int
{
    test()->artisan('warden:project', ['name' => $name])->assertSuccessful();
    return \VictorStochero\Warden\Models\Project::where('slug', \Illuminate\Support\Str::slug($name))->firstOrFail()->id;
}

it('lists issues and filters by status', function () {
    $pid = seedProject('Reliability App');
    Issue::create(['project_id' => $pid, 'fingerprint' => 'fp-open', 'status' => 'open', 'class' => 'RuntimeException', 'message' => 'boom', 'count' => 3, 'last_seen_at' => now()]);
    Issue::create(['project_id' => $pid, 'fingerprint' => 'fp-res', 'status' => 'resolved', 'class' => 'LogicException', 'message' => 'fixed', 'count' => 1, 'last_seen_at' => now()]);
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Issues::class, ['slug' => 'reliability-app'])
        ->assertViewHas('issues', fn ($i) => $i->count() === 1)          // default status=open → only the open one
        ->assertSee('RuntimeException')
        ->set('status', 'resolved')
        ->assertViewHas('issues', fn ($i) => $i->count() === 1)
        ->assertSee('LogicException')
        ->set('status', '')                                              // all
        ->assertViewHas('issues', fn ($i) => $i->count() === 2);
});

it('requires auth for the issues list', function () {
    $this->get('/projects/reliability-app/issues')->assertRedirect('/login');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=ProjectIssuesTest`
Expected: FAIL (component/route missing).

- [ ] **Step 3: Implement the component**

```php
// app/Livewire/Project/Issues.php
<?php

namespace App\Livewire\Project;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Issues extends Component
{
    public string $slug;

    #[Url]
    public string $status = 'open';

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $dashboard->project($slug);
    }

    public function render(DashboardRepository $dashboard)
    {
        $project = $dashboard->project($this->slug);
        $filters = $this->status !== '' ? ['status' => $this->status] : [];

        return view('livewire.project.issues', [
            'project' => $project,
            'issues' => $dashboard->issues($project->id, $filters),
        ]);
    }
}
```

- [ ] **Step 4: Implement the view**

```blade
{{-- resources/views/livewire/project/issues.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Issues</flux:heading>
        <flux:select wire:model.live="status" class="max-w-40">
            <flux:select.option value="open">Open</flux:select.option>
            <flux:select.option value="resolved">Resolved</flux:select.option>
            <flux:select.option value="ignored">Ignored</flux:select.option>
            <flux:select.option value="">All</flux:select.option>
        </flux:select>
    </div>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Issue</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Count</flux:table.column>
                <flux:table.column>Last seen</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($issues as $issue)
                    <flux:table.row wire:key="issue-{{ $issue->id }}">
                        <flux:table.cell>
                            <a class="text-brand-400 font-mono text-sm" href="{{ route('project.issue', ['slug' => $project->slug, 'issueId' => $issue->id]) }}" wire:navigate>
                                {{ $issue->class }}
                            </a>
                            <div class="text-slate-400 text-xs truncate max-w-md">{{ $issue->message }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $issue->status }}</flux:table.cell>
                        <flux:table.cell class="font-mono">{{ number_format($issue->count) }}</flux:table.cell>
                        <flux:table.cell class="text-slate-400 text-sm">{{ $issue->last_seen_at }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No issues for this filter.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
```
(The `route('project.issue', ...)` link is created in Task 2 — forward-wired, as in Phase 3.)

- [ ] **Step 5: Register the route**

In `routes/web.php` `auth` group:
```php
use App\Livewire\Project\Issues as ProjectIssues;
Route::get('/projects/{slug}/issues', ProjectIssues::class)->name('project.issues');
```

- [ ] **Step 6: Add the Issues nav item**

In the sidebar "Project" group (after Traces):
```blade
<flux:navlist.item :href="route('project.issues', $slug)" :current="request()->routeIs('project.issues') || request()->routeIs('project.issue')" wire:navigate>Issues</flux:navlist.item>
```

- [ ] **Step 7: Run to verify it passes**

Run: `ddev artisan test --filter=ProjectIssuesTest`
Expected: PASS.

- [ ] **Step 8: Build + commit**

```bash
ddev npm run build
git add -A && git commit -m "feat(project): issues list with status filter"
```

---

### Task 2: Issue detail + lifecycle actions

**Files:**
- Create: `app/Livewire/Project/Issue.php`
- Create: `resources/views/livewire/project/issue.blade.php`
- Modify: `routes/web.php` (`/projects/{slug}/issues/{issueId}`)
- Test: `tests/Feature/ProjectIssueTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::project`, `::issue`, `::comments`; `IssueWorkflow::{resolve,ignore,reopen,assign,snooze,comment}`; `Models\Issue`.
- Produces: route `project.issue` (params `slug`,`issueId`). Public action methods `resolve()`, `ignore()`, `reopen()`, `assignToMe()`, `unassign()`, `snooze()` (1 day), `addComment()` (uses `public string $newComment`).

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ProjectIssueTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Issue as IssueComponent;
use Livewire\Livewire;
use VictorStochero\Warden\Models\Issue;
use VictorStochero\Warden\Models\Project;

function seedIssue(): array
{
    test()->artisan('warden:project', ['name' => 'Ops App'])->assertSuccessful();
    $pid = Project::where('slug', 'ops-app')->firstOrFail()->id;
    $issue = Issue::create(['project_id' => $pid, 'fingerprint' => 'fp-1', 'status' => 'open', 'class' => 'RuntimeException', 'message' => 'kaboom', 'count' => 5, 'last_seen_at' => now()]);
    return [$pid, $issue->id];
}

it('renders an issue with its comments', function () {
    [$pid, $id] = seedIssue();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(IssueComponent::class, ['slug' => 'ops-app', 'issueId' => $id])
        ->assertViewHas('issue', fn ($i) => $i->class === 'RuntimeException')
        ->assertViewHas('comments')
        ->assertSet('issueId', $id);
});

it('lets an admin resolve and comment, denies a viewer', function () {
    [$pid, $id] = seedIssue();
    $admin = User::factory()->create(['is_admin' => true]);
    $viewer = User::factory()->create(['is_admin' => false]);

    // admin resolves
    Livewire::actingAs($admin)->test(IssueComponent::class, ['slug' => 'ops-app', 'issueId' => $id])
        ->call('resolve');
    expect(Issue::find($id)->status)->toBe('resolved');

    // admin comments
    Livewire::actingAs($admin)->test(IssueComponent::class, ['slug' => 'ops-app', 'issueId' => $id])
        ->set('newComment', 'looking into it')
        ->call('addComment')
        ->assertSet('newComment', '');
    expect(\VictorStochero\Warden\Models\IssueComment::where('issue_id', $id)->count())->toBe(1);

    // viewer is forbidden to resolve
    Livewire::actingAs($viewer)->test(IssueComponent::class, ['slug' => 'ops-app', 'issueId' => $id])
        ->call('reopen')
        ->assertForbidden();
});

it('404s for an unknown issue', function () {
    seedIssue();
    $user = User::factory()->create();
    $this->actingAs($user)->get('/projects/ops-app/issues/999999')->assertNotFound();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=ProjectIssueTest`
Expected: FAIL.

- [ ] **Step 3: Implement the component**

```php
// app/Livewire/Project/Issue.php
<?php

namespace App\Livewire\Project;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Issues\IssueWorkflow;
use VictorStochero\Warden\Models\Issue as IssueModel;

#[Layout('components.layouts.app')]
class Issue extends Component
{
    public string $slug;

    public int $issueId;

    public string $newComment = '';

    protected int $projectId;

    public function mount(string $slug, int $issueId, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $this->issueId = $issueId;
        $this->projectId = $dashboard->project($slug)->id;
    }

    /** Load the package Issue model for a mutation, scoped to this project. */
    protected function model(DashboardRepository $dashboard): IssueModel
    {
        $projectId = $dashboard->project($this->slug)->id;

        return IssueModel::query()->where('project_id', $projectId)->where('id', $this->issueId)->firstOrFail();
    }

    public function resolve(DashboardRepository $dashboard, IssueWorkflow $workflow): void
    {
        $this->authorize('panel.manage');
        $workflow->resolve($this->model($dashboard));
    }

    public function ignore(DashboardRepository $dashboard, IssueWorkflow $workflow): void
    {
        $this->authorize('panel.manage');
        $workflow->ignore($this->model($dashboard));
    }

    public function reopen(DashboardRepository $dashboard, IssueWorkflow $workflow): void
    {
        $this->authorize('panel.manage');
        $workflow->reopen($this->model($dashboard));
    }

    public function assignToMe(DashboardRepository $dashboard, IssueWorkflow $workflow): void
    {
        $this->authorize('panel.manage');
        $workflow->assign($this->model($dashboard), Auth::user()->name ?? Auth::user()->email);
    }

    public function unassign(DashboardRepository $dashboard, IssueWorkflow $workflow): void
    {
        $this->authorize('panel.manage');
        $workflow->assign($this->model($dashboard), null);
    }

    public function snooze(DashboardRepository $dashboard, IssueWorkflow $workflow): void
    {
        $this->authorize('panel.manage');
        $workflow->snooze($this->model($dashboard), 1440); // 1 day
    }

    public function addComment(DashboardRepository $dashboard, IssueWorkflow $workflow): void
    {
        $this->authorize('panel.manage');
        $this->validate(['newComment' => 'required|string|max:2000']);
        $author = Auth::user()->name ?? Auth::user()->email;
        $workflow->comment($this->model($dashboard), $author, $this->newComment);
        $this->newComment = '';
    }

    public function render(DashboardRepository $dashboard)
    {
        $project = $dashboard->project($this->slug);
        $issue = $dashboard->issue($project->id, $this->issueId);
        abort_if($issue === null, 404);

        return view('livewire.project.issue', [
            'project' => $project,
            'issue' => $issue,
            'comments' => $dashboard->comments($this->issueId),
        ]);
    }
}
```

- [ ] **Step 4: Implement the view**

```blade
{{-- resources/views/livewire/project/issue.blade.php --}}
<div class="space-y-6">
    <a href="{{ route('project.issues', $project->slug) }}" class="text-brand-400 text-sm" wire:navigate>← Issues</a>

    <div class="rounded-xl bg-ink-850 p-4 space-y-2">
        <flux:heading size="lg" class="font-mono">{{ $issue->class }}</flux:heading>
        <p class="text-slate-300">{{ $issue->message }}</p>
        <div class="flex gap-4 text-sm font-mono text-slate-400">
            <span>status: <span class="text-brand-400">{{ $issue->status }}</span></span>
            <span>count: {{ number_format($issue->count) }}</span>
            <span>assignee: {{ $issue->assignee ?? '—' }}</span>
        </div>
    </div>

    @can('panel.manage')
        <div class="flex flex-wrap gap-2">
            <flux:button wire:click="resolve" size="sm" variant="primary">Resolve</flux:button>
            <flux:button wire:click="ignore" size="sm">Ignore</flux:button>
            <flux:button wire:click="reopen" size="sm">Reopen</flux:button>
            <flux:button wire:click="assignToMe" size="sm">Assign to me</flux:button>
            <flux:button wire:click="unassign" size="sm">Unassign</flux:button>
            <flux:button wire:click="snooze" size="sm">Snooze 1d</flux:button>
        </div>
    @endcan

    @if (is_array($issue->stack) && count($issue->stack))
        <div class="rounded-xl bg-ink-850 p-4">
            <flux:heading size="lg" class="mb-2">Stack</flux:heading>
            <pre class="font-mono text-xs text-slate-400 overflow-x-auto">{{ json_encode($issue->stack, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif

    <div class="rounded-xl bg-ink-850 p-4 space-y-3">
        <flux:heading size="lg">Comments</flux:heading>
        @forelse ($comments as $c)
            <div class="border-l-2 border-ink-700 pl-3">
                <div class="text-xs text-slate-500 font-mono">{{ $c->author }} · {{ $c->created_at }}</div>
                <div class="text-slate-300 text-sm">{{ $c->body }}</div>
            </div>
        @empty
            <div class="text-slate-400 text-sm">No comments yet.</div>
        @endforelse

        @can('panel.manage')
            <form wire:submit="addComment" class="flex gap-2 pt-2">
                <flux:input wire:model="newComment" placeholder="Add a comment…" class="flex-1" />
                <flux:button type="submit" variant="primary" size="sm">Comment</flux:button>
            </form>
        @endcan
    </div>
</div>
```

- [ ] **Step 5: Register the route**

In `routes/web.php` `auth` group (after `/projects/{slug}/issues`):
```php
use App\Livewire\Project\Issue as ProjectIssue;
Route::get('/projects/{slug}/issues/{issueId}', ProjectIssue::class)->name('project.issue');
```

- [ ] **Step 6: Run to verify it passes**

Run: `ddev artisan test --filter=ProjectIssueTest`
Expected: PASS.

- [ ] **Step 7: Build + commit**

```bash
ddev npm run build
git add -A && git commit -m "feat(project): issue detail with lifecycle actions (gated by panel.manage)"
```

---

### Task 3: Incidents list

**Files:**
- Create: `app/Livewire/Project/Incidents.php`
- Create: `resources/views/livewire/project/incidents.blade.php`
- Modify: `routes/web.php` (`/projects/{slug}/incidents`)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (Incidents nav item)
- Test: `tests/Feature/ProjectIncidentsTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::project`, `::incidents(int,int)`.
- Produces: route `project.incidents`; view data key `incidents`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ProjectIncidentsTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Incidents;
use Livewire\Livewire;

it('renders the incidents list for a project', function () {
    $this->artisan('warden:project', ['name' => 'Uptime App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Incidents::class, ['slug' => 'uptime-app'])
        ->assertViewHas('incidents')
        ->assertViewHas('project');
});

it('requires auth for the incidents list', function () {
    $this->get('/projects/uptime-app/incidents')->assertRedirect('/login');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=ProjectIncidentsTest`
Expected: FAIL.

- [ ] **Step 3: Implement the component**

```php
// app/Livewire/Project/Incidents.php
<?php

namespace App\Livewire\Project;

use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Incidents extends Component
{
    public string $slug;

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $dashboard->project($slug);
    }

    public function render(DashboardRepository $dashboard)
    {
        $project = $dashboard->project($this->slug);

        return view('livewire.project.incidents', [
            'project' => $project,
            'incidents' => $dashboard->incidents($project->id, 30),
        ]);
    }
}
```

- [ ] **Step 4: Implement the view**

```blade
{{-- resources/views/livewire/project/incidents.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Incidents</flux:heading>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Incident</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Started</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($incidents as $incident)
                    <flux:table.row wire:key="incident-{{ $incident->id }}">
                        <flux:table.cell class="font-mono text-sm">#{{ $incident->id }} {{ $incident->title ?? $incident->kind ?? '' }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="@if(($incident->status ?? '') === 'open') text-rose-400 @else text-slate-400 @endif">{{ $incident->status ?? '' }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="text-slate-400 text-sm">{{ $incident->started_at ?? '' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No incidents recorded.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
```
(Incident columns aren't part of this task's contract — access optional props defensively with `??`. The test only asserts the view data is present.)

- [ ] **Step 5: Register the route**

In `routes/web.php` `auth` group:
```php
use App\Livewire\Project\Incidents as ProjectIncidents;
Route::get('/projects/{slug}/incidents', ProjectIncidents::class)->name('project.incidents');
```

- [ ] **Step 6: Add the Incidents nav item**

In the sidebar "Project" group (after Issues):
```blade
<flux:navlist.item :href="route('project.incidents', $slug)" :current="request()->routeIs('project.incidents')" wire:navigate>Incidents</flux:navlist.item>
```

- [ ] **Step 7: Run to verify it passes**

Run: `ddev artisan test --filter=ProjectIncidentsTest`
Expected: PASS.

- [ ] **Step 8: Full suite + build + commit**

```bash
ddev artisan test
ddev npm run build
git add -A && git commit -m "feat(project): incidents list"
```

---

## Phase 4 Done — Definition of Done

- `ddev artisan test` green; `ddev npm run build` succeeds.
- The project sidebar links Issues + Incidents. The issues list filters by status; an issue opens to a detail page where an admin can resolve/ignore/reopen/assign/snooze/comment and a viewer cannot (actions 403). Incidents list renders.
- All reads via `DashboardRepository`; all issue mutations via `IssueWorkflow`; package unmodified; lifecycle actions authorize `panel.manage` in every action method.

## Self-review notes (addressed)

- **Authorization:** every mutating action calls `$this->authorize('panel.manage')` itself (not just `mount()`), since Livewire actions are client-invocable. The view also `@can`-gates the buttons (UX), but the server check is the real gate (tested with a viewer → 403).
- **No direct writes:** mutations go through `IssueWorkflow`; the only direct model use is loading `Models\Issue` by id to hand to the workflow (the package's own model).
- **Test design:** seeds real `Issue` rows via the package model (`guarded=[]`), asserts real status transitions in the DB and a real comment row, plus the viewer-forbidden path and 404. Uses `warden:project` (parent-mode), never `warden:demo`.

## Out of scope (subsequent plans)

- **Phase 5 — Events + Logs.**
- **Phase 6 — Admin completeness + deploy (docker-compose, README).**
