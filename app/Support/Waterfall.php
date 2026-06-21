<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Waterfall
{
    /** Type → bar colour (hex), matching the Warden DS span palette. */
    public static function color(string $type): string
    {
        return [
            'request' => '#2E7BFF',
            'query' => '#5BD98F',
            'http' => '#FFB020',
            'cache' => '#8FB6FF',
            'job' => '#FFC04D',
            'exception' => '#FF5A52',
            'log' => '#9BA7C0',
        ][$type] ?? '#64748b';
    }

    /** Human label for a span, derived from its payload per type. */
    public static function label(array $span): string
    {
        $p = $span['payload'] ?? [];

        return match ($span['type'] ?? '') {
            'query' => (string) ($p['sql'] ?? 'query'),
            'request' => trim(($p['method'] ?? '').' '.($p['route'] ?? $p['path'] ?? '')),
            'http' => trim(($p['method'] ?? '').' '.($p['host'] ?? '')),
            'cache' => trim(($p['action'] ?? 'cache').' '.($p['key'] ?? '')),
            'job' => trim(($p['status'] ?? '').' '.($p['class'] ?? '')),
            'exception' => (string) ($p['class'] ?? 'exception'),
            'log' => '['.($p['level'] ?? 'info').'] '.($p['message'] ?? ''),
            default => (string) ($span['type'] ?? ''),
        };
    }

    /**
     * Turn ordered spans into positioned waterfall rows. Each returned row is
     * the original span plus `_left`/`_width` (percent) and `_color`/`_label`.
     *
     * @param  Collection<int, array<string, mixed>>  $spans
     * @return list<array<string, mixed>>
     */
    public static function rows(Collection $spans): array
    {
        if ($spans->isEmpty()) {
            return [];
        }

        $timed = $spans->map(function (array $s): array {
            $start = (float) Carbon::parse($s['occurred_at'])->format('U.u');
            $s['_start'] = $start;
            $s['_end'] = $start + (($s['duration_us'] ?? 0) / 1_000_000);

            return $s;
        });

        $min = $timed->min('_start');
        $max = $timed->max('_end');
        $window = max($max - $min, 0.000001);

        return $timed->map(function (array $s) use ($min, $window): array {
            $s['_left'] = (($s['_start'] - $min) / $window) * 100;
            $s['_width'] = max(0.6, (($s['_end'] - $s['_start']) / $window) * 100);
            $s['_color'] = self::color((string) ($s['type'] ?? ''));
            $s['_label'] = self::label($s);

            return $s;
        })->values()->all();
    }
}
