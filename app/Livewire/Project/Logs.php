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

    #[Url]
    public string $level = '';

    #[Url]
    public string $q = '';

    public const LEVELS = ['debug', 'info', 'warning', 'error', 'critical'];

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
        if ($this->level !== '' && ! in_array($this->level, self::LEVELS, true)) {
            $this->level = '';
        }
        $project = $dashboard->project($this->slug);

        $level = $this->level;
        $needle = mb_strtolower(trim($this->q));

        $logs = $dashboard->recentEvents($project->id, 'log', 200, $this->range)
            ->filter(function (\stdClass $log) use ($level, $needle): bool {
                $payload = is_array($log->payload) ? $log->payload : [];
                if ($level !== '' && ($payload['level'] ?? 'info') !== $level) {
                    return false;
                }
                if ($needle !== '' && ! str_contains(mb_strtolower((string) ($payload['message'] ?? '')), $needle)) {
                    return false;
                }

                return true;
            })
            ->values();

        // Level distribution for the stacked bar (over the unfiltered window).
        $counts = $dashboard->recentEvents($project->id, 'log', 200, $this->range)
            ->countBy(fn (\stdClass $log) => is_array($log->payload) ? ($log->payload['level'] ?? 'info') : 'info');

        return view('livewire.project.logs', [
            'project' => $project,
            'ranges' => Ranges::all(),
            'kpis' => $dashboard->kpis($project->id, $this->range),
            'logs' => $logs,
            'levels' => self::LEVELS,
            'counts' => $counts,
        ]);
    }
}
