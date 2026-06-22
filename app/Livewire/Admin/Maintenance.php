<?php

namespace App\Livewire\Admin;

use App\Support\WritesAudit;
use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Maintenance\RunMaintenanceJob;
use VictorStochero\Warden\Models\CommandRun;

#[Layout('components.layouts.app')]
class Maintenance extends Component
{
    use WritesAudit;

    public function mount(): void
    {
        $this->authorize('panel.manage');
    }

    public function run(string $command): void
    {
        $this->authorize('panel.manage');

        if (! in_array($command, RunMaintenanceJob::ALLOWED, true)) {
            session()->flash('warden_error', 'Unknown maintenance command.');

            return;
        }

        $run = CommandRun::create([
            'command' => $command,
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        // Maintenance artisan commands are only registered in console context,
        // so they must run on a queue worker — mirror the package's dispatch.
        RunMaintenanceJob::dispatch($command, (int) $run->id);

        $this->audit('panel.maintenance.run', $command, ['command' => $command]);
        session()->flash('warden_status', "Queued warden:{$command}.");
    }

    public function render()
    {
        $latest = CommandRun::query()
            ->whereIn('command', RunMaintenanceJob::ALLOWED)
            ->orderByDesc('id')
            ->get()
            ->groupBy('command')
            ->map(fn ($rows) => $rows->first());

        return view('livewire.admin.maintenance', [
            'commands' => RunMaintenanceJob::ALLOWED,
            'descriptions' => RunMaintenanceJob::DESCRIPTIONS,
            'latest' => $latest,
            'recent' => CommandRun::query()->orderByDesc('id')->limit(15)->get(),
        ]);
    }
}
