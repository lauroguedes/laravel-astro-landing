## Laravel Astro Landing

This application keeps an Astro landing page in its own directory (`config('landing.path')`,
`landing/` by default). The landing is a **separate static site**: it has its own
`package.json`, lockfile, `node_modules` and build, and it deploys somewhere the
Laravel application does not, through its own GitHub Actions workflow.

### Keep the two projects apart

- Never import anything from `resources/js`, `resources/css`, or the application's
  `node_modules` into the landing, and never import the landing from the application.
  They are built by different tools and deployed to different servers.
- Install landing dependencies inside the landing directory with the landing's own
  package manager (its lockfile decides), never from the application root.
- Landing content is Astro: `.astro` pages and components under `src/pages` and
  `src/components`, Markdown or content collections for copy, files in `public/`
  served as-is. Do not create Blade views, routes or controllers for landing pages.
- The landing talks to the application only over HTTP — links to the app's URLs and,
  at build time, `fetch()` calls to public API endpoints. Read those URLs from Astro
  environment variables (`astro:env` or `import.meta.env`), never hardcode them.

### Commands

- `{{ $assist->artisanCommand('landing:dev') }}` — the Astro dev server (http://localhost:4321).
  On Laravel 13 `composer run dev` already starts it as the `landing` process.
- `{{ $assist->artisanCommand('landing:build') }}` — builds into the landing's `dist/`,
  exactly as the deploy workflow does. Run it after changing the landing to prove it builds.
- `{{ $assist->artisanCommand('landing:workflow') }}` — writes a deploy workflow. Pass a
  target (`cloudflare-pages`, `netlify`, `vercel`, `github-pages`, `ssh`); use `--file=`
  to deploy the same landing to a second place.
- `{{ $assist->artisanCommand('landing:install') }}` — creates a landing. It asks questions,
  so in an unattended run pass `--no-interaction` with `--template=`, `--pm=` and `--deploy=`.

### Adding to the landing

@boostsnippet('Add an Astro integration from the application root', 'bash')
cd landing && npx astro add sitemap --yes
@endboostsnippet

Use the package manager the landing uses (`bunx`, `pnpm`, `yarn` or `npx`). Prefer
`astro add` over editing `astro.config.mjs` by hand for official integrations.

### Deployment

The deploy workflow in `.github/workflows/` only runs on changes under the landing
directory. Do not merge it into the application's workflows, and keep the
application's workflows from running on landing-only changes with
`paths-ignore: ['landing/**']`. Secrets the workflow needs are repository secrets —
never commit them to the landing's `.env`.

For templates, themes, deploy targets and configuration, use the `astro-landing-development` skill.
