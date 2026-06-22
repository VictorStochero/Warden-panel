<?php

use App\Livewire\Admin\ApiTokens as AdminApiTokens;
use App\Livewire\Admin\Audit as AdminAudit;
use App\Livewire\Admin\Maintenance as AdminMaintenance;
use App\Livewire\Admin\Project as AdminProject;
use App\Livewire\Admin\Projects;
use App\Livewire\Admin\Settings as AdminSettings;
use App\Livewire\Overview;
use App\Livewire\Project\Database as ProjectDatabase;
use App\Livewire\Project\Delivery as ProjectDelivery;
use App\Livewire\Project\Errors as ProjectErrors;
use App\Livewire\Project\Event as ProjectEvent;
use App\Livewire\Project\Host as ProjectHost;
use App\Livewire\Project\Http as ProjectHttp;
use App\Livewire\Project\Issue as ProjectIssue;
use App\Livewire\Project\Incident as ProjectIncident;
use App\Livewire\Project\Incidents as ProjectIncidents;
use App\Livewire\Project\Logs as ProjectLogs;
use App\Livewire\Project\Mail as ProjectMail;
use App\Livewire\Project\Events as ProjectEvents;
use App\Livewire\Project\Issues as ProjectIssues;
use App\Livewire\Project\Jobs as ProjectJobs;
use App\Livewire\Project\Requests as ProjectRequests;
use App\Livewire\Project\Schedule as ProjectSchedule;
use App\Livewire\Project\Security as ProjectSecurity;
use App\Livewire\Project\Show as ProjectShow;
use App\Livewire\Project\Trace as ProjectTrace;
use App\Livewire\Project\Traces as ProjectTraces;
use App\Livewire\Project\Uptime as ProjectUptime;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/', Overview::class)->name('home');

    Route::post('/locale', function (\Illuminate\Http\Request $request) {
        $locale = (string) $request->input('locale');
        if (in_array($locale, \App\Http\Middleware\SetLocale::SUPPORTED, true)) {
            $request->session()->put('locale', $locale);
        }

        return back();
    })->name('locale.set');

    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    Route::get('/admin/projects', Projects::class)->middleware('can:panel.manage')->name('admin.projects');
    Route::get('/admin/projects/{slug}/manage', AdminProject::class)->middleware('can:panel.manage')->name('admin.project');
    Route::get('/admin/audit', AdminAudit::class)->middleware('can:panel.manage')->name('admin.audit');
    Route::get('/admin/maintenance', AdminMaintenance::class)->middleware('can:panel.manage')->name('admin.maintenance');
    Route::get('/admin/settings', AdminSettings::class)->middleware('can:panel.manage')->name('admin.settings');
    Route::get('/admin/api-tokens', AdminApiTokens::class)->middleware('can:panel.manage')->name('admin.api-tokens');

    Route::get('/projects/{slug}', ProjectShow::class)->name('project.show');
    Route::get('/projects/{slug}/requests', ProjectRequests::class)->name('project.requests');
    Route::get('/projects/{slug}/database', ProjectDatabase::class)->name('project.database');
    Route::get('/projects/{slug}/jobs', ProjectJobs::class)->name('project.jobs');
    Route::get('/projects/{slug}/http', ProjectHttp::class)->name('project.http');
    Route::get('/projects/{slug}/schedule', ProjectSchedule::class)->name('project.schedule');
    Route::get('/projects/{slug}/errors', ProjectErrors::class)->name('project.errors');
    Route::get('/projects/{slug}/uptime', ProjectUptime::class)->name('project.uptime');
    Route::get('/projects/{slug}/host', ProjectHost::class)->name('project.host');
    Route::get('/projects/{slug}/mail', ProjectMail::class)->name('project.mail');
    Route::get('/projects/{slug}/security', ProjectSecurity::class)->name('project.security');
    Route::get('/projects/{slug}/delivery', ProjectDelivery::class)->name('project.delivery');
    Route::get('/projects/{slug}/traces', ProjectTraces::class)->name('project.traces');
    Route::get('/projects/{slug}/traces/{traceId}', ProjectTrace::class)->name('project.trace');
    Route::get('/projects/{slug}/issues', ProjectIssues::class)->name('project.issues');
    Route::get('/projects/{slug}/issues/{issueId}', ProjectIssue::class)->name('project.issue');
    Route::get('/projects/{slug}/incidents', ProjectIncidents::class)->name('project.incidents');
    Route::get('/projects/{slug}/incidents/{incidentId}', ProjectIncident::class)->whereNumber('incidentId')->name('project.incident');
    Route::get('/projects/{slug}/logs', ProjectLogs::class)->name('project.logs');
    Route::get('/projects/{slug}/events', ProjectEvents::class)->name('project.events');
    Route::get('/projects/{slug}/events/{eventId}', ProjectEvent::class)->whereNumber('eventId')->name('project.event');
});

require __DIR__.'/auth.php';
