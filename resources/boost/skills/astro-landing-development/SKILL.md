---
name: astro-landing-development
description: Build, configure and deploy the Astro landing page that lives inside this Laravel application (lauroguedes/laravel-astro-landing): installing it from a template or theme, running it with composer run dev, sharing data with the Laravel app, and deploying it on its own with GitHub Actions.
---

# Astro landing development

## When to use this skill

Use it when creating or changing the landing page, adding an Astro integration,
pointing the landing at the Laravel application's URLs or API, or setting up or
changing where the landing deploys.

Finish every change to the landing with `php artisan landing:build`. It runs the
same build the deploy workflow runs, so a page that builds here builds there.

## Where things are

| Thing | Where |
|---|---|
| The Astro project | `config('landing.path')`, default `landing/` |
| Its config | `landing/astro.config.mjs` (or `.ts`) |
| Pages | `landing/src/pages/*.astro`, one file per route |
| Static files | `landing/public/` |
| Build output | `landing/dist/` (git-ignored) |
| Deploy workflow | `.github/workflows/deploy-landing*.yml` |
| Package config | `config/landing.php` |

## Creating a landing

```bash
php artisan landing:install
```

It asks where the landing lives, what it starts from, which package manager to use
and where it deploys. Every question has a flag:

| Flag | Values |
|---|---|
| `--path=` | a directory in the application root, default `landing` |
| `--template=` | `empty`, an Astro example (`basics`, `blog`, `portfolio`, `starlight`, `with-tailwindcss`, …), `owner/repo`, `owner/repo/sub/dir#branch`, a GitHub/GitLab/Bitbucket URL, `gitlab:owner/repo`, or `theme:<slug>` from astro.build/themes |
| `--pm=` | `npm`, `pnpm`, `yarn`, `bun`; defaults to the application's |
| `--add=` | an integration for `astro add`, repeatable |
| `--deploy=` | `cloudflare-pages`, `netlify`, `vercel`, `github-pages`, `ssh`, `none` |
| `--base=` | serve the landing under this path of the application's domain, e.g. `/lp`; sets `base` in `astro.config` |
| `--redirect-root` | rewrite the home route in `routes/web.php` to redirect to `LANDING_URL` |
| `--skip-install` | scaffold without installing dependencies |

Only free themes can be installed: a paid theme has no public repository. Buy it,
then install from its repository with `--template=owner/repo`.

The installer also adds the landing's `node_modules`, `dist` and `.astro` to
`.gitignore` and adds `@source not '../../landing';` to `resources/css/app.css`,
so Tailwind in the application does not pick up the landing's classes.

## Domains and routes

The recommended setup gives each site its own address: the landing on
`example.com`, the application on `app.example.com`. The application knows where
the landing is from `LANDING_URL`, read as `config('landing.url')`. Link back to
the landing with it, never with a hardcoded URL:

```blade
<a href="{{ config('landing.url') ?? url('/') }}">{{ config('app.name') }}</a>
```

A landing under a path of the application's domain (`example.com/lp`) needs
`base: '/lp'` in `astro.config` and a proxy rule on the application's server;
`docs/domains.md` in the package has the Nginx and Cloudflare recipes. Never add
Laravel routes to serve landing pages.

The home route `landing:install` writes reads only `config('landing.url')`. Keep
it that way: this package is a development dependency, so application code that
references its classes breaks in production.

## Linking the landing to the application

The landing is static and deployed to another host, so it reaches the application
by URL. Declare the URLs in `astro.config.mjs` with `astro:env` and set them per
environment:

```js
import { defineConfig, envField } from 'astro/config';

export default defineConfig({
  site: process.env.LANDING_SITE_URL || 'https://example.com',
  env: {
    schema: {
      PUBLIC_APP_URL: envField.string({ context: 'client', access: 'public', default: 'http://localhost:8000' }),
      APP_API_URL: envField.string({ context: 'server', access: 'public', optional: true }),
    },
  },
});
```

```astro
---
import { PUBLIC_APP_URL } from 'astro:env/client';
---
<a href={`${PUBLIC_APP_URL}/register`}>Get started</a>
```

Data that changes (prices, plans, counts) comes from a public, unauthenticated
Laravel endpoint fetched **at build time** in the page frontmatter. No CORS setup is
needed for a build-time fetch. Keep a fallback for when the API cannot be reached,
or the deploy fails whenever the application is down.

Set the same variables in the deploy workflow's build step, from repository
variables:

```yaml
      - name: Build landing
        env:
          LANDING_SITE_URL: ${{ vars.LANDING_SITE_URL }}
          PUBLIC_APP_URL: ${{ vars.PUBLIC_APP_URL }}
        run: npm run build
```

If a landing page has to be rebuilt when application data changes, trigger the
workflow with `workflow_dispatch` (it is already enabled) from the application,
rather than making the page fetch client-side.

## Deploying

`php artisan landing:workflow <target>` writes `.github/workflows/deploy-landing.yml`.
It runs only when files under the landing change (or on manual dispatch), installs
with the landing's lockfile, builds, and deploys `dist/`.

| Target | Secrets |
|---|---|
| `cloudflare-pages` | `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID` |
| `netlify` | `NETLIFY_AUTH_TOKEN`, `NETLIFY_SITE_ID` |
| `vercel` | `VERCEL_TOKEN`, `VERCEL_ORG_ID`, `VERCEL_PROJECT_ID` |
| `github-pages` | none; set Pages' source to GitHub Actions |
| `ssh` | `LANDING_SSH_HOST`, `LANDING_SSH_USER`, `LANDING_SSH_KEY`, `LANDING_SSH_PATH`; optional `LANDING_SSH_PORT`, `LANDING_SSH_KNOWN_HOSTS` (pins the host key) |

To deploy to two places, write a second file:
`php artisan landing:workflow ssh --file=deploy-landing-mirror`.

To change what every generated workflow looks like, publish the stubs and edit them:
`php artisan vendor:publish --tag=landing-stubs` (they land in `stubs/landing/workflows`).

## Configuration

`config/landing.php`:

- `path` (`LANDING_PATH`): where the landing is.
- `url` (`LANDING_URL`): where visitors reach the landing in this environment; null when the application does not know.
- `package_manager` (`LANDING_PACKAGE_MANAGER`): forces one; otherwise the landing's lockfile decides.
- `dev.enabled` (`LANDING_DEV`): whether `composer run dev` starts the landing (Laravel 13.16+).
- `dev.name`: the process name shown by `php artisan dev`.

## Common mistakes

- Running `npm install` in the application root and expecting the landing to work:
  the landing has its own `node_modules`. `landing:dev` and `landing:build` install
  them when they are missing.
- Using Laravel's `@vite` assets, Blade components or `route()` in the landing. It is
  not rendered by Laravel; use full URLs from `PUBLIC_APP_URL`.
- Forgetting `site` in `astro.config.mjs`: canonical URLs and the sitemap are wrong
  without it.
- Deploying a GitHub Pages project page without `base` set to the repository name.
