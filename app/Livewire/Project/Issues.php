<?php

namespace App\Livewire\Project;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Issues extends Component
{
    public string $slug;

    #[Url]
    public string $status = 'open';

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $dashboard->project($slug);
    }

    public function render(DashboardRepository $dashboard)
    {
        $project = $dashboard->project($this->slug);
        $filters = $this->status !== '' ? ['status' => $this->status] : [];

        return view('livewire.project.issues', [
            'project' => $project,
            'issues' => $dashboard->issues($project->id, $filters),
        ]);
    }
}
