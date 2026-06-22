<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Throwable;
use VictorStochero\Warden\Dashboard\DashboardRepository;

/**
 * Adds an exact from→to time window to a range-driven section component. When
 * both bounds parse, reads are scoped to that exact interval via
 * DashboardRepository::withWindow(); otherwise the preset `range` applies.
 */
trait ResolvesWindow
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    protected function applyWindow(DashboardRepository $dashboard): void
    {
        $start = $this->parseDateTime($this->from);
        $end = $this->parseDateTime($this->to);

        if ($start !== null && $end !== null) {
            if ($start->greaterThan($end)) {
                [$start, $end] = [$end, $start];
            }
            $dashboard->withWindow($start, $end);
        }
    }

    public function hasCustomWindow(): bool
    {
        return $this->parseDateTime($this->from) !== null && $this->parseDateTime($this->to) !== null;
    }

    private function parseDateTime(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
