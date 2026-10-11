<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LauroGuedes\AstroLanding\Support\DeployTarget;
use LauroGuedes\AstroLanding\Support\PackageManager;
use LauroGuedes\AstroLanding\Support\Workflow;
use Symfony\Component\Yaml\Yaml;

/**
 * Every target with every package manager, parsed as YAML.
 *
 * A workflow GitHub cannot parse fails on push with nothing in the Actions tab
 * but a red cross, so this is the property worth proving for the whole matrix
 * rather than for the one combination somebody happened to try.
 */
it('renders a workflow GitHub can parse', function (DeployTarget $target, PackageManager $manager): void {
    $yaml = (new Workflow(new Filesystem, $this->root))->render(
        target: $target,
        manager: $manager,
        path: 'landing',
        branch: 'main',
        file: 'deploy-landing.yml',
        project: 'acme-landing',
    );

    $workflow = Yaml::parse($yaml);

    expect($yaml)->not->toMatch('/(?<!\$)\{\{ /')
        ->and($workflow['name'])->toBe('deploy-landing')
        ->and($workflow['on']['push']['paths'])->toBe(['landing/**', '.github/workflows/deploy-landing.yml'])
        ->and($workflow['on'])->toHaveKey('workflow_dispatch')
        ->and($workflow['concurrency']['group'])->toBe('${{ github.workflow }}');

    $steps = array_merge(...array_map(static fn (array $job): array => $job['steps'], array_values($workflow['jobs'])));
    $runs = array_column($steps, 'run');

    expect($runs)->toContain($manager->cleanInstall())
        ->and($runs)->toContain(implode(' ', $manager->run('build')));
})->with(DeployTarget::cases())->with(PackageManager::cases());

it('writes the workflow and lists the secrets it reads', function (): void {
    $this->artisan('landing:workflow', ['target' => 'netlify'])
        ->expectsOutputToContain('NETLIFY_AUTH_TOKEN, NETLIFY_SITE_ID')
        ->assertSuccessful();

    expect(file_get_contents($this->root.'/.github/workflows/deploy-landing.yml'))
        ->toContain('netlify-cli@latest deploy --dir=dist --prod');
});

it('leaves an existing workflow alone without --force', function (): void {
    $this->file('.github/workflows/deploy-landing.yml', 'mine');

    $this->artisan('landing:workflow', ['target' => 'vercel'])->assertSuccessful();
    expect(file_get_contents($this->root.'/.github/workflows/deploy-landing.yml'))->toBe('mine');

    $this->artisan('landing:workflow', ['target' => 'vercel', '--force' => true])->assertSuccessful();
    expect(file_get_contents($this->root.'/.github/workflows/deploy-landing.yml'))->toContain('vercel@latest');
});

it('deploys one landing to two places from two files', function (): void {
    $this->artisan('landing:workflow', ['target' => 'cloudflare-pages'])->assertSuccessful();
    $this->artisan('landing:workflow', ['target' => 'ssh', '--file' => 'deploy-landing-mirror'])->assertSuccessful();

    $mirror = Yaml::parseFile($this->root.'/.github/workflows/deploy-landing-mirror.yml');

    expect($this->root.'/.github/workflows/deploy-landing.yml')->toBeFile()
        ->and($mirror['name'])->toBe('deploy-landing-mirror')
        ->and($mirror['on']['push']['paths'])->toContain('.github/workflows/deploy-landing-mirror.yml');
});

it('names the Cloudflare project after the application', function (): void {
    config(['app.name' => 'Acme Cloud']);

    $this->artisan('landing:workflow', ['target' => 'cloudflare-pages'])->assertSuccessful();

    expect(file_get_contents($this->root.'/.github/workflows/deploy-landing.yml'))
        ->toContain('--project-name=acme-cloud-landing --branch=main');
});

it('uses the configured directory and the landing\'s own package manager', function (): void {
    config(['landing.path' => 'marketing']);
    $this->file('marketing/package.json', '{}');
    $this->file('marketing/bun.lock');
    $this->file('package-lock.json');

    $this->artisan('landing:workflow', ['target' => 'github-pages'])->assertSuccessful();

    $workflow = Yaml::parseFile($this->root.'/.github/workflows/deploy-landing.yml');

    expect($workflow['jobs']['build']['defaults']['run']['working-directory'])->toBe('marketing')
        ->and($workflow['jobs']['build']['steps'][1]['uses'])->toBe('oven-sh/setup-bun@v2');
});

it('prefers a published stub', function (): void {
    $this->file('stubs/landing/workflows/ssh.yml.stub', "name: {{ name }}\n# custom {{ path }}\n");

    $this->artisan('landing:workflow', ['target' => 'ssh'])->assertSuccessful();

    expect(file_get_contents($this->root.'/.github/workflows/deploy-landing.yml'))->toBe("name: deploy-landing\n# custom landing\n");
});

it('rejects what it cannot write', function (array $parameters): void {
    $this->artisan('landing:workflow', [...$parameters, '--no-interaction' => true])->assertFailed();

    expect($this->root.'/.github')->not->toBeDirectory();
})->with([
    'no target' => [[]],
    'an unknown target' => [['target' => 'heroku']],
    'a path as the file' => [['target' => 'ssh', '--file' => '../ci.yml']],
    'a branch that is not one' => [['target' => 'ssh', '--branch' => 'main; echo']],
]);

it('refuses a configured path it cannot put in a workflow', function (): void {
    config(['landing.path' => "landing'\n  evil: true"]);

    $this->artisan('landing:workflow', ['target' => 'ssh'])
        ->expectsOutputToContain('Check LANDING_PATH')
        ->assertFailed();

    expect($this->root.'/.github')->not->toBeDirectory();
});
