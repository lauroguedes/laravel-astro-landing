<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding\Console\Concerns;

use Illuminate\Support\Facades\Process;
use LauroGuedes\AstroLanding\Support\Landing;

/**
 * Runs a package.json script inside the landing, installing its dependencies
 * first when they are missing.
 *
 * The install-if-missing is what lets a fresh clone run `composer run dev`
 * without anybody knowing the landing has a node_modules of its own to fill:
 * the application's `npm install` does not reach into it.
 */
trait RunsLandingScripts
{
    private const int SIGINT = 2;

    private const int SIGTERM = 15;

    private function runScript(Landing $landing, string $script): int
    {
        if (! $landing->exists()) {
            $this->components->error(sprintf(
                'There is no Astro project in %s/. Run landing:install, or set LANDING_PATH.',
                $landing->path(),
            ));

            return self::FAILURE;
        }

        $manager = $landing->packageManager();

        if (! $landing->dependenciesInstalled()) {
            $this->components->info(sprintf('Installing the landing\'s dependencies with %s.', $manager->value));

            $installed = $this->stream($landing, $manager->install());

            if ($installed !== self::SUCCESS) {
                return $installed;
            }
        }

        return $this->stream($landing, $manager->run($script));
    }

    /**
     * Started rather than run, so a SIGINT or SIGTERM sent to artisan — which
     * is how `php artisan dev` stops its processes — reaches the dev server
     * too, instead of leaving it holding its port.
     *
     * @param  list<string>  $command
     */
    private function stream(Landing $landing, array $command): int
    {
        $process = Process::path($landing->absolutePath())
            ->forever()
            ->env(['FORCE_COLOR' => '1'])
            ->start($command, function (string $type, string $output): void {
                $this->output->write($output);
            });

        $this->trap([self::SIGINT, self::SIGTERM], static function (int $signal) use ($process): void {
            $process->signal($signal);
        });

        return $process->wait()->exitCode() ?? self::FAILURE;
    }
}
