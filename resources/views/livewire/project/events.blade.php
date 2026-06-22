<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <div class="flex items-center justify-between gap-3">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Events</flux:heading>
        <div class="flex gap-2">
            <flux:select wire:model.live="type" class="max-w-40">
                @foreach ($types as $t)<flux:select.option value="{{ $t }}">{{ ucfirst($t) }}</flux:select.option>@endforeach
            </flux:select>
            <flux:select wire:model.live="range" class="max-w-32">
                @foreach ($ranges as $r)<flux:select.option value="{{ $r }}">{{ $r }}</flux:select.option>@endforeach
            </flux:select>
        </div>
    </div>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Time</flux:table.column>
                <flux:table.column>Summary</flux:table.column>
                <flux:table.column>Trace</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($events as $event)
                    <flux:table.row wire:key="event-{{ $event->id }}">
                        <flux:table.cell class="font-mono text-xs text-slate-400">{{ $event->occurred_at }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs truncate max-w-md">{{ is_array($event->payload) ? json_encode($event->payload) : '' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">
                            @if ($event->trace_id)
                                <a class="text-brand-400" href="{{ route('project.trace', ['slug' => $project->slug, 'traceId' => $event->trace_id]) }}" wire:navigate>{{ \Illuminate\Support\Str::limit($event->trace_id, 10) }}</a>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No {{ $type }} events in this window.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
