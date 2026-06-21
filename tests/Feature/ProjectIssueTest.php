<?php

use App\Models\User;
use App\Livewire\Project\Issue as IssueComponent;
use Livewire\Livewire;
use VictorStochero\Warden\Models\Issue;
use VictorStochero\Warden\Models\Project;

function seedIssue(): array
{
    test()->artisan('warden:project', ['name' => 'Ops App'])->assertSuccessful();
    $pid = Project::where('slug', 'ops-app')->firstOrFail()->id;
    $issue = Issue::create(['project_id' => $pid, 'fingerprint' => 'fp-1', 'status' => 'open', 'class' => 'RuntimeException', 'message' => 'kaboom', 'count' => 5, 'last_seen_at' => now()]);
    return [$pid, $issue->id];
}

it('renders an issue with its comments', function () {
    [$pid, $id] = seedIssue();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(IssueComponent::class, ['slug' => 'ops-app', 'issueId' => $id])
        ->assertViewHas('issue', fn ($i) => $i->class === 'RuntimeException')
        ->assertViewHas('comments')
        ->assertSet('issueId', $id);
});

it('lets an admin resolve and comment, denies a viewer', function () {
    [$pid, $id] = seedIssue();
    $admin = User::factory()->create(['is_admin' => true]);
    $viewer = User::factory()->create(['is_admin' => false]);

    // admin resolves
    Livewire::actingAs($admin)->test(IssueComponent::class, ['slug' => 'ops-app', 'issueId' => $id])
        ->call('resolve');
    expect(Issue::find($id)->status)->toBe('resolved');

    // admin comments
    Livewire::actingAs($admin)->test(IssueComponent::class, ['slug' => 'ops-app', 'issueId' => $id])
        ->set('newComment', 'looking into it')
        ->call('addComment')
        ->assertSet('newComment', '');
    expect(\VictorStochero\Warden\Models\IssueComment::where('issue_id', $id)->count())->toBe(1);

    // viewer is forbidden to resolve
    Livewire::actingAs($viewer)->test(IssueComponent::class, ['slug' => 'ops-app', 'issueId' => $id])
        ->call('reopen')
        ->assertForbidden();
});

it('404s for an unknown issue', function () {
    seedIssue();
    $user = User::factory()->create();
    $this->actingAs($user)->get('/projects/ops-app/issues/999999')->assertNotFound();
});
