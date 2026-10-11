<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding\Exceptions;

final class InvalidTemplate extends AstroLandingException
{
    public static function unrecognised(string $template): self
    {
        return new self(sprintf(
            '"%s" is not a template create-astro understands. Use an example name (basics, blog, portfolio), owner/repository, a GitHub, GitLab or Bitbucket URL, or theme:<slug>.',
            $template,
        ));
    }
}
