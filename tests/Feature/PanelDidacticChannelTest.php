<?php

use App\Alerting\PanelDidacticChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Models\Setting;

function seedAlertIncident(): Incident
{
    test()->artisan('warden:project', ['name' => 'Alert App'])->assertSuccessful();
    $project = Project::where('slug', 'alert-app')->firstOrFail();
    $issueId = DB::table('wdn_issues')->insertGetId([
        'project_id' => $project->id, 'fingerprint' => 'fp9', 'class' => 'App\\BoomException',
        'message' => 'Undefined array key total', 'status' => 'open', 'count' => 42,
        'last_trace_id' => 'tr9',
        'stack' => json_encode([
            ['file' => 'vendor/laravel/framework/src/X.php', 'line' => 1],
            ['file' => 'app/Http/Controllers/CheckoutController.php', 'line' => 42],
        ]),
    ]);

    return Incident::create([
        'project_id' => $project->id, 'subject' => 'issue:fp9', 'severity' => 'critical',
        'status' => 'open', 'summary' => 'App\\BoomException: Undefined array key total',
        'started_at' => now(), 'meta' => ['issue_id' => $issueId],
    ]);
}

it('posts a didactic webhook message with the app frame and link', function () {
    Http::fake();
    Setting::write('panel.alert_webhook', 'https://hooks.slack.com/services/T/B/X');
    $incident = seedAlertIncident();

    app(PanelDidacticChannel::class)->send($incident, 'opened');

    Http::assertSent(function ($request) {
        $body = $request['text'] ?? $request['content'] ?? '';
        return str_contains($body, 'App\\BoomException')
            && str_contains($body, 'app/Http/Controllers/CheckoutController.php:42')
            && str_contains($body, '/issues/');
    });
});

it('silences without a webhook and without e-mail, and never throws', function () {
    Http::fake();
    Setting::write('panel.alert_webhook', '');
    $incident = seedAlertIncident();

    app(PanelDidacticChannel::class)->send($incident, 'opened');

    Http::assertNothingSent();
});
