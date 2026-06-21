<?php

use App\Models\User;
use App\Livewire\Project\Issues;
use Livewire\Livewire;
use VictorStochero\Warden\Models\Issue;

function seedProject(string $name): int
{
    test()->artisan('warden:project', ['name' => $name])->assertSuccessful();
    return \VictorStochero\Warden\Models\Project::where('slug', \Illuminate\Support\Str::slug($name))->firstOrFail()->id;
}

it('lists issues and filters by status', function () {
    $pid = seedProject('Reliability App');
    Issue::create(['project_id' => $pid, 'fingerprint' => 'fp-open', 'status' => 'open', 'class' => 'RuntimeException', 'message' => 'boom', 'count' => 3, 'last_seen_at' => now()]);
    Issue::create(['project_id' => $pid, 'fingerprint' => 'fp-res', 'status' => 'resolved', 'class' => 'LogicException', 'message' => 'fixed', 'count' => 1, 'last_seen_at' => now()]);
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Issues::class, ['slug' => 'reliability-app'])
        ->assertViewHas('issues', fn ($i) => $i->count() === 1)          // default status=open → only the open one
        ->assertSee('RuntimeException')
        ->set('status', 'resolved')
        ->assertViewHas('issues', fn ($i) => $i->count() === 1)
        ->assertSee('LogicException')
        ->set('status', '')                                              // all
        ->assertViewHas('issues', fn ($i) => $i->count() === 2);
});

it('requires auth for the issues list', function () {
    $this->get('/projects/reliability-app/issues')->assertRedirect('/login');
});
