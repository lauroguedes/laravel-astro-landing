# Linking the landing to the application

For where each site is served, and links from the application back to the
landing (`LANDING_URL`), see [domains.md](domains.md).

The landing is a static site on a different host, so the only connection to the
application is a URL.

## Links into the application

Declare the application's URL with `astro:env` and read it where you link:

```js
// landing/astro.config.mjs
import { defineConfig, envField } from 'astro/config';

export default defineConfig({
  site: process.env.LANDING_SITE_URL || 'https://example.com',
  env: {
    schema: {
      PUBLIC_APP_URL: envField.string({ context: 'client', access: 'public', default: 'http://localhost:8000' }),
    },
  },
});
```

```astro
---
import { PUBLIC_APP_URL } from 'astro:env/client';
---
<a href={`${PUBLIC_APP_URL}/register`}>Start free</a>
```

Locally the value comes from `landing/.env`; in the deploy workflow from the
build step's `env:` block, which the generated workflow has commented out and
ready.

## Data from the application

Prices, plans or anything else the application owns belong in a public JSON
endpoint, fetched at build time in the page's frontmatter:

```astro
---
const response = await fetch(`${import.meta.env.APP_API_URL}/api/public/pricing`);
const plans = response.ok ? await response.json() : fallbackPlans;
---
```

A build-time fetch needs no CORS configuration. Keep a fallback: without one, the
landing cannot deploy while the application is down.

When that data changes, redeploy the landing: the generated workflow accepts
`workflow_dispatch`, so the application (or a person) can trigger it.

## Sharing a design

The two projects can share design tokens, but not by importing each other's
files: they are built by different tools and one of them is not deployed with
the other. Copy the tokens into the landing's CSS, or publish them as a package
both depend on.
