<?php

namespace App\Livewire\Project;

use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Uptime extends Component
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

        return view('livewire.project.uptime', [
            'project' => $project,
            'kpis' => $dashboard->kpis($project->id, '24h'),
            'uptime' => $dashboard->uptime($project->id, '30d'),
            'windows' => $dashboard->uptimeWindows($project->id, '30d'),
            'incidents' => $dashboard->downtimeIncidents($project->id, 30, 50),
        ]);
    }
}
