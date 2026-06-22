<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name . ' · ' . __('panel.nav.requests')" :range="$range" :ranges="$ranges" />
    <x-panel.kpi-strip :project="$project" :kpis="$kpis" />

    @if ($markers->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2 rounded-xl bg-ink-850 p-3">
            <span class="text-[11px] uppercase tracking-wider text-slate-500">Deploys</span>
            @foreach ($markers as $m)
                <span class="flex items-center gap-1.5 rounded-md bg-ink-900 px-2 py-1 font-mono text-xs text-brand-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>{{ \Illuminate\Support\Str::limit($m->release, 12) }}
                    <span class="text-slate-500">{{ $m->first_seen }}</span>
                </span>
            @endforeach
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl bg-ink-850 p-4">
            <div class="mb-2 text-[11px] uppercase tracking-wider text-slate-500">Throughput</div>
            <x-panel.bars :values="$series->pluck('count')->all()" color="#5B97FF" :height="64" />
        </div>
        <div class="rounded-xl bg-ink-850 p-4">
            <div class="mb-2 text-[11px] uppercase tracking-wider text-slate-500">Errors</div>
            <x-panel.bars :values="$series->pluck('errors')->all()" color="#fb7185" :height="64" />
        </div>
        <div class="rounded-xl bg-ink-850 p-4">
            <div class="mb-2 text-[11px] uppercase tracking-wider text-slate-500">p95 latency</div>
            <x-panel.chart :values="$series->pluck('p95')->map(fn ($v) => $v ?? 0)->all()" color="#fbbf24" :height="64" />
        </div>
    </div>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:heading size="lg" class="mb-3">{{ __('panel.common.top_routes') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('panel.common.route') }}</flux:table.column>
                <flux:table.column>{{ __('panel.common.count') }}</flux:table.column>
                <flux:table.column>p95</flux:table.column>
                <flux:table.column>{{ __('panel.common.errors') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($routes as $row)
                    <flux:table.row wire:key="route-{{ $loop->index }}">
                        <flux:table.cell class="font-mono">{{ $row['key'] }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($row['count']) }}</flux:table.cell>
                        <flux:table.cell>{{ $row['p95'] !== null ? $row['p95'].'ms' : '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $row['errors'] }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:heading size="lg" class="mb-3">{{ __('panel.common.recent_requests') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('panel.common.time') }}</flux:table.column>
                <flux:table.column>{{ __('panel.common.request') }}</flux:table.column>
                <flux:table.column>{{ __('panel.common.status') }}</flux:table.column>
                <flux:table.column>{{ __('panel.common.duration') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($recent as $e)
                    <flux:table.row wire:key="req-{{ $e->id }}">
                        <flux:table.cell class="font-mono text-xs text-slate-400">{{ $e->occurred_at }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ is_array($e->payload) ? (($e->payload['method'] ?? '').' '.($e->payload['path'] ?? '')) : '' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ is_array($e->payload) ? ($e->payload['status'] ?? '') : '' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $e->duration_us !== null ? round($e->duration_us / 1000).'ms' : '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No requests in this window.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
