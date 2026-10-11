# Astro on Laravel

[![Tests](https://github.com/lauroguedes/astro-on-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/lauroguedes/astro-on-laravel/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/lauroguedes/astro-on-laravel.svg)](https://packagist.org/packages/lauroguedes/astro-on-laravel)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat)](LICENSE.md)

Keep an [Astro](https://astro.build) landing page in the same repository as your
Laravel application, and deploy it somewhere else.

A product's marketing site and the product itself change for different reasons
and belong on different servers: the landing is static, cheap to host on a CDN
and edited by whoever writes the copy; the application needs PHP, a database and
a deploy that runs migrations. Keeping them in one repository keeps them in sync —
same pull requests, same issues, the landing can link to the routes that actually
exist — as long as nothing couples them.

This package is the decoupling, set up in one command:

- **`landing:install`** creates the Astro project in `landing/` from an empty
  template, an official example, any template repository, or a free theme from
  the [Astro themes catalog](https://astro.build/themes/).
- **`composer run dev`** starts the landing next to the application (Laravel 13).
- **`landing:workflow`** writes a GitHub Actions workflow that builds and deploys
  the landing to Cloudflare Pages, Netlify, Vercel, GitHub Pages or your own
  server — and runs only when the landing changed.

## Installation

```bash
composer require lauroguedes/astro-on-laravel --dev
php artisan landing:install
```

It is a development dependency: nothing in it runs on the server that serves
your application. The landing is deployed by GitHub Actions, not by PHP.

`landing:install` asks four things — where the landing lives, what it starts
from, which package manager to use and where it deploys — then runs
`create-astro`, installs the dependencies and wires the rest:

```
 ┌ What should the landing start from? ─────────────────────────┐
 │ › ● An empty project — Astro's minimal template              │
 │   ○ An official Astro example                                │
 │   ○ A template repository on GitHub, GitLab or Bitbucket     │
 │   ○ A free theme from astro.build/themes                     │
 └──────────────────────────────────────────────────────────────┘
```

| What it does | Why |
|---|---|
| Creates `landing/` with `create-astro` | Its own `package.json`, lockfile and build |
| Publishes `config/landing.php` | Where the landing is, for every other command |
| Adds `landing/node_modules`, `dist`, `.astro` to `.gitignore` | Not every template ignores them |
| Adds `@source not '../../landing';` to `resources/css/app.css` | Tailwind v4 scans the whole project and would put the landing's classes in the application's CSS |
| Writes `.github/workflows/deploy-landing.yml` | If you chose a deploy target |

Nothing that exists is overwritten without `--force`, and `.gitignore` and
`app.css` are only ever appended to.

### Starting from a template

```bash
php artisan landing:install --template=empty                 # Astro's minimal template
php artisan landing:install --template=blog                  # an official example
php artisan landing:install --template=onwidget/astrowind    # any repository
php artisan landing:install --template=https://github.com/owner/repo/tree/main/apps/site
php artisan landing:install --template=gitlab:owner/repo
php artisan landing:install --template=theme:landpack        # a theme from astro.build/themes
```

`theme:<slug>` (or the theme's `astro.build/themes/details/…` URL) is looked up
in the catalog and installed from the repository the theme links to. Paid themes
have no public repository; the installer says so and links to where to buy one,
which you then install with `--template=owner/repo`.

### Unattended

Every question has a flag. With `--no-interaction` the defaults are `landing/`,
the empty template, the application's package manager and no workflow.

```bash
php artisan landing:install --no-interaction \
  --path=landing --template=basics --pm=bun \
  --add=tailwind --add=sitemap \
  --deploy=cloudflare-pages --branch=main
```

## Running it

On Laravel 13, `composer run dev` (which runs `php artisan dev`) starts the
landing as one more process, at http://localhost:4321:

```
 server  php artisan serve
 queue   php artisan queue:listen --tries=1 --timeout=0
 vite    npm run dev
 landing php artisan landing:dev
```

On its own, or on Laravel 12:

```bash
php artisan landing:dev     # astro dev
php artisan landing:build   # astro build, into landing/dist
```

Both install the landing's dependencies first when `node_modules` is missing, so
a fresh clone needs nothing beyond the application's own setup. Set
`LANDING_DEV=false` to keep the landing out of `composer run dev`.

## Deploying it

```bash
php artisan landing:workflow cloudflare-pages
```

| Target | Repository secrets |
|---|---|
| `cloudflare-pages` | `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID` |
| `netlify` | `NETLIFY_AUTH_TOKEN`, `NETLIFY_SITE_ID` |
| `vercel` | `VERCEL_TOKEN`, `VERCEL_ORG_ID`, `VERCEL_PROJECT_ID` |
| `github-pages` | none |
| `ssh` | `LANDING_SSH_HOST`, `LANDING_SSH_USER`, `LANDING_SSH_KEY`, `LANDING_SSH_PATH` |

The workflow is filtered to `landing/**`, so a change to the application never
redeploys the landing. Do the reverse in your application's workflows:

```yaml
on:
  push:
    branches: [main]
    paths-ignore: ['landing/**']
```

Deploying to two places is two files:

```bash
php artisan landing:workflow cloudflare-pages
php artisan landing:workflow ssh --file=deploy-landing-mirror
```

To change every workflow this package writes, publish the stubs — they are read
from `stubs/landing/workflows` before the package's own:

```bash
php artisan vendor:publish --tag=landing-stubs
```

See [docs/deployment.md](docs/deployment.md) for each target in detail.

## Configuration

```php
// config/landing.php
'path' => env('LANDING_PATH', 'landing'),
'package_manager' => env('LANDING_PACKAGE_MANAGER'),   // null: the landing's lockfile decides
'dev' => [
    'enabled' => (bool) env('LANDING_DEV', true),      // part of composer run dev
    'name' => 'landing',
],
```

## Commands

| Command | What it does |
|---|---|
| `landing:install` | Create the Astro landing and wire it into the application |
| `landing:dev` | Run the Astro dev server |
| `landing:build` | Build the landing into its `dist/` |
| `landing:workflow` | Write a GitHub Actions workflow that deploys it |

## Working with an AI agent

If the application uses [Laravel Boost](https://laravel.com/docs/boost), this
package ships guidelines and an `astro-landing-development` skill. Run
`php artisan boost:install` and tick `lauroguedes/astro-on-laravel` when it asks
which third-party guidelines and skills to install; the choice is saved to
`boost.json`, so later unattended runs keep it. They tell an agent that the
landing is a separate static site, which commands build and run it, how it
reaches the application, and how it deploys.

## Requirements

- PHP 8.3+, Laravel 12 or 13
- Node.js 22.12+ (or Bun) for Astro

## Documentation

[docs/](docs/README.md) covers templates, linking the landing to the
application, and each deploy target.

## License

MIT. See [LICENSE.md](LICENSE.md).
