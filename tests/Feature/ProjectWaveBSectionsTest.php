<?php

use App\Models\User;
use App\Livewire\Project\Requests;
use App\Livewire\Project\Errors;
use App\Livewire\Project\Mail;
use App\Livewire\Project\Host;
use App\Livewire\Project\Security;
use App\Livewire\Project\Delivery;
use App\Livewire\Project\Event;
use App\Livewire\Project\Incident;
use Livewire\Livewire;
use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Models\Project;

function seedWaveBProject(string $name = 'Wave B App'): Project
{
    test()->artisan('warden:project', ['name' => $name])->assertSuccessful();
    return Project::where('slug', \Illuminate\Support\Str::slug($name))->firstOrFail();
}

it('renders the requests section with a range filter', function () {
    $project = seedWaveBProject();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Requests::class, ['slug' => $project->slug])
        ->assertViewHas('routes')->assertViewHas('recent')
        ->assertSet('range', '1h')->set('range', '6h')->assertSet('range', '6h');
});

it('renders the errors section and coerces an unknown release', function () {
    $project = seedWaveBProject();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Errors::class, ['slug' => $project->slug])
        ->assertViewHas('errors')->assertViewHas('releases')
        ->set('release', 'bogus-release')->assertSet('release', '');
});

it('renders mail, host, security, delivery sections', function () {
    $project = seedWaveBProject();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Mail::class, ['slug' => $project->slug])->assertViewHas('mailers');
    Livewire::actingAs($user)->test(Host::class, ['slug' => $project->slug])->assertViewHas('latest');
    Livewire::actingAs($user)->test(Security::class, ['slug' => $project->slug])->assertViewHas('audit');
    Livewire::actingAs($user)->test(Delivery::class, ['slug' => $project->slug])->assertViewHas('delivery');
});

it('requires auth for a new section', function () {
    $project = seedWaveBProject();
    $this->get("/projects/{$project->slug}/delivery")->assertRedirect('/login');
});

it('renders event detail and 404s an unknown event', function () {
    $project = seedWaveBProject();
    $user = User::factory()->create();
    $id = DB::table('wdn_events')->insertGetId([
        'project_id' => $project->id, 'type' => 'exception', 'trace_id' => 'tr1',
        'occurred_at' => now(), 'occurred_date' => now()->toDateString(), 'payload' => '{"class":"X"}',
    ]);

    Livewire::actingAs($user)->test(Event::class, ['slug' => $project->slug, 'eventId' => $id])
        ->assertViewHas('event');

    $this->actingAs($user)->get("/projects/{$project->slug}/events/999999")->assertNotFound();
});

it('renders incident detail and 404s an unknown incident', function () {
    $project = seedWaveBProject();
    $user = User::factory()->create();
    $id = DB::table('wdn_incidents')->insertGetId([
        'project_id' => $project->id, 'subject' => 'DB down', 'severity' => 'critical',
        'status' => 'open', 'started_at' => now(),
    ]);

    Livewire::actingAs($user)->test(Incident::class, ['slug' => $project->slug, 'incidentId' => $id])
        ->assertViewHas('incident');

    $this->actingAs($user)->get("/projects/{$project->slug}/incidents/999999")->assertNotFound();
});
