<?php

declare(strict_types=1);

namespace LauroGuedes\AstroLanding\Support;

/**
 * Sets `base` in a landing's astro.config, for a landing served under a path
 * of the application's domain.
 *
 * Templates write their config in many shapes, so this edits only the one it
 * can be sure of: the object literal passed to defineConfig(). Anything else,
 * including a config that already sets `base`, is left alone and reported, so
 * the person can add the line where it belongs.
 */
final class AstroConfig
{
    /**
     * @var list<string>
     */
    private const array FILES = [
        'astro.config.mjs',
        'astro.config.ts',
        'astro.config.mts',
        'astro.config.js',
        'astro.config.cjs',
    ];

    public static function find(string $directory): ?string
    {
        foreach (self::FILES as $file) {
            if (is_file($directory.'/'.$file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * The config with `base` added, or null when it cannot be added safely.
     */
    public static function withBase(string $contents, string $base): ?string
    {
        if (preg_match('/(^|[{,\s])[\'"]?base[\'"]?\s*:/m', $contents) === 1) {
            return null;
        }

        $line = "\n  base: '".$base."',";

        $updated = preg_replace_callback(
            '/defineConfig\(\s*\{(?<empty>\s*\})?/',
            static fn (array $match): string => ($match['empty'] ?? '') !== ''
                ? 'defineConfig({'.$line."\n}"
                : $match[0].$line,
            $contents,
            1,
            $count,
        );

        return $count === 1 && is_string($updated) ? $updated : null;
    }

    /**
     * "/lp", from "lp", "/lp/" or " /lp ". Null for "/" or anything that is
     * not a plain path.
     */
    public static function normaliseBase(string $base): ?string
    {
        $base = '/'.trim(trim($base), '/');

        if ($base === '/' || preg_match('#^/[A-Za-z0-9._~\-]+(/[A-Za-z0-9._~\-]+)*$#', $base) !== 1 || preg_match('#(^|/)\.\.?(/|$)#', $base) === 1) {
            return null;
        }

        return $base;
    }
}
