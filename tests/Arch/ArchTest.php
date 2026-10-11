<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use LauroGuedes\AstroLanding\Exceptions\AstroLandingException;

arch('nothing is left behind from debugging')
    ->expect(['dd', 'dump', 'var_dump', 'ray', 'die', 'exit'])
    ->not->toBeUsed();

arch('everything declares strict types')
    ->expect('LauroGuedes\AstroLanding')
    ->toUseStrictTypes();

arch('exceptions extend the package base')
    ->expect('LauroGuedes\AstroLanding\Exceptions')
    ->toExtend(AstroLandingException::class);

arch('commands are final console commands')
    ->expect('LauroGuedes\AstroLanding\Console')
    ->classes()
    ->toBeFinal()
    ->toExtend(Command::class)
    ->ignoring('LauroGuedes\\AstroLanding\\Console\\Concerns');

/**
 * Commands are passed to processes as argument lists. A shell string would
 * hand a template name or a directory to sh, and both come from a prompt.
 */
arch('nothing reaches a shell')
    ->expect('LauroGuedes\AstroLanding')
    ->not->toUse(['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen']);
