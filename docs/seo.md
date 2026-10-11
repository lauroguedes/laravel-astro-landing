# SEO

What search engines see comes from the landing's own code, so how much SEO a
landing has depends on the template it started from. This package does two
things about it, both in the deploy workflow: it builds the landing with the
right public URL, and it refuses to deploy a build that points anywhere else.

## The one URL everything hangs on

Astro builds every absolute URL from the `site` option: canonical links, Open
Graph and Twitter URLs, the sitemap, and the `Sitemap:` line in `robots.txt`.
Templates usually set it from an environment variable with a placeholder default,
and each template names the variable differently:

```js
// A typical theme
site: process.env.PUBLIC_SITE_URL || 'https://example.com',
```

Built without that variable, every canonical link says `https://example.com`,
which tells search engines to index that domain instead of yours.

## What the deploy workflow does

The generated workflow reads one repository variable, `LANDING_URL` (Settings →
Secrets and variables → Actions → **Variables**), the same name the application
uses:

```
LANDING_URL = https://example.com          # its own domain
LANDING_URL = https://example.com/lp/      # under a path, with --base=/lp
```

1. **It stops without it.** A build with no `LANDING_URL` fails at once with a
   message saying what to set, instead of publishing placeholder URLs.
2. **It passes the origin to Astro as `--site`.** A URL given on the command line
   wins over whatever `astro.config` says, so this works whatever variable the
   template reads. The path, if any, still comes from `base` in `astro.config`.
3. **It checks the build before deploying it:**
   - any HTML, XML or text file in `dist/` that still contains
     `https://example.com` fails the deploy, and the files are listed;
   - a canonical link on the home page that is not on `LANDING_URL`'s origin
     fails the deploy;
   - a home page without a canonical link is a warning, not a failure, so a
     template that has no SEO yet still deploys.

`--site` reaches Astro through the package manager's `build` script. If that
script runs something after `astro build` (`astro build && node scripts/x.mjs`),
the argument goes to the last command instead, and the check above fails the
deploy. Move `astro build` to the end of the script, or read `LANDING_URL` in
`astro.config` yourself:

```js
site: process.env.LANDING_URL || 'http://localhost:4321',
```

## What a landing should have

| | Where it comes from |
|---|---|
| A `<title>` and `<meta name="description">` per page | the layout's `<head>` |
| `<link rel="canonical">` | the layout, from `Astro.site` |
| Open Graph and Twitter tags, with an absolute image URL | the layout, from `Astro.site` |
| `sitemap-index.xml` | [`@astrojs/sitemap`](https://docs.astro.build/en/guides/integrations-guide/sitemap/) |
| `robots.txt` pointing at the sitemap | a file in `public/` or an endpoint |
| `<html lang="…">` | the layout |
| A 404 page | `src/pages/404.astro` |

Themes from the catalog often ship all of it. Astro's own starters (`minimal`,
`basics`, `blog`) ship little or none: `basics` has a title and a favicon. The
check in the workflow only proves the URLs are right; it does not add what a
template leaves out.

### Adding it to a template that has none

The sitemap:

```bash
cd landing && npx astro add sitemap --yes
```

Canonical, description and Open Graph, in the layout's `<head>`:

```astro
---
interface Props {
  title: string;
  description: string;
  image?: string;
}

const { title, description, image = '/og-image.jpg' } = Astro.props;
const canonical = new URL(Astro.url.pathname, Astro.site);
const socialImage = new URL(image, Astro.site);
---
<title>{title}</title>
<meta name="description" content={description} />
<link rel="canonical" href={canonical} />
<link rel="sitemap" href={new URL('sitemap-index.xml', Astro.site)} />

<meta property="og:type" content="website" />
<meta property="og:url" content={canonical} />
<meta property="og:title" content={title} />
<meta property="og:description" content={description} />
<meta property="og:image" content={socialImage} />
<meta name="twitter:card" content="summary_large_image" />
```

`robots.txt`, as an endpoint so it follows `site`:

```ts
// src/pages/robots.txt.ts
import type { APIRoute } from 'astro';

export const GET: APIRoute = ({ site }) => new Response(
  ['User-agent: *', 'Allow: /', site ? `Sitemap: ${new URL('sitemap-index.xml', site)}` : '']
    .join('\n'),
  { headers: { 'Content-Type': 'text/plain; charset=utf-8' } },
);
```

`Astro.url.pathname` already includes `base`, so these work unchanged for a
landing under a path.

## Previews and the application

- **Preview and staging URLs** (`*.pages.dev`, Netlify deploy previews, Vercel
  preview URLs) serve the same pages on another host. With canonical links on
  `LANDING_URL`, search engines credit the production URL, so nothing else is
  needed.
- **The application** is a separate site with its own `public/robots.txt`.
  Pages behind a login have nothing to index; disallow them there if you want
  crawlers to spend their time on the landing.

## Checking a build yourself

```bash
cd landing
npm run build -- --site https://example.com
grep -o '<link rel="canonical"[^>]*>' dist/index.html
cat dist/robots.txt
```
