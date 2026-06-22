<?php

namespace App\Livewire\Project;

use App\Support\Ranges;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Logs extends Component
{
    public string $slug;

    #[Url]
    public string $range = '1h';

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
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

        return view('livewire.project.logs', [
            'project' => $project,
            'ranges' => Ranges::all(),
            'logs' => $dashboard->recentEvents($project->id, 'log', 100, $this->range),
        ]);
    }
}
