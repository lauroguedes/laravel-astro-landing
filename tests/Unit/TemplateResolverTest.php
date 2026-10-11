<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LauroGuedes\AstroLanding\Exceptions\InvalidTemplate;
use LauroGuedes\AstroLanding\Exceptions\ThemeNotInstallable;
use LauroGuedes\AstroLanding\Support\TemplateResolver;
use LauroGuedes\AstroLanding\Support\Theme;
use LauroGuedes\AstroLanding\Support\ThemeCatalog;

beforeEach(function (): void {
    Http::preventStrayRequests();

    $this->resolver = app(TemplateResolver::class);
});

it('resolves what people type to what create-astro takes', function (string $input, string $expected): void {
    expect($this->resolver->resolve($input))->toBe($expected);
})->with([
    'nothing' => ['', 'minimal'],
    'empty' => ['empty', 'minimal'],
    'Empty' => [' Empty ', 'minimal'],
    'an example' => ['portfolio', 'portfolio'],
    'starlight' => ['starlight', 'starlight'],
    'owner/repo' => ['onwidget/astrowind', 'onwidget/astrowind'],
    'a subdirectory and ref' => ['owner/repo/sites/landing#v2', 'owner/repo/sites/landing#v2'],
    'giget prefixes' => ['gitlab:owner/repo', 'gitlab:owner/repo'],
    'a GitHub URL' => ['https://github.com/onwidget/astrowind', 'onwidget/astrowind'],
    'with .git' => ['https://github.com/onwidget/astrowind.git', 'onwidget/astrowind'],
    'with a trailing slash' => ['https://github.com/onwidget/astrowind/', 'onwidget/astrowind'],
    'a tree URL' => ['https://github.com/owner/repo/tree/dev', 'owner/repo#dev'],
    'a tree URL with a directory' => ['https://github.com/owner/repo/tree/main/apps/landing', 'owner/repo/apps/landing#main'],
    'a GitLab URL' => ['https://gitlab.com/owner/repo', 'gitlab:owner/repo'],
    'a GitLab tree URL' => ['https://gitlab.com/owner/repo/-/tree/main/site', 'gitlab:owner/repo/site#main'],
    'a Bitbucket URL' => ['https://bitbucket.org/owner/repo/src/main/', 'bitbucket:owner/repo#main'],
]);

it('rejects what is not a template', function (string $input): void {
    $this->resolver->resolve($input);
})->with([
    'shell' => ['blog; rm -rf ~'],
    'a flag' => ['--dry-run'],
    'a parent directory' => ['owner/../../etc'],
    'a host it does not know' => ['https://example.com/owner/repo'],
    'a space' => ['owner/my repo'],
])->throws(InvalidTemplate::class);

it('resolves a catalog theme through its page', function (string $input): void {
    Http::fake([
        'astro.build/themes/details/landpack/' => Http::response(file_get_contents(__DIR__.'/../Fixtures/theme-free.html')),
    ]);

    expect($this->resolver->resolve($input))->toBe('someone/landpack');
})->with([
    'theme:slug' => ['theme:landpack'],
    'a catalog URL' => ['https://astro.build/themes/details/landpack/'],
    'without the slash' => ['https://astro.build/themes/details/landpack'],
]);

it('keeps the branch and directory of a theme that lives in a monorepo', function (): void {
    Http::fake(['astro.build/*' => Http::response(file_get_contents(__DIR__.'/../Fixtures/theme-subdirectory.html'))]);

    expect(app(ThemeCatalog::class)->template('kairo-saas'))->toBe('someone/themes/packages/kairo#v2');
});

it('explains why a theme cannot be installed', function (string $fixture, int $status, string $message): void {
    Http::fake(['astro.build/*' => Http::response($fixture === '' ? '' : file_get_contents(__DIR__.'/../Fixtures/'.$fixture), $status)]);

    expect(fn (): string => app(ThemeCatalog::class)->template('foxi-pro'))
        ->toThrow(ThemeNotInstallable::class, $message);
})->with([
    'a paid theme' => ['theme-paid.html', 200, 'probably a paid theme. Get it from https://vendor.example.com/buy/foxi-pro'],
    'a free theme hosted elsewhere' => ['theme-elsewhere.html', 200, 'Get it from https://codeberg.org/someone/theme'],
    'an unknown theme' => ['', 404, 'No theme'],
    'a page without the link' => ['catalog.html', 200, 'No theme "foxi-pro"'],
    'the catalog being down' => ['', 503, 'Could not read'],
]);

it('refuses a theme name that could not be a slug', function (): void {
    app(ThemeCatalog::class)->template('../../admin');
})->throws(ThemeNotInstallable::class, 'is not a theme slug');

it('lists the catalog\'s cards, paid ones marked', function (): void {
    Http::fake(['astro.build/themes/1/*' => Http::response(file_get_contents(__DIR__.'/../Fixtures/catalog.html'))]);

    $themes = app(ThemeCatalog::class)->search('saas & more');

    expect(array_map(static fn (Theme $theme): array => [$theme->slug, $theme->label()], $themes))->toBe([
        ['landpack', 'Landpack'],
        ['kairo-saas', 'Kairo & Co - SaaS'],
        ['foxi-pro', 'Foxi Pro (paid)'],
    ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://astro.build/themes/1/?search=saas%20%26%20more&categories%5B%5D=landing-page');
});
