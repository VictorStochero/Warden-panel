<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Uptime</flux:heading>

    <div class="rounded-xl bg-ink-850 p-4 shadow-glow">
        <div class="text-slate-400 text-sm">30-day availability</div>
        <div class="font-mono text-3xl text-brand-400">{{ round($uptime, 3) }}%</div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach ($windows as $w)
            <div class="rounded-xl bg-ink-850 p-3 @if($w['active']) ring-1 ring-brand-500 @endif">
                <div class="text-slate-400 text-xs">{{ $w['label'] }}</div>
                <div class="font-mono text-lg">{{ round($w['pct'], 2) }}%</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:heading size="lg" class="mb-3">Downtime incidents (30d)</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Incident</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($incidents as $incident)
                    <flux:table.row wire:key="dt-{{ $loop->index }}">
                        <flux:table.cell class="font-mono text-sm">{{ $incident->title ?? ('Incident #'.($incident->id ?? $loop->index)) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No downtime in the last 30 days.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
