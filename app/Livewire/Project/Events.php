<?php

namespace App\Livewire\Project;

use App\Support\Ranges;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Events extends Component
{
    public string $slug;

    #[Url]
    public string $type = 'mail';

    #[Url]
    public string $range = '1h';

    /** @return list<string> */
    public static function types(): array
    {
        return ['mail', 'notification', 'cache', 'command', 'schedule', 'exception', 'http'];
    }

    public function mount(string $slug, DashboardRepository $dashboard): void
    {
        $this->slug = $slug;
        $dashboard->project($slug);
    }

    public function updatedType(): void
    {
        if (! in_array($this->type, self::types(), true)) {
            $this->type = 'mail';
        }
    }

    public function render(DashboardRepository $dashboard)
    {
        if (! in_array($this->type, self::types(), true)) {
            $this->type = 'mail';
        }
        $this->range = Ranges::sanitize($this->range);
        $project = $dashboard->project($this->slug);

        return view('livewire.project.events', [
            'project' => $project,
            'ranges' => Ranges::all(),
            'kpis' => $dashboard->kpis($project->id, $this->range),
            'types' => self::types(),
            'events' => $dashboard->recentEvents($project->id, $this->type, 100, $this->range),
        ]);
    }
}
