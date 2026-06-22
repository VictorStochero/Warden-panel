# Warden Panel — Parity Wave A: Navigation & Shell — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring the panel's navigation and page shell to parity with the package parent dashboard — grouped sidebar, a shared page header (title + range + LIVE), a consistent 8-KPI strip on section pages, and state banners.

**Architecture:** Three reusable anonymous Blade components under `resources/views/components/panel/` (`page-header`, `kpi-strip`, `banners`), the sidebar regrouped into the package's 5 groups, and each project section view updated to use the shared header/KPI-strip instead of its own range select.

**Tech Stack:** Laravel 12.62, Livewire 4, Flux 2.15, Tailwind v4 (Warden DS), Pest, DDEV.

## Global Constraints

- **Do NOT modify `vendor/victorstochero/warden`.** Reuse only.
- All reads via `DashboardRepository`; no direct `wdn_*` queries in panel code.
- **DDEV runtime:** `ddev artisan test`, `ddev npm run build`. Bare `php`/`npm` fail on the host.
- Range presets are `App\Support\Ranges::all()` = `['15m','1h','6h','24h','7d','30d']`, default `'1h'`, sanitized via `Ranges::sanitize()`.
- Real-time stays `wire:poll.{{ config('panel.poll_seconds') }}s`; the LIVE indicator is visual only.
- `panel.manage` gate (`app/Providers/AppServiceProvider.php:24` → `is_admin === true`).
- Warden DS theme classes (`bg-ink-850`, `text-brand-400`, `text-rose-400`, `font-mono`); only valid Flux icons (verify under `vendor/livewire/flux/stubs/resources/views/flux/icon/<name>.blade.php`).
- `PanelLayoutRendersTest` must stay green (every page → 200).

## Read-layer reference (verbatim)

- `DashboardRepository::kpis(int $projectId, string $range): array` keys: `throughput`, `error_rate`, `errors`, `p95` (nullable), `slow`, `failed_jobs`, `cache_hit_rate` (nullable), `open_issues`, `open_incidents`, `host`, `uptime`.
- `project->capture_profile` (`lean`/`full`/`custom`/null).

---

### Task 1: Shared shell components (page-header, kpi-strip, banners)

**Files:**
- Create: `resources/views/components/panel/page-header.blade.php`
- Create: `resources/views/components/panel/kpi-strip.blade.php`
- Create: `resources/views/components/panel/banners.blade.php`
- Test: `tests/Feature/ShellComponentsTest.php`

**Interfaces:**
- Produces: `<x-panel.page-header :title :range :ranges :showRanges :live />` (range pills call `$set('range', …)` on the host component), `<x-panel.kpi-strip :project :kpis />` (8 KPI cards linking to sections), `<x-panel.banners :project />` (read-only / capture / version banners).

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ShellComponentsTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Show;
use Livewire\Livewire;

function seedShellProject(): string
{
    test()->artisan('warden:project', ['name' => 'Shell App'])->assertSuccessful();
    return 'shell-app';
}

it('renders the page header range pills and KPI strip on the overview', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $slug = seedShellProject();

    Livewire::actingAs($admin)->test(Show::class, ['slug' => $slug])
        ->assertSee('Throughput')      // KPI strip label
        ->assertSee('Open issues')     // KPI strip label
        ->assertSee('6h')              // range pill
        ->set('range', '6h')
        ->assertSet('range', '6h');
});

it('shows the read-only banner to non-admins and hides it from admins', function () {
    $slug = seedShellProject();

    $viewer = User::factory()->create(['is_admin' => false]);
    $this->actingAs($viewer)->get("/projects/{$slug}")->assertSee('read-only');

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get("/projects/{$slug}")->assertDontSee('read-only');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=ShellComponentsTest`
Expected: FAIL (components not yet wired into Show).

- [ ] **Step 3: Create `page-header.blade.php`**

```blade
{{-- resources/views/components/panel/page-header.blade.php --}}
@props([
    'title',
    'subtitle' => null,
    'range' => null,
    'ranges' => [],
    'showRanges' => true,
    'live' => true,
])
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <flux:heading size="xl" class="font-wordmark">{{ $title }}</flux:heading>
        @if ($subtitle)<flux:subheading>{{ $subtitle }}</flux:subheading>@endif
    </div>
    <div class="flex items-center gap-3">
        @if ($live)
            <span class="flex items-center gap-1.5 text-xs text-emerald-400">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                </span>
                LIVE
            </span>
        @endif
        @if ($showRanges && $ranges)
            <div class="flex gap-1 rounded-lg bg-ink-850 p-1">
                @foreach ($ranges as $r)
                    <button type="button" wire:click="$set('range', '{{ $r }}')"
                        @class([
                            'rounded-md px-2.5 py-1 text-xs font-mono transition',
                            'bg-brand-600 text-white' => $range === $r,
                            'text-slate-400 hover:text-slate-200' => $range !== $r,
                        ])>{{ $r }}</button>
                @endforeach
            </div>
        @endif
    </div>
</div>
```

- [ ] **Step 4: Create `kpi-strip.blade.php`**

```blade
{{-- resources/views/components/panel/kpi-strip.blade.php --}}
@props(['project', 'kpis'])
@php($cards = [
    ['Throughput', number_format($kpis['throughput']), route('project.show', $project->slug)],
    ['Error rate', $kpis['error_rate'].'%', route('project.show', $project->slug)],
    ['p95', $kpis['p95'] !== null ? $kpis['p95'].'ms' : '—', route('project.show', $project->slug)],
    ['Slow', number_format($kpis['slow'] ?? 0), route('project.show', $project->slug)],
    ['Failed jobs', number_format($kpis['failed_jobs']), route('project.jobs', $project->slug)],
    ['Cache hit', $kpis['cache_hit_rate'] !== null ? $kpis['cache_hit_rate'].'%' : '—', route('project.database', $project->slug)],
    ['Open issues', $kpis['open_issues'], route('project.issues', $project->slug)],
    ['Uptime', round($kpis['uptime'], 2).'%', route('project.uptime', $project->slug)],
])
<div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-8">
    @foreach ($cards as [$label, $value, $href])
        <a href="{{ $href }}" wire:navigate class="rounded-xl bg-ink-850 p-3 transition hover:bg-ink-800 @if($loop->first) shadow-glow @endif">
            <div class="text-slate-400 text-xs">{{ $label }}</div>
            <div class="font-mono text-lg text-brand-400">{{ $value }}</div>
        </a>
    @endforeach
</div>
```

- [ ] **Step 5: Create `banners.blade.php`**

```blade
{{-- resources/views/components/panel/banners.blade.php --}}
@props(['project' => null])
@cannot('panel.manage')
    <flux:callout variant="warning" class="mb-2">You have read-only access — management actions are hidden.</flux:callout>
@endcannot
@if ($project && in_array($project->capture_profile, ['lean', 'custom'], true))
    <flux:callout variant="secondary" class="mb-2">Capture is reduced ({{ $project->capture_profile }}) — some metrics may be sparse.</flux:callout>
@endif
```

- [ ] **Step 6: Wire header + KPI strip + banners into the Overview view** — rewrite the top of `resources/views/livewire/project/show.blade.php` (replace the heading+select block and the inline KPI grid):

```blade
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name" :range="$range" :ranges="$ranges" />
    <x-panel.kpi-strip :project="$project" :kpis="$kpis" />

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
</div>
```

- [ ] **Step 7: Run to verify it passes**

Run: `ddev artisan test --filter=ShellComponentsTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/views/components/panel tests/Feature/ShellComponentsTest.php resources/views/livewire/project/show.blade.php
git commit -m "feat(shell): shared page-header, kpi-strip, banners components"
```

---

### Task 2: Grouped sidebar

**Files:**
- Modify: `resources/views/components/layouts/app/sidebar.blade.php`
- Test: `tests/Feature/SidebarGroupsTest.php`

**Interfaces:**
- Produces: project nav split into 5 `flux:navlist.group` headings (Overview, Performance, Reliability, Diagnostics, System). System is empty in Wave A (omit the group until B) — render only non-empty groups.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/SidebarGroupsTest.php
<?php

use App\Models\User;
use VictorStochero\Warden\Models\Project;
use Illuminate\Support\Str;

it('groups the project navigation into named sections', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    test()->artisan('warden:project', ['name' => 'Nav App'])->assertSuccessful();
    $slug = Project::where('slug', 'nav-app')->firstOrFail()->slug;

    $this->actingAs($admin)->get("/projects/{$slug}")
        ->assertSee('Performance')
        ->assertSee('Reliability')
        ->assertSee('Diagnostics')
        ->assertSee('Database')
        ->assertSee('Incidents');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=SidebarGroupsTest`
Expected: FAIL (`Performance` heading not present).

- [ ] **Step 3: Regroup the project nav** — replace the single `<flux:navlist.group heading="Project">…</flux:navlist.group>` block in `resources/views/components/layouts/app/sidebar.blade.php` with five groups:

```blade
                @if ($slug)
                    <flux:navlist.group heading="Overview" class="grid">
                        <flux:navlist.item :href="route('project.show', $slug)" :current="request()->routeIs('project.show')" wire:navigate>Overview</flux:navlist.item>
                    </flux:navlist.group>
                    <flux:navlist.group heading="Performance" class="grid">
                        <flux:navlist.item :href="route('project.database', $slug)" :current="request()->routeIs('project.database')" wire:navigate>Database</flux:navlist.item>
                        <flux:navlist.item :href="route('project.jobs', $slug)" :current="request()->routeIs('project.jobs')" wire:navigate>Jobs</flux:navlist.item>
                        <flux:navlist.item :href="route('project.http', $slug)" :current="request()->routeIs('project.http')" wire:navigate>HTTP</flux:navlist.item>
                        <flux:navlist.item :href="route('project.schedule', $slug)" :current="request()->routeIs('project.schedule')" wire:navigate>Schedule</flux:navlist.item>
                    </flux:navlist.group>
                    <flux:navlist.group heading="Reliability" class="grid">
                        <flux:navlist.item :href="route('project.issues', $slug)" :current="request()->routeIs('project.issues') || request()->routeIs('project.issue')" wire:navigate>Issues</flux:navlist.item>
                        <flux:navlist.item :href="route('project.incidents', $slug)" :current="request()->routeIs('project.incidents')" wire:navigate>Incidents</flux:navlist.item>
                        <flux:navlist.item :href="route('project.uptime', $slug)" :current="request()->routeIs('project.uptime')" wire:navigate>Uptime</flux:navlist.item>
                    </flux:navlist.group>
                    <flux:navlist.group heading="Diagnostics" class="grid">
                        <flux:navlist.item :href="route('project.traces', $slug)" :current="request()->routeIs('project.traces') || request()->routeIs('project.trace')" wire:navigate>Traces</flux:navlist.item>
                        <flux:navlist.item :href="route('project.logs', $slug)" :current="request()->routeIs('project.logs')" wire:navigate>Logs</flux:navlist.item>
                        <flux:navlist.item :href="route('project.events', $slug)" :current="request()->routeIs('project.events')" wire:navigate>Events</flux:navlist.item>
                    </flux:navlist.group>
                @endif
```

- [ ] **Step 4: Run to verify it passes**

Run: `ddev artisan test --filter=SidebarGroupsTest && ddev artisan test --filter=PanelLayoutRendersTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/layouts/app/sidebar.blade.php tests/Feature/SidebarGroupsTest.php
git commit -m "feat(shell): group the project sidebar into the package's 5 sections"
```

---

### Task 3: Adopt header + KPI strip across section pages

**Files:**
- Modify components (add `kpis` to render where missing): `app/Livewire/Project/{Database,Jobs,Http,Schedule,Uptime,Logs,Events}.php`
- Modify views (header + kpi-strip, drop local range select): `resources/views/livewire/project/{database,jobs,http,schedule,uptime,logs,events}.blade.php`
- Modify list views (header only): `resources/views/livewire/project/{traces,issues,incidents}.blade.php`

**Interfaces:**
- Consumes: `<x-panel.page-header>`, `<x-panel.kpi-strip>`, `<x-panel.banners>` from Task 1; `DashboardRepository::kpis`.

- [ ] **Step 1: Add `kpis` to each section component's render()** — for `Database, Jobs, Http, Schedule, Logs, Events`, add this key to the `view(...)` data array (these have `#[Url] $range`):

```php
            'kpis' => $dashboard->kpis($project->id, $this->range),
```

For `Uptime` (no `$range` prop), use a fixed window:

```php
            'kpis' => $dashboard->kpis($project->id, '24h'),
```

Confirm each render already resolves `$project` (via `$dashboard->project($this->slug)`); if a component names it differently, use that variable.

- [ ] **Step 2: Update each section view** — at the very top of each of `database, jobs, http, schedule, logs, events, uptime` blade files, replace the existing heading+`<flux:select range>` block with:

```blade
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name . ' · {{SECTION}}'" :range="$range ?? null" :ranges="$ranges ?? []" :showRanges="isset($range)" />
    <x-panel.kpi-strip :project="$project" :kpis="$kpis" />
```

Replace `{{SECTION}}` with the section label per file: Database, Jobs, HTTP, Schedule, Logs, Events, Uptime. Remove the now-duplicate `<flux:select wire:model.live="range">…</flux:select>` and any standalone `<flux:heading>{{ $project->name }} · …</flux:heading>` that the header now renders. Keep the rest of each view (tables, filters specific to the section — e.g. the Logs level filter, the Events type select) intact.

- [ ] **Step 3: Update the list views (header only)** — for `traces, issues, incidents`, replace the standalone top `<flux:heading>` with:

```blade
    <x-panel.page-header :title="$project->name . ' · {{SECTION}}'" :showRanges="false" />
```

Replace `{{SECTION}}` with Traces / Issues / Incidents. Keep each list's own filters (e.g. the Issues status tabs) intact.

- [ ] **Step 4: Run the full suite + render test**

Run: `ddev artisan test`
Expected: PASS (all prior tests + the new ones). If a section view referenced `$ranges`/`$range` that no longer exists, the `?? null`/`?? []` guards keep it safe.

- [ ] **Step 5: Build**

Run: `ddev npm run build`
Expected: success.

- [ ] **Step 6: Chrome DevTools local validation**

Against `https://warden-panel.ddev.site` logged in as an admin:
1. Open a project → confirm the sidebar shows the 5 groups (Overview/Performance/Reliability/Diagnostics).
2. Confirm the header shows the title, range pills, and the LIVE dot; click `6h` → the page updates and the URL gains `?range=6h`.
3. Confirm the 8-KPI strip appears on Overview/Database/Jobs/HTTP/Schedule/Logs/Events/Uptime and the cards link to their sections.
4. Log in as a non-admin (or toggle) → confirm the read-only banner shows.
5. Confirm **zero console errors** on each page.

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Project resources/views/livewire/project
git commit -m "feat(shell): shared header + KPI strip across project sections"
```

---

## Wave A Done — Definition of Done

- `ddev artisan test` green; `ddev npm run build` succeeds.
- Project sidebar grouped into the package's sections; shared header (title + range pills + LIVE) replaces per-page range selects; 8-KPI strip on every section page; read-only/capture banners. Covered by tests + `PanelLayoutRendersTest`.
- All reads via `DashboardRepository`; package unmodified.
- Validated via Chrome DevTools local — zero console errors.

## Next waves

- **B** — Requests, Errors, Mail, Host, Security, Delivery sections + Event/Incident detail.
- **C** — Maintenance, Alert Settings, rich Project edit.
- **D** — Global ⌘K search.
