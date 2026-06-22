<div class="space-y-6">
    <x-panel.page-header :title="$project->name . ' · Incident'" :showRanges="false" :live="false" />

    <div class="rounded-xl bg-ink-850 p-4 space-y-2">
        <div class="flex items-center gap-3">
            <flux:badge :color="$incident->status === 'open' ? 'rose' : 'lime'" size="sm">{{ $incident->status }}</flux:badge>
            <flux:badge color="zinc" size="sm">{{ $incident->severity }}</flux:badge>
        </div>
        <flux:heading size="lg">{{ $incident->subject }}</flux:heading>
        @if ($incident->summary)<p class="text-sm text-slate-400">{{ $incident->summary }}</p>@endif
        <div class="font-mono text-xs text-slate-500">Started {{ $incident->started_at }}@if ($incident->resolved_at) · Resolved {{ $incident->resolved_at }}@endif</div>
    </div>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:heading size="lg" class="mb-3">Open issues</flux:heading>
        @forelse ($context['open_issues'] as $issue)
            <div class="border-b border-ink-800 py-2 font-mono text-xs">
                <a class="text-brand-400" href="{{ route('project.issue', ['slug' => $project->slug, 'issueId' => $issue->id]) }}" wire:navigate>{{ $issue->exception_class ?? ($issue->title ?? 'issue #'.$issue->id) }}</a>
            </div>
        @empty
            <div class="text-slate-400 text-sm">No open issues.</div>
        @endforelse
    </div>
</div>
