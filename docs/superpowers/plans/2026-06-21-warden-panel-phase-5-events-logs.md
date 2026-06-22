# Warden Panel — Phase 5: Events + Logs — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the diagnostics surface — a Logs page (recent log events with level/message) and an Events page (recent events of a selectable type: mail / notification / cache / command / schedule / exception / http) — reading the Warden read layer, themed to match Warden 0.3.5, refreshing via `wire:poll`.

**Architecture:** Two Livewire components under `App\Livewire\Project`, each reading `DashboardRepository::recentEvents($projectId, $type, $limit, $range)`. Logs is a fixed `type='log'`; Events has a `type` selector over the non-primary recorder types. No package modification.

**Tech Stack:** Laravel 12.62, Livewire 4, Flux 2.15, Tailwind v4 (Warden DS theme), Pest, DDEV.

## Global Constraints

- **Do NOT modify `vendor/victorstochero/warden`.** Reuse only.
- Read event data ONLY through `DashboardRepository::recentEvents`. No direct `wdn_*` queries in panel code.
- **DDEV runtime:** all commands via `ddev` — `ddev artisan test`, `ddev npm run build`. Bare `php`/`npm` fail on the host.
- Real-time = Livewire `wire:poll` from `config('panel.poll_seconds')` (default 3).
- Every page auth-gated; unknown project slug → 404.
- Range values are the shared allow-list `App\Support\Ranges::all()` = `['15m','1h','6h','24h','7d','30d']`, default `'1h'`; sanitize via `App\Support\Ranges::sanitize()` before any read.
- Event `type` must be validated against an allow-list (do NOT pass an arbitrary client string to the repository). Logs is fixed `'log'`; Events allows only `['mail','notification','cache','command','schedule','exception','http']`, default `'mail'`.
- Warden DS theme classes (`bg-ink-850`, `text-brand-400`, `text-rose-400`, `font-mono`).
- Tests use Pest; test DB SQLite `:memory:`; seed projects with `ddev artisan warden:project`. Do NOT use `warden:demo` (child-only).
- Append nav items to the existing contextual "Project" sidebar group; keep all existing items.
- **Every new authenticated page MUST be added to `tests/Feature/PanelLayoutRendersTest.php`'s dataset** so the full-layout render (sidebar + Flux) is exercised and a broken Flux component/icon fails the suite. Use only valid Flux icons (verify under `vendor/livewire/flux/stubs/resources/views/flux/icon/<name>.blade.php`).

## Read-layer reference (exact signatures + shapes — consume verbatim)

- `DashboardRepository::project(string $idOrSlug): Project`
- `DashboardRepository::recentEvents(int $projectId, string $type, int $limit = 50, ?string $range = null): Collection` of `\stdClass {id, trace_id, span_id, occurred_at, duration_us, payload (decoded array), release}` ordered by `id` desc. Log payloads carry `level` + `message`; other types carry type-specific payload keys (mail: `subject`/`to`; cache: `action`/`key`; command: `command`; exception: `class`/`message`; http: `method`/`host`).
- Valid recorder types (from `RecorderManager`): `query, exception, log, job, mail, notification, cache, command, schedule, http`.

---

### Task 1: Logs page

**Files:**
- Create: `app/Livewire/Project/Logs.php`
- Create: `resources/views/livewire/project/logs.blade.php`
- Modify: `routes/web.php` (`/projects/{slug}/logs`)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (Logs nav item)
- Modify: `tests/Feature/PanelLayoutRendersTest.php` (add `/projects/{slug}/logs` to the dataset)
- Test: `tests/Feature/ProjectLogsTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::project`, `::recentEvents($pid,'log',$limit,$range)`.
- Produces: route `project.logs`; view data key `logs`; a `#[Url] public string $range = '1h'` (sanitized).

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ProjectLogsTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Logs;
use Livewire\Livewire;

it('renders the logs page for a project', function () {
    $this->artisan('warden:project', ['name' => 'Log App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Logs::class, ['slug' => 'log-app'])
        ->assertViewHas('logs')
        ->assertViewHas('project')
        ->assertSet('range', '1h')
        ->set('range', 'bogus')
        ->assertSet('range', '1h');
});

it('requires auth for the logs page', function () {
    $this->get('/projects/log-app/logs')->assertRedirect('/login');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=ProjectLogsTest`
Expected: FAIL (component/route missing).

- [ ] **Step 3: Implement the component**

```php
// app/Livewire/Project/Logs.php
<?php

namespace App\Livewire\Project;

use App\Support\Ranges;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Logs extends Component
{
    public string $slug;

    #[Url]
    public string $range = '1h';

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
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

        return view('livewire.project.logs', [
            'project' => $project,
            'ranges' => Ranges::all(),
            'logs' => $dashboard->recentEvents($project->id, 'log', 100, $this->range),
        ]);
    }
}
```

- [ ] **Step 4: Implement the view**

```blade
{{-- resources/views/livewire/project/logs.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Logs</flux:heading>
        <flux:select wire:model.live="range" class="max-w-32">
            @foreach ($ranges as $r)<flux:select.option value="{{ $r }}">{{ $r }}</flux:select.option>@endforeach
        </flux:select>
    </div>

    <div class="rounded-xl bg-ink-850 p-4 space-y-1">
        @php($levelColor = ['error' => 'text-rose-400', 'critical' => 'text-rose-400', 'warning' => 'text-amber-400', 'info' => 'text-brand-400', 'debug' => 'text-slate-400'])
        @forelse ($logs as $log)
            @php($level = is_array($log->payload) ? ($log->payload['level'] ?? 'info') : 'info')
            <div class="flex gap-3 text-xs font-mono border-b border-ink-800 py-1">
                <span class="w-40 shrink-0 text-slate-500">{{ $log->occurred_at }}</span>
                <span class="w-16 shrink-0 {{ $levelColor[$level] ?? 'text-slate-400' }}">{{ strtoupper($level) }}</span>
                <span class="text-slate-300 truncate">{{ is_array($log->payload) ? ($log->payload['message'] ?? '') : '' }}</span>
            </div>
        @empty
            <div class="text-slate-400 text-sm">No logs in this window.</div>
        @endforelse
    </div>
</div>
```

- [ ] **Step 5: Register the route**

In `routes/web.php` `auth` group:
```php
use App\Livewire\Project\Logs as ProjectLogs;
Route::get('/projects/{slug}/logs', ProjectLogs::class)->name('project.logs');
```

- [ ] **Step 6: Add the Logs nav item** (use a VALID Flux icon)

In the sidebar "Project" group (after Incidents):
```blade
<flux:navlist.item :href="route('project.logs', $slug)" :current="request()->routeIs('project.logs')" wire:navigate>Logs</flux:navlist.item>
```
(No `icon=` is required on these section items — they are text-only like the others. If you add an icon, verify it exists under `vendor/livewire/flux/stubs/resources/views/flux/icon/`.)

- [ ] **Step 7: Add the page to the layout render test**

In `tests/Feature/PanelLayoutRendersTest.php`, add `'/projects/{slug}/logs',` to the `->with([...])` dataset.

- [ ] **Step 8: Run to verify it passes**

Run: `ddev artisan test --filter=ProjectLogsTest && ddev artisan test --filter=PanelLayoutRendersTest`
Expected: PASS (both — including the new render case).

- [ ] **Step 9: Build + commit**

```bash
ddev npm run build
git add -A && git commit -m "feat(project): logs page"
```

---

### Task 2: Events page (type selector)

**Files:**
- Create: `app/Livewire/Project/Events.php`
- Create: `resources/views/livewire/project/events.blade.php`
- Modify: `routes/web.php` (`/projects/{slug}/events`)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (Events nav item)
- Modify: `tests/Feature/PanelLayoutRendersTest.php` (add `/projects/{slug}/events` to the dataset)
- Test: `tests/Feature/ProjectEventsTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::project`, `::recentEvents($pid,$type,$limit,$range)`.
- Produces: route `project.events`; view data keys `events`, `types`, `type`; `#[Url] public string $type = 'mail'` (allow-listed), `#[Url] public string $range = '1h'`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/ProjectEventsTest.php
<?php

use App\Models\User;
use App\Livewire\Project\Events;
use Livewire\Livewire;

it('renders the events page and validates the type', function () {
    $this->artisan('warden:project', ['name' => 'Event App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Events::class, ['slug' => 'event-app'])
        ->assertViewHas('events')
        ->assertViewHas('types')
        ->assertSet('type', 'mail')
        ->set('type', 'cache')
        ->assertSet('type', 'cache')
        ->set('type', 'evil-injection')        // not in the allow-list
        ->assertSet('type', 'mail');           // coerced back to default
});

it('requires auth for the events page', function () {
    $this->get('/projects/event-app/events')->assertRedirect('/login');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev artisan test --filter=ProjectEventsTest`
Expected: FAIL.

- [ ] **Step 3: Implement the component**

```php
// app/Livewire/Project/Events.php
<?php

namespace App\Livewire\Project;

use App\Support\Ranges;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Events extends Component
{
    public string $slug;

    #[Url]
    public string $type = 'mail';

    #[Url]
    public string $range = '1h';

    /** @return list<string> */
    public static function types(): array
    {
        return ['mail', 'notification', 'cache', 'command', 'schedule', 'exception', 'http'];
    }

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $dashboard->project($slug);
    }

    public function updatedType(): void
    {
        if (! in_array($this->type, self::types(), true)) {
            $this->type = 'mail';
        }
    }

    public function render(DashboardRepository $dashboard)
    {
        if (! in_array($this->type, self::types(), true)) {
            $this->type = 'mail';
        }
        $this->range = Ranges::sanitize($this->range);
        $project = $dashboard->project($this->slug);

        return view('livewire.project.events', [
            'project' => $project,
            'ranges' => Ranges::all(),
            'types' => self::types(),
            'events' => $dashboard->recentEvents($project->id, $this->type, 100, $this->range),
        ]);
    }
}
```

- [ ] **Step 4: Implement the view**

```blade
{{-- resources/views/livewire/project/events.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <div class="flex items-center justify-between gap-3">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Events</flux:heading>
        <div class="flex gap-2">
            <flux:select wire:model.live="type" class="max-w-40">
                @foreach ($types as $t)<flux:select.option value="{{ $t }}">{{ ucfirst($t) }}</flux:select.option>@endforeach
            </flux:select>
            <flux:select wire:model.live="range" class="max-w-32">
                @foreach ($ranges as $r)<flux:select.option value="{{ $r }}">{{ $r }}</flux:select.option>@endforeach
            </flux:select>
        </div>
    </div>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Time</flux:table.column>
                <flux:table.column>Summary</flux:table.column>
                <flux:table.column>Trace</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($events as $event)
                    <flux:table.row wire:key="event-{{ $event->id }}">
                        <flux:table.cell class="font-mono text-xs text-slate-400">{{ $event->occurred_at }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs truncate max-w-md">{{ is_array($event->payload) ? json_encode($event->payload) : '' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">
                            @if ($event->trace_id)
                                <a class="text-brand-400" href="{{ route('project.trace', ['slug' => $project->slug, 'traceId' => $event->trace_id]) }}" wire:navigate>{{ \Illuminate\Support\Str::limit($event->trace_id, 10) }}</a>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No {{ $type }} events in this window.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
```

- [ ] **Step 5: Register the route**

In `routes/web.php` `auth` group:
```php
use App\Livewire\Project\Events as ProjectEvents;
Route::get('/projects/{slug}/events', ProjectEvents::class)->name('project.events');
```

- [ ] **Step 6: Add the Events nav item**

In the sidebar "Project" group (after Logs):
```blade
<flux:navlist.item :href="route('project.events', $slug)" :current="request()->routeIs('project.events')" wire:navigate>Events</flux:navlist.item>
```

- [ ] **Step 7: Add the page to the layout render test**

In `tests/Feature/PanelLayoutRendersTest.php`, add `'/projects/{slug}/events',` to the `->with([...])` dataset.

- [ ] **Step 8: Run to verify it passes**

Run: `ddev artisan test --filter=ProjectEventsTest && ddev artisan test --filter=PanelLayoutRendersTest`
Expected: PASS.

- [ ] **Step 9: Full suite + build + commit**

```bash
ddev artisan test
ddev npm run build
git add -A && git commit -m "feat(project): events page with type selector"
```

---

## Phase 5 Done — Definition of Done

- `ddev artisan test` green; `ddev npm run build` succeeds.
- The project sidebar links Logs + Events. Logs shows level/message lines with a range filter; Events lets the operator pick an event type (allow-listed) and range. Both pages are covered by `PanelLayoutRendersTest` (full-layout render → 200).
- All reads via `DashboardRepository::recentEvents`; package unmodified; range + type sanitized against allow-lists before any read.

## Self-review notes (addressed)

- **Injection safety:** neither `range` nor `type` is passed raw to the repository — both are coerced to an allow-list (`Ranges::sanitize`, `Events::types()`) in `render()` AND on the `updated*` hook, so a client-set value cannot reach `recentEvents` unvalidated.
- **Render-test coverage:** both new pages are added to `PanelLayoutRendersTest` so a broken Flux component in the layout/page fails CI (the lesson from the `folder-cog` incident).
- **Test design:** seeds via `warden:project` (parent-mode), asserts real view data + the type/range coercion + auth-gating. No `warden:demo`.

## Out of scope (subsequent plans)

- **Phase 6 — Admin completeness** (token rotation/revoke, settings, audit log) + deploy (docker-compose, README, `warden:demo` polish).
