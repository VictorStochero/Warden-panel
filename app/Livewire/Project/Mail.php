<?php

namespace App\Livewire\Project;

use App\Support\Ranges;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Mail extends Component
{
    public string $slug;

    #[Url]
    public string $range = '1h';

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $dashboard->project($slug);
    }

    public function render(DashboardRepository $dashboard)
    {
        $this->range = Ranges::sanitize($this->range);
        $project = $dashboard->project($this->slug);

        return view('livewire.project.mail', [
            'project' => $project,
            'ranges' => Ranges::all(),
            'kpis' => $dashboard->kpis($project->id, $this->range),
            'mailers' => $dashboard->breakdown($project->id, 'mail', $this->range),
            'notifications' => $dashboard->breakdown($project->id, 'notification', $this->range),
            'recentMail' => $dashboard->recentEvents($project->id, 'mail', 50, $this->range),
            'recentNotifications' => $dashboard->recentEvents($project->id, 'notification', 50, $this->range),
        ]);
    }
}
