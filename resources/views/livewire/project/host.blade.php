<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name . ' · Host'" :range="$range" :ranges="$ranges" />
    <x-panel.kpi-strip :project="$project" :kpis="$kpis" />

    @if (empty($latest))
        <flux:callout variant="secondary">No host metrics captured in this window — enable the host recorder on the child.</flux:callout>
    @else
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @php($cards = [
                ['CPU', isset($latest['cpu']) && $latest['cpu'] !== null ? $latest['cpu'].'%' : '—'],
                ['Memory', isset($latest['mem']) && $latest['mem'] !== null ? $latest['mem'].'%' : '—'],
                ['Load (1m)', $latest['load'] ?? '—'],
                ['Disk', isset($latest['disk']) && $latest['disk'] !== null ? $latest['disk'].'%' : '—'],
            ])
            @foreach ($cards as [$label, $value])
                <div class="rounded-xl bg-ink-850 p-4">
                    <div class="text-slate-400 text-sm">{{ $label }}</div>
                    <div class="font-mono text-2xl text-brand-400">{{ $value }}</div>
                </div>
            @endforeach
        </div>
        <div class="rounded-xl bg-ink-850 p-4 font-mono text-sm text-slate-400">
            {{ $series->count() }} samples in this window.
        </div>
    @endif
</div>
