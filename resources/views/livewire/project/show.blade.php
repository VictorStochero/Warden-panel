<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name" :range="$range" :ranges="$ranges" />
    <x-panel.kpi-strip :project="$project" :kpis="$kpis" />

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main column --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl bg-ink-850 p-4">
                    <div class="mb-2 text-[11px] uppercase tracking-wider text-slate-500">Throughput</div>
                    <x-panel.bars :values="$series->pluck('count')->all()" color="#5B97FF" :height="64" />
                </div>
                <div class="rounded-xl bg-ink-850 p-4">
                    <div class="mb-2 text-[11px] uppercase tracking-wider text-slate-500">p95 latency</div>
                    <x-panel.chart :values="$series->pluck('p95')->map(fn ($v) => $v ?? 0)->all()" color="#fbbf24" :height="64" />
                </div>
            </div>

            <div class="rounded-xl bg-ink-850 p-4">
                <flux:heading size="lg" class="mb-3">Top routes</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Route</flux:table.column>
                        <flux:table.column>Count</flux:table.column>
                        <flux:table.column>p95</flux:table.column>
                        <flux:table.column>Errors</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($routes as $row)
                            <flux:table.row wire:key="route-{{ $loop->index }}">
                                <flux:table.cell class="font-mono text-xs">{{ $row['key'] }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($row['count']) }}</flux:table.cell>
                                <flux:table.cell>{{ $row['p95'] !== null ? $row['p95'].'ms' : '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $row['errors'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="rounded-xl bg-ink-850 p-4">
                <flux:heading size="lg" class="mb-3">Slowest queries</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Query</flux:table.column>
                        <flux:table.column>Count</flux:table.column>
                        <flux:table.column>Avg</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($slowQueries as $q)
                            <flux:table.row wire:key="sq-{{ $loop->index }}">
                                <flux:table.cell class="font-mono text-xs truncate max-w-md">{{ $q['sql'] }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($q['count']) }}</flux:table.cell>
                                <flux:table.cell>{{ $q['avg'] }}ms</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell class="text-slate-400">No slow queries.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>

        {{-- Sidebar widgets --}}
        <div class="space-y-6">
            @if ($incidents->where('status', 'open')->isNotEmpty())
                <div class="rounded-xl border border-rose-500/40 bg-ink-850 p-4">
                    <flux:heading size="sm" class="mb-2 text-rose-400">Active incidents</flux:heading>
                    @foreach ($incidents->where('status', 'open') as $incident)
                        <a href="{{ route('project.incident', ['slug' => $project->slug, 'incidentId' => $incident->id]) }}" wire:navigate
                            class="block border-b border-ink-800 py-1.5 text-sm hover:text-brand-400">{{ $incident->subject ?? $incident->summary }}</a>
                    @endforeach
                </div>
            @endif

            <div class="rounded-xl bg-ink-850 p-4">
                <flux:heading size="sm" class="mb-2">Recent issues</flux:heading>
                @forelse ($recentIssues as $issue)
                    <a href="{{ route('project.issue', ['slug' => $project->slug, 'issueId' => $issue->id]) }}" wire:navigate
                        class="block border-b border-ink-800 py-1.5 hover:text-brand-400">
                        <div class="font-mono text-xs">{{ $issue->class }}</div>
                        <div class="truncate text-xs text-slate-500">{{ $issue->message }}</div>
                    </a>
                @empty
                    <div class="text-sm text-slate-400">No open issues.</div>
                @endforelse
            </div>

            <div class="rounded-xl bg-ink-850 p-4">
                <flux:heading size="sm" class="mb-2">Heartbeats</flux:heading>
                @forelse ($heartbeats as $hb)
                    <div class="flex items-center gap-2 border-b border-ink-800 py-1.5 text-xs">
                        <span class="h-2 w-2 rounded-full {{ $hb['healthy'] ? 'bg-emerald-400' : 'bg-rose-400' }}"></span>
                        <span class="font-mono">{{ $hb['key'] }}</span>
                        <span class="ml-auto text-slate-500">{{ $hb['last_seen'] ?? '—' }}</span>
                    </div>
                @empty
                    <div class="text-sm text-slate-400">No heartbeats.</div>
                @endforelse
            </div>

            <div class="rounded-xl bg-ink-850 p-4">
                <flux:heading size="sm" class="mb-2">Recent traces</flux:heading>
                @forelse ($recentTraces as $t)
                    <a href="{{ route('project.trace', ['slug' => $project->slug, 'traceId' => $t['trace_id']]) }}" wire:navigate
                        class="flex items-center gap-2 border-b border-ink-800 py-1.5 text-xs hover:text-brand-400">
                        <span class="font-mono text-slate-400">{{ $t['type'] }}</span>
                        <span class="truncate">{{ $t['label'] }}</span>
                        @if ($t['errored'] ?? false)<span class="text-rose-400">err</span>@endif
                        <span class="ml-auto text-slate-500">{{ $t['duration_us'] !== null ? round($t['duration_us'] / 1000).'ms' : '' }}</span>
                    </a>
                @empty
                    <div class="text-sm text-slate-400">No traces.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
