@props(['project', 'kpis'])
@php($cards = [
    ['Throughput', number_format($kpis['throughput']), route('project.show', $project->slug)],
    ['Error rate', $kpis['error_rate'].'%', route('project.show', $project->slug)],
    ['p95', $kpis['p95'] !== null ? $kpis['p95'].'ms' : '—', route('project.show', $project->slug)],
    ['Slow', number_format($kpis['slow'] ?? 0), route('project.show', $project->slug)],
    ['Failed jobs', number_format($kpis['failed_jobs']), route('project.jobs', $project->slug)],
    ['Cache hit', $kpis['cache_hit_rate'] !== null ? $kpis['cache_hit_rate'].'%' : '—', route('project.database', $project->slug)],
    ['Open issues', $kpis['open_issues'], route('project.issues', $project->slug)],
    ['Uptime', round($kpis['uptime'], 2).'%', route('project.uptime', $project->slug)],
])
<div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-8">
    @foreach ($cards as [$label, $value, $href])
        <a href="{{ $href }}" wire:navigate class="rounded-xl bg-ink-850 p-3 transition hover:bg-ink-800 @if($loop->first) shadow-glow @endif">
            <div class="text-slate-400 text-xs">{{ $label }}</div>
            <div class="font-mono text-lg text-brand-400">{{ $value }}</div>
        </a>
    @endforeach
</div>
