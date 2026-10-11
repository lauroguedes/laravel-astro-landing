<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Process;
use LauroGuedes\AstroLanding\Exceptions\AstroLandingException;
use LauroGuedes\AstroLanding\Support\DeployTarget;
use LauroGuedes\AstroLanding\Support\Landing;
use LauroGuedes\AstroLanding\Support\PackageManager;
use LauroGuedes\AstroLanding\Support\TemplateResolver;
use LauroGuedes\AstroLanding\Support\Theme;
use LauroGuedes\AstroLanding\Support\ThemeCatalog;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

/**
 * Puts an Astro landing next to the Laravel application, in one command.
 *
 * The landing is a separate project in a subdirectory, with its own package.json,
 * its own lockfile and its own build, so it can be deployed somewhere the
 * application is not, on a schedule the application does not set. Everything
 * this command does beyond running create-astro is about keeping the two from
 * stepping on each other: the landing's build output stays out of git, its
 * markup stays out of the application's Tailwind build, and its deploy only
 * fires when the landing changed.
 *
 * It asks what cannot be inferred (where the landing lives, what it starts
 * from, which package manager, where it deploys), and every question has a flag,
 * so a script can answer all of them. A run with nobody at the keyboard takes
 * landing/, the empty template, the application's package manager, and no
 * deploy workflow.
 *
 * Nothing in the application is overwritten. The config and the workflow are
 * skipped when they exist unless --force is given; .gitignore and app.css are
 * only appended to, and only when the line is not already there.
 */
final class InstallCommand extends Command
{
    /**
     * Offered for the empty template only: every other starting point already
     * made these choices, and adding a second Tailwind to a theme that has one
     * is a broken build.
     *
     * @var array<string, string>
     */
    private const array INTEGRATIONS = [
        'tailwind' => 'Tailwind CSS',
        'sitemap' => 'Sitemap',
        'mdx' => 'MDX',
        'vue' => 'Vue',
        'react' => 'React',
        'svelte' => 'Svelte',
    ];

    protected $signature = 'landing:install
        {--path= : Directory for the landing, relative to the application root (default: landing)}
        {--template= : empty, an Astro example, owner/repo, a repository URL, or theme:<slug> from astro.build/themes}
        {--pm= : npm, pnpm, yarn or bun (default: the application\'s)}
        {--add=* : An Astro integration to add, e.g. --add=tailwind --add=sitemap}
        {--deploy= : cloudflare-pages, netlify, vercel, github-pages, ssh or none}
        {--branch=main : Branch whose pushes deploy the landing}
        {--skip-install : Scaffold only, without installing dependencies}
        {--force : Overwrite the published config and the deploy workflow}';

    protected $description = 'Create an Astro landing page inside this Laravel application';

    public function handle(Filesystem $files, Landing $landing, TemplateResolver $templates, ThemeCatalog $catalog): int
    {
        /*
         * Every question before anything is written or downloaded, so a wrong
         * answer, or a theme that turns out to be paid, costs nothing.
         */
        $path = $this->landingPath($files, $landing);

        if ($path === null) {
            return self::FAILURE;
        }

        try {
            $template = $this->template($templates, $catalog);
        } catch (AstroLandingException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $manager = $this->packageManager($landing);

        if (! $manager instanceof PackageManager) {
            return self::FAILURE;
        }

        $integrations = $this->integrations($template);

        if ($integrations === null) {
            return self::FAILURE;
        }

        $target = $this->deployTarget();

        if ($target === false) {
            return self::FAILURE;
        }

        $branch = $this->option('branch');

        if ($target instanceof DeployTarget && ! WorkflowCommand::isBranchName($branch)) {
            $this->components->error(sprintf('"%s" is not a branch name.', is_string($branch) ? $branch : ''));

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info(sprintf('Creating the landing in %s/ from "%s" with %s.', $path, $template, $manager->value));

        if (! $this->scaffold($landing, $manager, $path, $template)) {
            return self::FAILURE;
        }

        $this->dropForeignLockfiles($files, $landing->absolutePath($path), $manager);

        $skipInstall = $this->option('skip-install') === true;

        if (! $skipInstall && ! $this->installDependencies($landing, $manager, $path, $integrations)) {
            return self::FAILURE;
        }

        $configuredPath = $landing->path();

        /*
         * Without an install there is no lockfile in the landing yet, so every
         * later command would fall back to the application's package manager.
         * Pinning it in the config is what keeps them on the one chosen here.
         */
        $pin = $skipInstall && (PackageManager::detect($landing->absolutePath($path), $landing->basePath()) ?? PackageManager::Npm) !== $manager
            ? $manager
            : null;

        config([
            'landing.path' => $path,
            'landing.package_manager' => $manager->value,
        ]);

        $configured = $this->publishConfig($files, $landing, $path, $configuredPath, $pin);
        $this->ignoreBuildOutput($files, $landing, $path);
        $this->keepOutOfTailwind($files, $landing, $path);

        if ($target instanceof DeployTarget) {
            $this->call('landing:workflow', array_filter([
                'target' => $target->value,
                '--branch' => $branch,
                '--force' => $this->option('force') === true ? true : null,
            ], static fn (mixed $value): bool => $value !== null));
        }

        $this->newLine();
        $this->components->info('The landing is ready. What is left:');
        $this->components->bulletList($this->checklist($path, $manager, $target, $configured));
        $this->newLine();

        return self::SUCCESS;
    }

    private function landingPath(Filesystem $files, Landing $landing): ?string
    {
        $given = $this->option('path');

        if (is_string($given) && $given !== '') {
            $path = $given;
        } elseif ($this->input->isInteractive()) {
            $path = text(
                label: 'Where should the landing live?',
                default: $landing->path(),
                required: true,
                validate: fn (string $value): ?string => $this->pathProblem($files, $landing, $value),
                hint: 'A directory in the application root. It gets its own package.json and deploys on its own.',
            );
        } else {
            $path = $landing->path();
        }

        $problem = $this->pathProblem($files, $landing, $path);

        if ($problem !== null) {
            $this->components->error($problem);

            return null;
        }

        return Landing::normalise($path);
    }

    private function pathProblem(Filesystem $files, Landing $landing, string $path): ?string
    {
        if (! Landing::isValidPath($path)) {
            return sprintf('"%s" must be a directory inside the application, and not one Laravel already uses.', $path);
        }

        $absolute = $landing->absolutePath(Landing::normalise($path));

        if ($files->isDirectory($absolute) && ! $files->isEmptyDirectory($absolute)) {
            return sprintf('%s/ already exists and is not empty.', Landing::normalise($path));
        }

        return null;
    }

    /**
     * @throws AstroLandingException
     */
    private function template(TemplateResolver $templates, ThemeCatalog $catalog): string
    {
        $given = $this->option('template');

        if (is_string($given) && $given !== '') {
            return $this->resolve($templates, $given);
        }

        if (! $this->input->isInteractive()) {
            return TemplateResolver::EMPTY;
        }

        $source = select(
            label: 'What should the landing start from?',
            options: [
                'empty' => 'An empty project: Astro\'s minimal template',
                'example' => 'An official Astro example',
                'repository' => 'A template repository on GitHub, GitLab or Bitbucket',
                'theme' => 'A free theme from astro.build/themes',
            ],
            default: 'empty',
        );

        return match ($source) {
            'example' => $this->example(),
            'repository' => $this->resolve($templates, text(
                label: 'Which repository?',
                placeholder: 'owner/repo, owner/repo/sub/dir#branch, or https://github.com/owner/repo',
                required: true,
            )),
            'theme' => $this->theme($catalog),
            default => TemplateResolver::EMPTY,
        };
    }

    private function example(): string
    {
        $example = (string) select(
            label: 'Which example?',
            options: [...TemplateResolver::EXAMPLES, 'other' => 'Another one, by name'],
            default: 'basics',
        );

        if ($example !== 'other') {
            return $example;
        }

        return text(
            label: 'Which example? Any directory of github.com/withastro/astro/tree/main/examples',
            placeholder: 'framework-vue',
            required: true,
            validate: static fn (string $value): ?string => preg_match('/^[a-z0-9-]+$/', $value) === 1 ? null : 'An example name is lowercase letters, digits and dashes.',
        );
    }

    /**
     * Searches the catalog, then resolves the chosen theme to its repository.
     *
     * Only free themes are offered: a paid one has no public repository to
     * install from, and listing it would only lead to an error one step later.
     *
     * @throws AstroLandingException
     */
    private function theme(ThemeCatalog $catalog): string
    {
        while (true) {
            $query = text(
                label: 'Search the Astro themes catalog',
                placeholder: 'saas, startup, product, or leave empty to browse landing pages',
                hint: 'A theme slug or an astro.build/themes/details/ URL works too.',
            );

            if (preg_match('#^https?://#', $query) === 1 || str_starts_with($query, 'theme:')) {
                return spin(static fn (): string => $catalog->template($query), 'Finding the theme\'s repository...');
            }

            $themes = spin(static fn (): array => $catalog->search($query), 'Searching astro.build/themes...');
            $free = array_values(array_filter($themes, static fn (Theme $theme): bool => $theme->free));

            if ($free === []) {
                $this->components->warn($themes === []
                    ? 'Nothing in the catalog matched that.'
                    : 'Only paid themes matched that. Buy one, then install it from its repository.');

                continue;
            }

            $options = [];

            foreach ($free as $theme) {
                $options[$theme->slug] = $theme->label();
            }

            $slug = (string) select(
                label: 'Which theme?',
                options: [...$options, '' => '← Search again'],
                scroll: 10,
            );

            if ($slug === '') {
                continue;
            }

            return spin(static fn (): string => $catalog->template($slug), 'Finding the theme\'s repository...');
        }
    }

    /**
     * @throws AstroLandingException
     */
    private function resolve(TemplateResolver $templates, string $input): string
    {
        $isCatalog = str_starts_with(trim($input), 'theme:') || str_contains($input, 'astro.build/');

        return $isCatalog && $this->input->isInteractive()
            ? spin(static fn (): string => $templates->resolve($input), 'Finding the theme\'s repository...')
            : $templates->resolve($input);
    }

    private function packageManager(Landing $landing): ?PackageManager
    {
        $given = $this->option('pm');

        if (is_string($given) && $given !== '') {
            $manager = PackageManager::tryFrom($given);

            if (! $manager instanceof PackageManager) {
                $this->components->error(sprintf('--pm takes %s, not "%s".', implode(', ', PackageManager::values()), $given));
            }

            return $manager;
        }

        $detected = PackageManager::detect($landing->basePath()) ?? PackageManager::Npm;

        if (! $this->input->isInteractive()) {
            return $detected;
        }

        return PackageManager::from((string) select(
            label: 'Which package manager should the landing use?',
            options: array_combine(PackageManager::values(), PackageManager::values()),
            default: $detected->value,
            hint: 'Defaults to the one the application uses.',
        ));
    }

    /**
     * Null means integrations were asked for that cannot be added.
     *
     * @return list<string>|null
     */
    private function integrations(string $template): ?array
    {
        /** @var list<string> $given */
        $given = array_values(array_filter((array) $this->option('add'), static fn (mixed $value): bool => is_string($value) && $value !== ''));

        foreach ($given as $integration) {
            if (preg_match('/^(@[a-z0-9-]+\/)?[a-z0-9][a-z0-9.\-]*$/', $integration) !== 1) {
                $this->components->error(sprintf('"%s" is not an integration name.', $integration));

                return null;
            }
        }

        $skipInstall = $this->option('skip-install') === true;

        if ($given !== [] && $skipInstall) {
            $this->components->error('Integrations are added with `astro add`, which needs the dependencies installed. Drop --skip-install or --add.');

            return null;
        }

        if ($given === [] && ! $skipInstall && $template === TemplateResolver::EMPTY && $this->input->isInteractive()) {
            return array_values(array_map(strval(...), multiselect(
                label: 'Add any integrations?',
                options: self::INTEGRATIONS,
                hint: 'Each runs `astro add`. Anything else can be added later the same way.',
            )));
        }

        return $given;
    }

    /**
     * A DeployTarget, null for none, or false for an answer that is neither.
     */
    private function deployTarget(): DeployTarget|false|null
    {
        $given = $this->option('deploy');

        if (! is_string($given) || $given === '') {
            if (! $this->input->isInteractive()) {
                return null;
            }

            $given = (string) select(
                label: 'Where will the landing be deployed?',
                options: [...DeployTarget::options(), 'none' => 'Nowhere yet, no workflow'],
                default: 'none',
                hint: 'Writes a GitHub Actions workflow that deploys only when the landing changes.',
            );
        }

        if ($given === 'none') {
            return null;
        }

        $target = DeployTarget::tryFrom($given);

        if (! $target instanceof DeployTarget) {
            $this->components->error(sprintf('--deploy takes %s or none, not "%s".', implode(', ', DeployTarget::values()), $given));

            return false;
        }

        return $target;
    }

    /**
     * create-astro is told everything so it never prompts: a prompt from a
     * child process with no TTY is a hang, not a question.
     *
     * --no-install because the install is done here, with the package manager
     * that was chosen; create-astro would use whichever one launched it.
     * --no-git because the landing lives inside the application's repository.
     */
    private function scaffold(Landing $landing, PackageManager $manager, string $path, string $template): bool
    {
        $result = Process::path($landing->basePath())
            ->timeout(600)
            ->env(['CI' => 'true'])
            ->run($manager->create([
                $path,
                '--template', $template,
                '--no-install',
                '--no-git',
                '--skip-houston',
                '--yes',
            ]), $this->streamOutput(...));

        if ($result->failed() || ! is_file($landing->absolutePath($path).'/package.json')) {
            $this->components->error(sprintf(
                'create-astro could not create %s/ from "%s". Its output is above.',
                $path,
                $template,
            ));

            return false;
        }

        $this->components->twoColumnDetail('Astro project', '<fg=green>'.$path.'/</>');

        return true;
    }

    /**
     * Templates often ship the lockfile of whoever wrote them. Left next to the
     * one the chosen package manager writes, it wins the lockfile lookup, so
     * landing:dev and the deploy workflow would use the template author's
     * package manager instead of this one, against a stale lockfile.
     *
     * Only ever in a directory create-astro has just filled, which was empty
     * before this command ran.
     */
    private function dropForeignLockfiles(Filesystem $files, string $directory, PackageManager $manager): void
    {
        foreach ($manager->foreignLockfiles() as $lockfile) {
            if ($files->exists($directory.'/'.$lockfile)) {
                $files->delete($directory.'/'.$lockfile);

                $this->components->twoColumnDetail($lockfile, '<fg=yellow>removed, the template\'s; '.$manager->value.' writes its own</>');
            }
        }
    }

    /**
     * @param  list<string>  $integrations
     */
    private function installDependencies(Landing $landing, PackageManager $manager, string $path, array $integrations): bool
    {
        $directory = $landing->absolutePath($path);

        $install = Process::path($directory)->timeout(900)->run($manager->install(), $this->streamOutput(...));

        if ($install->failed()) {
            $this->components->error(sprintf('%s could not install the landing\'s dependencies. Its output is above; run `%s` in %s/ to retry.', $manager->value, implode(' ', $manager->install()), $path));

            return false;
        }

        $this->components->twoColumnDetail('Dependencies', '<fg=green>installed with '.$manager->value.'</>');

        foreach ($integrations as $integration) {
            $added = Process::path($directory)->timeout(600)->run($manager->astroAdd($integration), $this->streamOutput(...));

            if ($added->failed()) {
                $this->components->error(sprintf('`astro add %s` failed. Its output is above.', $integration));

                return false;
            }

            $this->components->twoColumnDetail('Integration', '<fg=green>'.$integration.'</>');
        }

        return true;
    }

    private function streamOutput(string $type, string $output): void
    {
        $this->output->write($output);
    }

    /**
     * Published with the chosen path written in as the default, so the landing
     * is found without anybody adding LANDING_PATH to every environment.
     *
     * Returns whether the config, as it now stands, finds this landing.
     */
    private function publishConfig(Filesystem $files, Landing $landing, string $path, string $configuredPath, ?PackageManager $pin): bool
    {
        $destination = $landing->basePath('config/landing.php');

        if ($files->exists($destination) && $this->option('force') !== true) {
            $this->components->twoColumnDetail('config/landing.php', '<fg=yellow>already exists, left alone</>');

            if ($pin instanceof PackageManager) {
                $this->components->warn(sprintf('Set LANDING_PACKAGE_MANAGER=%s until the landing has a lockfile, or later commands will use another package manager.', $pin->value));
            }

            if ($configuredPath !== $path) {
                $this->components->warn(sprintf('config/landing.php points at %s/. Set LANDING_PATH=%s, or update it, for landing:dev and composer run dev to find this one.', $configuredPath, $path));

                return false;
            }

            return true;
        }

        $config = (string) $files->get(dirname(__DIR__, 2).'/config/landing.php');

        $files->ensureDirectoryExists(dirname($destination));
        $files->put($destination, strtr($config, [
            "env('LANDING_PATH', 'landing')" => sprintf("env('LANDING_PATH', '%s')", $path),
            "env('LANDING_PACKAGE_MANAGER')" => $pin instanceof PackageManager
                ? sprintf("env('LANDING_PACKAGE_MANAGER', '%s')", $pin->value)
                : "env('LANDING_PACKAGE_MANAGER')",
        ]));

        $this->components->twoColumnDetail('config/landing.php', '<fg=green>published</>');

        return true;
    }

    /**
     * Anchored entries in the application's .gitignore. Templates usually carry
     * their own, but not all of them, and a committed node_modules is a mistake
     * nobody notices until the repository is a gigabyte.
     */
    private function ignoreBuildOutput(Filesystem $files, Landing $landing, string $path): void
    {
        $gitignore = $landing->basePath('.gitignore');
        $current = $files->exists($gitignore) ? (string) $files->get($gitignore) : '';

        $lines = array_values(array_filter(
            ["/{$path}/node_modules", "/{$path}/dist", "/{$path}/.astro"],
            static fn (string $line): bool => preg_match('/^'.preg_quote($line, '/').'\/?\s*$/m', $current) !== 1,
        ));

        if ($lines === []) {
            $this->components->twoColumnDetail('.gitignore', '<fg=yellow>already ignores the landing\'s build output</>');

            return;
        }

        $prefix = $current === '' || str_ends_with($current, "\n") ? '' : "\n";

        $files->append($gitignore, $prefix."\n# Astro landing\n".implode("\n", $lines)."\n");

        $this->components->twoColumnDetail('.gitignore', '<fg=green>ignores '.$path.'/node_modules, dist and .astro</>');
    }

    /**
     * Tailwind v4 finds its sources by scanning the project, and the landing is
     * inside the project, so without this, every class the landing uses ends up
     * in the application's stylesheet as well.
     */
    private function keepOutOfTailwind(Filesystem $files, Landing $landing, string $path): void
    {
        $css = $landing->basePath('resources/css/app.css');

        if (! $files->exists($css)) {
            return;
        }

        $contents = (string) $files->get($css);
        $line = sprintf("@source not '../../%s';", $path);

        if (! str_contains($contents, 'tailwindcss') || str_contains($contents, $line)) {
            return;
        }

        $updated = preg_replace(
            '/^(@import\s+["\']tailwindcss["\'][^;\n]*;)$/m',
            '$1'."\n\n".$line,
            $contents,
            1,
            $count,
        );

        if ($count !== 1 || ! is_string($updated)) {
            return;
        }

        $files->put($css, $updated);

        $this->components->twoColumnDetail('resources/css/app.css', '<fg=green>Tailwind no longer scans '.$path.'/</>');
    }

    /**
     * @return list<string>
     */
    private function checklist(string $path, PackageManager $manager, ?DeployTarget $target, bool $configured): array
    {
        $run = implode(' ', $manager->run('dev'));

        $steps = [
            ! $configured
                ? sprintf('Start it with `%s` in %s/ until the config points at it.', $run, $path)
                : (class_exists(DevCommands::class)
                ? sprintf('`composer run dev` now starts the landing as well, at http://localhost:4321. On its own: `php artisan landing:dev`, or `%s` in %s/.', $run, $path)
                : sprintf('Start it with `php artisan landing:dev`, or `%s` in %s/.', $run, $path)),
            sprintf('Set `site` in %s/astro.config.mjs to the landing\'s public URL. Canonical links and the sitemap are built from it.', $path),
        ];

        if ($this->option('skip-install') === true) {
            array_unshift($steps, sprintf('Install the dependencies: `%s` in %s/.', implode(' ', $manager->install()), $path));
        }

        if (! $target instanceof DeployTarget) {
            $steps[] = 'Deploy it with `php artisan landing:workflow` once you know where it goes.';
        }

        $steps[] = sprintf('If the application runs ESLint, Prettier or TypeScript from the root, add %s/ to their ignore lists.', $path);

        return $steps;
    }
}
