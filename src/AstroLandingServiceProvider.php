<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;
use LauroGuedes\AstroLanding\Console\BuildCommand;
use LauroGuedes\AstroLanding\Console\DevCommand;
use LauroGuedes\AstroLanding\Console\InstallCommand;
use LauroGuedes\AstroLanding\Console\WorkflowCommand;
use LauroGuedes\AstroLanding\Support\Landing;

/**
 * Console only. The landing is a static site deployed somewhere else, so there
 * is nothing for an HTTP request of the application to load: no routes, no
 * views, no middleware. Outside the console boot() returns before doing
 * anything at all.
 */
final class AstroLandingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/landing.php', 'landing');

        $this->app->singleton(Landing::class, static fn (Container $app): Landing => new Landing(
            $app->make('config'),
            $app->basePath(),
        ));
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/landing.php' => $this->app->configPath('landing.php'),
        ], 'landing-config');

        $this->publishes([
            __DIR__.'/../stubs/workflows' => $this->app->basePath('stubs/landing/workflows'),
        ], 'landing-stubs');

        $this->commands([
            InstallCommand::class,
            WorkflowCommand::class,
            DevCommand::class,
            BuildCommand::class,
        ]);

        $this->registerDevServer();
    }

    /**
     * Adds the landing to `php artisan dev`, which is what `composer run dev`
     * runs on Laravel 13.
     *
     * Registered as an artisan command rather than as `npm run dev` in the
     * landing directory, for two reasons: DevCommands has no way to set a
     * working directory, and landing:dev installs the landing's dependencies on
     * a fresh clone before starting the server.
     *
     * Laravel 12 has no DevCommands, so there it is landing:dev by hand.
     */
    private function registerDevServer(): void
    {
        if (! class_exists(DevCommands::class)) {
            return;
        }

        $landing = $this->app->make(Landing::class);

        if (! $landing->devServerEnabled() || ! $landing->exists()) {
            return;
        }

        DevCommands::artisan('landing:dev', $landing->devServerName());
    }
}
