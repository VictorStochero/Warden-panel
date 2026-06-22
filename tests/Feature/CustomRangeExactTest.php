<?php

use App\Models\User;
use App\Livewire\Project\Logs;
use Livewire\Livewire;
use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Models\Project;

function seedLogAt(int $projectId, string $message, \Illuminate\Support\Carbon $at): void
{
    DB::table('wdn_events')->insert([
        'project_id' => $projectId, 'type' => 'log', 'trace_id' => 'tr-'.uniqid(),
        'occurred_at' => $at, 'occurred_date' => $at->toDateString(),
        'payload' => json_encode(['level' => 'info', 'message' => $message]),
    ]);
}

it('honors an exact from→to window beyond the preset range', function () {
    test()->artisan('warden:project', ['name' => 'Window App'])->assertSuccessful();
    $project = Project::where('slug', 'window-app')->firstOrFail();
    seedLogAt($project->id, 'recent-line', now());
    seedLogAt($project->id, 'old-line', now()->subDays(2));
    $user = User::factory()->create();

    // Default 1h preset: only the recent line is in range.
    Livewire::actingAs($user)->test(Logs::class, ['slug' => $project->slug])
        ->assertSee('recent-line')
        ->assertDontSee('old-line');

    // Exact window covering the last 3 days: the 2-day-old line now appears.
    Livewire::actingAs($user)->test(Logs::class, ['slug' => $project->slug])
        ->set('from', now()->subDays(3)->format('Y-m-d\TH:i'))
        ->set('to', now()->addHour()->format('Y-m-d\TH:i'))
        ->assertSee('recent-line')
        ->assertSee('old-line');
});

it('ignores an unparseable window and falls back to the preset', function () {
    test()->artisan('warden:project', ['name' => 'Window App'])->assertSuccessful();
    $project = Project::where('slug', 'window-app')->firstOrFail();
    seedLogAt($project->id, 'recent-line', now());
    seedLogAt($project->id, 'old-line', now()->subDays(2));
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Logs::class, ['slug' => $project->slug])
        ->set('from', 'not-a-date')
        ->set('to', '')
        ->assertSee('recent-line')
        ->assertDontSee('old-line');
});
