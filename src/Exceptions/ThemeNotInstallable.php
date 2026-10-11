<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding\Exceptions;

use Throwable;

final class ThemeNotInstallable extends AstroLandingException
{
    public static function unrecognised(string $theme): self
    {
        return new self(sprintf(
            '"%s" is not a theme slug or an astro.build/themes/details/ URL.',
            $theme,
        ));
    }

    public static function notFound(string $theme): self
    {
        return new self(sprintf(
            'No theme "%s" was found in the Astro themes catalog.',
            $theme,
        ));
    }

    public static function notOpenSource(string $theme, string $link): self
    {
        return new self(sprintf(
            '"%s" is not published as a public repository, so it cannot be installed from here — it is probably a paid theme. Get it from %s, then install it with --template=<owner>/<repository>.',
            $theme,
            $link,
        ));
    }

    public static function unreachable(string $url, ?Throwable $previous = null): self
    {
        return new self(sprintf(
            'Could not read %s. Check the connection, or pass the theme\'s repository with --template=<owner>/<repository>.',
            $url,
        ), previous: $previous);
    }
}
