# lauroguedes/astro-on-laravel — agent guide

A Laravel package (not an application) that puts an Astro landing page inside a
Laravel application: `landing:install` scaffolds it with create-astro,
`landing:workflow` writes a GitHub Actions deploy workflow, `landing:dev` and
`landing:build` run it, and on Laravel 13 the landing joins `composer run dev`
through `Illuminate\Foundation\DevCommands`.

## Layout

| Path | What |
|---|---|
| `src/AstroOnLaravelServiceProvider.php` | Console-only provider: config, publishing, commands, DevCommands registration |
| `src/Console/` | The four commands; `Concerns/RunsLandingScripts` runs package.json scripts |
| `src/Support/Landing.php` | The only reader of `config('landing.*')` |
| `src/Support/PackageManager.php` | Every npm/pnpm/yarn/bun command, as argument lists |
| `src/Support/TemplateResolver.php` | User input → create-astro `--template` value |
| `src/Support/ThemeCatalog.php` | Reads astro.build/themes HTML (no API exists) |
| `src/Support/DeployTarget.php`, `Workflow.php` | Workflow targets and rendering |
| `stubs/workflows/*.yml.stub` | One workflow per target; publishable as `landing-stubs` |
| `config/landing.php` | Published as `landing-config` |
| `resources/boost/` | Guidelines and skill shipped to applications using Laravel Boost |
| `tests/` | Pest + Testbench. Each test gets its own temporary application root (`TestCase::$root`) |

## Commands

```bash
composer test              # everything CI runs
vendor/bin/pest            # tests only
vendor/bin/pint            # fix style
vendor/bin/phpstan analyse # level 8
```

## Conventions

- PHP 8.3 is the floor: no `new Foo()->bar()` without parentheses, no 8.4-only
  functions. Laravel 12 and 13 are both supported; guard Laravel 13-only APIs
  with `class_exists()` (see `DevCommands`).
- `declare(strict_types=1)` everywhere; classes are `final` unless there is a reason.
- Processes: `Illuminate\Support\Facades\Process` with array commands only. Never
  `exec`, `shell_exec`, `system`, `passthru`, `proc_open` (arch test).
- Prompts: `Laravel\Prompts` functions. Every prompt has an equivalent option, and
  non-interactive runs take a documented default.
- Output: `$this->components->twoColumnDetail()` for each file touched, a
  `bulletList()` checklist at the end.
- Errors the user can act on are `AstroOnLaravelException` subclasses with named
  constructors carrying the full message.
- Tests fake every process (`fakeProcesses()` in `tests/Pest.php`) and every HTTP
  call (`Http::fake()` + fixtures). Never let a test hit the network or npm.
- A new deploy target needs: a `DeployTarget` case with `label()`, `secrets()`,
  `note()`; a stub using the documented placeholders; a row in README and
  `docs/deployment.md`; the Boost skill's table. The matrix test covers the rest.
- Keep `README.md`, `docs/`, `resources/boost/` and `CHANGELOG.md` in step with
  behaviour changes.
