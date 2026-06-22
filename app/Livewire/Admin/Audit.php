<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Dashboard\DashboardRepository;

#[Layout('components.layouts.app')]
class Audit extends Component
{
    public function mount(): void
    {
        $this->authorize('panel.manage');
    }

    public function render(DashboardRepository $dashboard)
    {
        return view('livewire.admin.audit', [
            'entries' => $dashboard->auditLog(200),
        ]);
    }
}
