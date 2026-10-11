<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding\Support;

use LauroGuedes\AstroLanding\Exceptions\InvalidTemplate;
use LauroGuedes\AstroLanding\Exceptions\ThemeNotInstallable;

/**
 * Turns whatever somebody typed into the value create-astro's --template takes.
 *
 * create-astro hands anything containing a slash to giget, which understands
 * owner/repo, owner/repo/sub/dir, a #ref suffix and the gitlab:, bitbucket: and
 * sourcehut: prefixes. Anything without a slash is one of Astro's own examples.
 * This class adds the forms people actually paste (a repository URL, a tree
 * URL, a theme from the catalog) and the word "empty".
 */
final readonly class TemplateResolver
{
    /**
     * The official examples worth offering by name. Any other directory under
     * withastro/astro/examples still works when typed.
     *
     * @var array<string, string>
     */
    public const array EXAMPLES = [
        'basics' => 'Basics: a helpful starter page',
        'blog' => 'Blog: posts, RSS and a sitemap',
        'portfolio' => 'Portfolio: a personal site',
        'starlight' => 'Starlight: a documentation site',
        'with-tailwindcss' => 'Tailwind CSS: an empty page with Tailwind wired in',
    ];

    public const string EMPTY = 'minimal';

    public function __construct(private ThemeCatalog $catalog) {}

    /**
     * @throws InvalidTemplate
     * @throws ThemeNotInstallable
     */
    public function resolve(string $input): string
    {
        $input = trim($input);

        if ($input === '' || strtolower($input) === 'empty') {
            return self::EMPTY;
        }

        if (str_starts_with($input, 'theme:') || preg_match('#^https?://(www\.)?astro\.build/#i', $input) === 1) {
            return $this->catalog->template($input);
        }

        if (preg_match('#^https?://#i', $input) === 1) {
            return self::fromRepositoryUrl($input) ?? throw InvalidTemplate::unrecognised($input);
        }

        if (preg_match('~^[A-Za-z0-9][A-Za-z0-9_.\-/:#]*$~', $input) !== 1 || str_contains($input, '..')) {
            throw InvalidTemplate::unrecognised($input);
        }

        return $input;
    }

    /**
     * A GitHub, GitLab or Bitbucket URL as a giget source, or null.
     *
     * A tree URL keeps its branch and subdirectory: the first segment after
     * /tree/ is taken as the ref, which is wrong only for a branch name with a
     * slash in it, and those can still be typed as owner/repo/dir#ref.
     */
    public static function fromRepositoryUrl(string $url): ?string
    {
        $pattern = '#^https?://(?:www\.)?(github\.com|gitlab\.com|bitbucket\.org)/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?(?:/(?:-/)?(?:tree|src)/([A-Za-z0-9_.-]+)(/[A-Za-z0-9_.\-/]*)?)?/?(?:[?\#].*)?$#';

        if (preg_match($pattern, trim($url), $match) !== 1) {
            return null;
        }

        $prefix = match ($match[1]) {
            'gitlab.com' => 'gitlab:',
            'bitbucket.org' => 'bitbucket:',
            default => '',
        };

        $source = $prefix.$match[2].'/'.$match[3];

        $subdirectory = trim($match[5] ?? '', '/');

        if ($subdirectory !== '') {
            $source .= '/'.$subdirectory;
        }

        if (($match[4] ?? '') !== '') {
            $source .= '#'.$match[4];
        }

        return $source;
    }
}
