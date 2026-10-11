<?php

declare(strict_types=1);

namespace LauroGuedes\AstroOnLaravel\Support;

use Illuminate\Filesystem\Filesystem;

/**
 * Renders the GitHub Actions workflow that builds and deploys the landing.
 *
 * The workflow is scoped to the landing by a paths filter, so a change to the
 * Laravel app never redeploys the landing and a copy change never touches the
 * app's pipeline. That separation is the reason this package exists: one
 * repository, two deploy targets that do not wait on each other.
 *
 * Stubs are read from the application's stubs/landing/workflows first, so a
 * project that published and edited them keeps its edits on every regeneration.
 */
final readonly class Workflow
{
    public function __construct(
        private Filesystem $files,
        private string $basePath,
    ) {}

    public function render(
        DeployTarget $target,
        PackageManager $manager,
        string $path,
        string $branch,
        string $file,
        string $project,
    ): string {
        $berry = $this->files->exists($this->basePath.'/'.$path.'/.yarnrc.yml');

        $build = implode(' ', $manager->run('build'));

        return strtr((string) $this->files->get($this->stub($target)), [
            '{{ name }}' => pathinfo($file, PATHINFO_FILENAME),
            '{{ path }}' => $path,
            '{{ branch }}' => $branch,
            '{{ workflow_file }}' => $file,
            '{{ setup }}' => $manager->setupSteps($path, $berry),
            '{{ build }}' => $build,
            '{{ project }}' => $project,
        ]);
    }

    public function stub(DeployTarget $target): string
    {
        $custom = $this->basePath.'/stubs/landing/workflows/'.$target->value.'.yml.stub';

        return $this->files->exists($custom)
            ? $custom
            : self::packageStub($target);
    }

    public static function packageStub(DeployTarget $target): string
    {
        return dirname(__DIR__, 2).'/stubs/workflows/'.$target->value.'.yml.stub';
    }
}
