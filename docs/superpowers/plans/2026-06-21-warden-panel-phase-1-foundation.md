# Warden Panel — Phase 1: Foundation + Shell + Live Fleet Overview — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up `warden-panel` as a self-hosted Laravel 13 + Livewire + Flux app that runs the Warden package in parent mode (ingest + pipeline + read layer reused off-the-shelf), with starter-kit auth, an admin screen that mints child credentials, the Warden Design System theme, and a live fleet-overview screen.

**Architecture:** The panel `composer require`s `victorstochero/warden:^0.3.5` and reuses its parent backend (ingest route, scheduled pipeline commands, `DashboardRepository`, `ProjectManager`, `IssueWorkflow`). The package dashboard/auth is disabled (`WARDEN_DASHBOARD=false`); the panel ships its own Livewire UI themed to match the package's 0.3.5 dashboard and refreshes live via `wire:poll`.

**Tech Stack:** Laravel 13, Livewire 3, Flux, Tailwind (Warden Design System tokens), Pest, Vite. **Local runtime: DDEV** (PHP/Composer/Node all run inside the DDEV web container). Local DB: DDEV MariaDB (exercises the MySQL/partitioning path); test DB: SQLite `:memory:` (runs inside the container, fast). Production DB is the operator's choice (MySQL/MariaDB/PostgreSQL).

## Global Constraints

- **Do NOT modify the `victorstochero/warden` package** (`vendor/victorstochero/warden`). Reuse only.
- Package version pinned to `victorstochero/warden:^0.3.5` (Packagist).
- **DDEV is the local runtime.** Every PHP/Composer/Node/Artisan command runs **inside DDEV**: `ddev composer ...`, `ddev npm ...`, `ddev artisan ...`, `ddev php ...`, `ddev exec ...`. The host has no `node`/`npm`/project `php` — bare commands will fail. Command blocks below show the exact `ddev`-prefixed form; in later tasks where a step says `php artisan test`, run it as **`ddev artisan test`**.
- Package config in `.env`: `WARDEN_MODE=parent`, `WARDEN_DASHBOARD=false`, `WARDEN_CONNECTION=null` (use the default app connection — DDEV MariaDB — as the `wdn_*` store).
- The panel UI reads exclusively through the package read/service layer: `VictorStochero\Warden\Dashboard\DashboardRepository`, `VictorStochero\Warden\Contracts\WardenRepository`, `VictorStochero\Warden\Projects\ProjectManager`, `VictorStochero\Warden\Issues\IssueWorkflow`. Do NOT query `wdn_*` tables directly from panel components.
- Real-time = Livewire `wire:poll` only (no Reverb/WebSocket in Phase 1). Poll interval from `config('panel.poll_seconds')`, default `3`.
- Tests use Pest (the starter kit default). Run with `ddev artisan test`. Test connection = SQLite `:memory:` (set in `phpunit.xml`).
- Warden Design System tokens (copy verbatim): brand `#2E7BFF`; `ink` night surfaces; fonts Archivo / Archivo Expanded / JetBrains Mono (self-hosted woff2 reused from `vendor/victorstochero/warden/resources/dist/fonts`).

## Prerequisites (one-time, before Task 1)

Environment confirmed: `ddev v1.25.2` on the host; the project is **not yet DDEV-configured** (no `.ddev/`) and **not a git repo**. DDEV provides PHP, Composer and Node inside the container — there is no host-level Node to install.

- [ ] **P1: Preserve the spec/plan docs** (the scaffold in Task 1 replaces the skeleton; keep our docs):

```bash
cp -r /home/victor/Projetos/warden-panel/docs /tmp/warden-panel-docs-backup
ls /tmp/warden-panel-docs-backup/superpowers/specs   # expect the design spec
```

- [ ] **P2: Configure and start DDEV** (Laravel project type, Node 20):

```bash
cd /home/victor/Projetos/warden-panel
ddev config --project-type=laravel --docroot=public --php-version=8.4 --nodejs-version=20
ddev start
ddev php -v && ddev node -v && ddev npm -v   # expect PHP 8.4, Node 20, npm present
```
DDEV provisions a MariaDB `db` service and injects `DB_HOST=db`, `DB_DATABASE=db`, `DB_USERNAME=db`, `DB_PASSWORD=db` (driver `mysql`) into the container environment.

---

### Task 1: Scaffold the Livewire + Flux starter kit under DDEV

**Files:**
- Replace: the project tree at `/home/victor/Projetos/warden-panel` with the `laravel/livewire-starter-kit` scaffold (preserving `.ddev/` and `docs/`)
- Create: `.git` (initialize version control — the project is currently NOT a git repo)

**Interfaces:**
- Produces: a booting Laravel 13 + Livewire 3 + Flux app served by DDEV, with auth (login/register/passkey/2FA per the starter kit), Pest test suite, and `ddev npm run build` working. Later tasks rely on: `App\Models\User`, `routes/web.php`, `resources/css/app.css`, the Tailwind config, and the starter-kit app layout view.

This is a scaffolding task (setup), not red-green TDD; verify with the commands shown. Assumes P1+P2 done (docs backed up; DDEV configured + started).

- [ ] **Step 1: Empty the project root except `.ddev` (docs are already backed up in /tmp)**

```bash
cd /home/victor/Projetos/warden-panel
find . -maxdepth 1 -mindepth 1 ! -name '.ddev' -exec rm -rf {} +
ls -A   # expect only: .ddev
```

- [ ] **Step 2: Scaffold the starter kit into the project root via DDEV**

`ddev composer create-project` builds in a container temp dir and moves the result into the project root, preserving `.ddev`:
```bash
ddev composer create-project laravel/livewire-starter-kit -y
```
Expected: completes; `artisan`, `composer.json` (name `laravel/livewire-starter-kit`), and `resources/views` now exist.

- [ ] **Step 3: Restore our docs**

```bash
mkdir -p docs && cp -r /tmp/warden-panel-docs-backup/* docs/
ls docs/superpowers/specs   # expect the design spec
```

- [ ] **Step 4: Point the app at DDEV's MariaDB, generate key, install front-end, build**

```bash
ddev artisan key:generate
# Ensure .env uses the DDEV DB (DDEV injects these in-container; set them in .env too):
ddev exec 'sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/" .env'
ddev exec 'grep -q "^DB_HOST" .env || printf "DB_HOST=db\nDB_DATABASE=db\nDB_USERNAME=db\nDB_PASSWORD=db\n" >> .env'
ddev artisan migrate --force
ddev npm install && ddev npm run build
```
Expected: migrations run on MariaDB; `public/build/manifest.json` created.

- [ ] **Step 5: Set the test connection to SQLite :memory:**

In `phpunit.xml`, ensure these env entries exist (add/uncomment):
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

- [ ] **Step 6: Run the starter-kit test suite to confirm a green baseline**

Run: `ddev artisan test`
Expected: PASS (the starter kit ships auth feature tests).

- [ ] **Step 7: Initialize git and commit the baseline**

```bash
cd /home/victor/Projetos/warden-panel
git init
printf "/vendor\n/node_modules\n/.env\n/public/build\n/.ddev/.dbimageBuild\n" >> .gitignore
git add -A && git commit -m "chore: scaffold livewire+flux starter kit under ddev"
```

---

### Task 2: Add the Warden package in parent mode with a dedicated schema

**Files:**
- Modify: `composer.json` (require `victorstochero/warden`)
- Create: `config/warden.php` (published by `warden:install`)
- Modify: `.env`, `.env.example` (Warden parent keys)
- Create: `database/seeders/WardenDemoSeeder.php` (wrapper to seed read-path data in tests — optional helper)
- Test: `tests/Feature/WardenParentInstallTest.php`

**Interfaces:**
- Consumes: nothing from prior tasks beyond a booting app.
- Produces: the `wdn_*` schema in the panel's default DB connection; the named route `warden.ingest` (POST); package read/service classes resolvable from the container. Later tasks rely on `app(VictorStochero\Warden\Dashboard\DashboardRepository::class)` resolving.

- [ ] **Step 1: Require the package (via DDEV)**

```bash
cd /home/victor/Projetos/warden-panel
ddev composer require victorstochero/warden:^0.3.5
```
Expected: installs; `vendor/victorstochero/warden` present.

- [ ] **Step 2: Write the failing test**

```php
// tests/Feature/WardenParentInstallTest.php
<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use VictorStochero\Warden\Dashboard\DashboardRepository;

it('has the warden parent schema installed', function () {
    expect(Schema::hasTable('wdn_events'))->toBeTrue();
    expect(Schema::hasTable('wdn_aggregates'))->toBeTrue();
    expect(Schema::hasTable('wdn_projects'))->toBeTrue();
});

it('registers the parent ingest route', function () {
    expect(Route::has('warden.ingest'))->toBeTrue();
});

it('resolves the package read layer from the container', function () {
    expect(app(DashboardRepository::class))->toBeInstanceOf(DashboardRepository::class);
});

it('does not expose the package dashboard routes', function () {
    expect(Route::has('warden.overview'))->toBeFalse();
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --filter=WardenParentInstallTest`
Expected: FAIL (tables/route missing; dashboard route may still be present).

- [ ] **Step 4: Configure parent mode in `.env` and `.env.example`**

Add to both files:
```dotenv
WARDEN_MODE=parent
WARDEN_DASHBOARD=false
WARDEN_CONNECTION=null
WARDEN_SELF_MONITOR=true
```

- [ ] **Step 5: Install the Warden parent schema (via DDEV)**

```bash
ddev artisan warden:install --parent --force
ddev artisan migrate --force
```
Expected: publishes `config/warden.php`, creates `wdn_*` tables on the default (MariaDB) connection.

- [ ] **Step 6: Ensure the test environment also installs the schema**

In `phpunit.xml` confirm `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` for tests, then register the Warden migrations to run in tests by adding to `tests/Pest.php` (or `TestCase`) a `RefreshDatabase` setup that also runs `warden:install`. Concretely, in `tests/TestCase.php` add:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->artisan('warden:install', ['--parent' => true, '--no-migrate' => false, '--force' => true]);
}
```
(If the starter kit uses `RefreshDatabase` per test, ensure the Warden tables are created within the refreshed schema — run `warden:install` after the base migration.)

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=WardenParentInstallTest`
Expected: PASS (4 assertions/tests green).

- [ ] **Step 8: Commit**

```bash
git add -A && git commit -m "feat: add warden package in parent mode with dedicated schema"
```

---

### Task 3: Seed read-path data and prove the read layer end-to-end

**Files:**
- Test: `tests/Feature/WardenReadLayerTest.php`

**Interfaces:**
- Consumes: `DashboardRepository` resolvable (Task 2).
- Produces: confidence that `DashboardRepository::overview()` returns the documented shape. Later tasks (overview screen) rely on the keys `projects`, `open_issues`, `open_incidents`, `throughput`.

- [ ] **Step 1: Write the failing test** (seed synthetic fleet data via the package's demo command, then read it):

```php
// tests/Feature/WardenReadLayerTest.php
<?php

use VictorStochero\Warden\Dashboard\DashboardRepository;

it('returns the documented overview shape after demo seeding', function () {
    $this->artisan('warden:demo')->assertSuccessful();

    $overview = app(DashboardRepository::class)->overview();

    expect($overview)->toHaveKeys(['projects', 'open_issues', 'open_incidents', 'throughput']);
    expect($overview['projects'])->not->toBeEmpty();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=WardenReadLayerTest`
Expected: FAIL initially if `warden:demo` options differ — inspect with `php artisan warden:demo --help` and adjust the call (e.g. pass a project count) until the command succeeds; the assertion on shape must drive the fix.

- [ ] **Step 3: Make it pass**

Adjust the `warden:demo` invocation in the test to match the command's real options (discovered via `--help`). No app code changes — this validates the reused read path.

- [ ] **Step 4: Run to verify it passes**

Run: `php artisan test --filter=WardenReadLayerTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "test: verify warden read layer overview end-to-end"
```

---

### Task 4: Schedule the parent pipeline + document the cron/queue

**Files:**
- Modify: `routes/console.php` (or `bootstrap/app.php` `->withSchedule(...)`) — confirm the package auto-schedule is active
- Create: `docs/deploy/PROCESSES.md`
- Test: `tests/Feature/WardenScheduleTest.php`

**Interfaces:**
- Consumes: parent mode active (Task 2).
- Produces: the scheduled commands `warden:aggregate`, `warden:evaluate`, `warden:partition`, `warden:prune` registered by the package; operator docs for cron + queue worker.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/WardenScheduleTest.php
<?php

use Illuminate\Console\Scheduling\Schedule;

it('auto-registers the warden parent pipeline on the scheduler', function () {
    $events = app(Schedule::class)->events();
    $commands = collect($events)->map(fn ($e) => $e->command ?? '')->implode(' ');

    expect($commands)->toContain('warden:aggregate');
    expect($commands)->toContain('warden:evaluate');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=WardenScheduleTest`
Expected: FAIL if `parent.schedule.enabled` is off or aggregate cadence differs.

- [ ] **Step 3: Confirm `WARDEN_PARENT_SCHEDULE=true`** (default) in `.env`/`.env.example`; the package's `WardenServiceProvider::registerSchedule()` wires the pipeline. No custom code needed unless the test reveals it's gated off.

- [ ] **Step 4: Run to verify it passes**

Run: `php artisan test --filter=WardenScheduleTest`
Expected: PASS.

- [ ] **Step 5: Write the operator process doc**

```markdown
<!-- docs/deploy/PROCESSES.md -->
# Warden Panel — required processes

The panel needs two long-running concerns on the VPS:

1. **Scheduler** (drives the Warden parent pipeline — aggregate/evaluate/partition/prune):
   `* * * * * cd /path/to/warden-panel && php artisan schedule:run >> /dev/null 2>&1`
2. **Queue worker** (alerts + maintenance jobs): `php artisan queue:work --tries=3`

KPI freshness tracks the `warden:aggregate` cadence; the package schedules it frequently. No Node/Vite is needed at runtime (assets are prebuilt with `npm run build`).
```

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: confirm parent pipeline schedule + add process docs"
```

---

### Task 5: Lock public registration and add an admin authorization flag

**Files:**
- Create: `database/migrations/2026_06_21_000000_add_is_admin_to_users.php`
- Modify: `app/Models/User.php` (cast `is_admin`)
- Modify: `routes/web.php` and/or the starter kit auth routes (disable public registration)
- Create: `app/Providers/AppServiceProvider.php` gate `panel.manage`
- Test: `tests/Feature/PanelAccessTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (Task 1).
- Produces: gate `panel.manage` (admin-only), disabled registration. Later admin tasks authorize with `Gate::authorize('panel.manage')` / `@can('panel.manage')`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/PanelAccessTest.php
<?php

use App\Models\User;

it('disables public registration', function () {
    $this->get('/register')->assertNotFound();
});

it('grants panel.manage only to admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $viewer = User::factory()->create(['is_admin' => false]);

    expect($admin->can('panel.manage'))->toBeTrue();
    expect($viewer->can('panel.manage'))->toBeFalse();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=PanelAccessTest`
Expected: FAIL (register route exists; `is_admin` + gate missing).

- [ ] **Step 3: Add the migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_admin')->default(false));
    }
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};
```

- [ ] **Step 4: Cast `is_admin` on the User model**

In `app/Models/User.php`, add to the `casts()` method: `'is_admin' => 'boolean',` and add `'is_admin'` to `$fillable`.

- [ ] **Step 5: Define the gate**

In `app/Providers/AppServiceProvider.php` `boot()`:
```php
\Illuminate\Support\Facades\Gate::define('panel.manage', fn (\App\Models\User $u) => $u->is_admin === true);
```

- [ ] **Step 6: Disable public registration**

In the starter kit's auth routes (`routes/auth.php` or wherever `register` is defined), remove/comment the GET+POST `register` routes. If the starter kit uses Volt/Folio pages, delete the register page route registration so `/register` 404s. Keep login intact.

- [ ] **Step 7: Run to verify it passes**

Run: `php artisan test --filter=PanelAccessTest`
Expected: PASS.

- [ ] **Step 8: Add a console command to create the first admin** (operator bootstrap):

Create `app/Console/Commands/MakeAdminCommand.php` with signature `panel:make-admin {email} {--name=} {--password=}` that creates or promotes a `User` with `is_admin=true`. Then:
```bash
php artisan panel:make-admin you@example.com --name="Op" --password=secret
```

- [ ] **Step 9: Commit**

```bash
git add -A && git commit -m "feat: lock registration, add admin gate + bootstrap command"
```

---

### Task 6: Admin — projects list + mint child credentials (one-time secret + snippet)

**Files:**
- Create: `app/Livewire/Admin/Projects.php`
- Create: `resources/views/livewire/admin/projects.blade.php`
- Modify: `routes/web.php` (authenticated + `panel.manage` route `/admin/projects`)
- Test: `tests/Feature/AdminProjectsTest.php`

**Interfaces:**
- Consumes: gate `panel.manage` (Task 5); `VictorStochero\Warden\Projects\ProjectManager::create(string $name, ?string $slug = null): array` returning `['project' => Project, 'token' => string, 'secret' => string]`; `ProjectManager::envSnippet(slug, token, secret, parentUrl, delivery='scheduler'): string`.
- Produces: a Livewire component `Admin\Projects` with public method `createProject()` that mints credentials and exposes `$newSecret`, `$newToken`, `$snippet` for one-time display.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/AdminProjectsTest.php
<?php

use App\Models\User;
use Livewire\Livewire;
use App\Livewire\Admin\Projects;

it('mints a project and shows the child snippet once', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test(Projects::class)
        ->set('name', 'Checkout API')
        ->call('createProject')
        ->assertSet('newToken', fn ($t) => is_string($t) && strlen($t) === 40)
        ->assertSet('newSecret', fn ($s) => is_string($s) && strlen($s) === 64)
        ->assertSee('WARDEN_MODE=child')
        ->assertSee('WARDEN_PROJECT=checkout-api');

    expect(\VictorStochero\Warden\Models\Project::where('slug', 'checkout-api')->exists())->toBeTrue();
});

it('forbids non-admins from the projects screen', function () {
    $viewer = User::factory()->create(['is_admin' => false]);
    $this->actingAs($viewer)->get('/admin/projects')->assertForbidden();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=AdminProjectsTest`
Expected: FAIL (component + route missing).

- [ ] **Step 3: Implement the Livewire component**

```php
// app/Livewire/Admin/Projects.php
<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Models\Project;

class Projects extends Component
{
    public string $name = '';
    public ?string $newToken = null;
    public ?string $newSecret = null;
    public ?string $snippet = null;

    public function mount(): void
    {
        $this->authorize('panel.manage');
    }

    public function createProject(ProjectManager $projects): void
    {
        $this->authorize('panel.manage');
        $this->validate(['name' => 'required|string|max:120']);

        $result = $projects->create($this->name);
        $project = $result['project'];

        $this->newToken = $result['token'];
        $this->newSecret = $result['secret'];
        $this->snippet = $projects->envSnippet(
            $project->slug,
            $result['token'],
            $result['secret'],
            rtrim(config('app.url'), '/'),
        );
        $this->name = '';
    }

    public function render()
    {
        return view('livewire.admin.projects', [
            'projects' => Project::query()->orderBy('name')->get(),
        ]);
    }
}
```

- [ ] **Step 4: Implement the view** (Flux-styled; shows the one-time secret + snippet after creation):

```blade
{{-- resources/views/livewire/admin/projects.blade.php --}}
<div class="space-y-6">
    <flux:heading size="xl">Projects</flux:heading>

    <form wire:submit="createProject" class="flex items-end gap-3">
        <flux:input wire:model="name" label="New project name" class="max-w-sm" />
        <flux:button type="submit" variant="primary">Create + mint credentials</flux:button>
    </form>

    @if ($snippet)
        <flux:callout variant="warning">
            <flux:heading>Copy this now — the secret is shown only once.</flux:heading>
            <pre class="font-mono text-sm whitespace-pre-wrap">{{ $snippet }}</pre>
        </flux:callout>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Slug</flux:table.column>
            <flux:table.column>Active</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($projects as $project)
                <flux:table.row wire:key="proj-{{ $project->id }}">
                    <flux:table.cell>{{ $project->name }}</flux:table.cell>
                    <flux:table.cell class="font-mono">{{ $project->slug }}</flux:table.cell>
                    <flux:table.cell>{{ $project->active ? 'yes' : 'no' }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
```
(If a referenced Flux component name differs in the installed Flux version, substitute the equivalent — verify against `vendor/livewire/flux` components. The test asserts behavior/text, not Flux internals.)

- [ ] **Step 5: Register the route**

In `routes/web.php`, inside the authenticated group:
```php
use App\Livewire\Admin\Projects;
Route::get('/admin/projects', Projects::class)->middleware(['auth', 'can:panel.manage'])->name('admin.projects');
```

- [ ] **Step 6: Run to verify it passes**

Run: `php artisan test --filter=AdminProjectsTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat(admin): project list + mint child credentials with one-time snippet"
```

---

### Task 7: Apply the Warden Design System theme

**Files:**
- Modify: `resources/css/app.css` (tokens, fonts, base surface)
- Modify: `tailwind.config.js` (or the Tailwind v4 `@theme` block in `app.css`, per starter kit) — Warden palette + fonts
- Create: `public/fonts/` (copy self-hosted woff2 from the package)
- Test: `tests/Feature/ThemeBuildTest.php` (smoke: a themed page renders the brand wordmark)

**Interfaces:**
- Consumes: the starter kit layout (Task 1).
- Produces: brand tokens (`brand`, `ink`) and fonts available to all panel views; a dark-first surface matching 0.3.5.

- [ ] **Step 1: Copy the self-hosted fonts**

```bash
mkdir -p public/fonts
cp vendor/victorstochero/warden/resources/dist/fonts/*.woff2 public/fonts/
ls public/fonts   # expect archivo-*, archivo-expanded-*, jetbrains-mono-*
```

- [ ] **Step 2: Add the Warden tokens** — in `tailwind.config.js` `theme.extend` (or the Tailwind v4 `@theme`), copy verbatim from the package:

```js
colors: {
  brand: { 300:'#8FB6FF', 400:'#5B97FF', 500:'#2E7BFF', 600:'#1F5FE0', 700:'#1747AE' },
  ink:   { 400:'#3C4866', 500:'#2E3950', 600:'#2E3950', 700:'#232C42', 750:'#1A2235',
           800:'#151C2E', 850:'#111726', 900:'#0A0E18', 950:'#070A12' },
},
fontFamily: {
  sans: ['Archivo','system-ui','sans-serif'],
  wordmark: ['"Archivo Expanded"','Archivo','sans-serif'],
  mono: ['"JetBrains Mono"','ui-monospace','monospace'],
},
boxShadow: { glow: '0 0 0 1px rgba(46,123,255,0.18), 0 6px 24px rgba(46,123,255,0.18)' },
```
And in `resources/css/app.css` add the `@font-face` blocks pointing to `/fonts/*.woff2` (mirror the package's `resources/css/warden.css` font declarations) and set the base `body { background-color:#070A12; }` with `darkMode: 'class'`.

- [ ] **Step 3: Build and write the smoke test**

```bash
ddev npm run build   # expect success, tokens compiled
```
```php
// tests/Feature/ThemeBuildTest.php
<?php
it('builds the themed stylesheet', function () {
    expect(file_exists(public_path('build/manifest.json')))->toBeTrue();
    expect(glob(public_path('fonts/archivo-*.woff2')))->not->toBeEmpty();
});
```

- [ ] **Step 4: Run to verify it passes**

Run: `php artisan test --filter=ThemeBuildTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(theme): apply Warden Design System tokens + self-hosted fonts"
```

---

### Task 8: Live fleet overview screen

**Files:**
- Create: `app/Livewire/Overview.php`
- Create: `resources/views/livewire/overview.blade.php`
- Modify: `routes/web.php` (authenticated `/` or `/overview`)
- Modify: `config/panel.php` (create; `poll_seconds` default 3)
- Test: `tests/Feature/OverviewScreenTest.php`

**Interfaces:**
- Consumes: `DashboardRepository::overview(): array` with keys `projects` (Collection of projects, each with `throughput`), `open_issues` (int), `open_incidents` (int), `throughput` (int); gate auth (Task 5).
- Produces: a `wire:poll`-refreshed fleet screen.

- [ ] **Step 1: Create the panel config**

```php
// config/panel.php
<?php
return [
    'poll_seconds' => (int) env('PANEL_POLL_SECONDS', 3),
];
```

- [ ] **Step 2: Write the failing test**

```php
// tests/Feature/OverviewScreenTest.php
<?php

use App\Models\User;
use App\Livewire\Overview;
use Livewire\Livewire;

it('renders fleet KPIs from the package read layer', function () {
    $this->artisan('warden:demo')->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Overview::class)
        ->assertViewHas('openIssues')
        ->assertViewHas('throughput')
        ->assertViewHas('projects');
});

it('requires authentication for the overview route', function () {
    $this->get('/')->assertRedirect('/login');
});
```

- [ ] **Step 3: Run to verify it fails**

Run: `php artisan test --filter=OverviewScreenTest`
Expected: FAIL (component/route missing).

- [ ] **Step 4: Implement the component**

```php
// app/Livewire/Overview.php
<?php

namespace App\Livewire;

use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

class Overview extends Component
{
    public function render(DashboardRepository $dashboard)
    {
        $overview = $dashboard->overview();

        return view('livewire.overview', [
            'projects' => $overview['projects'],
            'openIssues' => $overview['open_issues'],
            'openIncidents' => $overview['open_incidents'],
            'throughput' => $overview['throughput'],
        ]);
    }
}
```

- [ ] **Step 5: Implement the view with `wire:poll`**

```blade
{{-- resources/views/livewire/overview.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">Fleet overview</flux:heading>

    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-xl bg-ink-850 p-4 shadow-glow">
            <div class="text-slate-400 text-sm">Throughput</div>
            <div class="font-mono text-2xl text-brand-400">{{ number_format($throughput) }}</div>
        </div>
        <div class="rounded-xl bg-ink-850 p-4">
            <div class="text-slate-400 text-sm">Open issues</div>
            <div class="font-mono text-2xl">{{ $openIssues }}</div>
        </div>
        <div class="rounded-xl bg-ink-850 p-4">
            <div class="text-slate-400 text-sm">Open incidents</div>
            <div class="font-mono text-2xl">{{ $openIncidents }}</div>
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Project</flux:table.column>
            <flux:table.column>Throughput</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($projects as $project)
                <flux:table.row wire:key="ov-{{ $project->id }}">
                    <flux:table.cell>
                        <a href="{{ url('/projects/'.$project->slug) }}" class="text-brand-400">{{ $project->name }}</a>
                    </flux:table.cell>
                    <flux:table.cell class="font-mono">{{ number_format($project->throughput ?? 0) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
```

- [ ] **Step 6: Register the route**

In `routes/web.php` authenticated group:
```php
use App\Livewire\Overview;
Route::get('/', Overview::class)->middleware('auth')->name('overview');
```

- [ ] **Step 7: Run to verify it passes**

Run: `php artisan test --filter=OverviewScreenTest`
Expected: PASS.

- [ ] **Step 8: Full suite + commit**

```bash
php artisan test
git add -A && git commit -m "feat: live fleet overview screen with wire:poll"
```

---

## Phase 1 Done — Definition of Done

- `php artisan test` green; `npm run build` succeeds.
- A child app pointed at the panel (using the snippet minted in `/admin/projects`) ships batches that land in `wdn_events` and surface on the live overview.
- The package is unmodified; the package dashboard/auth is off; the panel UI reads only through the package read/service layer.

## Out of scope (subsequent plans)

Each becomes its own `docs/superpowers/plans/...` file, same task pattern:
- **Phase 2 — Per-project dashboard** (KPIs/series/uptime + requests/database/jobs/http/schedule sections).
- **Phase 3 — Traces** (list + waterfall + distributed trace).
- **Phase 4 — Issues (lifecycle) + Incidents** (via `IssueWorkflow`).
- **Phase 5 — Events + Logs.**
- **Phase 6 — Admin completeness** (token rotation/revoke, settings, audit log) + `warden:demo` polish + deploy README/docker-compose (roadmap #31).
```
