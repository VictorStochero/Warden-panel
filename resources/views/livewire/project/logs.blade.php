<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name . ' · ' . __('panel.nav.logs')" :range="$range" :ranges="$ranges" />
    <x-panel.kpi-strip :project="$project" :kpis="$kpis" />

    @php($levelBar = ['debug' => 'bg-slate-500', 'info' => 'bg-brand-500', 'warning' => 'bg-amber-500', 'error' => 'bg-rose-500', 'critical' => 'bg-rose-600'])
    @php($total = max(1, $counts->sum()))
    <div class="rounded-xl bg-ink-850 p-4 space-y-3">
        <div class="flex h-3 overflow-hidden rounded-full bg-ink-900">
            @foreach ($levels as $lv)
                @if (($counts[$lv] ?? 0) > 0)
                    <button type="button" wire:click="$set('level', '{{ $lv }}')" title="{{ $lv }}: {{ $counts[$lv] }}"
                        class="{{ $levelBar[$lv] }} transition" style="width: {{ ($counts[$lv] / $total) * 100 }}%"></button>
                @endif
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="$set('level', '')" @class(['rounded-md px-2 py-1 text-xs', 'bg-brand-600 text-white' => $level === '', 'bg-ink-900 text-slate-400' => $level !== ''])>All</button>
            @foreach ($levels as $lv)
                <button type="button" wire:click="$set('level', '{{ $lv }}')" @class(['rounded-md px-2 py-1 text-xs uppercase', 'bg-brand-600 text-white' => $level === $lv, 'bg-ink-900 text-slate-400' => $level !== $lv])>{{ $lv }} <span class="opacity-60">{{ $counts[$lv] ?? 0 }}</span></button>
            @endforeach
            <input type="text" wire:model.live.debounce.300ms="q" placeholder="Search messages…"
                class="ml-auto w-56 rounded-md bg-ink-900 px-2 py-1 text-xs text-slate-100 placeholder-slate-500 focus:outline-none" />
        </div>
    </div>

    <div class="rounded-xl bg-ink-850 p-4 space-y-1">
        @php($levelColor = ['error' => 'text-rose-400', 'critical' => 'text-rose-400', 'warning' => 'text-amber-400', 'info' => 'text-brand-400', 'debug' => 'text-slate-400'])
        @forelse ($logs as $log)
            @php($level = is_array($log->payload) ? ($log->payload['level'] ?? 'info') : 'info')
            <div class="flex gap-3 text-xs font-mono border-b border-ink-800 py-1">
                <span class="w-40 shrink-0 text-slate-500">{{ $log->occurred_at }}</span>
                <span class="w-16 shrink-0 {{ $levelColor[$level] ?? 'text-slate-400' }}">{{ strtoupper($level) }}</span>
                <span class="text-slate-300 truncate">{{ is_array($log->payload) ? ($log->payload['message'] ?? '') : '' }}</span>
            </div>
        @empty
            <div class="text-slate-400 text-sm">No logs in this window.</div>
        @endforelse
    </div>
</div>
