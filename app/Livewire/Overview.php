<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Overview extends Component
{
    #[Url]
    public string $group = '';

    #[Url]
    public string $tag = '';

    public function render(DashboardRepository $dashboard)
    {
        $overview = $dashboard->overview();
        $groupSlugs = collect($overview['groups'])->pluck('slug')->all();
        $tagSlugs = collect($overview['tags'])->pluck('slug')->all();

        if ($this->group !== '' && ! in_array($this->group, $groupSlugs, true)) {
            $this->group = '';
        }
        if ($this->tag !== '' && ! in_array($this->tag, $tagSlugs, true)) {
            $this->tag = '';
        }

        $filtered = $dashboard->overview(array_filter([
            'group' => $this->group,
            'tag' => $this->tag,
        ], fn (string $v): bool => $v !== ''));

        return view('livewire.overview', [
            'projects' => $filtered['projects'],
            'openIssues' => $filtered['open_issues'],
            'openIncidents' => $filtered['open_incidents'],
            'throughput' => $filtered['throughput'],
            'groups' => $overview['groups'],
            'tags' => $overview['tags'],
        ]);
    }
}
