<?php

namespace App\Livewire\Project;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Errors extends Component
{
    public string $slug;

    #[Url]
    public string $release = '';

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $dashboard->project($slug);
    }

    public function render(DashboardRepository $dashboard)
    {
        $project = $dashboard->project($this->slug);
        $releases = $dashboard->releases($project->id, 20);

        if ($this->release !== '' && ! in_array($this->release, $releases->all(), true)) {
            $this->release = '';
        }

        return view('livewire.project.errors', [
            'project' => $project,
            'releases' => $releases,
            'errors' => $dashboard->recentErrors($project->id, 50, $this->release ?: null),
        ]);
    }
}
