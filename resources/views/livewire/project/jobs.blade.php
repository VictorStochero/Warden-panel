<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Jobs</flux:heading>
        <flux:select wire:model.live="range" class="max-w-32">
            @foreach ($ranges as $r)<flux:select.option value="{{ $r }}">{{ $r }}</flux:select.option>@endforeach
        </flux:select>
    </div>
    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Queue / Job</flux:table.column>
                <flux:table.column>Processed</flux:table.column>
                <flux:table.column>Failures</flux:table.column>
                <flux:table.column>Avg</flux:table.column>
                <flux:table.column>Max</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($queues as $row)
                    <flux:table.row wire:key="queue-{{ $loop->index }}">
                        <flux:table.cell class="font-mono">{{ $row['key'] }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($row['processed']) }}</flux:table.cell>
                        <flux:table.cell class="@if($row['failures']>0) text-rose-400 @endif">{{ $row['failures'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['avg'] }}ms</flux:table.cell>
                        <flux:table.cell>{{ $row['max'] }}ms</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</div>
