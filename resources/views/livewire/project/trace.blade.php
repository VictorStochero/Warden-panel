{{-- resources/views/livewire/project/trace.blade.php --}}
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('project.traces', $project->slug) }}" class="text-brand-400 text-sm" wire:navigate>← Traces</a>
        <span class="font-mono text-xs text-slate-500">{{ $traceId }}</span>
    </div>
    <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Trace</flux:heading>

    <div class="rounded-xl bg-ink-850 p-4 space-y-1">
        @forelse ($rows as $row)
            <div class="flex items-center gap-2 text-xs">
                <div class="w-1/3 truncate font-mono text-slate-300" style="padding-left: {{ min($row['_left'], 0) }}px">{{ \Illuminate\Support\Str::limit($row['_label'], 60) }}</div>
                <div class="relative h-4 flex-1 rounded bg-ink-900">
                    <div class="absolute h-4 rounded" style="left: {{ $row['_left'] }}%; width: {{ $row['_width'] }}%; background: {{ $row['_color'] }}"></div>
                </div>
                <div class="w-16 text-right font-mono text-slate-400">{{ round(($row['duration_us'] ?? 0) / 1000) }}ms</div>
            </div>
        @empty
            <div class="text-slate-400 text-sm">No spans for this trace.</div>
        @endforelse
    </div>
</div>
