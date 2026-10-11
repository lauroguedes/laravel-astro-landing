# Changelog

All notable changes to `laravel-astro-landing` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.1.0 - 2026-10-11

### Added

- `LANDING_URL` (`config('landing.url')`) tells the application where visitors
  reach the landing. `landing:install` writes the dev server's address to `.env`
  and `.env.example`.
- `landing:install` asks whether to send the application's home page to the
  landing (`--redirect-root`). It rewrites the home route in `routes/web.php` to
  redirect to `LANDING_URL`, in plain Laravel that keeps working in production
  without the package, and keeps the old page wherever `LANDING_URL` is not set.
- `landing:install` asks where visitors will reach the landing. Under a path of
  the application's domain (`--base=/lp`) it sets `base` in the landing's
  `astro.config`.
- `docs/domains.md`: separate domains, a landing under a path (Nginx, Cloudflare
  Worker, or served from the application's `public/`), and an application under
  paths of the landing's domain.

### Upgrading

Run `php artisan landing:install` again only for a new landing. For an existing
one, add `'url' => env('LANDING_URL'),` to `config/landing.php` (the installer
does this for you when it finds the key missing) and `LANDING_URL` to `.env`.
Without the key in the application's own file, production, which runs without
this package, reads `config('landing.url')` as null.

## 1.0.1 - 2026-10-11

### Fixed

- `landing:dev` (and so `composer run dev`) no longer leaves the Astro dev
  server running on its own. Astro 7 moves the server into the background when
  it detects an AI agent, which includes running `composer run dev` from an
  agent's terminal; the script then returned at once and the server kept port
  4321. `landing:dev` now sets `ASTRO_DEV_BACKGROUND=0`.
- Stopping `landing:dev` stops the Astro server too. npm does not forward
  SIGTERM to the script it runs, so the signal now goes to the whole process
  tree.

## 1.0.0 - 2026-10-11

First release.

### Added

- `landing:install` creates an Astro landing inside the application from an
  empty template, an official example, a GitHub/GitLab/Bitbucket repository or a
  free theme from astro.build/themes; installs it with npm, pnpm, Yarn or Bun;
  adds integrations; ignores its build output; keeps it out of the application's
  Tailwind build; and optionally writes its deploy workflow.
- `landing:workflow` writes GitHub Actions workflows for Cloudflare Pages, Netlify,
  Vercel, GitHub Pages and SSH/rsync, filtered to the landing's directory.
- `landing:dev` and `landing:build`, which install missing dependencies first.
- The landing joins `composer run dev` on Laravel 13.16+ through `DevCommands`.
- Laravel Boost guidelines and the `astro-landing-development` skill.
