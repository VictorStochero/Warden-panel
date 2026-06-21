<?php

use App\Models\User;
use App\Livewire\Project\Trace;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use VictorStochero\Warden\Models\Project;

it('exposes distributed-trace context', function () {
    $this->artisan('warden:project', ['name' => 'Edge App'])->assertSuccessful();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Trace::class, ['slug' => 'edge-app', 'traceId' => 'xyz789'])
        ->assertViewHas('projects')
        ->assertViewHas('distributed')
        ->assertViewHas('rows');
});

it('sets distributed=true and tags rows with project_name when trace spans two projects', function () {
    // Create two projects
    $this->artisan('warden:project', ['name' => 'Edge App'])->assertSuccessful();
    $this->artisan('warden:project', ['name' => 'Core App'])->assertSuccessful();

    $edgeId = Project::where('slug', 'edge-app')->firstOrFail()->id;
    $coreId = Project::where('slug', 'core-app')->firstOrFail()->id;

    $traceId = 'dist-trace-1';

    // Insert one event per project sharing the same trace_id
    DB::table('wdn_events')->insert([
        [
            'project_id'    => $edgeId,
            'type'          => 'request',
            'trace_id'      => $traceId,
            'span_id'       => 'span-edge-1',
            'parent_span_id'=> null,
            'occurred_at'   => '2026-06-21 10:00:00',
            'occurred_date' => '2026-06-21',
            'duration_us'   => 50000,
            'payload'       => json_encode(['method' => 'GET', 'path' => '/api/data', 'status' => 200]),
        ],
        [
            'project_id'    => $coreId,
            'type'          => 'request',
            'trace_id'      => $traceId,
            'span_id'       => 'span-core-1',
            'parent_span_id'=> null,
            'occurred_at'   => '2026-06-21 10:00:00.100000',
            'occurred_date' => '2026-06-21',
            'duration_us'   => 30000,
            'payload'       => json_encode(['method' => 'GET', 'path' => '/internal/data', 'status' => 200]),
        ],
    ]);

    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Trace::class, [
        'slug'    => 'edge-app',
        'traceId' => $traceId,
    ]);

    $component->assertSet('traceId', $traceId);

    // distributed must be true — two distinct projects carry this trace_id
    $distributed = $component->viewData('distributed');
    expect($distributed)->toBeTrue();

    // projects collection must contain exactly 2 entries
    $projects = $component->viewData('projects');
    expect($projects->count())->toBe(2);

    // every row in the waterfall must carry a project_name key (tagged by distributedTrace())
    $rows = $component->viewData('rows');
    expect(collect($rows)->every(fn ($r) => array_key_exists('project_name', $r)))->toBeTrue();
});
