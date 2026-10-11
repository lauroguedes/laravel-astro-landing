<?php

declare(strict_types=1);

use Illuminate\Foundation\DevCommands;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use LauroGuedes\AstroLanding\AstroLandingServiceProvider;

beforeEach(function (): void {
    fakeProcesses();
});

it('runs the script inside the landing', function (string $command, string $script): void {
    $this->file('landing/package.json', '{}');
    mkdir($this->root.'/landing/node_modules');

    $this->artisan($command)->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['npm', 'run', $script]
        && $process->path === $this->root.'/landing');
    Process::assertDidntRun(fn (PendingProcess $process): bool => commandLine($process) === 'npm install');
})->with([
    'dev' => ['landing:dev', 'dev'],
    'build' => ['landing:build', 'build'],
]);

it('installs the dependencies of a fresh clone first, with the landing\'s package manager', function (): void {
    $this->file('landing/package.json', '{}');
    $this->file('landing/pnpm-lock.yaml');

    $this->artisan('landing:build')->assertSuccessful();

    Process::assertRanTimes(fn (PendingProcess $process): bool => commandLine($process) === 'pnpm install', 1);
    Process::assertRan(fn (PendingProcess $process): bool => commandLine($process) === 'pnpm run build');
});

it('does not start the server when the install failed', function (): void {
    fakeProcesses(['npm install' => 1]);
    $this->file('landing/package.json', '{}');

    $this->artisan('landing:dev')->assertFailed();

    Process::assertDidntRun(fn (PendingProcess $process): bool => commandLine($process) === 'npm run dev');
});

it('passes the script\'s exit code on', function (): void {
    fakeProcesses(['run build' => 3]);
    $this->file('landing/package.json', '{}');
    mkdir($this->root.'/landing/node_modules');

    $this->artisan('landing:build')->assertExitCode(3);
});

it('says so when there is no landing', function (): void {
    $this->artisan('landing:dev')
        ->expectsOutputToContain('There is no Astro project in landing/')
        ->assertFailed();

    Process::assertNothingRan();
});

/**
 * DevCommands keeps its registry in statics that outlive the application, so
 * this resets it either side rather than trusting the test before it.
 */
describe('composer run dev', function (): void {
    beforeEach(function (): void {
        if (! class_exists(DevCommands::class)) {
            $this->markTestSkipped('DevCommands arrived in Laravel 13.16.');
        }

        $reset = static function (): void {
            $commands = new ReflectionProperty(DevCommands::class, 'commands');
            $commands->setValue(null, []);
        };

        $reset();
        $this->beforeApplicationDestroyed($reset);
    });

    function registeredDevCommands(): array
    {
        return array_column(DevCommands::commands(), 'command', 'name');
    }

    it('starts the landing alongside the application', function (): void {
        $this->file('landing/package.json', '{}');

        app()->register(AstroLandingServiceProvider::class, force: true);

        expect(registeredDevCommands())->toHaveKey('landing', 'php artisan landing:dev');
    });

    it('stays out of it until there is a landing, or when it is turned off', function (bool $exists, bool $enabled): void {
        if ($exists) {
            $this->file('landing/package.json', '{}');
        }

        config(['landing.dev.enabled' => $enabled]);

        app()->register(AstroLandingServiceProvider::class, force: true);

        expect(registeredDevCommands())->not->toHaveKey('landing');
    })->with([
        'no landing' => [false, true],
        'turned off' => [true, false],
    ]);
});
