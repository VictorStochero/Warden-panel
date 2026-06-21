# Warden Panel — Phase 2: Per-Project Dashboard — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the live per-project dashboard — a project overview (KPIs + request series + top routes) plus the database, jobs, http, schedule and uptime sections — each reading the Warden package read layer and refreshing via `wire:poll`, themed to match Warden 0.3.5.

**Architecture:** Each surface is a class-based Livewire component wrapped in the app layout, resolving the project via `DashboardRepository::project($slug)` and reading section data via the corresponding `DashboardRepository` method. A shared range selector (`15m/1h/6h/24h/7d/30d`, default `1h`) is a public Livewire property bound into every read call. A per-project sidebar nav links the sections. No package modification; reads go only through `DashboardRepository`.

**Tech Stack:** Laravel 12.62, Livewire 4, Flux 2.15, Tailwind v4 (Warden DS theme from Phase 1), Pest, DDEV.

## Global Constraints

- **Do NOT modify `vendor/victorstochero/warden`.** Reuse only.
- Read project/section data ONLY through `VictorStochero\Warden\Dashboard\DashboardRepository`. No direct `wdn_*` queries in panel code.
- **DDEV runtime:** run all commands via `ddev` — `ddev artisan test`, `ddev artisan ...`, `ddev npm run build`. Bare `php`/`npm` fail on the host.
- Valid range values (the only allowed set): `['15m','1h','6h','24h','7d','30d']`, default `'1h'`. Validate the bound range against this list; fall back to `'1h'` on anything else.
- Real-time = Livewire `wire:poll` only; interval from `config('panel.poll_seconds')` (default 3, from Phase 1).
- Every page is auth-gated (`auth` middleware). Project pages are view-only (no `panel.manage` needed to view).
- Use the Warden DS theme classes from Phase 1 (`bg-ink-850`, `text-brand-400`, `shadow-glow`, `font-mono`, `font-wordmark`).
- Tests use Pest; test DB SQLite `:memory:`; seed projects with `ddev artisan warden:project` (parent-mode; `warden:demo` is child-only and must NOT be used in parent-mode tests).
- `DashboardRepository::project(string $idOrSlug): \VictorStochero\Warden\Models\Project` resolves by slug (throws `ModelNotFoundException` → 404 if missing).

## Read-layer reference (exact signatures + return shapes — consume verbatim)

All methods are on `DashboardRepository`. `$range` is one of the valid set above. Project id = `$project->id`.

- `project(string $idOrSlug): Project`
- `kpis(int $projectId, string $range): array` → `{throughput:int, error_rate:float, errors:int, p95:int|null, slow:int, failed_jobs:int, cache_hit_rate:float|null, open_issues:int, open_incidents:int, host:array|null, uptime:float}`
- `requestSeries(int $projectId, string $range): Collection` of `{bucket:string, count:int, errors:int, p95:int|null}`
- `topRoutes(int $projectId, string $range, int $limit = 12, bool $includeWarden = true): Collection` of `{key:string, count:int, avg:int, max:int, errors:int, p95:int|null}`
- `slowQueries(int $projectId, string $range, int $limit = 15): Collection` of `{key:string, sql:string, count:int, avg:int, max:int, slow:int, total:int}`
- `frequentQueries(int $projectId, string $range, int $limit = 15): Collection` (same QueryRow shape)
- `queryHealth(int $projectId, string $range): array` (N+1 / slow / duplicate summary; render as key/value)
- `queues(int $projectId, string $range): Collection` of `{key:string, count:int, processed:int, failures:int, avg:int, max:int}`
- `cacheStores(int $projectId, string $range): Collection` of `{key:string, hits:int, misses:int, rate:float, writes:int}`
- `httpHosts(int $projectId, string $range): Collection` of `{key:string, count:int, errors:int, avg:int, max:int}`
- `scheduleTasks(int $projectId, string $range): Collection` of `{key:string, count:int, avg:int, max:int}`
- `uptime(int $projectId, string $range = '30d'): float`
- `uptimeWindows(int $projectId, string $configured = '30d'): array` → `list<{label:string, pct:float, active:bool}>`
- `downtimeIncidents(int $projectId, int $days = 30, int $limit = 50): Collection`

---

### Task 1: Project page shell — route, range selector, KPIs header, sidebar nav

**Files:**
- Create: `app/Livewire/Project/Show.php`
- Create: `resources/views/livewire/project/show.blade.php`
- Create: `app/Support/Ranges.php` (shared range allow-list + validation)
- Modify: `routes/web.php` (add `/projects/{slug}` inside the `auth` group)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (Fleet link + project section nav)
- Test: `tests/Feature/ProjectShowTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::project(string): Project`, `::kpis(int,string): array`.
- Produces: `App\Support\Ranges::all(): array` and `Ranges::sanitize(?string): string`; route name `project.show` (param `slug`); the per-project section route-name convention `project.<section>` (slug param) used by later tasks for nav `:current` checks.

- [ ] **Step 1: Write `App\Support\Ranges` failing test**

```php
// tests/Feature/ProjectShowTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Show;
use App\Support\Ranges;
use Livewire\Livewire;

it('sanitizes ranges to the allow-list', function () {
    expect(Ranges::sanitize('6h'))->toBe('6h');
    expect(Ranges::sanitize('bogus'))->toBe('1h');
    expect(Ranges::sanitize(null))->toBe('1h');
    expect(Ranges::all())->toBe(['15m','1h','6h','24h','7d','30d']);
});

it('renders project KPIs and supports range switching', function () {
    $this->artisan('warden:project', ['name' => 'Checkout API'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Show::class, ['slug' => 'checkout-api'])
        ->assertViewHas('kpis')
        ->assertViewHas('project')
        ->assertSet('range', '1h')
        ->set('range', '24h')
        ->assertSet('range', '24h')
        ->set('range', 'bogus')
        ->assertSet('range', '1h');
});

it('404s for an unknown project', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/projects/does-not-exist')->assertNotFound();
});

it('requires auth for project pages', function () {
    $this->get('/projects/checkout-api')->assertRedirect('/login');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=ProjectShowTest`
Expected: FAIL (Ranges/component/route missing).

- [ ] **Step 3: Implement `App\Support\Ranges`**

```php
// app/Support/Ranges.php
<?php

namespace App\Support;

class Ranges
{
    /** @return list<string> */
    public static function all(): array
    {
        return ['15m', '1h', '6h', '24h', '7d', '30d'];
    }

    public static function sanitize(?string $range): string
    {
        return in_array($range, self::all(), true) ? $range : '1h';
    }
}
```

- [ ] **Step 4: Implement the `Show` component**

```php
// app/Livewire/Project/Show.php
<?php

namespace App\Livewire\Project;

use App\Support\Ranges;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\Project;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public string $slug;

    #[Url]
    public string $range = '1h';

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        // Resolve once to 404 early on unknown slug.
        $dashboard->project($slug);
    }

    public function updatedRange(): void
    {
        $this->range = Ranges::sanitize($this->range);
    }

    public function render(DashboardRepository $dashboard)
    {
        $this->range = Ranges::sanitize($this->range);
        $project = $dashboard->project($this->slug);

        return view('livewire.project.show', [
            'project' => $project,
            'ranges' => Ranges::all(),
            'kpis' => $dashboard->kpis($project->id, $this->range),
        ]);
    }
}
```

- [ ] **Step 5: Implement the view (KPIs grid + range selector + `wire:poll`)**

```blade
{{-- resources/views/livewire/project/show.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }}</flux:heading>
        <flux:select wire:model.live="range" class="max-w-32">
            @foreach ($ranges as $r)
                <flux:select.option value="{{ $r }}">{{ $r }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @php($cards = [
            ['Throughput', number_format($kpis['throughput'])],
            ['Error rate', $kpis['error_rate'].'%'],
            ['p95', $kpis['p95'] !== null ? $kpis['p95'].'ms' : '—'],
            ['Failed jobs', number_format($kpis['failed_jobs'])],
            ['Open issues', $kpis['open_issues']],
            ['Open incidents', $kpis['open_incidents']],
            ['Uptime', round($kpis['uptime'], 2).'%'],
            ['Cache hit', $kpis['cache_hit_rate'] !== null ? $kpis['cache_hit_rate'].'%' : '—'],
        ])
        @foreach ($cards as [$label, $value])
            <div class="rounded-xl bg-ink-850 p-4 @if($loop->first) shadow-glow @endif">
                <div class="text-slate-400 text-sm">{{ $label }}</div>
                <div class="font-mono text-2xl text-brand-400">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    {{-- Request series + top routes added in Task 2 --}}
</div>
```

- [ ] **Step 6: Register the route**

In `routes/web.php`, inside the `auth` group:
```php
use App\Livewire\Project\Show as ProjectShow;
Route::get('/projects/{slug}', ProjectShow::class)->name('project.show');
```

- [ ] **Step 7: Add Fleet + project nav to the sidebar**

In `resources/views/components/layouts/app/sidebar.blade.php`, replace the "Platform" navlist group with:
```blade
<flux:navlist.group heading="Warden" class="grid">
    <flux:navlist.item icon="server" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>Fleet</flux:navlist.item>
    @can('panel.manage')
        <flux:navlist.item icon="folder-cog" :href="route('admin.projects')" :current="request()->routeIs('admin.projects')" wire:navigate>Projects</flux:navlist.item>
    @endcan
</flux:navlist.group>
```
(The starter kit's GitHub/docs links below may be removed or kept — remove them to keep the panel clean.)

- [ ] **Step 8: Run to verify it passes**

Run: `ddev artisan test --filter=ProjectShowTest`
Expected: PASS.

- [ ] **Step 9: Build + commit**

```bash
ddev npm run build
git add -A && git commit -m "feat(project): project page shell with KPIs, range selector, sidebar nav"
```

---

### Task 2: Project overview — request series + top routes

**Files:**
- Modify: `app/Livewire/Project/Show.php` (add `series` + `routes` to the view data)
- Modify: `resources/views/livewire/project/show.blade.php` (render series summary + top-routes table)
- Test: `tests/Feature/ProjectOverviewDataTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::requestSeries(int,string): Collection`, `::topRoutes(int,string,int,bool): Collection`.
- Produces: view data keys `series`, `routes` on the project show page.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ProjectOverviewDataTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Show;
use Livewire\Livewire;

it('exposes request series and top routes for the project', function () {
    $this->artisan('warden:project', ['name' => 'Billing'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Show::class, ['slug' => 'billing'])
        ->assertViewHas('series')
        ->assertViewHas('routes');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=ProjectOverviewDataTest`
Expected: FAIL (view lacks `series`/`routes`).

- [ ] **Step 3: Add series + routes to `Show::render()`**

In `app/Livewire/Project/Show.php` `render()`, extend the view array:
```php
'series' => $dashboard->requestSeries($project->id, $this->range),
'routes' => $dashboard->topRoutes($project->id, $this->range, 12, false),
```

- [ ] **Step 4: Render them in the view**

Append before the closing `</div>` of `resources/views/livewire/project/show.blade.php`:
```blade
<div class="rounded-xl bg-ink-850 p-4">
    <flux:heading size="lg" class="mb-3">Requests</flux:heading>
    <div class="font-mono text-sm text-slate-400">
        {{ $series->sum('count') }} requests · {{ $series->sum('errors') }} errors across {{ $series->count() }} buckets
    </div>
</div>

<div class="rounded-xl bg-ink-850 p-4">
    <flux:heading size="lg" class="mb-3">Top routes</flux:heading>
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Route</flux:table.column>
            <flux:table.column>Count</flux:table.column>
            <flux:table.column>p95</flux:table.column>
            <flux:table.column>Errors</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($routes as $row)
                <flux:table.row wire:key="route-{{ $loop->index }}">
                    <flux:table.cell class="font-mono">{{ $row['key'] }}</flux:table.cell>
                    <flux:table.cell>{{ number_format($row['count']) }}</flux:table.cell>
                    <flux:table.cell>{{ $row['p95'] !== null ? $row['p95'].'ms' : '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $row['errors'] }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
```
(`$row` is an array — use array access `$row['key']`, matching the RouteRow shape.)

- [ ] **Step 5: Run to verify it passes**

Run: `ddev artisan test --filter=ProjectOverviewDataTest`
Expected: PASS.

- [ ] **Step 6: Build + commit**

```bash
ddev npm run build
git add -A && git commit -m "feat(project): request series summary + top routes table"
```

---

### Task 3: Database section — slow/frequent queries + query health

**Files:**
- Create: `app/Livewire/Project/Database.php`
- Create: `resources/views/livewire/project/database.blade.php`
- Modify: `routes/web.php` (`/projects/{slug}/database`)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (section nav — see note)
- Test: `tests/Feature/ProjectDatabaseTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::slowQueries(int,string,int): Collection`, `::frequentQueries(int,string,int): Collection`, `::queryHealth(int,string): array`.
- Produces: route `project.database`; the reusable section-page pattern (resolve project, sanitize range, wire:poll) that Tasks 4-7 follow.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ProjectDatabaseTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Database;
use Livewire\Livewire;

it('renders the database section for a project', function () {
    $this->artisan('warden:project', ['name' => 'Shop'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Database::class, ['slug' => 'shop'])
        ->assertViewHas('slowQueries')
        ->assertViewHas('frequentQueries')
        ->assertViewHas('queryHealth')
        ->assertViewHas('project');
});

it('requires auth for the database section', function () {
    $this->get('/projects/shop/database')->assertRedirect('/login');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=ProjectDatabaseTest`
Expected: FAIL.

- [ ] **Step 3: Implement the component**

```php
// app/Livewire/Project/Database.php
<?php

namespace App\Livewire\Project;

use App\Support\Ranges;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Database extends Component
{
    public string $slug;

    #[Url]
    public string $range = '1h';

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $dashboard->project($slug);
    }

    public function render(DashboardRepository $dashboard)
    {
        $this->range = Ranges::sanitize($this->range);
        $project = $dashboard->project($this->slug);

        return view('livewire.project.database', [
            'project' => $project,
            'ranges' => Ranges::all(),
            'slowQueries' => $dashboard->slowQueries($project->id, $this->range, 15),
            'frequentQueries' => $dashboard->frequentQueries($project->id, $this->range, 15),
            'queryHealth' => $dashboard->queryHealth($project->id, $this->range),
        ]);
    }
}
```

- [ ] **Step 4: Implement the view**

```blade
{{-- resources/views/livewire/project/database.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Database</flux:heading>
        <flux:select wire:model.live="range" class="max-w-32">
            @foreach ($ranges as $r)<flux:select.option value="{{ $r }}">{{ $r }}</flux:select.option>@endforeach
        </flux:select>
    </div>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:heading size="lg" class="mb-3">Query health</flux:heading>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 font-mono text-sm">
            @foreach ($queryHealth as $label => $value)
                <div><span class="text-slate-400">{{ $label }}:</span> {{ is_scalar($value) ? $value : json_encode($value) }}</div>
            @endforeach
        </div>
    </div>

    @php($tables = [['Slowest queries', $slowQueries], ['Most frequent queries', $frequentQueries]])
    @foreach ($tables as [$title, $rows])
        <div class="rounded-xl bg-ink-850 p-4">
            <flux:heading size="lg" class="mb-3">{{ $title }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Query</flux:table.column>
                    <flux:table.column>Count</flux:table.column>
                    <flux:table.column>Avg</flux:table.column>
                    <flux:table.column>Max</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($rows as $row)
                        <flux:table.row wire:key="{{ $title }}-{{ $loop->index }}">
                            <flux:table.cell class="font-mono text-xs max-w-md truncate">{{ $row['sql'] }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($row['count']) }}</flux:table.cell>
                            <flux:table.cell>{{ $row['avg'] }}ms</flux:table.cell>
                            <flux:table.cell>{{ $row['max'] }}ms</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endforeach
</div>
```

- [ ] **Step 5: Register the route**

In `routes/web.php` `auth` group:
```php
use App\Livewire\Project\Database as ProjectDatabase;
Route::get('/projects/{slug}/database', ProjectDatabase::class)->name('project.database');
```

- [ ] **Step 6: Add the project section nav to the sidebar**

In `resources/views/components/layouts/app/sidebar.blade.php`, add (after the "Warden" group) a contextual group shown only on project pages:
```blade
@php($slug = request()->route('slug'))
@if ($slug)
    <flux:navlist.group heading="Project" class="grid">
        <flux:navlist.item :href="route('project.show', $slug)" :current="request()->routeIs('project.show')" wire:navigate>Overview</flux:navlist.item>
        <flux:navlist.item :href="route('project.database', $slug)" :current="request()->routeIs('project.database')" wire:navigate>Database</flux:navlist.item>
        {{-- jobs/http/schedule/uptime items added by their tasks --}}
    </flux:navlist.group>
@endif
```

- [ ] **Step 7: Run to verify it passes**

Run: `ddev artisan test --filter=ProjectDatabaseTest`
Expected: PASS.

- [ ] **Step 8: Build + commit**

```bash
ddev npm run build
git add -A && git commit -m "feat(project): database section (slow/frequent queries + query health)"
```

---

### Task 4: Jobs section — queues

**Files:**
- Create: `app/Livewire/Project/Jobs.php`
- Create: `resources/views/livewire/project/jobs.blade.php`
- Modify: `routes/web.php` (`/projects/{slug}/jobs`)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (add Jobs nav item)
- Test: `tests/Feature/ProjectJobsTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::queues(int,string): Collection` of `{key,count,processed,failures,avg,max}`.
- Produces: route `project.jobs`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ProjectJobsTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Jobs;
use Livewire\Livewire;

it('renders the jobs section for a project', function () {
    $this->artisan('warden:project', ['name' => 'Worker'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Jobs::class, ['slug' => 'worker'])
        ->assertViewHas('queues')
        ->assertViewHas('project');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=ProjectJobsTest`
Expected: FAIL.

- [ ] **Step 3: Implement the component** (mirror `Database`, swapping the read call):

```php
// app/Livewire/Project/Jobs.php
<?php

namespace App\Livewire\Project;

use App\Support\Ranges;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Jobs extends Component
{
    public string $slug;

    #[Url]
    public string $range = '1h';

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $dashboard->project($slug);
    }

    public function render(DashboardRepository $dashboard)
    {
        $this->range = Ranges::sanitize($this->range);
        $project = $dashboard->project($this->slug);

        return view('livewire.project.jobs', [
            'project' => $project,
            'ranges' => Ranges::all(),
            'queues' => $dashboard->queues($project->id, $this->range),
        ]);
    }
}
```

- [ ] **Step 4: Implement the view**

```blade
{{-- resources/views/livewire/project/jobs.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Jobs</flux:heading>
        <flux:select wire:model.live="range" class="max-w-32">
            @foreach ($ranges as $r)<flux:select.option value="{{ $r }}">{{ $r }}</flux:select.option>@endforeach
        </flux:select>
    </div>
    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Queue / Job</flux:table.column>
                <flux:table.column>Processed</flux:table.column>
                <flux:table.column>Failures</flux:table.column>
                <flux:table.column>Avg</flux:table.column>
                <flux:table.column>Max</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($queues as $row)
                    <flux:table.row wire:key="queue-{{ $loop->index }}">
                        <flux:table.cell class="font-mono">{{ $row['key'] }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($row['processed']) }}</flux:table.cell>
                        <flux:table.cell class="@if($row['failures']>0) text-rose-400 @endif">{{ $row['failures'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['avg'] }}ms</flux:table.cell>
                        <flux:table.cell>{{ $row['max'] }}ms</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</div>
```

- [ ] **Step 5: Register route + nav**

In `routes/web.php` `auth` group:
```php
use App\Livewire\Project\Jobs as ProjectJobs;
Route::get('/projects/{slug}/jobs', ProjectJobs::class)->name('project.jobs');
```
In the sidebar "Project" group, add:
```blade
<flux:navlist.item :href="route('project.jobs', $slug)" :current="request()->routeIs('project.jobs')" wire:navigate>Jobs</flux:navlist.item>
```

- [ ] **Step 6: Run to verify it passes**

Run: `ddev artisan test --filter=ProjectJobsTest`
Expected: PASS.

- [ ] **Step 7: Build + commit**

```bash
ddev npm run build
git add -A && git commit -m "feat(project): jobs section (queues)"
```

---

### Task 5: HTTP section — outbound hosts

**Files:**
- Create: `app/Livewire/Project/Http.php`
- Create: `resources/views/livewire/project/http.blade.php`
- Modify: `routes/web.php` (`/projects/{slug}/http`)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (add Http nav item)
- Test: `tests/Feature/ProjectHttpTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::httpHosts(int,string): Collection` of `{key,count,errors,avg,max}`.
- Produces: route `project.http`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ProjectHttpTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Http;
use Livewire\Livewire;

it('renders the http section for a project', function () {
    $this->artisan('warden:project', ['name' => 'Gateway'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Http::class, ['slug' => 'gateway'])
        ->assertViewHas('hosts')
        ->assertViewHas('project');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=ProjectHttpTest`
Expected: FAIL.

- [ ] **Step 3: Implement the component**

```php
// app/Livewire/Project/Http.php
<?php

namespace App\Livewire\Project;

use App\Support\Ranges;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Http extends Component
{
    public string $slug;

    #[Url]
    public string $range = '1h';

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $dashboard->project($slug);
    }

    public function render(DashboardRepository $dashboard)
    {
        $this->range = Ranges::sanitize($this->range);
        $project = $dashboard->project($this->slug);

        return view('livewire.project.http', [
            'project' => $project,
            'ranges' => Ranges::all(),
            'hosts' => $dashboard->httpHosts($project->id, $this->range),
        ]);
    }
}
```

- [ ] **Step 4: Implement the view**

```blade
{{-- resources/views/livewire/project/http.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · HTTP</flux:heading>
        <flux:select wire:model.live="range" class="max-w-32">
            @foreach ($ranges as $r)<flux:select.option value="{{ $r }}">{{ $r }}</flux:select.option>@endforeach
        </flux:select>
    </div>
    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Host</flux:table.column>
                <flux:table.column>Count</flux:table.column>
                <flux:table.column>Errors</flux:table.column>
                <flux:table.column>Avg</flux:table.column>
                <flux:table.column>Max</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($hosts as $row)
                    <flux:table.row wire:key="host-{{ $loop->index }}">
                        <flux:table.cell class="font-mono">{{ $row['key'] }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($row['count']) }}</flux:table.cell>
                        <flux:table.cell class="@if($row['errors']>0) text-rose-400 @endif">{{ $row['errors'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['avg'] }}ms</flux:table.cell>
                        <flux:table.cell>{{ $row['max'] }}ms</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</div>
```

- [ ] **Step 5: Register route + nav**

In `routes/web.php` `auth` group:
```php
use App\Livewire\Project\Http as ProjectHttp;
Route::get('/projects/{slug}/http', ProjectHttp::class)->name('project.http');
```
Sidebar "Project" group add:
```blade
<flux:navlist.item :href="route('project.http', $slug)" :current="request()->routeIs('project.http')" wire:navigate>HTTP</flux:navlist.item>
```

- [ ] **Step 6: Run to verify it passes**

Run: `ddev artisan test --filter=ProjectHttpTest`
Expected: PASS.

- [ ] **Step 7: Build + commit**

```bash
ddev npm run build
git add -A && git commit -m "feat(project): http section (outbound hosts)"
```

---

### Task 6: Schedule section — scheduled tasks

**Files:**
- Create: `app/Livewire/Project/Schedule.php`
- Create: `resources/views/livewire/project/schedule.blade.php`
- Modify: `routes/web.php` (`/projects/{slug}/schedule`)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (add Schedule nav item)
- Test: `tests/Feature/ProjectScheduleTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::scheduleTasks(int,string): Collection` of `{key,count,avg,max}`.
- Produces: route `project.schedule`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ProjectScheduleTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Schedule;
use Livewire\Livewire;

it('renders the schedule section for a project', function () {
    $this->artisan('warden:project', ['name' => 'Cron App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Schedule::class, ['slug' => 'cron-app'])
        ->assertViewHas('tasks')
        ->assertViewHas('project');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=ProjectScheduleTest`
Expected: FAIL.

- [ ] **Step 3: Implement the component**

```php
// app/Livewire/Project/Schedule.php
<?php

namespace App\Livewire\Project;

use App\Support\Ranges;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Schedule extends Component
{
    public string $slug;

    #[Url]
    public string $range = '1h';

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $dashboard->project($slug);
    }

    public function render(DashboardRepository $dashboard)
    {
        $this->range = Ranges::sanitize($this->range);
        $project = $dashboard->project($this->slug);

        return view('livewire.project.schedule', [
            'project' => $project,
            'ranges' => Ranges::all(),
            'tasks' => $dashboard->scheduleTasks($project->id, $this->range),
        ]);
    }
}
```

- [ ] **Step 4: Implement the view**

```blade
{{-- resources/views/livewire/project/schedule.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Schedule</flux:heading>
        <flux:select wire:model.live="range" class="max-w-32">
            @foreach ($ranges as $r)<flux:select.option value="{{ $r }}">{{ $r }}</flux:select.option>@endforeach
        </flux:select>
    </div>
    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Task</flux:table.column>
                <flux:table.column>Runs</flux:table.column>
                <flux:table.column>Avg</flux:table.column>
                <flux:table.column>Max</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($tasks as $row)
                    <flux:table.row wire:key="task-{{ $loop->index }}">
                        <flux:table.cell class="font-mono">{{ $row['key'] }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($row['count']) }}</flux:table.cell>
                        <flux:table.cell>{{ $row['avg'] }}ms</flux:table.cell>
                        <flux:table.cell>{{ $row['max'] }}ms</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</div>
```

- [ ] **Step 5: Register route + nav**

In `routes/web.php` `auth` group:
```php
use App\Livewire\Project\Schedule as ProjectSchedule;
Route::get('/projects/{slug}/schedule', ProjectSchedule::class)->name('project.schedule');
```
Sidebar "Project" group add:
```blade
<flux:navlist.item :href="route('project.schedule', $slug)" :current="request()->routeIs('project.schedule')" wire:navigate>Schedule</flux:navlist.item>
```

- [ ] **Step 6: Run to verify it passes**

Run: `ddev artisan test --filter=ProjectScheduleTest`
Expected: PASS.

- [ ] **Step 7: Build + commit**

```bash
ddev npm run build
git add -A && git commit -m "feat(project): schedule section (scheduled tasks)"
```

---

### Task 7: Uptime section — availability + downtime incidents

**Files:**
- Create: `app/Livewire/Project/Uptime.php`
- Create: `resources/views/livewire/project/uptime.blade.php`
- Modify: `routes/web.php` (`/projects/{slug}/uptime`)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (add Uptime nav item)
- Test: `tests/Feature/ProjectUptimeTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::uptime(int,string): float`, `::uptimeWindows(int,string): array` (`list<{label,pct,active}>`), `::downtimeIncidents(int,int,int): Collection`.
- Produces: route `project.uptime`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ProjectUptimeTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Uptime;
use Livewire\Livewire;

it('renders the uptime section for a project', function () {
    $this->artisan('warden:project', ['name' => 'Status App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Uptime::class, ['slug' => 'status-app'])
        ->assertViewHas('uptime')
        ->assertViewHas('windows')
        ->assertViewHas('incidents')
        ->assertViewHas('project');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=ProjectUptimeTest`
Expected: FAIL.

- [ ] **Step 3: Implement the component**

```php
// app/Livewire/Project/Uptime.php
<?php

namespace App\Livewire\Project;

use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Uptime extends Component
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

        return view('livewire.project.uptime', [
            'project' => $project,
            'uptime' => $dashboard->uptime($project->id, '30d'),
            'windows' => $dashboard->uptimeWindows($project->id, '30d'),
            'incidents' => $dashboard->downtimeIncidents($project->id, 30, 50),
        ]);
    }
}
```
(Uptime windows are fixed availability windows, not the shared range — no range selector here.)

- [ ] **Step 4: Implement the view**

```blade
{{-- resources/views/livewire/project/uptime.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Uptime</flux:heading>

    <div class="rounded-xl bg-ink-850 p-4 shadow-glow">
        <div class="text-slate-400 text-sm">30-day availability</div>
        <div class="font-mono text-3xl text-brand-400">{{ round($uptime, 3) }}%</div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach ($windows as $w)
            <div class="rounded-xl bg-ink-850 p-3 @if($w['active']) ring-1 ring-brand-500 @endif">
                <div class="text-slate-400 text-xs">{{ $w['label'] }}</div>
                <div class="font-mono text-lg">{{ round($w['pct'], 2) }}%</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:heading size="lg" class="mb-3">Downtime incidents (30d)</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Incident</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($incidents as $incident)
                    <flux:table.row wire:key="dt-{{ $loop->index }}">
                        <flux:table.cell class="font-mono text-sm">{{ $incident->title ?? ('Incident #'.($incident->id ?? $loop->index)) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No downtime in the last 30 days.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
```
(`downtimeIncidents` returns `Collection<int,\stdClass>` — access object props with `?->`/`??`. Keep the cell defensive since the exact incident columns aren't part of this task's contract; the test only asserts the view data is present.)

- [ ] **Step 5: Register route + nav**

In `routes/web.php` `auth` group:
```php
use App\Livewire\Project\Uptime as ProjectUptime;
Route::get('/projects/{slug}/uptime', ProjectUptime::class)->name('project.uptime');
```
Sidebar "Project" group add:
```blade
<flux:navlist.item :href="route('project.uptime', $slug)" :current="request()->routeIs('project.uptime')" wire:navigate>Uptime</flux:navlist.item>
```

- [ ] **Step 6: Run to verify it passes**

Run: `ddev artisan test --filter=ProjectUptimeTest`
Expected: PASS.

- [ ] **Step 7: Build + commit**

```bash
ddev npm run build
git add -A && git commit -m "feat(project): uptime section (availability + downtime)"
```

---

## Phase 2 Done — Definition of Done

- `ddev artisan test` green; `ddev npm run build` succeeds.
- From the fleet overview, clicking a project opens its live page; the range selector switches `15m/1h/6h/24h/7d/30d` and the data re-reads; the sidebar "Project" group links Overview / Database / Jobs / HTTP / Schedule / Uptime.
- All reads go through `DashboardRepository`; package unmodified; every page auth-gated.

## Self-review notes (addressed)

- **Range consistency:** `App\Support\Ranges` (Task 1) is the single allow-list; every section sanitizes through it. Uptime intentionally uses fixed availability windows, not the shared range.
- **DRY vs YAGNI:** the section components share an identical shape (slug + range + mount-resolve + render-read). They are deliberately kept as separate small components (one responsibility, independently testable) rather than one mega-component switching on a `section` param — matches the file-per-responsibility guidance. If a later phase adds many more sections, extracting a base `ProjectSection` component is the right refactor then, not now.
- **Test design:** each test seeds a real project via `warden:project` (parent-mode) and asserts the component exposes its section's view data + auth-gating — real behavior, not mocks. `warden:demo` is intentionally avoided (child-only).

## Out of scope (subsequent plans)

- **Phase 3 — Traces** (list + waterfall + distributed trace).
- **Phase 4 — Issues (lifecycle) + Incidents.**
- **Phase 5 — Events + Logs.**
- **Phase 6 — Admin completeness + deploy (docker-compose, README).**
