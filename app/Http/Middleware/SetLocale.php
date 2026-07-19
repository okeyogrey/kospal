<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Resolve locale precedence: session → user preferred → business default → app.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locales = array_keys(config('kospal.locales', ['en' => 'English']));
        $locale = $this->resolveLocale($request, $locales);

        app()->setLocale($locale);

        if (! $request->session()->has('locale')) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }

    /**
     * @param  list<string>  $locales
     */
    protected function resolveLocale(Request $request, array $locales): string
    {
        $user = $request->user();

        $candidates = [
            $request->session()->get('locale'),
            $user?->preferred_locale,
            $user?->currentBusiness?->default_locale,
            config('app.locale'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, $locales, true)) {
                return $candidate;
            }
        }

        return 'en';
    }
}
