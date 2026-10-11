<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding\Support;

/**
 * One card from the Astro themes catalog.
 */
final readonly class Theme
{
    public function __construct(
        public string $slug,
        public string $title,
        public bool $free,
    ) {}

    public function label(): string
    {
        return $this->free ? $this->title : $this->title.' (paid)';
    }
}
