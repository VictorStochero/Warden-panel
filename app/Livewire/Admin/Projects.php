<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Models\Project;

#[Layout('components.layouts.app')]
class Projects extends Component
{
    public string $name = '';
    public ?string $newToken = null;
    public ?string $newSecret = null;
    public ?string $snippet = null;

    public function mount(): void
    {
        $this->authorize('panel.manage');
    }

    public function createProject(ProjectManager $projects): void
    {
        $this->authorize('panel.manage');
        $this->validate(['name' => 'required|string|max:120']);

        $result = $projects->create($this->name);
        $project = $result['project'];

        $this->newToken = $result['token'];
        $this->newSecret = $result['secret'];
        $this->snippet = $projects->envSnippet(
            $project->slug,
            $result['token'],
            $result['secret'],
            rtrim(config('app.url'), '/'),
        );
        $this->name = '';
    }

    public function render()
    {
        return view('livewire.admin.projects', [
            'projects' => Project::query()->orderBy('name')->get(),
        ]);
    }
}
