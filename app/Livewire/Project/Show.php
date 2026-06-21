<?php

namespace App\Livewire\Project;

use App\Support\Ranges;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public string $slug;

    #[Url]
    public string $range = '1h';

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        // Resolve once to 404 early on unknown slug.
        $dashboard->project($slug);
    }

    public function updatedRange(): void
    {
        $this->range = Ranges::sanitize($this->range);
    }

    public function render(DashboardRepository $dashboard)
    {
        $this->range = Ranges::sanitize($this->range);
        $project = $dashboard->project($this->slug);

        return view('livewire.project.show', [
            'project' => $project,
            'ranges' => Ranges::all(),
            'kpis' => $dashboard->kpis($project->id, $this->range),
        ]);
    }
}
