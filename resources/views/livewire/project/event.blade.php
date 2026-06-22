<div class="space-y-6">
    <x-panel.page-header :title="$project->name . ' · Event #' . $event->id" :showRanges="false" :live="false" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-xl bg-ink-850 p-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                @php($meta = [
                    ['Type', $event->type],
                    ['When', $event->occurred_at],
                    ['Duration', $event->duration_us !== null ? round($event->duration_us / 1000).'ms' : '—'],
                    ['Release', $event->release ?? '—'],
                ])
                @foreach ($meta as [$label, $value])
                    <div>
                        <div class="text-slate-400 text-xs">{{ $label }}</div>
                        <div class="font-mono text-sm text-white">{{ $value }}</div>
                    </div>
                @endforeach
            </div>

            @if ($event->trace_id)
                <div>
                    <a class="text-brand-400" href="{{ route('project.trace', ['slug' => $project->slug, 'traceId' => $event->trace_id]) }}" wire:navigate>View trace {{ \Illuminate\Support\Str::limit($event->trace_id, 12) }} →</a>
                </div>
            @endif

            <div class="rounded-xl bg-ink-850 p-4">
                <flux:heading size="lg" class="mb-3">Payload</flux:heading>
                <pre class="font-mono text-xs whitespace-pre-wrap text-slate-300">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>

        <div>
            <x-panel.related :project="$project" :traceId="$event->trace_id" />
        </div>
    </div>
</div>
