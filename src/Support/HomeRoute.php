<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding\Support;

/**
 * Points the application's home page at the landing, in routes/web.php.
 *
 * The rewritten route is plain Laravel reading config('landing.url'), with no
 * reference to this package: the package is a development dependency, and a
 * route that needed it would break the first production deploy. Without a
 * LANDING_URL it returns exactly what it returned before, so the change is
 * safe in every environment that has not set one.
 *
 * Only the shapes Laravel's skeleton and starter kits ship are recognised: a
 * closure returning one expression, an arrow function, or Route::view(). A
 * home route that does more than that is the application's own, and is left
 * for a person to change.
 */
final class HomeRoute
{
    public static function isRedirecting(string $contents): bool
    {
        return str_contains($contents, "config('landing.url')");
    }

    /**
     * The routes file with its home route rewritten, or null when there is no
     * home route this can rewrite safely.
     */
    public static function rewrite(string $contents): ?string
    {
        $closures = [
            // Route::get('/', function () { return view('welcome'); })->name('home');
            '/Route::get\(\s*([\'"])\/\1\s*,\s*function\s*\(\)\s*\{\s*return\s+(?<expression>[^;]+?);\s*\}\s*\)(?<name>(?:->name\([^)]*\))?)\s*;/s',
            // Route::get('/', fn () => view('welcome'))->name('home');
            '/Route::get\(\s*([\'"])\/\1\s*,\s*(?:static\s+)?fn\s*\(\)\s*=>\s*(?<expression>[^;]+?)\s*\)(?<name>(?:->name\([^)]*\))?)\s*;/s',
        ];

        foreach ($closures as $pattern) {
            if (preg_match($pattern, $contents, $match, PREG_OFFSET_CAPTURE) === 1) {
                return self::replace($contents, $match[0], trim($match['expression'][0]), $match['name'][0]);
            }
        }

        // Route::view('/', 'welcome')->name('home');
        $view = '/Route::view\(\s*([\'"])\/\1\s*,\s*(?<view>[\'"][\w.\-\/]+[\'"])\s*\)(?<name>(?:->name\([^)]*\))?)\s*;/';

        if (preg_match($view, $contents, $match, PREG_OFFSET_CAPTURE) === 1) {
            return self::replace($contents, $match[0], 'view('.$match['view'][0].')', $match['name'][0]);
        }

        return null;
    }

    public static function route(string $fallback, string $name = ''): string
    {
        return <<<PHP
            Route::get('/', function () {
                // The public site is the Astro landing (config/landing.php). Without a
                // LANDING_URL, this keeps serving the page it served before.
                return config('landing.url')
                    ? redirect()->away(config('landing.url'))
                    : {$fallback};
            }){$name};
            PHP;
    }

    /**
     * @param  array{string, int}  $route  the matched route and its offset
     */
    private static function replace(string $contents, array $route, string $fallback, string $name): string
    {
        return substr_replace($contents, self::route($fallback, $name), $route[1], strlen($route[0]));
    }
}
