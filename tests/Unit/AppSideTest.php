<?php

declare(strict_types=1);

use LauroGuedes\AstroLanding\Support\AstroConfig;
use LauroGuedes\AstroLanding\Support\HomeRoute;

it('rewrites the home routes Laravel ships', function (string $route, string $fallback, string $name): void {
    $rewritten = HomeRoute::rewrite("<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n{$route}\n\nRoute::get('/about', fn () => 'about');\n");

    expect($rewritten)->toContain(HomeRoute::route($fallback, $name))
        ->and($rewritten)->toContain("Route::get('/about', fn () => 'about');");
})->with([
    'skeleton' => ["Route::get('/', function () {\n    return view('welcome');\n});", "view('welcome')", ''],
    'named' => ["Route::get('/', function () {\n    return view('welcome');\n})->name('home');", "view('welcome')", "->name('home')"],
    'inertia' => ["Route::get('/', function () {\n    return Inertia::render('welcome', [\n        'canRegister' => Features::enabled(Features::registration()),\n    ]);\n})->name('home');", "Inertia::render('welcome', [\n        'canRegister' => Features::enabled(Features::registration()),\n    ])", "->name('home')"],
    'arrow function' => ["Route::get('/', fn () => view('welcome'))->name('home');", "view('welcome')", "->name('home')"],
    'Route::view' => ["Route::view('/', 'welcome')->name('home');", "view('welcome')", "->name('home')"],
]);

it('produces a routes file PHP can parse', function (): void {
    $routes = (string) HomeRoute::rewrite("<?php\n\nRoute::get('/', function () {\n    return view('welcome');\n})->name('home');\n");

    expect(token_get_all($routes, TOKEN_PARSE))->not->toBeEmpty();
});

it('leaves a home route it does not recognise alone', function (string $routes): void {
    expect(HomeRoute::rewrite($routes))->toBeNull();
})->with([
    'a controller' => ["<?php\nRoute::get('/', [HomeController::class, 'show']);\n"],
    'no home route' => ["<?php\nRoute::get('/about', fn () => 'about');\n"],
    'a closure that does more' => ["<?php\nRoute::get('/', function () {\n    \$posts = Post::all();\n    return view('welcome', compact('posts'));\n});\n"],
]);

it('sets base in the defineConfig object, and only there', function (): void {
    expect(AstroConfig::withBase("export default defineConfig({\n  integrations: [],\n});\n", '/lp'))
        ->toBe("export default defineConfig({\n  base: '/lp',\n  integrations: [],\n});\n")
        ->and(AstroConfig::withBase("// @ts-check\nexport default defineConfig({});\n", '/lp'))->toBe("// @ts-check\nexport default defineConfig({\n  base: '/lp',\n});\n")
        ->and(AstroConfig::withBase("export default defineConfig({ base: '/x' });", '/lp'))->toBeNull()
        ->and(AstroConfig::withBase("export default {\n  integrations: [],\n};\n", '/lp'))->toBeNull();
});

it('finds the config whatever its extension', function (): void {
    $this->file('landing/astro.config.ts');

    expect(AstroConfig::find($this->root.'/landing'))->toBe('astro.config.ts')
        ->and(AstroConfig::find($this->root.'/nowhere'))->toBeNull();
});

it('normalises a base path', function (string $given, ?string $expected): void {
    expect(AstroConfig::normaliseBase($given))->toBe($expected);
})->with([
    ['lp', '/lp'],
    ['/lp/', '/lp'],
    [' /marketing/site ', '/marketing/site'],
    ['/', null],
    ['', null],
    ['/../etc', null],
    ['/a/./b', null],
    ['/a b', null],
    ['/a?b', null],
]);
