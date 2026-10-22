<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

it('ships Boost skills whose frontmatter is valid YAML', function (string $path): void {
    $contents = (string) file_get_contents($path);

    expect(preg_match('/\A---\R(.*?)\R---\R/s', $contents, $matches))->toBe(1);

    $frontmatter = Yaml::parse($matches[1]);

    expect($frontmatter)->toBeArray()
        ->and($frontmatter['name'] ?? null)->toBe(basename(dirname($path)))
        ->and($frontmatter['description'] ?? null)->toBeString()->not->toBeEmpty();
})->with(fn (): array => glob(dirname(__DIR__, 2).'/resources/boost/skills/*/SKILL.md') ?: []);
