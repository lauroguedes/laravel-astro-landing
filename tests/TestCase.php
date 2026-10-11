<?php

declare(strict_types=1);

namespace LauroGuedes\AstroOnLaravel\Tests;

use Illuminate\Filesystem\Filesystem;
use LauroGuedes\AstroOnLaravel\AstroOnLaravelServiceProvider;
use LauroGuedes\AstroOnLaravel\Support\Landing;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Every test gets an empty application root of its own.
     *
     * The installer writes into the root — config/, .gitignore, .github/, the
     * landing itself — and testbench's skeleton is shared by every test in the
     * process and outlives it. A file left there by one test changes what the
     * next one sees, which is the kind of failure that only CI notices.
     */
    protected string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/astro-on-laravel-'.bin2hex(random_bytes(6));

        (new Filesystem)->ensureDirectoryExists($this->root);

        $this->app->setBasePath($this->root);
        $this->app->forgetInstance(Landing::class);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [AstroOnLaravelServiceProvider::class];
    }

    /**
     * Write a file in the test's application root.
     */
    protected function file(string $path, string $contents = ''): string
    {
        $absolute = $this->root.'/'.$path;

        (new Filesystem)->ensureDirectoryExists(dirname($absolute));
        file_put_contents($absolute, $contents);

        return $absolute;
    }
}
