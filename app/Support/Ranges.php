<?php

namespace App\Support;

class Ranges
{
    /** @return list<string> */
    public static function all(): array
    {
        return ['15m', '1h', '6h', '24h', '7d', '30d'];
    }

    public static function sanitize(?string $range): string
    {
        return in_array($range, self::all(), true) ? $range : '1h';
    }
}
