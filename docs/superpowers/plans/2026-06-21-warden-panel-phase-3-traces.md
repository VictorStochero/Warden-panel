# Warden Panel — Phase 3: Traces — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the traces surface to the per-project dashboard — a recent-traces list, a single-trace waterfall, and a distributed (multi-app) waterfall — reading the Warden package read layer, themed to match Warden 0.3.5, refreshing via `wire:poll`.

**Architecture:** Three Livewire components under `App\Livewire\Project`. A pure `App\Support\Waterfall` helper turns a span collection into positioned bars (left%/width%/color/label) so the single-trace and distributed views share identical geometry and it can be unit-tested without a DB. Reads go only through `DashboardRepository`. No package modification.

**Tech Stack:** Laravel 12.62, Livewire 4, Flux 2.15, Tailwind v4 (Warden DS theme from Phase 1), Pest, DDEV.

## Global Constraints

- **Do NOT modify `vendor/victorstochero/warden`.** Reuse only.
- Read traces ONLY through `VictorStochero\Warden\Dashboard\DashboardRepository`. No direct `wdn_*` queries in panel code.
- **DDEV runtime:** all commands via `ddev` — `ddev artisan test`, `ddev npm run build`. Bare `php`/`npm` fail on the host.
- Real-time = Livewire `wire:poll` only; interval from `config('panel.poll_seconds')` (default 3).
- Every page is auth-gated (`auth` middleware), view-only (no `panel.manage` to view). Unknown project slug → 404 (`DashboardRepository::project()` uses `firstOrFail()`).
- Warden DS theme classes (`bg-ink-850`, `text-brand-400`, `shadow-glow`, `font-mono`).
- Tests use Pest; test DB SQLite `:memory:`; seed projects with `ddev artisan warden:project` (parent-mode; `warden:demo` is child-only — do NOT use it in parent-mode tests).
- Add nav items to the existing contextual "Project" group in `resources/views/components/layouts/app/sidebar.blade.php` (created in Phase 2 with Overview/Database/Jobs/HTTP/Schedule/Uptime). Append the Traces item; keep the others intact.

## Read-layer reference (exact signatures + shapes — consume verbatim)

- `project(string $idOrSlug): Project`
- `recentTraces(int $projectId, int $limit = 30): Collection` of TraceRow `{trace_id:string, type:string, label:string, duration_us:int, occurred_at:string|null, errored:bool}`
- `trace(int $projectId, string $traceId): Collection` of span arrays `{id:int, type:string, span_id:string|null, parent_span_id:string|null, occurred_at:string, duration_us:int|null, payload:array, n_plus_one:bool, repeat_count:int}` (ordered by `occurred_at`)
- `traceProjects(string $traceId): Collection` of `\stdClass {id:int, name:string, slug:string}` — the distinct projects a trace touches
- `distributedTrace(string $traceId, Collection $projects): Collection` of span arrays (same span shape) each additionally tagged `project_name:string`, `project_slug:string`, ordered by `occurred_at, id`

**Waterfall geometry (reference — implemented in `App\Support\Waterfall`):** for each span `start = Carbon::parse(occurred_at)->format('U.u')` (float seconds), `end = start + (duration_us ?? 0)/1_000_000`. `min = min(starts)`, `max = max(ends)`, `span = max(max - min, 0.000001)`. Bar `left% = (start - min)/span * 100`, `width% = max(0.6, (end - start)/span * 100)`.

---

### Task 1: Traces list page

**Files:**
- Create: `app/Livewire/Project/Traces.php`
- Create: `resources/views/livewire/project/traces.blade.php`
- Modify: `routes/web.php` (`/projects/{slug}/traces`)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (Traces nav item)
- Test: `tests/Feature/ProjectTracesTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::project(string)`, `::recentTraces(int,int): Collection`.
- Produces: route `project.traces`; view data key `traces`. The trace-detail route name `project.trace` (params `slug`,`traceId`) used for row links (created in Task 2).

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ProjectTracesTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Traces;
use Livewire\Livewire;

it('renders the traces list for a project', function () {
    $this->artisan('warden:project', ['name' => 'Trace App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Traces::class, ['slug' => 'trace-app'])
        ->assertViewHas('traces')
        ->assertViewHas('project');
});

it('requires auth for the traces list', function () {
    $this->get('/projects/trace-app/traces')->assertRedirect('/login');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=ProjectTracesTest`
Expected: FAIL (component/route missing).

- [ ] **Step 3: Implement the component**

```php
// app/Livewire/Project/Traces.php
<?php

namespace App\Livewire\Project;

use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Traces extends Component
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

        return view('livewire.project.traces', [
            'project' => $project,
            'traces' => $dashboard->recentTraces($project->id, 40),
        ]);
    }
}
```

- [ ] **Step 4: Implement the view**

```blade
{{-- resources/views/livewire/project/traces.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Traces</flux:heading>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Trace</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Entry</flux:table.column>
                <flux:table.column>Duration</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($traces as $t)
                    <flux:table.row wire:key="trace-{{ $t['trace_id'] }}">
                        <flux:table.cell class="font-mono text-xs">
                            <a class="text-brand-400 @if($t['errored']) text-rose-400 @endif"
                               href="{{ route('project.trace', ['slug' => $project->slug, 'traceId' => $t['trace_id']]) }}"
                               wire:navigate>{{ \Illuminate\Support\Str::limit($t['trace_id'], 16) }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $t['type'] }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs max-w-md truncate">{{ $t['label'] }}</flux:table.cell>
                        <flux:table.cell>{{ round($t['duration_us'] / 1000) }}ms</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No traces captured yet.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
```
(TraceRow values are arrays — use `$t['trace_id']` etc. The `route('project.trace', ...)` link target is created in Task 2; this task's tests don't render the route, but to keep the list usable before Task 2, the route is added in Task 2. If you run the page manually before Task 2, the link will 404 — acceptable, it's wired forward exactly like Phase 1's overview→project links were.)

- [ ] **Step 5: Register the route**

In `routes/web.php` `auth` group:
```php
use App\Livewire\Project\Traces as ProjectTraces;
Route::get('/projects/{slug}/traces', ProjectTraces::class)->name('project.traces');
```

- [ ] **Step 6: Add the Traces nav item**

In the sidebar "Project" group (after the Uptime item):
```blade
<flux:navlist.item :href="route('project.traces', $slug)" :current="request()->routeIs('project.traces') || request()->routeIs('project.trace')" wire:navigate>Traces</flux:navlist.item>
```

- [ ] **Step 7: Run to verify it passes**

Run: `ddev artisan test --filter=ProjectTracesTest`
Expected: PASS.

- [ ] **Step 8: Build + commit**

```bash
ddev npm run build
git add -A && git commit -m "feat(project): traces list page"
```

---

### Task 2: Single-trace waterfall + the Waterfall helper

**Files:**
- Create: `app/Support/Waterfall.php`
- Create: `app/Livewire/Project/Trace.php`
- Create: `resources/views/livewire/project/trace.blade.php`
- Modify: `routes/web.php` (`/projects/{slug}/traces/{traceId}`)
- Test: `tests/Unit/WaterfallTest.php`, `tests/Feature/ProjectTraceTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::trace(int,string): Collection`.
- Produces: route `project.trace` (params `slug`, `traceId`); `App\Support\Waterfall::rows(Collection $spans): array` returning each span plus `_left:float`, `_width:float`, `_color:string`, `_label:string`; `Waterfall::color(string $type): string`; `Waterfall::label(array $span): string`.

- [ ] **Step 1: Write the failing unit test for the helper (pure geometry — deterministic)**

```php
// tests/Unit/WaterfallTest.php
<?php

use App\Support\Waterfall;
use Illuminate\Support\Collection;

it('positions spans proportionally across the trace window', function () {
    $spans = new Collection([
        ['type' => 'request', 'occurred_at' => '2026-06-21 12:00:00.000000', 'duration_us' => 1_000_000, 'payload' => ['method' => 'GET', 'path' => '/x']],
        ['type' => 'query',   'occurred_at' => '2026-06-21 12:00:00.500000', 'duration_us' => 500_000,   'payload' => ['sql' => 'select 1']],
    ]);

    $rows = Waterfall::rows($spans);

    expect($rows)->toHaveCount(2);
    // request spans the full window: left 0, width 100
    expect(round($rows[0]['_left']))->toBe(0.0);
    expect(round($rows[0]['_width']))->toBe(100.0);
    // query starts at 50% and runs to the end: left 50, width 50
    expect(round($rows[1]['_left']))->toBe(50.0);
    expect(round($rows[1]['_width']))->toBe(50.0);
});

it('returns an empty array for no spans', function () {
    expect(Waterfall::rows(new Collection()))->toBe([]);
});

it('labels and colors spans by type', function () {
    expect(Waterfall::label(['type' => 'query', 'payload' => ['sql' => 'select 1']]))->toBe('select 1');
    expect(Waterfall::label(['type' => 'request', 'payload' => ['method' => 'GET', 'path' => '/x']]))->toBe('GET /x');
    expect(Waterfall::color('query'))->toBeString();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=WaterfallTest`
Expected: FAIL (class missing).

- [ ] **Step 3: Implement the helper**

```php
// app/Support/Waterfall.php
<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Waterfall
{
    /** Type → bar colour (hex), matching the Warden DS span palette. */
    public static function color(string $type): string
    {
        return [
            'request' => '#2E7BFF',
            'query' => '#5BD98F',
            'http' => '#FFB020',
            'cache' => '#8FB6FF',
            'job' => '#FFC04D',
            'exception' => '#FF5A52',
            'log' => '#9BA7C0',
        ][$type] ?? '#64748b';
    }

    /** Human label for a span, derived from its payload per type. */
    public static function label(array $span): string
    {
        $p = $span['payload'] ?? [];

        return match ($span['type'] ?? '') {
            'query' => (string) ($p['sql'] ?? 'query'),
            'request' => trim(($p['method'] ?? '').' '.($p['route'] ?? $p['path'] ?? '')),
            'http' => trim(($p['method'] ?? '').' '.($p['host'] ?? '')),
            'cache' => trim(($p['action'] ?? 'cache').' '.($p['key'] ?? '')),
            'job' => trim(($p['status'] ?? '').' '.($p['class'] ?? '')),
            'exception' => (string) ($p['class'] ?? 'exception'),
            'log' => '['.($p['level'] ?? 'info').'] '.($p['message'] ?? ''),
            default => (string) ($span['type'] ?? ''),
        };
    }

    /**
     * Turn ordered spans into positioned waterfall rows. Each returned row is
     * the original span plus `_left`/`_width` (percent) and `_color`/`_label`.
     *
     * @param  Collection<int, array<string, mixed>>  $spans
     * @return list<array<string, mixed>>
     */
    public static function rows(Collection $spans): array
    {
        if ($spans->isEmpty()) {
            return [];
        }

        $timed = $spans->map(function (array $s): array {
            $start = (float) Carbon::parse($s['occurred_at'])->format('U.u');
            $s['_start'] = $start;
            $s['_end'] = $start + (($s['duration_us'] ?? 0) / 1_000_000);

            return $s;
        });

        $min = $timed->min('_start');
        $max = $timed->max('_end');
        $window = max($max - $min, 0.000001);

        return $timed->map(function (array $s) use ($min, $window): array {
            $s['_left'] = (($s['_start'] - $min) / $window) * 100;
            $s['_width'] = max(0.6, (($s['_end'] - $s['_start']) / $window) * 100);
            $s['_color'] = self::color((string) ($s['type'] ?? ''));
            $s['_label'] = self::label($s);

            return $s;
        })->values()->all();
    }
}
```

- [ ] **Step 4: Run the unit test to verify it passes**

Run: `ddev artisan test --filter=WaterfallTest`
Expected: PASS.

- [ ] **Step 5: Write the failing feature test for the component**

```php
// tests/Feature/ProjectTraceTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Trace;
use Livewire\Livewire;

it('renders a trace waterfall page', function () {
    $this->artisan('warden:project', ['name' => 'Span App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Trace::class, ['slug' => 'span-app', 'traceId' => 'abc123'])
        ->assertViewHas('rows')
        ->assertViewHas('project')
        ->assertSet('traceId', 'abc123');
});

it('requires auth for the trace page', function () {
    $this->get('/projects/span-app/traces/abc123')->assertRedirect('/login');
});
```

- [ ] **Step 6: Run to verify it fails**

Run: `ddev artisan test --filter=ProjectTraceTest`
Expected: FAIL.

- [ ] **Step 7: Implement the component**

```php
// app/Livewire/Project/Trace.php
<?php

namespace App\Livewire\Project;

use App\Support\Waterfall;
use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Trace extends Component
{
    public string $slug;

    public string $traceId;

    public function mount(string $slug, string $traceId, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $this->traceId = $traceId;
        $dashboard->project($slug);
    }

    public function render(DashboardRepository $dashboard)
    {
        $project = $dashboard->project($this->slug);
        $spans = $dashboard->trace($project->id, $this->traceId);

        return view('livewire.project.trace', [
            'project' => $project,
            'rows' => Waterfall::rows($spans),
        ]);
    }
}
```

- [ ] **Step 8: Implement the view**

```blade
{{-- resources/views/livewire/project/trace.blade.php --}}
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('project.traces', $project->slug) }}" class="text-brand-400 text-sm" wire:navigate>← Traces</a>
        <span class="font-mono text-xs text-slate-500">{{ $traceId }}</span>
    </div>
    <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Trace</flux:heading>

    <div class="rounded-xl bg-ink-850 p-4 space-y-1">
        @forelse ($rows as $row)
            <div class="flex items-center gap-2 text-xs">
                <div class="w-1/3 truncate font-mono text-slate-300" style="padding-left: {{ min($row['_left'], 0) }}px">{{ \Illuminate\Support\Str::limit($row['_label'], 60) }}</div>
                <div class="relative h-4 flex-1 rounded bg-ink-900">
                    <div class="absolute h-4 rounded" style="left: {{ $row['_left'] }}%; width: {{ $row['_width'] }}%; background: {{ $row['_color'] }}"></div>
                </div>
                <div class="w-16 text-right font-mono text-slate-400">{{ round(($row['duration_us'] ?? 0) / 1000) }}ms</div>
            </div>
        @empty
            <div class="text-slate-400 text-sm">No spans for this trace.</div>
        @endforelse
    </div>
</div>
```

- [ ] **Step 9: Register the route**

In `routes/web.php` `auth` group:
```php
use App\Livewire\Project\Trace as ProjectTrace;
Route::get('/projects/{slug}/traces/{traceId}', ProjectTrace::class)->name('project.trace');
```
(Register this AFTER `/projects/{slug}/traces` so the literal `traces` segment isn't shadowed; Laravel matches the more specific `traces` route first regardless, but keep order clear.)

- [ ] **Step 10: Run both tests to verify they pass**

Run: `ddev artisan test --filter=WaterfallTest && ddev artisan test --filter=ProjectTraceTest`
Expected: PASS.

- [ ] **Step 11: Build + commit**

```bash
ddev npm run build
git add -A && git commit -m "feat(project): single-trace waterfall + Waterfall helper"
```

---

### Task 3: Distributed (multi-app) trace waterfall

**Files:**
- Modify: `app/Livewire/Project/Trace.php` (detect multi-project traces; stitch via `distributedTrace`)
- Modify: `resources/views/livewire/project/trace.blade.php` (show the origin app per span when distributed)
- Test: `tests/Feature/DistributedTraceTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::traceProjects(string): Collection`, `::distributedTrace(string, Collection): Collection`.
- Produces: view data keys `rows` (already), `projects` (the touched-projects collection), `distributed` (bool). Distributed span rows additionally carry `project_name`/`project_slug` (from `distributedTrace`).

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/DistributedTraceTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Trace;
use Livewire\Livewire;

it('exposes distributed-trace context', function () {
    $this->artisan('warden:project', ['name' => 'Edge App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Trace::class, ['slug' => 'edge-app', 'traceId' => 'xyz789'])
        ->assertViewHas('projects')
        ->assertViewHas('distributed')
        ->assertViewHas('rows');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=DistributedTraceTest`
Expected: FAIL (`projects`/`distributed` not in view data).

- [ ] **Step 3: Update `Trace::render()` to detect + stitch**

Replace the body of `render()` in `app/Livewire/Project/Trace.php`:
```php
public function render(DashboardRepository $dashboard)
{
    $project = $dashboard->project($this->slug);
    $projects = $dashboard->traceProjects($this->traceId);
    $distributed = $projects->count() > 1;

    $spans = $distributed
        ? $dashboard->distributedTrace($this->traceId, $projects)
        : $dashboard->trace($project->id, $this->traceId);

    return view('livewire.project.trace', [
        'project' => $project,
        'projects' => $projects,
        'distributed' => $distributed,
        'rows' => Waterfall::rows($spans),
    ]);
}
```
(When distributed, each row carries `project_name`/`project_slug` from `distributedTrace`; the single-project path leaves those keys absent — guard with `??` in the view.)

- [ ] **Step 4: Show the origin app per span when distributed**

In `resources/views/livewire/project/trace.blade.php`, add a distributed banner after the heading:
```blade
@if ($distributed)
    <div class="flex flex-wrap gap-2">
        @foreach ($projects as $p)
            <flux:badge>{{ $p->name }}</flux:badge>
        @endforeach
    </div>
@endif
```
And in the span row's label cell, prefix the origin app when present:
```blade
<div class="w-1/3 truncate font-mono text-slate-300">
    @isset($row['project_name'])<span class="text-brand-400">{{ $row['project_name'] }}</span> @endisset{{ \Illuminate\Support\Str::limit($row['_label'], 50) }}
</div>
```
(Replace the existing label cell `<div class="w-1/3 ...">...</div>` from Task 2 with this version.)

- [ ] **Step 5: Run to verify it passes**

Run: `ddev artisan test --filter=DistributedTraceTest && ddev artisan test --filter=ProjectTraceTest && ddev artisan test --filter=WaterfallTest`
Expected: PASS (the single-trace and helper tests still pass — the single-project path is unchanged for a one-project trace).

- [ ] **Step 6: Full suite + build + commit**

```bash
ddev artisan test
ddev npm run build
git add -A && git commit -m "feat(project): distributed multi-app trace waterfall"
```

---

## Phase 3 Done — Definition of Done

- `ddev artisan test` green; `ddev npm run build` succeeds.
- The project sidebar has a Traces item; the list links each trace to its waterfall; a trace spanning multiple apps renders one stitched timeline with each span tagged by origin app.
- All reads via `DashboardRepository`; package unmodified; every page auth-gated; `Waterfall` geometry unit-tested.

## Self-review notes (addressed)

- **DRY:** the `Waterfall` helper is the single source of bar geometry + labels/colours, shared by the single and distributed views and unit-tested in isolation (no DB).
- **Test design:** the helper has deterministic geometry tests (synthetic spans → known left/width); the components have feature tests asserting real view data + auth-gating. Seeding uses `warden:project` (parent-mode), never `warden:demo`.
- **YAGNI:** no client-side charting lib — bars are CSS percentages, matching the package's zero-JS waterfall and the panel's no-extra-infra posture.

## Out of scope (subsequent plans)

- **Phase 4 — Issues (lifecycle) + Incidents.**
- **Phase 5 — Events + Logs.**
- **Phase 6 — Admin completeness + deploy (docker-compose, README).**
