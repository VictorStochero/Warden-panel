<?php

namespace App\Livewire\Admin;

use App\Support\WritesAudit;
use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Models\Project as ProjectModel;
use VictorStochero\Warden\Projects\ProjectManager;

#[Layout('components.layouts.app')]
class Project extends Component
{
    use WritesAudit;

    public string $slug;
    public string $name = '';
    public string $client = '';
    public string $contact = '';
    public string $group = '';
    public string $tags = '';

    public function mount(string $slug): void
    {
        $this->authorize('panel.manage');
        $this->slug = $slug;

        $project = $this->project();
        $this->name = $project->name;
        $this->client = (string) $project->client;
        $this->contact = (string) $project->contact;
        $this->group = (string) ($project->group?->name ?? '');
        $this->tags = $project->tags->pluck('name')->implode(', ');
    }

    private function project(): ProjectModel
    {
        return ProjectModel::where('slug', $this->slug)->firstOrFail();
    }

    public function save(ProjectManager $projects): void
    {
        $this->authorize('panel.manage');
        $this->validate(['name' => 'required|string|max:120']);

        $projects->updateDetails($this->project(), [
            'name' => $this->name,
            'client' => $this->client,
            'contact' => $this->contact,
            'group' => $this->group,
            'tags' => $this->tags,
        ]);
        $this->audit('panel.project.update', $this->slug);

        session()->flash('admin_project_saved', true);
    }

    public function render()
    {
        return view('livewire.admin.project', ['project' => $this->project()]);
    }
}
