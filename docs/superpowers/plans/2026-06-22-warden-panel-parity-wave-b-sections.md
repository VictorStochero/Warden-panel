# Warden Panel — Parity Wave B: Missing Sections — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Add the project sections the package has and the panel lacks — Requests, Errors, Mail, Host, Security, Delivery — plus Event and Incident detail pages, completing the 5 sidebar groups.

**Architecture:** One small Livewire component per screen under `App\Livewire\Project`, each reading existing `DashboardRepository` methods and reusing the Wave A shell (`<x-panel.banners>`, `<x-panel.page-header>`, `<x-panel.kpi-strip>`). New routes + sidebar items + `PanelLayoutRendersTest` entries.

**Tech Stack:** Laravel 12.62, Livewire 4, Flux 2.15, Tailwind v4 (Warden DS), Pest, DDEV.

## Global Constraints

- Do NOT modify `vendor/`. All reads via `DashboardRepository`. DDEV commands. `wire:poll` real-time.
- Gate: pages are viewable by any authenticated user (no `panel.manage` needed for reads).
- Range presets via `App\Support\Ranges`. `#[Url]` filters sanitized to allow-lists.
- Warden DS theme; valid Flux icons only. `PanelLayoutRendersTest` stays green.

## Established pattern (every section component)

```php
<?php
namespace App\Livewire\Project;

use App\Support\Ranges;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class <Name> extends Component
{
    public string $slug;

    #[Url]
    public string $range = '1h'; // omit for sections without a range

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $dashboard->project($slug); // 404 early on unknown slug
    }

    public function render(DashboardRepository $dashboard)
    {
        $this->range = Ranges::sanitize($this->range); // omit if no range
        $project = $dashboard->project($this->slug);

        return view('livewire.project.<name>', [
            'project' => $project,
            'ranges' => Ranges::all(),                                  // if range
            'kpis' => $dashboard->kpis($project->id, $this->range),     // if KPI-strip
            // ...section-specific data...
        ]);
    }
}
```

View top (sections with range + KPI-strip):
```blade
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name . ' · <Label>'" :range="$range" :ranges="$ranges" />
    <x-panel.kpi-strip :project="$project" :kpis="$kpis" />
    {{-- section body --}}
</div>
```
For sections without range/KPI: `<x-panel.page-header :title="…" :showRanges="false" />` and omit the strip.

---

### Task 1: Requests section

**Files:** Create `app/Livewire/Project/Requests.php`, `resources/views/livewire/project/requests.blade.php`; modify `routes/web.php`, `sidebar.blade.php`, `PanelLayoutRendersTest.php`; test `tests/Feature/ProjectRequestsTest.php`.

**Data:** `requestSeries($id,$range)`, `topRoutes($id,$range,50,false)` (rows `{key,count,errors,p95}`), `recentRequests($id,60,$range,false)` (stdClass with `occurred_at`, `duration_us`, `payload` array → `method`,`path`,`status`,`user`), plus `kpis`.

- [ ] **Step 1: Failing test** `tests/Feature/ProjectRequestsTest.php`:
```php
<?php
use App\Models\User;
use App\Livewire\Project\Requests;
use Livewire\Livewire;

it('renders the requests section', function () {
    test()->artisan('warden:project', ['name' => 'Req App'])->assertSuccessful();
    $user = User::factory()->create();
    Livewire::actingAs($user)->test(Requests::class, ['slug' => 'req-app'])
        ->assertViewHas('routes')->assertViewHas('recent')->assertSet('range', '1h')
        ->set('range', '6h')->assertSet('range', '6h');
});

it('requires auth for the requests section', function () {
    test()->artisan('warden:project', ['name' => 'Req App'])->assertSuccessful();
    $this->get('/projects/req-app/requests')->assertRedirect('/login');
});
```
- [ ] **Step 2:** `ddev artisan test --filter=ProjectRequestsTest` → FAIL.
- [ ] **Step 3:** Component per the pattern (with `range`), render data:
```php
            'kpis' => $dashboard->kpis($project->id, $this->range),
            'series' => $dashboard->requestSeries($project->id, $this->range),
            'routes' => $dashboard->topRoutes($project->id, $this->range, 50, false),
            'recent' => $dashboard->recentRequests($project->id, 60, $this->range, false),
```
- [ ] **Step 4:** View — header+kpi-strip, a "Requests" summary line (`{{ $series->sum('count') }} requests · {{ $series->sum('errors') }} errors`), a Top routes table (Route/Count/p95/Errors over `$routes` using `$row['key']`,`$row['count']`,`$row['p95']`,`$row['errors']`), and a Recent requests table (Time/Method+Path/Status/Duration) over `$recent` using `$e->occurred_at`, `$e->payload['method'] ?? ''`, `$e->payload['path'] ?? ''`, `$e->payload['status'] ?? ''`, `$e->duration_us`.
- [ ] **Step 5:** Route in `routes/web.php` auth group: `use App\Livewire\Project\Requests as ProjectRequests;` + `Route::get('/projects/{slug}/requests', ProjectRequests::class)->name('project.requests');`
- [ ] **Step 6:** Sidebar: add to Performance group after Database: `<flux:navlist.item :href="route('project.requests', $slug)" :current="request()->routeIs('project.requests')" wire:navigate>Requests</flux:navlist.item>`
- [ ] **Step 7:** Add `'/projects/{slug}/requests'` to `PanelLayoutRendersTest` dataset.
- [ ] **Step 8:** `ddev artisan test --filter=ProjectRequestsTest && ddev artisan test --filter=PanelLayoutRendersTest` → PASS.
- [ ] **Step 9:** Commit `feat(project): requests section`.

---

### Task 2: Errors section

**Files:** `app/Livewire/Project/Errors.php`, `resources/views/livewire/project/errors.blade.php`; routes/sidebar/render-test; `tests/Feature/ProjectErrorsTest.php`.

**Data:** `#[Url] public string $release = '';` (validated against `releases()` keys). `recentErrors($id,50,$release ?: null)` (stdClass `request` events status≥500 with `payload` `method/path/status`, `occurred_at`, `release`), `releases($id,20)`. No range; no KPI-strip (it's a focused list). Header `:showRanges="false"`.

- [ ] **Step 1: Failing test**: renders, `assertViewHas('errors')` + `assertViewHas('releases')`; `set('release','bogus')` coerces to `''`; auth redirect.
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** Component: `updatedRelease()` + render-time coercion — if `$this->release !== '' && ! $releases->contains('tag', $this->release)` then `$this->release = ''` (use the `releases()` row key for the tag; releases rows are stdClass — coerce by `pluck`). Simpler: build `$valid = $dashboard->releases($project->id,20)->pluck('release')->all();` and `if (! in_array($this->release, $valid, true)) $this->release='';`. Pass `errors`, `releases`, `release`.
- [ ] **Step 4:** View: header (no range), help text linking to Issues/Incidents, a release filter (pills: "All" + each release tag, `wire:click="$set('release', '…')"`), and a Recent exceptions/errors table (Time/Method+Path/Status/Release) over `$errors`.
- [ ] **Step 5–7:** Route `project.errors` (`/projects/{slug}/errors`); sidebar Reliability group after Issues; add to render test.
- [ ] **Step 8:** Tests PASS.
- [ ] **Step 9:** Commit `feat(project): errors section`.

---

### Task 3: Mail section

**Files:** `Mail.php`, `mail.blade.php`; routes/sidebar/render-test; `ProjectMailTest.php`.

**Data (range, KPI-strip):** `breakdown($id,'mail',$range)` (`{key,count,avg,max}`), `breakdown($id,'notification',$range)`, `recentEvents($id,'mail',50,$range)`, `recentEvents($id,'notification',50,$range)`, `kpis`.

- [ ] Test renders + `assertViewHas('mailers')` + range coercion + auth. Component per pattern. View: header+kpi-strip, two breakdown tables (Mailers, Notifications: Key/Count/Avg/Max) + two recent event lists (Time/Summary using `json_encode($e->payload)`). Route `project.mail` (System group). Render-test entry. Commit `feat(project): mail section`.

---

### Task 4: Host section

**Files:** `Host.php`, `host.blade.php`; routes/sidebar/render-test; `ProjectHostTest.php`.

**Data (range, KPI-strip):** `hostLatest($id,$range)` (`?array` meta `cpu`,`mem`,`load`,`disk`), `hostSeries($id,$range)` (`{bucket,cpu,mem}`), `kpis`.

- [ ] Test renders + `assertViewHas('latest')` + range coercion + auth. View: header+kpi-strip; if `$latest` empty show an empty-state callout; else 4 KPI cards (CPU `$latest['cpu'].'%'`, Memory `$latest['mem'].'%'`, Load `$latest['load']`, Disk `$latest['disk'].'%'` with `?? '—'`), and a series summary line (`{{ $series->count() }} samples · last cpu/mem`). Route `project.host` (System). Render-test. Commit `feat(project): host section`.

---

### Task 5: Security section

**Files:** `Security.php`, `security.blade.php`; routes/sidebar/render-test; `ProjectSecurityTest.php`.

**Data (no range, no KPI-strip):** `$audit = recentEvents($id,'security',1,$this->range='30d')->first();` — payload carries advisories. Header `:showRanges="false"`.

- [ ] Test renders + `assertViewHas('audit')` (may be null) + auth. View: header; if `$audit` null show empty-state ("No dependency audit recorded — run `warden:audit` on the child."); else show payload summary (advisories list from `$audit->payload`). Route `project.security` (System). Render-test. Commit `feat(project): security section`.

---

### Task 6: Delivery section

**Files:** `Delivery.php`, `delivery.blade.php`; routes/sidebar/render-test; `ProjectDeliveryTest.php`.

**Data (no range, LIVE):** `delivery($id,60)` → `{last,window,batches,events,cadence,series[],recent[]}` (recent: stdClass `{received_at,batches,events}`). Header `:showRanges="false"`.

- [ ] Test renders + `assertViewHas('delivery')` + auth. View: header; KPI cards (Batches `$delivery['batches']`, Events `$delivery['events']`, Cadence `$delivery['cadence']` s, Last `$delivery['last'] ?? '—'`); a recent batches table (Received/Batches/Events) over `$delivery['recent']`. Route `project.delivery` (System). Render-test. Commit `feat(project): delivery section`.

---

### Task 7: Event detail

**Files:** `Event.php`, `event.blade.php`; route `project.event`; `ProjectEventTest.php`. No nav item (linked from Events list).

**Data:** `mount(string $slug, int $eventId, DashboardRepository $d)` stores both; `render`: `$event = $d->event($project->id, $this->eventId); abort_if($event === null, 404);`. Show metadata (type/occurred_at/duration_us/release), payload (`<pre>{{ json_encode($event->payload, JSON_PRETTY_PRINT) }}</pre>`), and a link to the trace if `$event->trace_id`.

- [ ] Test: seed a `wdn_events` row, render via `Livewire::test(Event::class, ['slug'=>…, 'eventId'=>$id])` `assertViewHas('event')`; unknown id → `assertStatus(404)` via the HTTP route `/projects/{slug}/events/{id}`. Route `Route::get('/projects/{slug}/events/{eventId}', ProjectEvent::class)->whereNumber('eventId')->name('project.event');` **placed before** `/projects/{slug}/events` is fine (distinct path). Link `occurred_at`/rows in `events.blade.php` to `route('project.event', ['slug'=>$project->slug,'eventId'=>$event->id])`. Render-test: seed an event and add `'/projects/{slug}/events/{eventId}'` is awkward (needs id) → instead cover via `ProjectEventTest` only. Commit `feat(project): event detail`.

---

### Task 8: Incident detail

**Files:** `Incident.php`, `incident.blade.php`; route `project.incident`; `ProjectIncidentTest.php`. No nav item (linked from Incidents list).

**Data:** `mount(slug, int $incidentId, $d)`; `render`: `$incident = $d->incident($project->id, $this->incidentId); abort_if($incident === null, 404); $context = $d->relatedContext($project->id);`. Show header (subject/summary/severity/status/started_at) + related context (open issues, recent traces). Link incidents-list rows to `route('project.incident', …)`.

- [ ] Test: seed a `wdn_incidents` row, render `assertViewHas('incident')`; unknown id → 404. Route `Route::get('/projects/{slug}/incidents/{incidentId}', ProjectIncident::class)->whereNumber('incidentId')->name('project.incident');`. Commit `feat(project): incident detail`.

---

### Task 9: Full suite + build + devtools validation

- [ ] `ddev artisan test` (all green) and `ddev npm run build`.
- [ ] **Chrome DevTools local** (`https://warden-panel.ddev.site`, admin): open Requests, Errors, Mail, Host, Security, Delivery — confirm the sidebar now shows the **System** group (Host/Mail/Security/Delivery), Requests under Performance, Errors under Reliability; each page renders with header/KPIs/filters; open an event and an incident detail from their lists. Confirm **zero console errors** on each.
- [ ] Final commit if any view tweaks were needed.

## Wave B Done — Definition of Done

- `ddev artisan test` green; build ok. Six new sections + Event/Incident detail, correctly grouped (5 sidebar groups complete), reads via `DashboardRepository`, Wave A shell. Covered by tests + render test. Validated via Chrome DevTools — zero console errors.

## Next waves

- **C** — Maintenance, Alert Settings, rich Project edit. **D** — Global ⌘K search.
