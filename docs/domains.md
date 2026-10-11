# Domains and routes

The landing and the application are two sites. Where each one is served decides
which URL visitors type, how the two link to each other, and whether anything
has to sit in front of them. There are three ways to arrange them.

| Setup | Landing | Application | Needs in front |
|---|---|---|---|
| [Separate domains](#separate-domains-recommended) (recommended) | `example.com` | `app.example.com` | nothing |
| [Landing under a path](#landing-under-a-path-of-the-applications-domain) | `example.com/lp/` | `example.com` | one proxy rule |
| [Application under paths](#application-under-paths-of-the-landings-domain) | `example.com` | `example.com/login`, `/dashboard`, … | a rule per route |

Both sides know about each other through one variable each:

- **`LANDING_URL`** in the application's `.env`, read as `config('landing.url')`:
  where visitors reach the landing.
- **`PUBLIC_APP_URL`** in the landing's environment: where the application is
  (see [linking.md](linking.md)).

## Separate domains (recommended)

```dotenv
# The application's .env in production
APP_URL=https://app.example.com
LANDING_URL=https://example.com
```

Point `example.com` at the landing's host (a custom domain on Cloudflare Pages,
Netlify, Vercel or GitHub Pages) and `app.example.com` at the application's
server. Nothing routes between them, nothing has to know the other's paths, and
each deploys without touching the other. This is what `landing:install` sets up
when you answer *On its own domain or subdomain*.

If the application needs its session on both (to show *Dashboard* instead of
*Log in* from a script on the landing, for instance), share the cookie with
`SESSION_DOMAIN=.example.com`.

## Landing under a path of the application's domain

```bash
php artisan landing:install --base=/lp
```

The installer sets `base: '/lp'` in the landing's `astro.config`, so every link
and asset Astro builds starts with `/lp/`. The files themselves still sit at the
root of `dist/`, so whatever serves `/lp/` has to strip the prefix on the way to
the landing's host.

```dotenv
APP_URL=https://example.com
LANDING_URL=https://example.com/lp/
```

`base` covers what Astro generates: built assets, `<Image>`, and links made from
`import.meta.env.BASE_URL`. A path the template wrote by hand, such as
`<link rel="icon" href="/favicon.svg">` in Astro's own starters, still points at
the root of the domain. Search the landing for `href="/` and `src="/` and prefix
them:

```astro
<link rel="icon" href={`${import.meta.env.BASE_URL}/favicon.svg`} />
```

### Nginx (Laravel Forge and other servers)

Add this to the application's site configuration, above the `location /` block:

```nginx
location = /lp {
    return 301 /lp/;
}

location /lp/ {
    # The trailing slash on proxy_pass removes /lp/ from the path.
    proxy_pass https://your-landing.pages.dev/;
    proxy_set_header Host your-landing.pages.dev;
    proxy_ssl_server_name on;
}
```

### Cloudflare Worker

With the application's domain on Cloudflare, add a Worker on the route
`example.com/lp*`:

```js
const LANDING = 'your-landing.pages.dev';
const BASE = '/lp';

export default {
  async fetch(request) {
    const url = new URL(request.url);

    if (url.pathname === BASE) {
      return Response.redirect(`${url.origin}${BASE}/`, 301);
    }

    url.hostname = LANDING;
    url.pathname = url.pathname.slice(BASE.length) || '/';

    return fetch(new Request(url, request));
  },
};
```

### Without a proxy

The application's server can serve the built files itself: build the landing
during the application's deploy and copy it into `public/lp`.

```bash
npm ci --prefix landing
npm run build --prefix landing
rm -rf public/lp && cp -R landing/dist public/lp
```

Add `/public/lp` to `.gitignore`. Nginx serves the files before Laravel sees the
request. The cost is the reason this package exists: the landing now deploys
with the application, on the application's schedule, and its own workflow is no
longer needed.

## Application under paths of the landing's domain

The landing owns `example.com/` and the application answers only on its own
paths. Everything in front has to know every one of the application's paths:

```js
// Cloudflare Worker on the route example.com/*
const APP = 'origin.example.com';
const APP_PATHS = ['/login', '/register', '/logout', '/dashboard', '/settings', '/api/', '/build/', '/livewire/', '/up'];

export default {
  async fetch(request) {
    const url = new URL(request.url);

    if (APP_PATHS.some((path) => url.pathname === path || url.pathname.startsWith(path.endsWith('/') ? path : `${path}/`))) {
      url.hostname = APP;
    } else {
      url.hostname = 'your-landing.pages.dev';
    }

    return fetch(new Request(url, request));
  },
};
```

```dotenv
APP_URL=https://example.com
LANDING_URL=https://example.com
```

It works, but every route the application gains has to be added here as well,
and one that is forgotten shows the landing's 404. Prefer a subdomain unless the
single domain is a hard requirement.

## The application's home page

`landing:install` asks whether to send the application's `/` to the landing
(`--redirect-root` without the prompt). It rewrites the home route in
`routes/web.php` to this:

```php
Route::get('/', function () {
    // The public site is the Astro landing (config/landing.php). Without a
    // LANDING_URL, this keeps serving the page it served before.
    return config('landing.url')
        ? redirect()->away(config('landing.url'))
        : view('welcome');
})->name('home');
```

It is plain Laravel reading the application's own config file, with nothing from
this package, so it keeps working in production, where the package is not
installed. Wherever `LANDING_URL` is set, `/` redirects to the landing; wherever
it is not, the page it served before is still there.

Only the home routes Laravel's skeleton and starter kits ship are rewritten. A
home route of your own is left alone, and the installer prints the line to
return from it instead.

In the [application under paths](#application-under-paths-of-the-landings-domain)
setup, `/` never reaches the application, so leave the home route as it is.

## Linking back to the landing

```blade
<a href="{{ config('landing.url') ?? url('/') }}">{{ config('app.name') }}</a>
```

## Local development

The installer writes `LANDING_URL=http://localhost:4321` (or
`http://localhost:4321/lp/` with `--base`) to `.env` and `.env.example`, which is
where `landing:dev` serves the landing. The application stays wherever it runs
locally (`php artisan serve`, Herd, Valet), so the two are on different origins in
development whatever the setup in production.
