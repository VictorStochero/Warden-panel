<?php

namespace App\Livewire\Project;

use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Event extends Component
{
    public string $slug;
    public int $eventId;

    public function mount(string $slug, int $eventId, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $this->eventId = $eventId;
        $dashboard->project($slug);
    }

    public function render(DashboardRepository $dashboard)
    {
        $project = $dashboard->project($this->slug);
        $event = $dashboard->event($project->id, $this->eventId);
        abort_if($event === null, 404);

        return view('livewire.project.event', [
            'project' => $project,
            'event' => $event,
        ]);
    }
}
