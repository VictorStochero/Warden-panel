@props(['project', 'traceId' => null])
@php($ctx = app(\VictorStochero\Warden\Dashboard\DashboardRepository::class)->relatedContext($project->id, $traceId))
<div x-data="{ open: localStorage.getItem('wdn_related_open') !== 'false' }"
    class="rounded-xl border border-ink-700/60 bg-ink-900 p-4">
    <button type="button" @click="open = !open; localStorage.setItem('wdn_related_open', open)"
        class="mb-2 flex w-full items-center justify-between text-[11px] uppercase tracking-wider text-slate-500">
        <span>Related</span>
        <span x-text="open ? '−' : '+'"></span>
    </button>
    <div x-show="open" class="space-y-4 text-sm">
        @if ($traceId && ! empty($ctx['counts']))
            <div>
                <div class="mb-1 text-xs text-slate-500">Spans</div>
                <div class="flex flex-wrap gap-2 font-mono text-xs">
                    @foreach ($ctx['counts'] as $type => $n)
                        <span class="rounded bg-ink-850 px-2 py-0.5 text-slate-300">{{ $type }} {{ $n }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        @if (! empty($ctx['open_issues']) && count($ctx['open_issues']))
            <div>
                <div class="mb-1 text-xs text-slate-500">Open issues</div>
                @foreach ($ctx['open_issues'] as $issue)
                    <a href="{{ route('project.issue', ['slug' => $project->slug, 'issueId' => $issue->id]) }}" wire:navigate
                        class="block truncate py-0.5 font-mono text-xs hover:text-brand-400">{{ $issue->class ?? ('issue #'.$issue->id) }}</a>
                @endforeach
            </div>
        @endif

        @if (! empty($ctx['incidents']) && count($ctx['incidents']))
            <div>
                <div class="mb-1 text-xs text-slate-500">Incidents</div>
                @foreach ($ctx['incidents'] as $incident)
                    <a href="{{ route('project.incident', ['slug' => $project->slug, 'incidentId' => $incident->id]) }}" wire:navigate
                        class="block truncate py-0.5 text-xs hover:text-brand-400">{{ $incident->subject ?? $incident->summary }}</a>
                @endforeach
            </div>
        @endif

        @if (! $traceId && ! empty($ctx['recent_traces']) && count($ctx['recent_traces']))
            <div>
                <div class="mb-1 text-xs text-slate-500">Recent traces</div>
                @foreach ($ctx['recent_traces'] as $t)
                    <a href="{{ route('project.trace', ['slug' => $project->slug, 'traceId' => $t['trace_id']]) }}" wire:navigate
                        class="block truncate py-0.5 font-mono text-xs hover:text-brand-400">{{ $t['label'] ?? $t['trace_id'] }}</a>
                @endforeach
            </div>
        @endif
    </div>
</div>
