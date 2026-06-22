<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name . ' · ' . __('panel.nav.schedule')" :range="$range" :ranges="$ranges" />
    <x-panel.kpi-strip :project="$project" :kpis="$kpis" />
    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Task</flux:table.column>
                <flux:table.column>Runs</flux:table.column>
                <flux:table.column>{{ __('panel.common.avg') }}</flux:table.column>
                <flux:table.column>{{ __('panel.common.max') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($tasks as $row)
                    <flux:table.row wire:key="task-{{ $loop->index }}">
                        <flux:table.cell class="font-mono">{{ $row['key'] }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($row['count']) }}</flux:table.cell>
                        <flux:table.cell>{{ $row['avg'] }}ms</flux:table.cell>
                        <flux:table.cell>{{ $row['max'] }}ms</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</div>
