<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public const SUPPORTED = ['en', 'pt', 'es'];

    public function handle(Request $request, Closure $next)
    {
        $locale = (string) $request->session()->get('locale', 'en');
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'en';
        }
        app()->setLocale($locale);

        return $next($request);
    }
}
