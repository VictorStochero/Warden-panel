<?php

namespace App\Livewire\Project;

use App\Support\Waterfall;
use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Trace extends Component
{
    public string $slug;

    public string $traceId;

    public function mount(string $slug, string $traceId, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $this->traceId = $traceId;
        $dashboard->project($slug);
    }

    public function render(DashboardRepository $dashboard)
    {
        $project = $dashboard->project($this->slug);
        $spans = $dashboard->trace($project->id, $this->traceId);

        return view('livewire.project.trace', [
            'project' => $project,
            'rows' => Waterfall::rows($spans),
        ]);
    }
}
