<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding\Support;

/**
 * The four package managers Astro supports, and every command this package
 * builds for one of them.
 *
 * Commands are returned as argument lists rather than strings, so a template
 * name or a directory never passes through a shell on the way to the process.
 */
enum PackageManager: string
{
    case Npm = 'npm';
    case Pnpm = 'pnpm';
    case Yarn = 'yarn';
    case Bun = 'bun';

    /**
     * Lockfiles in the order they are checked. bun and pnpm come first because
     * a project that switched to them often still carries a stale
     * package-lock.json, and the newer file is the one that is maintained.
     *
     * @var array<string, self>
     */
    private const array LOCKFILES = [
        'bun.lock' => self::Bun,
        'bun.lockb' => self::Bun,
        'pnpm-lock.yaml' => self::Pnpm,
        'yarn.lock' => self::Yarn,
        'package-lock.json' => self::Npm,
    ];

    /**
     * The first directory with a lockfile wins, so the landing's own choice
     * outranks the application's.
     */
    public static function detect(string ...$directories): ?self
    {
        foreach ($directories as $directory) {
            foreach (self::LOCKFILES as $lockfile => $manager) {
                if (is_file(rtrim($directory, '/').'/'.$lockfile)) {
                    return $manager;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $manager): string => $manager->value, self::cases());
    }

    /**
     * Runs create-astro through the manager's own executor.
     *
     * `yarn create astro` rather than `yarn dlx`, because Yarn 1 has no dlx and
     * `create` works in every version.
     *
     * @param  list<string>  $arguments
     * @return list<string>
     */
    public function create(array $arguments): array
    {
        return match ($this) {
            self::Npm => ['npx', '--yes', 'create-astro@latest', ...$arguments],
            self::Pnpm => ['pnpm', 'dlx', 'create-astro@latest', ...$arguments],
            self::Yarn => ['yarn', 'create', 'astro', ...$arguments],
            self::Bun => ['bunx', 'create-astro@latest', ...$arguments],
        };
    }

    /**
     * @return list<string>
     */
    public function install(): array
    {
        return [$this->value, 'install'];
    }

    /**
     * The install a CI runner uses: from the lockfile, failing if it is stale.
     */
    public function cleanInstall(bool $berry = false): string
    {
        return match ($this) {
            self::Npm => 'npm ci',
            self::Pnpm => 'pnpm install --frozen-lockfile',
            self::Yarn => $berry ? 'yarn install --immutable' : 'yarn install --frozen-lockfile',
            self::Bun => 'bun install --frozen-lockfile',
        };
    }

    /**
     * @return list<string>
     */
    public function run(string $script): array
    {
        return match ($this) {
            self::Npm => ['npm', 'run', $script],
            self::Yarn => ['yarn', $script],
            default => [$this->value, 'run', $script],
        };
    }

    /**
     * `astro add` with --yes, so it does not stop to ask whether it may edit
     * astro.config.mjs.
     *
     * @return list<string>
     */
    public function astroAdd(string $integration): array
    {
        return match ($this) {
            self::Npm => ['npx', 'astro', 'add', $integration, '--yes'],
            self::Pnpm => ['pnpm', 'astro', 'add', $integration, '--yes'],
            self::Yarn => ['yarn', 'astro', 'add', $integration, '--yes'],
            self::Bun => ['bunx', 'astro', 'add', $integration, '--yes'],
        };
    }

    /**
     * The GitHub Actions steps that put this manager on the runner and install
     * the landing's dependencies, indented to sit under `steps:`.
     */
    public function setupSteps(string $path, bool $berry = false): string
    {
        $lockfile = match ($this) {
            self::Npm => 'package-lock.json',
            self::Pnpm => 'pnpm-lock.yaml',
            self::Yarn => 'yarn.lock',
            self::Bun => 'bun.lock',
        };

        $steps = match ($this) {
            self::Bun => <<<'YAML'
                      - name: Setup Bun
                        uses: oven-sh/setup-bun@v2

                YAML,
            self::Pnpm => <<<YAML
                      - name: Setup pnpm
                        uses: pnpm/action-setup@v4
                        with:
                          version: 10

                      - name: Setup Node
                        uses: actions/setup-node@v6
                        with:
                          node-version: lts/*
                          cache: pnpm
                          cache-dependency-path: {$path}/{$lockfile}

                YAML,
            self::Yarn => <<<'YAML'
                      - name: Setup Node
                        uses: actions/setup-node@v6
                        with:
                          node-version: lts/*

                      - name: Enable Corepack
                        run: corepack enable

                YAML,
            self::Npm => <<<YAML
                      - name: Setup Node
                        uses: actions/setup-node@v6
                        with:
                          node-version: lts/*
                          cache: npm
                          cache-dependency-path: {$path}/{$lockfile}

                YAML,
        };

        return rtrim($steps)."\n\n".<<<YAML
                  - name: Install dependencies
                    run: {$this->cleanInstall($berry)}
            YAML;
    }
}
