<?php

use App\Livewire\Admin\Projects;
use App\Livewire\Overview;
use App\Livewire\Project\Database as ProjectDatabase;
use App\Livewire\Project\Http as ProjectHttp;
use App\Livewire\Project\Jobs as ProjectJobs;
use App\Livewire\Project\Schedule as ProjectSchedule;
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

    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    Route::get('/admin/projects', Projects::class)->middleware('can:panel.manage')->name('admin.projects');

    Route::get('/projects/{slug}', ProjectShow::class)->name('project.show');
    Route::get('/projects/{slug}/database', ProjectDatabase::class)->name('project.database');
    Route::get('/projects/{slug}/jobs', ProjectJobs::class)->name('project.jobs');
    Route::get('/projects/{slug}/http', ProjectHttp::class)->name('project.http');
    Route::get('/projects/{slug}/schedule', ProjectSchedule::class)->name('project.schedule');
    Route::get('/projects/{slug}/uptime', ProjectUptime::class)->name('project.uptime');
    Route::get('/projects/{slug}/traces', ProjectTraces::class)->name('project.traces');
    Route::get('/projects/{slug}/traces/{traceId}', ProjectTrace::class)->name('project.trace');
});

require __DIR__.'/auth.php';
