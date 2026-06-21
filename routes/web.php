<?php

use App\Livewire\Admin\Projects;
use App\Livewire\Overview;
use App\Livewire\Project\Show as ProjectShow;
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
});

require __DIR__.'/auth.php';
