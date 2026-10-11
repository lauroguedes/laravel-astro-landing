<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use LauroGuedes\AstroLanding\Exceptions\ThemeNotInstallable;

/**
 * Reads the themes catalog at astro.build/themes.
 *
 * There is no API, so this reads the same server-rendered HTML a browser gets.
 * Two things are taken from it and nothing else: the cards a search returns,
 * and the primary button on a theme's own page, which for a free theme links
 * its repository. Both are looked for narrowly, and anything that does not
 * match is reported as "not installable from here" rather than guessed at: a
 * wrong guess would download somebody else's code into the project.
 */
final readonly class ThemeCatalog
{
    public const string BASE_URL = 'https://astro.build';

    public function __construct(private Http $http) {}

    /**
     * @return list<Theme>
     */
    public function search(string $query, ?string $category = 'landing-page'): array
    {
        $url = self::BASE_URL.'/themes/1/?search='.rawurlencode(trim($query));

        if ($category !== null && $category !== '') {
            $url .= '&categories%5B%5D='.rawurlencode($category);
        }

        $html = $this->fetch($url);

        preg_match_all('/<article[^>]*aria-labelledby="([a-z0-9-]+)"[^>]*>(.*?)<\/article>/s', $html, $cards, PREG_SET_ORDER);

        $themes = [];

        foreach ($cards as [, $slug, $body]) {
            if (isset($themes[$slug])) {
                continue;
            }

            $title = preg_match('/<h3[^>]*>([^<]+)<\/h3>/', $body, $heading) === 1
                ? html_entity_decode(trim($heading[1]), ENT_QUOTES | ENT_HTML5)
                : $slug;

            $themes[$slug] = new Theme($slug, $title, free: preg_match('/>\s*Free\s*</', $body) === 1);
        }

        return array_values($themes);
    }

    /**
     * The create-astro template for a theme, given its slug or its URL.
     *
     * @throws ThemeNotInstallable
     */
    public function template(string $theme): string
    {
        $slug = self::slug($theme);

        if ($slug === null) {
            throw ThemeNotInstallable::unrecognised($theme);
        }

        $url = self::BASE_URL.'/themes/details/'.$slug.'/';
        $html = $this->fetch($url);

        if (preg_match('/<a[^>]*href="([^"]+)"[^>]*>\s*<span>\s*(Get started|Buy now)\s*<\/span>/i', $html, $link) !== 1) {
            throw ThemeNotInstallable::notFound($slug);
        }

        /*
         * A free theme's button says "Get started" and links its repository; a
         * paid one says "Buy now" and links a checkout. Both are checked rather
         * than trusting the label alone, because a free theme hosted somewhere
         * other than GitHub, GitLab or Bitbucket is just as uninstallable.
         */
        $repository = strcasecmp($link[2], 'Buy now') === 0
            ? null
            : TemplateResolver::fromRepositoryUrl(html_entity_decode($link[1]));

        if ($repository === null) {
            throw ThemeNotInstallable::notOpenSource($slug, html_entity_decode($link[1]));
        }

        return $repository;
    }

    /**
     * Accepts a bare slug, "theme:slug", or a catalog URL.
     */
    public static function slug(string $theme): ?string
    {
        $theme = trim($theme);

        if (preg_match('#^https?://(?:www\.)?astro\.build/themes/details/([a-z0-9-]+)/?#i', $theme, $match) === 1) {
            return strtolower($match[1]);
        }

        $theme = preg_replace('/^theme:/', '', $theme) ?? $theme;

        return preg_match('/^[a-z0-9][a-z0-9-]*$/', $theme) === 1 ? $theme : null;
    }

    /**
     * @throws ThemeNotInstallable
     */
    private function fetch(string $url): string
    {
        try {
            $response = $this->http
                ->withHeaders(['Accept' => 'text/html'])
                ->timeout(15)
                ->get($url);
        } catch (ConnectionException $exception) {
            throw ThemeNotInstallable::unreachable($url, $exception);
        }

        if ($response->status() === 404) {
            throw ThemeNotInstallable::notFound($url);
        }

        if (! $response->successful()) {
            throw ThemeNotInstallable::unreachable($url);
        }

        return $response->body();
    }
}
