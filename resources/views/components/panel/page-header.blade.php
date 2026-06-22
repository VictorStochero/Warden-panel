@props([
    'title',
    'subtitle' => null,
    'range' => null,
    'ranges' => [],
    'showRanges' => true,
    'live' => true,
])
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <flux:heading size="xl" class="font-wordmark">{{ $title }}</flux:heading>
        @if ($subtitle)<flux:subheading>{{ $subtitle }}</flux:subheading>@endif
    </div>
    <div class="flex items-center gap-3">
        @if ($live)
            <span class="flex items-center gap-1.5 text-xs text-emerald-400">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                </span>
                LIVE
            </span>
        @endif
        @if ($showRanges && $ranges)
            <div class="flex gap-1 rounded-lg bg-ink-850 p-1">
                @foreach ($ranges as $r)
                    <button type="button" wire:click="$set('range', '{{ $r }}')"
                        @class([
                            'rounded-md px-2.5 py-1 text-xs font-mono transition',
                            'bg-brand-600 text-white' => $range === $r,
                            'text-slate-400 hover:text-slate-200' => $range !== $r,
                        ])>{{ $r }}</button>
                @endforeach
            </div>
            <details class="relative">
                <summary class="cursor-pointer list-none rounded-md bg-ink-850 px-2.5 py-1 text-xs text-slate-400 hover:text-slate-200">Custom</summary>
                <div class="absolute right-0 z-20 mt-1 w-64 space-y-2 rounded-lg bg-ink-900 p-3 ring-1 ring-ink-700"
                    x-data="{ from: '', to: '', apply() {
                        if (!this.from || !this.to) return;
                        const mins = Math.max(1, Math.round((new Date(this.to) - new Date(this.from)) / 60000));
                        const presets = { '15m': 15, '1h': 60, '6h': 360, '24h': 1440, '7d': 10080, '30d': 43200 };
                        let best = '1h', bd = Infinity;
                        for (const k in presets) { const d = Math.abs(presets[k] - mins); if (d < bd) { bd = d; best = k; } }
                        $wire.set('range', best);
                    } }">
                    <label class="block text-[10px] uppercase tracking-wider text-slate-500">From
                        <input type="datetime-local" x-model="from" class="mt-1 w-full rounded bg-ink-850 px-2 py-1 text-xs text-slate-100" />
                    </label>
                    <label class="block text-[10px] uppercase tracking-wider text-slate-500">To
                        <input type="datetime-local" x-model="to" class="mt-1 w-full rounded bg-ink-850 px-2 py-1 text-xs text-slate-100" />
                    </label>
                    <button type="button" @click="apply()" class="w-full rounded bg-brand-600 px-2 py-1 text-xs text-white">Apply (nearest preset)</button>
                </div>
            </details>
        @endif
    </div>
</div>
