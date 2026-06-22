@props(['values' => [], 'color' => '#5B97FF', 'height' => 56, 'fill' => true])
@php
    $vals = array_values($values ?? []);
    $n = count($vals);
    $w = 600;
    $h = $height;
    $max = $n ? max(max($vals), 1) : 1;
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = $n > 1 ? ($i / ($n - 1)) * $w : 0;
        $y = $h - (((float) $v) / $max) * ($h - 6) - 3;
        $pts[] = round($x, 1).','.round($y, 1);
    }
    $line = implode(' ', $pts);
@endphp
@if ($n > 0)
    <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" class="w-full" style="height: {{ $h }}px">
        @if ($fill && $n > 1)
            <polygon points="0,{{ $h }} {{ $line }} {{ $w }},{{ $h }}" fill="{{ $color }}" opacity="0.12"></polygon>
        @endif
        <polyline points="{{ $line }}" fill="none" stroke="{{ $color }}" stroke-width="2"
            stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"></polyline>
    </svg>
@else
    <div class="flex items-center rounded-lg bg-ink-850 px-3 text-xs text-slate-600" style="height: {{ $h }}px">{{ __('panel.common.no_data') }}</div>
@endif
