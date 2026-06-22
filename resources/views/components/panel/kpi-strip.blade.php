@props(['project', 'kpis'])
@php
    $tones = [
        'white' => 'text-white',
        'emerald' => 'text-emerald-400',
        'amber' => 'text-amber-400',
        'rose' => 'text-rose-400',
    ];
    $errorTone = $kpis['error_rate'] > 1 ? 'rose' : ($kpis['error_rate'] > 0 ? 'amber' : 'white');
    $p95Tone = ($kpis['p95'] !== null && $kpis['p95'] > 500) ? 'amber' : 'white';
    $uptime = (float) $kpis['uptime'];
    $uptimeTone = $uptime >= 99.9 ? 'emerald' : ($uptime >= 95 ? 'amber' : 'rose');

    $cards = [
        ['Throughput', number_format($kpis['throughput']), 'white', route('project.requests', $project->slug)],
        ['Error rate', $kpis['error_rate'].'%', $errorTone, route('project.errors', $project->slug)],
        ['p95', $kpis['p95'] !== null ? $kpis['p95'].'ms' : '—', $p95Tone, route('project.requests', $project->slug)],
        ['Slow', number_format($kpis['slow'] ?? 0), 'white', route('project.requests', $project->slug)],
        ['Failed jobs', number_format($kpis['failed_jobs']), $kpis['failed_jobs'] > 0 ? 'rose' : 'white', route('project.jobs', $project->slug)],
        ['Cache hit', $kpis['cache_hit_rate'] !== null ? $kpis['cache_hit_rate'].'%' : '—', 'white', route('project.database', $project->slug)],
        ['Open issues', $kpis['open_issues'], $kpis['open_issues'] > 0 ? 'amber' : 'white', route('project.issues', $project->slug)],
        ['Uptime', round($uptime, 2).'%', $uptimeTone, route('project.uptime', $project->slug)],
    ];
@endphp
<div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-8">
    @foreach ($cards as [$label, $value, $tone, $href])
        <a href="{{ $href }}" wire:navigate
            class="rounded-2xl border border-ink-700/60 bg-ink-900 p-3 transition hover:border-brand-500/40 @if($loop->first) shadow-glow @endif">
            <div class="text-[10px] uppercase tracking-wider text-slate-500">{{ $label }}</div>
            <div class="mt-1 font-mono text-xl font-semibold {{ $tones[$tone] }}">{{ $value }}</div>
        </a>
    @endforeach
</div>
