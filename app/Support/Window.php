<?php

namespace App\Support;

class Window
{
    /** Preset windows in minutes, matching App\Support\Ranges::all(). */
    private const PRESETS = [
        '15m' => 15,
        '1h' => 60,
        '6h' => 360,
        '24h' => 1440,
        '7d' => 10080,
        '30d' => 43200,
    ];

    /**
     * Map an arbitrary minute span to the closest preset range. The read layer
     * aggregates by preset, so a custom from→to window resolves to the nearest
     * preset rather than an exact interval.
     */
    public static function nearestPreset(int $minutes): string
    {
        $best = '1h';
        $bestDiff = PHP_INT_MAX;
        foreach (self::PRESETS as $label => $m) {
            $diff = abs($m - $minutes);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $label;
            }
        }

        return $best;
    }
}
