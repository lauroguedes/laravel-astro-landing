<?php

declare(strict_types=1);

namespace LauroGuedes\AstroOnLaravel\Support;

use Illuminate\Contracts\Config\Repository;

/**
 * Where the landing is and what installs it — the one place that reads the
 * landing config, so every command agrees on both.
 */
final readonly class Landing
{
    public function __construct(
        private Repository $config,
        private string $basePath,
    ) {}

    public function path(): string
    {
        $path = $this->config->get('landing.path');

        return self::normalise(is_string($path) && $path !== '' ? $path : 'landing');
    }

    public function absolutePath(?string $path = null): string
    {
        return $this->basePath.'/'.($path ?? $this->path());
    }

    public function basePath(string $path = ''): string
    {
        return $path === '' ? $this->basePath : $this->basePath.'/'.ltrim($path, '/');
    }

    public function exists(): bool
    {
        return is_file($this->absolutePath().'/package.json');
    }

    public function dependenciesInstalled(): bool
    {
        return is_dir($this->absolutePath().'/node_modules');
    }

    public function devServerEnabled(): bool
    {
        return (bool) $this->config->get('landing.dev.enabled', true);
    }

    public function devServerName(): string
    {
        $name = $this->config->get('landing.dev.name');

        return is_string($name) && $name !== '' ? $name : 'landing';
    }

    /**
     * Configured, else the landing's lockfile, else the application's, else npm.
     */
    public function packageManager(?string $path = null): PackageManager
    {
        $configured = $this->config->get('landing.package_manager');

        if (is_string($configured) && ($manager = PackageManager::tryFrom($configured)) instanceof PackageManager) {
            return $manager;
        }

        return PackageManager::detect($this->absolutePath($path), $this->basePath) ?? PackageManager::Npm;
    }

    /**
     * Relative, forward-slashed, without leading ./ or trailing slash.
     */
    public static function normalise(string $path): string
    {
        $path = trim(str_replace('\\', '/', trim($path)), '/');

        return (string) preg_replace('#^(\./)+#', '', $path);
    }

    /**
     * A path inside the application, below its root, and not one of the
     * directories Laravel itself owns.
     */
    public static function isValidPath(string $path): bool
    {
        if (preg_match('#^\s*(/|\\\\|[A-Za-z]:)#', $path) === 1) {
            return false;
        }

        $path = self::normalise($path);

        if ($path === '' || preg_match('#(^|/)\.\.(/|$)#', $path) === 1) {
            return false;
        }

        if (preg_match('#^[A-Za-z0-9_.\-/]+$#', $path) !== 1) {
            return false;
        }

        $root = explode('/', $path)[0];

        return ! in_array($root, ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'storage', 'tests', 'vendor', 'node_modules', 'lang'], true);
    }
}
