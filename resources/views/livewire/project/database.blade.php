{{-- resources/views/livewire/project/database.blade.php --}}
<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name . ' · ' . __('panel.nav.database')" :range="$range" :ranges="$ranges" />
    <x-panel.kpi-strip :project="$project" :kpis="$kpis" />

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:heading size="lg" class="mb-3">Query health</flux:heading>
        <p class="text-sm text-slate-400 mb-4">Sampled {{ $queryHealth['sampled'] }} of up to {{ $queryHealth['limit'] }} queries.</p>

        @php($labels = [
            'n_plus_one'  => 'N+1 queries',
            'duplicates'  => 'Duplicate queries',
            'select_star' => 'SELECT *',
            'no_where'    => 'UPDATE/DELETE without WHERE',
            'fat_requests'=> 'Fat requests',
            'slow'        => 'Slow queries',
        ])

        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
            @foreach ($labels as $key => $label)
                @php($entries = $queryHealth['findings'][$key] ?? [])
                @php($cnt = count($entries))
                <div class="rounded-lg bg-ink-900 p-3">
                    <div class="text-xs text-slate-400 mb-1">{{ $label }}</div>
                    <div class="text-2xl font-semibold {{ $cnt > 0 ? 'text-amber-400' : 'text-slate-300' }}">{{ $cnt }}</div>
                </div>
            @endforeach
        </div>

        @foreach (['n_plus_one' => 'N+1 queries', 'duplicates' => 'Duplicate queries', 'slow' => 'Slow queries'] as $key => $label)
            @php($entries = $queryHealth['findings'][$key] ?? [])
            @if (count($entries) > 0)
                <div class="mb-4">
                    <div class="text-sm font-medium text-slate-300 mb-2">{{ $label }}</div>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('panel.common.query') }}</flux:table.column>
                            <flux:table.column>{{ $key === 'slow' ? 'Duration' : 'Count' }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach (array_slice($entries, 0, 5) as $i => $entry)
                                <flux:table.row wire:key="health-{{ $key }}-{{ $i }}">
                                    <flux:table.cell class="font-mono text-xs max-w-md truncate">{{ $entry['sql'] ?? '' }}</flux:table.cell>
                                    <flux:table.cell class="{{ $key === 'slow' ? 'text-rose-400' : 'text-amber-400' }} text-xs">
                                        @if ($key === 'slow')
                                            {{ round(($entry['duration_us'] ?? 0) / 1000) }}ms
                                        @else
                                            {{ $entry['count'] ?? '' }}
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
        @endforeach
    </div>

    @php($tables = [['Slowest queries', $slowQueries], ['Most frequent queries', $frequentQueries]])
    @foreach ($tables as [$title, $rows])
        <div class="rounded-xl bg-ink-850 p-4">
            <flux:heading size="lg" class="mb-3">{{ $title }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('panel.common.query') }}</flux:table.column>
                    <flux:table.column>{{ __('panel.common.count') }}</flux:table.column>
                    <flux:table.column>{{ __('panel.common.avg') }}</flux:table.column>
                    <flux:table.column>{{ __('panel.common.max') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($rows as $row)
                        <flux:table.row wire:key="{{ $title }}-{{ $loop->index }}">
                            <flux:table.cell class="font-mono text-xs max-w-md truncate">{{ $row['sql'] }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($row['count']) }}</flux:table.cell>
                            <flux:table.cell>{{ $row['avg'] }}ms</flux:table.cell>
                            <flux:table.cell>{{ $row['max'] }}ms</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endforeach

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:heading size="lg" class="mb-3">Cache stores</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Store</flux:table.column>
                <flux:table.column>Hits</flux:table.column>
                <flux:table.column>Misses</flux:table.column>
                <flux:table.column>Hit rate</flux:table.column>
                <flux:table.column>Writes</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($cacheStores as $store)
                    <flux:table.row wire:key="cache-{{ $loop->index }}">
                        <flux:table.cell class="font-mono text-xs">{{ $store['key'] }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($store['hits']) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($store['misses']) }}</flux:table.cell>
                        <flux:table.cell class="{{ $store['rate'] >= 80 ? 'text-emerald-400' : ($store['rate'] >= 50 ? 'text-amber-400' : 'text-rose-400') }}">{{ $store['rate'] }}%</flux:table.cell>
                        <flux:table.cell>{{ number_format($store['writes']) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No cache activity in this window.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
