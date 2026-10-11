<?php

declare(strict_types=1);

/*
 * Renders the deploy workflow for every target and package manager into the
 * directory given, for actionlint to check. Run by .github/workflows/workflow-stubs.yml.
 */

use Illuminate\Filesystem\Filesystem;
use LauroGuedes\AstroOnLaravel\Support\DeployTarget;
use LauroGuedes\AstroOnLaravel\Support\PackageManager;
use LauroGuedes\AstroOnLaravel\Support\Workflow;

require __DIR__.'/../vendor/autoload.php';

$output = $argv[1] ?? 'build/workflows';
$files = new Filesystem;
$files->ensureDirectoryExists($output);

$workflow = new Workflow($files, sys_get_temp_dir());

foreach (DeployTarget::cases() as $target) {
    foreach (PackageManager::cases() as $manager) {
        $file = sprintf('%s-%s.yml', $target->value, $manager->value);

        $files->put($output.'/'.$file, $workflow->render(
            target: $target,
            manager: $manager,
            path: 'landing',
            branch: 'main',
            file: $file,
            project: 'example-landing',
        ));

        echo $file, PHP_EOL;
    }
}
