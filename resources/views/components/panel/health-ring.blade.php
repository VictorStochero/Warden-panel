@props(['health' => null, 'size' => 36])
@php
    $color = match ($health) {
        'healthy', 'ok', 'up' => '#34d399',
        'warning', 'warn', 'degraded' => '#fbbf24',
        'down', 'critical', 'error' => '#fb7185',
        default => '#64748b',
    };
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 36 36" class="shrink-0">
    <circle cx="18" cy="18" r="15" fill="none" stroke="#1A2235" stroke-width="4"></circle>
    <circle cx="18" cy="18" r="15" fill="none" stroke="{{ $color }}" stroke-width="4"
        stroke-dasharray="94" stroke-dashoffset="0" stroke-linecap="round" transform="rotate(-90 18 18)"></circle>
    <circle cx="18" cy="18" r="6" fill="{{ $color }}"></circle>
</svg>
