<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">Fleet overview</flux:heading>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-ink-700/60 bg-ink-900 p-4 shadow-glow">
            <div class="text-[10px] uppercase tracking-wider text-slate-500">Projects</div>
            <div class="mt-1 font-mono text-xl font-semibold text-white">{{ $projects->count() }}</div>
        </div>
        <div class="rounded-2xl border border-ink-700/60 bg-ink-900 p-4">
            <div class="text-[10px] uppercase tracking-wider text-slate-500">Throughput</div>
            <div class="mt-1 font-mono text-xl font-semibold text-white">{{ number_format($throughput) }}</div>
        </div>
        <div class="rounded-2xl border border-ink-700/60 bg-ink-900 p-4">
            <div class="text-[10px] uppercase tracking-wider text-slate-500">Open issues</div>
            <div class="mt-1 font-mono text-xl font-semibold {{ $openIssues > 0 ? 'text-amber-400' : 'text-white' }}">{{ $openIssues }}</div>
        </div>
        <div class="rounded-2xl border border-ink-700/60 bg-ink-900 p-4">
            <div class="text-[10px] uppercase tracking-wider text-slate-500">Open incidents</div>
            <div class="mt-1 font-mono text-xl font-semibold {{ $openIncidents > 0 ? 'text-rose-400' : 'text-white' }}">{{ $openIncidents }}</div>
        </div>
    </div>

    @if ($groups->isNotEmpty() || $tags->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">
            @if ($groups->isNotEmpty())
                <span class="text-xs text-slate-500">Group:</span>
                <button type="button" wire:click="$set('group', '')" @class(['rounded-md px-2 py-1 text-xs', 'bg-brand-600 text-white' => $group === '', 'bg-ink-850 text-slate-400' => $group !== ''])>All</button>
                @foreach ($groups as $g)
                    <button type="button" wire:click="$set('group', '{{ $g->slug }}')" @class(['rounded-md px-2 py-1 text-xs', 'bg-brand-600 text-white' => $group === $g->slug, 'bg-ink-850 text-slate-400' => $group !== $g->slug])>{{ $g->name }}</button>
                @endforeach
            @endif
            @if ($tags->isNotEmpty())
                <span class="ml-3 text-xs text-slate-500">Tag:</span>
                <button type="button" wire:click="$set('tag', '')" @class(['rounded-md px-2 py-1 text-xs', 'bg-brand-600 text-white' => $tag === '', 'bg-ink-850 text-slate-400' => $tag !== ''])>All</button>
                @foreach ($tags as $t)
                    <button type="button" wire:click="$set('tag', '{{ $t->slug }}')" @class(['rounded-md px-2 py-1 text-xs', 'bg-brand-600 text-white' => $tag === $t->slug, 'bg-ink-850 text-slate-400' => $tag !== $t->slug])>{{ $t->name }}</button>
                @endforeach
            @endif
        </div>
    @endif

    @php($clusters = $projects->groupBy(fn ($p) => $p->group->name ?? 'Ungrouped'))
    @forelse ($clusters as $groupName => $items)
        <div>
            <div class="mb-2 text-[11px] uppercase tracking-wider text-slate-500">{{ $groupName }}</div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $project)
                    <a href="{{ url('/projects/'.$project->slug) }}" wire:navigate
                        class="flex items-center gap-3 rounded-2xl border border-ink-700/60 bg-ink-900 p-4 transition hover:border-brand-500/40">
                        <x-panel.health-ring :health="$project->health ?? null" />
                        <div class="min-w-0">
                            <div class="truncate font-medium text-slate-100">{{ $project->name }}</div>
                            <div class="font-mono text-xs text-slate-500">{{ $project->slug }}</div>
                            <div class="mt-1 flex gap-3 font-mono text-xs text-slate-400">
                                <span>{{ number_format($project->throughput ?? 0) }} req</span>
                                <span class="{{ ($project->error_rate ?? 0) > 1 ? 'text-rose-400' : '' }}">{{ $project->error_rate ?? 0 }}%</span>
                                <span>{{ $project->p95_ms !== null ? $project->p95_ms.'ms' : '—' }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-xl bg-ink-850 p-8 text-center text-slate-500">No projects match the current filters.</div>
    @endforelse
</div>
