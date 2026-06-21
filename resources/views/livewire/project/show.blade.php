<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }}</flux:heading>
        <flux:select wire:model.live="range" class="max-w-32">
            @foreach ($ranges as $r)
                <flux:select.option value="{{ $r }}">{{ $r }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @php($cards = [
            ['Throughput', number_format($kpis['throughput'])],
            ['Error rate', $kpis['error_rate'].'%'],
            ['p95', $kpis['p95'] !== null ? $kpis['p95'].'ms' : '—'],
            ['Failed jobs', number_format($kpis['failed_jobs'])],
            ['Open issues', $kpis['open_issues']],
            ['Open incidents', $kpis['open_incidents']],
            ['Uptime', round($kpis['uptime'], 2).'%'],
            ['Cache hit', $kpis['cache_hit_rate'] !== null ? $kpis['cache_hit_rate'].'%' : '—'],
        ])
        @foreach ($cards as [$label, $value])
            <div class="rounded-xl bg-ink-850 p-4 @if($loop->first) shadow-glow @endif">
                <div class="text-slate-400 text-sm">{{ $label }}</div>
                <div class="font-mono text-2xl text-brand-400">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    {{-- Request series + top routes added in Task 2 --}}
</div>
