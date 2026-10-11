<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use LauroGuedes\AstroOnLaravel\Exceptions\AstroOnLaravelException;

arch('nothing is left behind from debugging')
    ->expect(['dd', 'dump', 'var_dump', 'ray', 'die', 'exit'])
    ->not->toBeUsed();

arch('everything declares strict types')
    ->expect('LauroGuedes\AstroOnLaravel')
    ->toUseStrictTypes();

arch('exceptions extend the package base')
    ->expect('LauroGuedes\AstroOnLaravel\Exceptions')
    ->toExtend(AstroOnLaravelException::class);

arch('commands are final console commands')
    ->expect('LauroGuedes\AstroOnLaravel\Console')
    ->classes()
    ->toBeFinal()
    ->toExtend(Command::class)
    ->ignoring('LauroGuedes\\AstroOnLaravel\\Console\\Concerns');

/**
 * Commands are passed to processes as argument lists. A shell string would
 * hand a template name or a directory to sh, and both come from a prompt.
 */
arch('nothing reaches a shell')
    ->expect('LauroGuedes\AstroOnLaravel')
    ->not->toUse(['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen']);
