<?php

namespace App\Livewire\Project;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Issues\IssueWorkflow;
use VictorStochero\Warden\Models\Issue as IssueModel;

#[Layout('components.layouts.app')]
class Issue extends Component
{
    public string $slug;

    public int $issueId;

    public string $newComment = '';

    protected int $projectId;

    public function mount(string $slug, int $issueId, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $this->issueId = $issueId;
        $this->projectId = $dashboard->project($slug)->id;
    }

    /** Load the package Issue model for a mutation, scoped to this project. */
    protected function model(DashboardRepository $dashboard): IssueModel
    {
        $projectId = $dashboard->project($this->slug)->id;

        return IssueModel::query()->where('project_id', $projectId)->where('id', $this->issueId)->firstOrFail();
    }

    public function resolve(DashboardRepository $dashboard, IssueWorkflow $workflow): void
    {
        $this->authorize('panel.manage');
        $workflow->resolve($this->model($dashboard));
    }

    public function ignore(DashboardRepository $dashboard, IssueWorkflow $workflow): void
    {
        $this->authorize('panel.manage');
        $workflow->ignore($this->model($dashboard));
    }

    public function reopen(DashboardRepository $dashboard, IssueWorkflow $workflow): void
    {
        $this->authorize('panel.manage');
        $workflow->reopen($this->model($dashboard));
    }

    public function assignToMe(DashboardRepository $dashboard, IssueWorkflow $workflow): void
    {
        $this->authorize('panel.manage');
        $workflow->assign($this->model($dashboard), Auth::user()->name ?? Auth::user()->email);
    }

    public function unassign(DashboardRepository $dashboard, IssueWorkflow $workflow): void
    {
        $this->authorize('panel.manage');
        $workflow->assign($this->model($dashboard), null);
    }

    public function snooze(DashboardRepository $dashboard, IssueWorkflow $workflow): void
    {
        $this->authorize('panel.manage');
        $workflow->snooze($this->model($dashboard), 1440); // 1 day
    }

    public function addComment(DashboardRepository $dashboard, IssueWorkflow $workflow): void
    {
        $this->authorize('panel.manage');
        $this->validate(['newComment' => 'required|string|max:2000']);
        $author = Auth::user()->name ?? Auth::user()->email;
        $workflow->comment($this->model($dashboard), $author, $this->newComment);
        $this->newComment = '';
    }

    public function render(DashboardRepository $dashboard)
    {
        $project = $dashboard->project($this->slug);
        $issue = $dashboard->issue($project->id, $this->issueId);
        abort_if($issue === null, 404);

        return view('livewire.project.issue', [
            'project' => $project,
            'issue' => $issue,
            'whereToFix' => \App\Alerting\AlertComposer::topAppFrame(is_array($issue->stack) ? $issue->stack : null),
            'comments' => $dashboard->comments($this->issueId),
        ]);
    }
}
