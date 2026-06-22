<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name . ' · ' . __('panel.nav.security')" :showRanges="false" />

    @if ($audit === null)
        <flux:callout variant="secondary">No dependency audit recorded — run <span class="font-mono">warden:audit</span> on the child.</flux:callout>
    @else
        <div class="rounded-xl bg-ink-850 p-4 space-y-3">
            <flux:heading size="lg">Latest dependency audit</flux:heading>
            <div class="font-mono text-xs text-slate-400">{{ $audit->occurred_at }}</div>
            <pre class="font-mono text-xs whitespace-pre-wrap text-slate-300">{{ json_encode($audit->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @endif
</div>
