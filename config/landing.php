<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Landing directory
    |--------------------------------------------------------------------------
    |
    | Where the Astro project lives, relative to the application root. The
    | landing is a standalone project: it has its own package.json, its own
    | lockfile and its own build, and nothing in the Laravel app imports it.
    |
    */

    'path' => env('LANDING_PATH', 'landing'),

    /*
    |--------------------------------------------------------------------------
    | Landing URL
    |--------------------------------------------------------------------------
    |
    | Where visitors reach the landing in this environment: its own domain
    | (https://example.com) or a path of the application's domain
    | (https://example.com/lp). The application uses it to link back to the
    | landing, and landing:install can point the application's home page at
    | it. Null means the application does not know about a landing, and that
    | home page keeps serving what it served before.
    |
    | This file belongs to the application, so reading it needs nothing from
    | this package: it is safe in production, where the package is not
    | installed.
    |
    */

    'url' => env('LANDING_URL'),

    /*
    |--------------------------------------------------------------------------
    | Package manager
    |--------------------------------------------------------------------------
    |
    | npm, pnpm, yarn or bun. Null detects it from the lockfile inside the
    | landing directory, then from the one in the application root, and falls
    | back to npm.
    |
    */

    'package_manager' => env('LANDING_PACKAGE_MANAGER'),

    /*
    |--------------------------------------------------------------------------
    | composer run dev
    |--------------------------------------------------------------------------
    |
    | On Laravel 13.16 and later, `php artisan dev` (which `composer run dev` calls) runs
    | every process registered with Illuminate\Foundation\DevCommands. When
    | this is on and the landing exists, the Astro dev server is one of them,
    | under the name below. Turn it off to run `php artisan landing:dev` by
    | hand instead.
    |
    */

    'dev' => [
        'enabled' => (bool) env('LANDING_DEV', true),
        'name' => 'landing',
    ],

];
