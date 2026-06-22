<?php

namespace App\Livewire\Project;

use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Incident extends Component
{
    public string $slug;
    public int $incidentId;

    public function mount(string $slug, int $incidentId, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $this->incidentId = $incidentId;
        $dashboard->project($slug);
    }

    public function render(DashboardRepository $dashboard)
    {
        $project = $dashboard->project($this->slug);
        $incident = $dashboard->incident($project->id, $this->incidentId);
        abort_if($incident === null, 404);

        return view('livewire.project.incident', [
            'project' => $project,
            'incident' => $incident,
            'context' => $dashboard->relatedContext($project->id),
        ]);
    }
}
