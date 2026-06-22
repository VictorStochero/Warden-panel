<?php

use App\Models\User;
use App\Livewire\Project\Issue;
use Livewire\Livewire;
use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Models\Project;

it('shows the app frame to fix on the issue page', function () {
    test()->artisan('warden:project', ['name' => 'Fix App'])->assertSuccessful();
    $project = Project::where('slug', 'fix-app')->firstOrFail();
    $id = DB::table('wdn_issues')->insertGetId([
        'project_id' => $project->id, 'fingerprint' => 'fp1', 'class' => 'App\\BoomException',
        'message' => 'Undefined array key', 'status' => 'open', 'count' => 3,
        'last_trace_id' => 'trace-1',
        'stack' => json_encode([
            ['file' => 'vendor/laravel/framework/src/X.php', 'line' => 10],
            ['file' => 'app/Http/Controllers/CheckoutController.php', 'line' => 42],
        ]),
    ]);
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Issue::class, ['slug' => $project->slug, 'issueId' => $id])
        ->assertViewHas('whereToFix', 'app/Http/Controllers/CheckoutController.php:42')
        ->assertSee('app/Http/Controllers/CheckoutController.php:42')
        ->assertSee('Where to fix');
});
