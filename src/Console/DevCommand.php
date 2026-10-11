<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding\Console;

use Illuminate\Console\Command;
use LauroGuedes\AstroLanding\Console\Concerns\RunsLandingScripts;
use LauroGuedes\AstroLanding\Support\Landing;

/**
 * The Astro dev server, from the application root.
 *
 * On Laravel 13.16+ this is also what `composer run dev` starts for the landing,
 * registered with DevCommands by the service provider.
 */
final class DevCommand extends Command
{
    use RunsLandingScripts;

    protected $signature = 'landing:dev';

    protected $description = 'Run the Astro landing\'s dev server';

    public function handle(Landing $landing): int
    {
        return $this->runScript($landing, 'dev');
    }
}
