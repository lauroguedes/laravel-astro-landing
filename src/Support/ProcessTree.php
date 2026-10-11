<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding\Support;

use Illuminate\Support\Facades\Process;

/**
 * Signals a process and everything it started.
 *
 * `npm run dev` does not pass a SIGTERM on to the script it started.
 * Signalling only the process that artisan launched lets npm exit and leaves
 * Astro running under PID 1, holding its port, so the next `composer run dev`
 * finds 4321 taken. Signalling the whole tree, deepest first, stops the server
 * itself.
 */
final class ProcessTree
{
    public static function signal(int $pid, int $signal): void
    {
        $listing = Process::run(['ps', '-A', '-o', 'pid=,ppid=']);

        $pids = $listing->successful()
            ? [...self::descendants($pid, $listing->output()), $pid]
            : [$pid];

        Process::run(['kill', '-'.$signal, ...array_map(strval(...), $pids)]);
    }

    /**
     * Every process below $pid in a `ps -o pid=,ppid=` listing, children after
     * their own descendants, so the deepest is signalled first.
     *
     * @return list<int>
     */
    public static function descendants(int $pid, string $listing): array
    {
        $children = [];

        foreach (preg_split('/\R/', trim($listing)) ?: [] as $line) {
            if (preg_match('/^\s*(\d+)\s+(\d+)\s*$/', $line, $match) === 1) {
                $children[(int) $match[2]][] = (int) $match[1];
            }
        }

        $seen = [$pid => true];

        return self::walk($pid, $children, $seen);
    }

    /**
     * @param  array<int, list<int>>  $children
     * @param  array<int, true>  $seen  guards against a listing that loops
     * @return list<int>
     */
    private static function walk(int $parent, array $children, array &$seen): array
    {
        $found = [];

        foreach ($children[$parent] ?? [] as $child) {
            if (isset($seen[$child])) {
                continue;
            }

            $seen[$child] = true;
            $found = [...$found, ...self::walk($child, $children, $seen), $child];
        }

        return $found;
    }
}
