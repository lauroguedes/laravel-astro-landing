<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use LauroGuedes\AstroLanding\Support\ProcessTree;

/*
 * artisan (100) runs npm (200), which runs sh (300), which runs astro (400).
 * 500 is an unrelated process with a child of its own.
 */
const LISTING = <<<'PS'
      1     0
    100     1
    200   100
    300   200
    400   300
    401   300
    500     1
    501   500
PS;

it('lists every descendant, deepest first', function (): void {
    expect(ProcessTree::descendants(200, LISTING))->toBe([400, 401, 300]);
});

it('lists nothing for a process without children', function (): void {
    expect(ProcessTree::descendants(400, LISTING))->toBe([])
        ->and(ProcessTree::descendants(999, LISTING))->toBe([]);
});

it('survives a listing that loops', function (): void {
    expect(ProcessTree::descendants(10, "20 10\n10 20\n"))->toBe([20]);
});

it('signals the tree with one kill, the process itself last', function (): void {
    Process::fake(fn (PendingProcess $process) => $process->command[0] === 'ps'
        ? Process::result(output: LISTING)
        : Process::result());

    ProcessTree::signal(200, 15);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['kill', '-15', '400', '401', '300', '200']);
});

it('still signals the process when ps is unavailable', function (): void {
    Process::fake(fn (PendingProcess $process) => $process->command[0] === 'ps'
        ? Process::result(exitCode: 1)
        : Process::result());

    ProcessTree::signal(200, 2);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['kill', '-2', '200']);
});
