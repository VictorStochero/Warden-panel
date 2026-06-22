<?php

namespace App\Livewire\Project;

use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Security extends Component
{
    public string $slug;

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $dashboard->project($slug);
    }

    public function render(DashboardRepository $dashboard)
    {
        $project = $dashboard->project($this->slug);

        return view('livewire.project.security', [
            'project' => $project,
            'audit' => $dashboard->recentEvents($project->id, 'security', 1, '30d')->first(),
        ]);
    }
}
