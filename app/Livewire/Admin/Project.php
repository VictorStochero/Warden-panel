<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Project extends Component
{
    public string $slug;

    public function mount(string $slug): void
    {
        $this->authorize('panel.manage');
        $this->slug = $slug;
    }

    public function render()
    {
        return view('livewire.admin.project');
    }
}
