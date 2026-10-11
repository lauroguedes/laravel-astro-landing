<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use LauroGuedes\AstroLanding\Support\DeployTarget;
use LauroGuedes\AstroLanding\Support\Landing;
use LauroGuedes\AstroLanding\Support\Workflow;

use function Laravel\Prompts\select;

/**
 * Writes the GitHub Actions workflow that deploys the landing.
 *
 * Separate from landing:install so a project can add a second target later, or
 * move from one host to another, without reinstalling anything: each target is
 * one file, and two of them deploy the same landing to two places.
 */
final class WorkflowCommand extends Command
{
    protected $signature = 'landing:workflow
        {target? : cloudflare-pages, netlify, vercel, github-pages or ssh}
        {--file= : Workflow file name in .github/workflows (default: deploy-landing.yml)}
        {--branch=main : Branch whose pushes deploy the landing}
        {--project= : Project name on the host, where it needs one (Cloudflare Pages)}
        {--force : Overwrite the workflow file if it exists}';

    protected $description = 'Generate a GitHub Actions workflow that builds and deploys the Astro landing';

    public function handle(Filesystem $files, Landing $landing): int
    {
        $target = $this->target();

        if (! $target instanceof DeployTarget) {
            return self::FAILURE;
        }

        $file = $this->fileName();

        if ($file === null) {
            return self::FAILURE;
        }

        $branch = $this->option('branch');

        if (! self::isBranchName($branch)) {
            $this->components->error(sprintf('"%s" is not a branch name.', is_string($branch) ? $branch : ''));

            return self::FAILURE;
        }

        /*
         * The path goes into the YAML unquoted. landing:install only ever
         * writes a valid one, but LANDING_PATH can say anything.
         */
        if (! Landing::isValidPath($landing->path())) {
            $this->components->error(sprintf('The landing path "%s" is not a directory inside the application. Check LANDING_PATH.', $landing->path()));

            return self::FAILURE;
        }

        $destination = $landing->basePath('.github/workflows/'.$file);

        if ($files->exists($destination) && $this->option('force') !== true) {
            $this->components->twoColumnDetail('.github/workflows/'.$file, '<fg=yellow>already exists, left alone (--force replaces it)</>');

            return self::SUCCESS;
        }

        $workflow = (new Workflow($files, $landing->basePath()))->render(
            target: $target,
            manager: $landing->packageManager(),
            path: $landing->path(),
            branch: $branch,
            file: $file,
            project: $this->project(),
        );

        $files->ensureDirectoryExists(dirname($destination));
        $files->put($destination, $workflow);

        $this->components->twoColumnDetail('.github/workflows/'.$file, '<fg=green>deploys to '.$target->label().'</>');

        $this->components->bulletList($this->checklist($target, $landing->path()));

        return self::SUCCESS;
    }

    /**
     * Characters git allows in a branch name that also need no quoting in
     * YAML. Shared with landing:install, which checks before it creates
     * anything.
     *
     * @phpstan-assert-if-true =string $branch
     */
    public static function isBranchName(mixed $branch): bool
    {
        return is_string($branch)
            && preg_match('#^[A-Za-z0-9._/\-]+$#', $branch) === 1
            && ! str_starts_with($branch, '-')
            && ! str_contains($branch, '..');
    }

    private function target(): ?DeployTarget
    {
        $given = $this->argument('target');

        if (is_string($given) && $given !== '') {
            $target = DeployTarget::tryFrom($given);

            if (! $target instanceof DeployTarget) {
                $this->components->error(sprintf(
                    'The target is one of %s, not "%s".',
                    implode(', ', DeployTarget::values()),
                    $given,
                ));
            }

            return $target;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Name a target: '.implode(', ', DeployTarget::values()).'.');

            return null;
        }

        return DeployTarget::from((string) select(
            label: 'Where should the landing be deployed?',
            options: DeployTarget::options(),
        ));
    }

    private function fileName(): ?string
    {
        $file = $this->option('file');
        $file = is_string($file) && $file !== '' ? $file : 'deploy-landing.yml';

        if (! str_ends_with($file, '.yml') && ! str_ends_with($file, '.yaml')) {
            $file .= '.yml';
        }

        if (preg_match('#^[A-Za-z0-9._\-]+$#', $file) !== 1) {
            $this->components->error(sprintf('"%s" is not a workflow file name.', $file));

            return null;
        }

        return $file;
    }

    /**
     * Defaults to the application's name, so two applications deploying from
     * one Cloudflare account do not collide on "landing".
     */
    private function project(): string
    {
        $given = $this->option('project');

        if (is_string($given) && $given !== '') {
            return Str::slug($given);
        }

        $name = config('app.name');

        return Str::slug((is_string($name) && $name !== 'Laravel' ? $name : basename($this->laravel->basePath())).'-landing');
    }

    /**
     * @return list<string>
     */
    private function checklist(DeployTarget $target, string $path): array
    {
        $steps = [];

        $steps[] = 'Add the repository variable LANDING_URL (Settings → Secrets and variables → Actions → Variables) with the landing\'s public URL. The build passes it to Astro as --site, and stops without it.';

        if ($target->secrets() !== []) {
            $steps[] = 'Add these repository secrets (Settings → Secrets and variables → Actions): '.implode(', ', $target->secrets()).'.';
        }

        $steps[] = $target->note();

        $steps[] = sprintf("Keep the app's own workflows off landing-only changes: add `paths-ignore: ['%s/**']` to their push triggers.", $path);

        return $steps;
    }
}
