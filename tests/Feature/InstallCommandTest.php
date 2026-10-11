<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Testing\PendingCommand;
use Symfony\Component\Yaml\Yaml;

beforeEach(function (): void {
    fakeProcesses();
    Http::preventStrayRequests();
});

/**
 * Every question answered by a flag, so these tests are about what the
 * installer does rather than about the wording of what it asks.
 *
 * @param  array<string, mixed>  $parameters
 */
function landingInstall(array $parameters = []): PendingCommand
{
    return test()->artisan('landing:install', [
        '--path' => 'landing',
        '--template' => 'empty',
        '--pm' => 'npm',
        '--deploy' => 'none',
        '--no-interaction' => true,
        ...$parameters,
    ]);
}

it('scaffolds the empty template without letting create-astro prompt, install or init git', function (): void {
    landingInstall()->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        'npx', '--yes', 'create-astro@latest', 'landing',
        '--template', 'minimal',
        '--no-install', '--no-git', '--skip-houston', '--yes',
    ] && $process->path === $this->root);

    expect($this->root.'/landing/package.json')->toBeFile();
});

it('installs the dependencies inside the landing with the chosen package manager', function (string $manager, array $install): void {
    landingInstall(['--pm' => $manager])->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === $install
        && $process->path === $this->root.'/landing');
})->with([
    'npm' => ['npm', ['npm', 'install']],
    'pnpm' => ['pnpm', ['pnpm', 'install']],
    'yarn' => ['yarn', ['yarn', 'install']],
    'bun' => ['bun', ['bun', 'install']],
]);

it('skips the install when asked to', function (): void {
    landingInstall(['--skip-install' => true])->assertSuccessful();

    Process::assertDidntRun(fn (PendingProcess $process): bool => commandLine($process) === 'npm install');
});

it('adds each integration with astro add after installing', function (): void {
    landingInstall(['--add' => ['tailwind', 'sitemap']])->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => commandLine($process) === 'npx astro add tailwind --yes');
    Process::assertRan(fn (PendingProcess $process): bool => commandLine($process) === 'npx astro add sitemap --yes');
});

it('refuses integrations it could not add', function (): void {
    landingInstall(['--add' => ['tailwind'], '--skip-install' => true])->assertFailed();

    Process::assertNothingRan();
});

it('refuses an integration name that is not one', function (): void {
    landingInstall(['--add' => ['tailwind; rm -rf /']])->assertFailed();

    Process::assertNothingRan();
});

it('passes a repository template through to create-astro', function (string $given, string $expected): void {
    landingInstall(['--template' => $given])->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
        && in_array('create-astro@latest', $process->command, true)
        && $process->command[array_search('--template', $process->command, true) + 1] === $expected);
})->with([
    'an example' => ['blog', 'blog'],
    'owner/repo' => ['onwidget/astrowind', 'onwidget/astrowind'],
    'a GitHub URL' => ['https://github.com/onwidget/astrowind', 'onwidget/astrowind'],
    'a tree URL' => ['https://github.com/someone/themes/tree/main/landing', 'someone/themes/landing#main'],
]);

it('resolves a catalog theme to its repository before creating anything', function (): void {
    Http::fake([
        'astro.build/themes/details/landpack/' => Http::response(file_get_contents(__DIR__.'/../Fixtures/theme-free.html')),
    ]);

    landingInstall(['--template' => 'theme:landpack'])->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
        && in_array('someone/landpack', $process->command, true));
});

it('stops before downloading anything when the theme is paid', function (): void {
    Http::fake([
        'astro.build/themes/details/foxi-pro/' => Http::response(file_get_contents(__DIR__.'/../Fixtures/theme-paid.html')),
    ]);

    landingInstall(['--template' => 'theme:foxi-pro'])
        ->expectsOutputToContain('paid theme')
        ->assertFailed();

    Process::assertNothingRan();
});

it('refuses a directory that already has something in it', function (): void {
    $this->file('landing/index.html', '<h1>mine</h1>');

    landingInstall()->assertFailed();

    Process::assertNothingRan();
    expect(file_get_contents($this->root.'/landing/index.html'))->toBe('<h1>mine</h1>');
});

it('refuses a directory outside the application or one Laravel owns', function (string $path): void {
    landingInstall(['--path' => $path])->assertFailed();

    Process::assertNothingRan();
})->with(['../elsewhere', '/tmp/landing', 'resources/landing', 'public']);

it('rejects an unknown package manager and an unknown deploy target', function (array $parameters): void {
    landingInstall($parameters)->assertFailed();

    Process::assertNothingRan();
})->with([
    'package manager' => [['--pm' => 'pip']],
    'deploy target' => [['--deploy' => 'heroku']],
]);

it('reports a failed create-astro and writes nothing else', function (): void {
    fakeProcesses(['create-astro' => 1]);

    landingInstall()->assertFailed();

    expect($this->root.'/config/landing.php')->not->toBeFile()
        ->and($this->root.'/.gitignore')->not->toBeFile();
});

it('publishes the config with the chosen directory as the default', function (): void {
    landingInstall(['--path' => 'marketing'])->assertSuccessful();

    expect(file_get_contents($this->root.'/config/landing.php'))
        ->toContain("env('LANDING_PATH', 'marketing')");
});

it('leaves an existing config alone', function (): void {
    $this->file('config/landing.php', '<?php return ["path" => "landing"];');

    landingInstall()->assertSuccessful();

    expect(file_get_contents($this->root.'/config/landing.php'))->toBe('<?php return ["path" => "landing"];');
});

it('ignores the build output once, however many times it runs', function (): void {
    $this->file('.gitignore', "/vendor\n/node_modules");

    landingInstall()->assertSuccessful();

    (new Filesystem)->deleteDirectory($this->root.'/landing');

    landingInstall()->assertSuccessful();

    $gitignore = file_get_contents($this->root.'/.gitignore');

    expect($gitignore)->toStartWith("/vendor\n/node_modules\n")
        ->and(substr_count($gitignore, '/landing/node_modules'))->toBe(1)
        ->and(substr_count($gitignore, '/landing/dist'))->toBe(1)
        ->and(substr_count($gitignore, '/landing/.astro'))->toBe(1);
});

it('keeps the landing out of the application\'s Tailwind build', function (): void {
    $this->file('resources/css/app.css', "@import 'tailwindcss';\n\n@source '../views';\n");

    landingInstall()->assertSuccessful();

    expect(file_get_contents($this->root.'/resources/css/app.css'))
        ->toBe("@import 'tailwindcss';\n\n@source not '../../landing';\n\n@source '../views';\n");
});

it('leaves a stylesheet without Tailwind alone', function (): void {
    $this->file('resources/css/app.css', "body { color: red; }\n");

    landingInstall()->assertSuccessful();

    expect(file_get_contents($this->root.'/resources/css/app.css'))->toBe("body { color: red; }\n");
});

it('writes the deploy workflow for the chosen target', function (): void {
    landingInstall(['--deploy' => 'cloudflare-pages', '--branch' => 'production'])->assertSuccessful();

    $workflow = Yaml::parseFile($this->root.'/.github/workflows/deploy-landing.yml');

    expect($workflow['on']['push']['branches'])->toBe(['production'])
        ->and($workflow['on']['push']['paths'])->toContain('landing/**');
});

it('installs with defaults when nobody is at the keyboard', function (): void {
    $this->file('bun.lock');

    $this->artisan('landing:install', ['--no-interaction' => true])->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => commandLine($process) === 'bunx create-astro@latest landing --template minimal --no-install --no-git --skip-houston --yes');
    expect($this->root.'/.github/workflows')->not->toBeDirectory();
});

it('asks for everything it was not told', function (): void {
    Http::fake([
        'astro.build/themes/1/*' => Http::response(file_get_contents(__DIR__.'/../Fixtures/catalog.html')),
        'astro.build/themes/details/kairo-saas/' => Http::response(file_get_contents(__DIR__.'/../Fixtures/theme-subdirectory.html')),
    ]);

    $this->artisan('landing:install')
        ->expectsQuestion('Where should the landing live?', 'site')
        ->expectsChoice('What should the landing start from?', 'theme', [
            'empty' => 'An empty project: Astro\'s minimal template',
            'example' => 'An official Astro example',
            'repository' => 'A template repository on GitHub, GitLab or Bitbucket',
            'theme' => 'A free theme from astro.build/themes',
        ])
        ->expectsQuestion('Search the Astro themes catalog', 'saas')
        ->expectsChoice('Which theme?', 'kairo-saas', [
            'landpack' => 'Landpack',
            'kairo-saas' => 'Kairo & Co - SaaS',
            '' => '← Search again',
        ])
        ->expectsChoice('Which package manager should the landing use?', 'pnpm', ['npm' => 'npm', 'pnpm' => 'pnpm', 'yarn' => 'yarn', 'bun' => 'bun'])
        ->expectsChoice('Where will the landing be deployed?', 'netlify', [
            'cloudflare-pages' => 'Cloudflare Pages',
            'netlify' => 'Netlify',
            'vercel' => 'Vercel',
            'github-pages' => 'GitHub Pages',
            'ssh' => 'Your own server, over SSH and rsync',
            'none' => 'Nowhere yet, no workflow',
        ])
        ->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => commandLine($process) === 'pnpm dlx create-astro@latest site --template someone/themes/packages/kairo#v2 --no-install --no-git --skip-houston --yes');
    expect($this->root.'/.github/workflows/deploy-landing.yml')->toBeFile()
        ->and(file_get_contents($this->root.'/.github/workflows/deploy-landing.yml'))->toContain("- 'site/**'");
});

it('says when an existing config will not find the new landing', function (): void {
    $this->file('config/landing.php', '<?php return ["path" => "landing"];');

    landingInstall(['--path' => 'site'])
        ->expectsOutputToContain('config/landing.php points at landing/. Set LANDING_PATH=site')
        ->expectsOutputToContain('until the config points at it')
        ->assertSuccessful();
});

it('drops the template author\'s lockfile when another package manager was chosen', function (): void {
    fakeProcesses(templateFiles: ['bun.lock', 'package-lock.json']);

    landingInstall(['--pm' => 'npm'])->assertSuccessful();

    expect($this->root.'/landing/bun.lock')->not->toBeFile()
        ->and($this->root.'/landing/package-lock.json')->toBeFile();
});

it('pins the package manager when there is no lockfile to say which one it is', function (): void {
    $this->file('package-lock.json');

    landingInstall(['--pm' => 'bun', '--skip-install' => true])->assertSuccessful();

    expect(file_get_contents($this->root.'/config/landing.php'))
        ->toContain("env('LANDING_PACKAGE_MANAGER', 'bun')");
});

it('does not pin the package manager the lockfiles already name', function (): void {
    $this->file('package-lock.json');

    landingInstall(['--pm' => 'npm', '--skip-install' => true])->assertSuccessful();

    expect(file_get_contents($this->root.'/config/landing.php'))
        ->toContain("env('LANDING_PACKAGE_MANAGER')");
});

it('rejects a branch name before creating anything', function (): void {
    landingInstall(['--deploy' => 'netlify', '--branch' => '--upload-pack=x'])->assertFailed();

    Process::assertNothingRan();
});
