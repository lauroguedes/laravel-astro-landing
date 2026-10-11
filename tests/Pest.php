<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use LauroGuedes\AstroLanding\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit', 'Feature', 'Arch');

/**
 * Fakes every external process the way the real ones would leave the disk.
 *
 * create-astro is what makes package.json exist, and the installer checks for
 * it afterwards, so a fake that only returned success would describe a run in
 * which create-astro reported success and wrote nothing, which the installer
 * correctly treats as a failure. This writes what create-astro would have.
 *
 * @param  array<string, int>  $exitCodes  keyed by a fragment of the command
 * @param  list<string>  $templateFiles  extra files the template ships, such as its author's lockfile
 */
function fakeProcesses(array $exitCodes = [], array $templateFiles = []): void
{
    Process::fake(function (PendingProcess $process) use ($exitCodes, $templateFiles) {
        $command = commandLine($process);

        foreach ($exitCodes as $fragment => $code) {
            if (str_contains($command, $fragment)) {
                return Process::result(output: '', errorOutput: 'failed', exitCode: $code);
            }
        }

        if (str_contains($command, 'create-astro') || str_contains($command, 'create astro')) {
            $arguments = is_array($process->command) ? $process->command : [];
            $index = array_search('--template', $arguments, true);
            $directory = $arguments[$index - 1] ?? 'landing';

            $absolute = $process->path.'/'.$directory;
            @mkdir($absolute, recursive: true);
            file_put_contents($absolute.'/package.json', '{"name":"landing","scripts":{"dev":"astro dev","build":"astro build"}}');

            foreach ($templateFiles as $file) {
                file_put_contents($absolute.'/'.$file, str_starts_with($file, 'astro.config')
                    ? "import { defineConfig } from 'astro/config';\n\nexport default defineConfig({\n  site: 'https://example.com',\n});\n"
                    : '');
            }
        }

        return Process::result(output: 'ok');
    });
}

function commandLine(PendingProcess $process): string
{
    return is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;
}
