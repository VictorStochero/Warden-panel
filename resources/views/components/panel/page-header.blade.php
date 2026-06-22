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
        @endif
    </div>
</div>
