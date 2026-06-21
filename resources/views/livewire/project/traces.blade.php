<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Traces</flux:heading>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Trace</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Entry</flux:table.column>
                <flux:table.column>Duration</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($traces as $t)
                    <flux:table.row wire:key="trace-{{ $t['trace_id'] }}">
                        <flux:table.cell class="font-mono text-xs">
                            <a class="text-brand-400 @if($t['errored']) text-rose-400 @endif"
                               href="{{ route('project.trace', ['slug' => $project->slug, 'traceId' => $t['trace_id']]) }}"
                               wire:navigate>{{ \Illuminate\Support\Str::limit($t['trace_id'], 16) }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $t['type'] }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs max-w-md truncate">{{ $t['label'] }}</flux:table.cell>
                        <flux:table.cell>{{ round($t['duration_us'] / 1000) }}ms</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No traces captured yet.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
