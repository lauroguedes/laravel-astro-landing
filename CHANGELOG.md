# Changelog

All notable changes to `laravel-astro-landing` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
