<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding\Console;

use Illuminate\Console\Command;
use LauroGuedes\AstroLanding\Console\Concerns\RunsLandingScripts;
use LauroGuedes\AstroLanding\Support\Landing;

/**
 * Builds the landing into its dist/ directory, the same way the deploy
 * workflow does — so a build that fails in CI can be reproduced locally.
 */
final class BuildCommand extends Command
{
    use RunsLandingScripts;

    protected $signature = 'landing:build';

    protected $description = 'Build the Astro landing into its dist directory';

    public function handle(Landing $landing): int
    {
        return $this->runScript($landing, 'build');
    }
}
