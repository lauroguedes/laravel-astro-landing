# Deployment

`php artisan landing:workflow <target>` writes `.github/workflows/deploy-landing.yml`.
Every target's workflow:

- runs on a push to the branch (`--branch=`, default `main`) **only when files
  under the landing, or the workflow itself, changed**, and on manual dispatch;
- installs from the landing's lockfile with its package manager;
- builds with the landing's `build` script;
- cancels an older run of the same workflow when a newer one starts.

Add the secrets in **Settings → Secrets and variables → Actions**.

## Cloudflare Pages

Secrets: `CLOUDFLARE_API_TOKEN` (with *Cloudflare Pages: Edit*),
`CLOUDFLARE_ACCOUNT_ID`.

Create the Pages project once, in the dashboard or with
`npx wrangler pages project create <name>`. The name written into the workflow
is the application's name followed by `-landing`; pass `--project=` to choose
another.

## Netlify

Secrets: `NETLIFY_AUTH_TOKEN` (a personal access token), `NETLIFY_SITE_ID`
(Site configuration → Site details). Create the site once without connecting a
repository — the workflow deploys the built files, Netlify does not build.

## Vercel

Secrets: `VERCEL_TOKEN`, `VERCEL_ORG_ID`, `VERCEL_PROJECT_ID`. Run `npx vercel link`
in the landing directory once; the two IDs are in `.vercel/project.json`.

## GitHub Pages

No secrets. Set **Settings → Pages → Source** to *GitHub Actions*. For a project
page (`user.github.io/repo`) set `base: '/repo'` in `astro.config.mjs`.

## Your own server (SSH)

Secrets: `LANDING_SSH_HOST`, `LANDING_SSH_USER`, `LANDING_SSH_KEY` (a private key
whose public half is in that user's `authorized_keys`), `LANDING_SSH_PATH` (the
directory the web server serves). `LANDING_SSH_PORT` is optional.

The workflow `rsync`s `dist/` with `--delete`, so `LANDING_SSH_PATH` must be a
directory that holds nothing but the landing. On Laravel Forge, create a static
site and point the path at its web directory.

## More than one target

Each target is its own file, so the same landing can go to two places:

```bash
php artisan landing:workflow cloudflare-pages
php artisan landing:workflow ssh --file=deploy-landing-mirror
```

## Keeping the application's pipeline separate

The landing's workflow ignores application changes; make the application's
workflows ignore landing changes:

```yaml
on:
  push:
    branches: [main]
    paths-ignore: ['landing/**']
```

## Changing the workflows

```bash
php artisan vendor:publish --tag=landing-stubs
```

Edit the files in `stubs/landing/workflows/`. `landing:workflow` reads them in
preference to the package's own. The placeholders are `{{ name }}`, `{{ path }}`,
`{{ branch }}`, `{{ workflow_file }}`, `{{ setup }}`, `{{ build }}` and
`{{ project }}`.
