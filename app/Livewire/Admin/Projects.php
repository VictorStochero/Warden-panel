<?php

namespace App\Livewire\Admin;

use App\Support\WritesAudit;
use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Projects\ProjectManager;

#[Layout('components.layouts.app')]
class Projects extends Component
{
    use WritesAudit;

    public string $name = '';

    public function mount(): void
    {
        $this->authorize('panel.manage');
    }

    public function createProject(ProjectManager $projects): void
    {
        $this->authorize('panel.manage');
        $this->validate(['name' => 'required|string|max:120']);

        $result = $projects->create($this->name);
        $this->flashCredentials($projects, $result['project'], $result['token'], $result['secret']);
        $this->audit('panel.project.create', $result['project']->slug);

        $this->name = '';
    }

    public function rotateToken(ProjectManager $projects, string $slug): void
    {
        $this->authorize('panel.manage');
        $project = Project::where('slug', $slug)->firstOrFail();

        $creds = $projects->rotate($project);
        $this->flashCredentials($projects, $project, $creds['token'], $creds['secret']);
        $this->audit('panel.project.rotate', $project->slug);
    }

    public function toggleActive(ProjectManager $projects, string $slug): void
    {
        $this->authorize('panel.manage');
        $project = Project::where('slug', $slug)->firstOrFail();

        $next = ! $project->active;
        $projects->setActive($project, $next);
        $this->audit($next ? 'panel.project.activate' : 'panel.project.deactivate', $project->slug);
    }

    private function flashCredentials(ProjectManager $projects, Project $project, string $token, string $secret): void
    {
        $snippet = $projects->envSnippet($project->slug, $token, $secret, rtrim(config('app.url'), '/'));

        session()->flash('warden_new_credentials', [
            'token' => $token,
            'secret' => $secret,
            'snippet' => $snippet,
        ]);
    }

    public function render()
    {
        return view('livewire.admin.projects', [
            'projects' => Project::query()->orderBy('name')->get(),
        ]);
    }
}
