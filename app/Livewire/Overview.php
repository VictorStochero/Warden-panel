<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Overview extends Component
{
    public function render(DashboardRepository $dashboard)
    {
        $overview = $dashboard->overview();

        return view('livewire.overview', [
            'projects' => $overview['projects'],
            'openIssues' => $overview['open_issues'],
            'openIncidents' => $overview['open_incidents'],
            'throughput' => $overview['throughput'],
        ]);
    }
}
