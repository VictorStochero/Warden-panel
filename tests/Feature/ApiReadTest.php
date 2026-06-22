<?php

use VictorStochero\Warden\Models\ApiToken;
use VictorStochero\Warden\Models\Project;

function apiProject(): Project
{
    test()->artisan('warden:project', ['name' => 'Api App'])->assertSuccessful();
    return Project::where('slug', 'api-app')->firstOrFail();
}

it('rejects API requests without a valid token', function () {
    $this->getJson('/api/v1/overview')->assertStatus(401);
});

it('serves the overview to a valid token', function () {
    [, $plaintext] = ApiToken::mint('CI reader');

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/api/v1/overview')
        ->assertOk()
        ->assertJsonStructure(['projects', 'open_issues', 'open_incidents', 'throughput']);
});

it('serves project KPIs and rejects an unknown event type', function () {
    $project = apiProject();
    [, $plaintext] = ApiToken::mint('CI reader');

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson("/api/v1/projects/{$project->slug}")
        ->assertOk()
        ->assertJsonPath('project.slug', $project->slug)
        ->assertJsonStructure(['kpis' => ['throughput', 'error_rate']]);

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson("/api/v1/projects/{$project->slug}/events/bogus")
        ->assertStatus(422);
});

it('rejects a revoked token', function () {
    [$model, $plaintext] = ApiToken::mint('CI reader');
    $model->delete();

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/api/v1/overview')
        ->assertStatus(401);
});
