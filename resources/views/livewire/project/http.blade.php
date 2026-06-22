<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name . ' · HTTP'" :range="$range" :ranges="$ranges" />
    <x-panel.kpi-strip :project="$project" :kpis="$kpis" />
    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Host</flux:table.column>
                <flux:table.column>Count</flux:table.column>
                <flux:table.column>Errors</flux:table.column>
                <flux:table.column>Avg</flux:table.column>
                <flux:table.column>Max</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($hosts as $row)
                    <flux:table.row wire:key="host-{{ $loop->index }}">
                        <flux:table.cell class="font-mono">{{ $row['key'] }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($row['count']) }}</flux:table.cell>
                        <flux:table.cell class="@if($row['errors']>0) text-rose-400 @endif">{{ $row['errors'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['avg'] }}ms</flux:table.cell>
                        <flux:table.cell>{{ $row['max'] }}ms</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</div>
