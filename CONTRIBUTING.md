# Contributing

## Running the suite

```bash
composer test
```

That runs Pint, Rector, PHPStan at level 8, 100% type coverage and the tests.
All of them gate CI.

The suite never runs `create-astro`, a package manager or a network request:
processes are faked with `Process::fake()` and the themes catalog with
`Http::fake()` against fixtures in `tests/Fixtures`. Before a release, check the
real thing end to end in a fresh application:

```bash
laravel new demo && cd demo
composer config repositories.astro path ../laravel-astro-landing
composer require lauroguedes/laravel-astro-landing:@dev --dev
php artisan landing:install
php artisan landing:build
```

## Rules that are not style

**No shell.** Every process gets an argument list, never a string. Templates,
paths and integration names come from prompts and from a web page; a string
would hand them to `sh`. An arch test enforces it.

**Ask nothing that can be inferred, and give every question a flag.** A question
that blocks a script is a broken install.

**Never overwrite what the application already has** without `--force`, and only
ever append to files the application owns (`.gitignore`, `app.css`).

**Workflow stubs must parse for every target and package manager.**
`WorkflowCommandTest` renders the whole matrix; CI also runs actionlint on it.

## Style

Match what is there. Docblocks explain *why*, not what the signature already
says.
