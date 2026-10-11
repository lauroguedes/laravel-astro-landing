<?php

declare(strict_types=1);

use LauroGuedes\AstroLanding\Support\Landing;
use LauroGuedes\AstroLanding\Support\PackageManager;

it('detects the package manager from the first directory with a lockfile', function (): void {
    $this->file('landing/yarn.lock');
    $this->file('package-lock.json');

    expect(PackageManager::detect($this->root.'/landing', $this->root))->toBe(PackageManager::Yarn)
        ->and(PackageManager::detect($this->root))->toBe(PackageManager::Npm)
        ->and(PackageManager::detect($this->root.'/nowhere'))->toBeNull();
});

it('prefers the newer lockfile when a project carries two', function (): void {
    $this->file('bun.lock');
    $this->file('package-lock.json');

    expect(PackageManager::detect($this->root))->toBe(PackageManager::Bun);
});

it('lets the config name the package manager outright', function (): void {
    $this->file('landing/package-lock.json');
    config(['landing.package_manager' => 'pnpm']);

    expect(app(Landing::class)->packageManager())->toBe(PackageManager::Pnpm);
});

it('falls back to npm and to landing/', function (): void {
    config(['landing.path' => '', 'landing.package_manager' => 'pip']);

    $landing = app(Landing::class);

    expect($landing->path())->toBe('landing')
        ->and($landing->packageManager())->toBe(PackageManager::Npm);
});

it('normalises a path', function (string $path, string $expected): void {
    expect(Landing::normalise($path))->toBe($expected);
})->with([
    ['landing', 'landing'],
    ['./landing/', 'landing'],
    ['sites\\marketing', 'sites/marketing'],
]);

it('accepts only directories inside the application that Laravel does not use', function (string $path, bool $valid): void {
    expect(Landing::isValidPath($path))->toBe($valid);
})->with([
    ['landing', true],
    ['sites/marketing', true],
    ['./www', true],
    ['', false],
    ['/var/www', false],
    ['C:\\sites', false],
    ['../landing', false],
    ['sites/../../x', false],
    ['public', false],
    ['resources/landing', false],
    ['vendor/x', false],
    ['land ing', false],
]);

it('reads yarn berry\'s install from the lockfile flag it supports', function (): void {
    expect(PackageManager::Yarn->cleanInstall())->toBe('yarn install --frozen-lockfile')
        ->and(PackageManager::Yarn->cleanInstall(berry: true))->toBe('yarn install --immutable');
});
