<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.page-header :title="$project->name . ' · Incidents'" :showRanges="false" />

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Incident</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Started</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($incidents as $incident)
                    <flux:table.row wire:key="incident-{{ $incident->id }}">
                        <flux:table.cell class="font-mono text-sm">#{{ $incident->id }} {{ $incident->subject ?? $incident->summary ?? '' }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="@if(($incident->status ?? '') === 'open') text-rose-400 @else text-slate-400 @endif">{{ $incident->status ?? '' }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="text-slate-400 text-sm">{{ $incident->started_at ?? '' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No incidents recorded.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
