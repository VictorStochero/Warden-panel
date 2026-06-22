@props(['values' => [], 'color' => '#5B97FF', 'height' => 56])
@php
    $vals = array_values($values ?? []);
    $n = count($vals);
    $max = $n ? max(max($vals), 1) : 1;
@endphp
@if ($n > 0)
    <div class="flex items-end gap-[2px] rounded-lg bg-ink-850 p-1" style="height: {{ $height }}px">
        @foreach ($vals as $v)
            <div class="flex-1 rounded-full transition-all"
                style="height: {{ max(2, (((float) $v) / $max) * 100) }}%; background: {{ $color }}; opacity: {{ $v > 0 ? 0.85 : 0.18 }}"
                title="{{ $v }}"></div>
        @endforeach
    </div>
@else
    <div class="flex items-center rounded-lg bg-ink-850 px-3 text-xs text-slate-600" style="height: {{ $height }}px">No data</div>
@endif
