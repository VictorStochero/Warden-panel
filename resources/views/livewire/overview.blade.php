<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">Fleet overview</flux:heading>

    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-xl bg-ink-850 p-4 shadow-glow">
            <div class="text-slate-400 text-sm">Throughput</div>
            <div class="font-mono text-2xl text-brand-400">{{ number_format($throughput) }}</div>
        </div>
        <div class="rounded-xl bg-ink-850 p-4">
            <div class="text-slate-400 text-sm">Open issues</div>
            <div class="font-mono text-2xl">{{ $openIssues }}</div>
        </div>
        <div class="rounded-xl bg-ink-850 p-4">
            <div class="text-slate-400 text-sm">Open incidents</div>
            <div class="font-mono text-2xl">{{ $openIncidents }}</div>
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Project</flux:table.column>
            <flux:table.column>Throughput</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($projects as $project)
                <flux:table.row wire:key="ov-{{ $project->id }}">
                    <flux:table.cell>
                        <a href="{{ url('/projects/'.$project->slug) }}" class="text-brand-400">{{ $project->name }}</a>
                    </flux:table.cell>
                    <flux:table.cell class="font-mono">{{ number_format($project->throughput ?? 0) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
