<?php

namespace App\Livewire;

use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

class Search extends Component
{
    public bool $open = false;
    public string $q = '';

    public function render(DashboardRepository $dashboard)
    {
        $slug = request()->route('slug');
        $projectId = null;

        if (is_string($slug) && $slug !== '') {
            try {
                $projectId = $dashboard->project($slug)->id;
            } catch (\Throwable) {
                // Unknown slug — search projects globally only.
            }
        }

        $empty = ['projects' => [], 'routes' => [], 'issues' => [], 'traces' => []];
        $term = trim($this->q);
        $results = strlen($term) >= 2 ? $dashboard->search($term, $projectId) : $empty;

        return view('livewire.search', [
            'results' => $results,
            'slug' => $slug,
        ]);
    }
}
