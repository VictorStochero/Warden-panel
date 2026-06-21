{{-- resources/views/livewire/project/database.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Database</flux:heading>
        <flux:select wire:model.live="range" class="max-w-32">
            @foreach ($ranges as $r)<flux:select.option value="{{ $r }}">{{ $r }}</flux:select.option>@endforeach
        </flux:select>
    </div>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:heading size="lg" class="mb-3">Query health</flux:heading>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 font-mono text-sm">
            @foreach ($queryHealth as $label => $value)
                <div><span class="text-slate-400">{{ $label }}:</span> {{ is_scalar($value) ? $value : json_encode($value) }}</div>
            @endforeach
        </div>
    </div>

    @php($tables = [['Slowest queries', $slowQueries], ['Most frequent queries', $frequentQueries]])
    @foreach ($tables as [$title, $rows])
        <div class="rounded-xl bg-ink-850 p-4">
            <flux:heading size="lg" class="mb-3">{{ $title }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Query</flux:table.column>
                    <flux:table.column>Count</flux:table.column>
                    <flux:table.column>Avg</flux:table.column>
                    <flux:table.column>Max</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($rows as $row)
                        <flux:table.row wire:key="{{ $title }}-{{ $loop->index }}">
                            <flux:table.cell class="font-mono text-xs max-w-md truncate">{{ $row['sql'] }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($row['count']) }}</flux:table.cell>
                            <flux:table.cell>{{ $row['avg'] }}ms</flux:table.cell>
                            <flux:table.cell>{{ $row['max'] }}ms</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endforeach
</div>
