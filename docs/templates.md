# Templates and themes

`landing:install --template=` takes anything `create-astro` takes, plus a few
forms people paste.

| You give | create-astro gets |
|---|---|
| `empty` (or nothing) | `minimal` |
| `basics`, `blog`, `portfolio`, `with-tailwindcss`, … | the same: an example from [withastro/astro/examples](https://github.com/withastro/astro/tree/main/examples) |
| `starlight` | Starlight's docs starter |
| `owner/repo` | the repository's default branch |
| `owner/repo/sub/dir#branch` | that directory on that branch |
| `gitlab:owner/repo`, `bitbucket:owner/repo` | the same |
| `https://github.com/owner/repo` | `owner/repo` |
| `https://github.com/owner/repo/tree/branch/sub/dir` | `owner/repo/sub/dir#branch` |
| `https://gitlab.com/owner/repo/-/tree/branch` | `gitlab:owner/repo#branch` |
| `theme:slug`, `https://astro.build/themes/details/slug/` | the repository that theme links to |

A branch name containing a slash cannot be read from a tree URL; write it as
`owner/repo/dir#feature/x` instead.

## Themes from the catalog

Interactively, choose **A free theme from astro.build/themes**, search, and pick
one. The catalog has no API: the installer reads the same pages a browser does,
lists the cards a search returns, and follows the theme's **Get started** button
to its repository. Only free themes are listed, because a paid theme's button
leads to a checkout rather than to code. Buy it, then install it with
`--template=owner/repo`.

If astro.build is unreachable, or a theme page changes shape, the installer stops
before anything is downloaded and says so. Installing from the repository
directly always works.

## Integrations

For the empty template the installer offers Tailwind, Sitemap, MDX, Vue, React
and Svelte. With any template, `--add=<name>` runs `astro add <name> --yes` after
the install, and it can be repeated. Anything else is added later the same way,
from inside the landing directory.

## Package managers

npm, pnpm, Yarn and Bun. The default is whatever the application uses, read from
its lockfile. Afterwards the landing's own lockfile decides, for every command
and for the deploy workflow; `LANDING_PACKAGE_MANAGER` overrides both.

Templates often ship their author's lockfile. When it belongs to a different
package manager than the one you chose, the installer removes it, so the
lockfile your package manager writes is the only one and every later command
agrees on it. With `--skip-install` there is no lockfile yet, so the installer
writes your choice into `config/landing.php` instead.
