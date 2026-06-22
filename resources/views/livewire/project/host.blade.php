<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name . ' · ' . __('panel.nav.host')" :range="$range" :ranges="$ranges" />
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
                    <div class="font-mono text-2xl text-white">{{ $value }}</div>
                </div>
            @endforeach
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl bg-ink-850 p-4">
                <div class="mb-2 text-[11px] uppercase tracking-wider text-slate-500">CPU %</div>
                <x-panel.chart :values="$series->pluck('cpu')->map(fn ($v) => $v ?? 0)->all()" color="#6366f1" :height="72" />
            </div>
            <div class="rounded-xl bg-ink-850 p-4">
                <div class="mb-2 text-[11px] uppercase tracking-wider text-slate-500">Memory %</div>
                <x-panel.chart :values="$series->pluck('mem')->map(fn ($v) => $v ?? 0)->all()" color="#10b981" :height="72" />
            </div>
        </div>
    @endif
</div>
