<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Supported locales.
     *
     * @var array<string>
     */
    public const SUPPORTED_LOCALES = ['bn', 'en'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if user passed an explicit ?lang= param
        $queryLang = $request->query('lang');
        if (is_string($queryLang) && in_array($queryLang, self::SUPPORTED_LOCALES, true)) {
            session(['locale' => $queryLang]);
            cookie()->queue(cookie()->forever('app_locale', $queryLang));
            $locale = $queryLang;
        } else {
            // 2. Check session or persistent cookie, fallback to default 'bn'
            $locale = session('locale', $request->cookie('app_locale', 'bn'));
            if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
                $locale = 'bn';
            }
        }

        App::setLocale((string) $locale);
        View::share('currentLocale', (string) $locale);

        return $next($request);
    }
}
