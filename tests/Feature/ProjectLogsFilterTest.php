<?php

use App\Models\User;
use App\Livewire\Project\Logs;
use Livewire\Livewire;
use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Models\Project;

function seedLog(int $projectId, string $level, string $message): void
{
    DB::table('wdn_events')->insert([
        'project_id' => $projectId, 'type' => 'log', 'trace_id' => 't-'.$level,
        'occurred_at' => now(), 'occurred_date' => now()->toDateString(),
        'payload' => json_encode(['level' => $level, 'message' => $message]),
    ]);
}

it('filters logs by level and coerces an unknown level', function () {
    test()->artisan('warden:project', ['name' => 'Log Filter App'])->assertSuccessful();
    $project = Project::where('slug', 'log-filter-app')->firstOrFail();
    seedLog($project->id, 'error', 'boom happened');
    seedLog($project->id, 'info', 'all good');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Logs::class, ['slug' => $project->slug])
        ->set('level', 'error')
        ->assertSee('boom happened')
        ->assertDontSee('all good')
        ->set('level', 'bogus')
        ->assertSet('level', '');
});

it('filters logs by message search', function () {
    test()->artisan('warden:project', ['name' => 'Log Filter App'])->assertSuccessful();
    $project = Project::where('slug', 'log-filter-app')->firstOrFail();
    seedLog($project->id, 'info', 'database timeout');
    seedLog($project->id, 'info', 'cache warmed');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Logs::class, ['slug' => $project->slug])
        ->set('q', 'timeout')
        ->assertSee('database timeout')
        ->assertDontSee('cache warmed');
});
