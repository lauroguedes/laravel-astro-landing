<?php

declare(strict_types=1);

namespace LauroGuedes\AstroOnLaravel\Support;

/**
 * Where a landing can be deployed by the generated GitHub Actions workflow.
 *
 * Each case is one stub in stubs/workflows, and the secrets and variables it
 * reads — listed here so the installer can say what to add to the repository
 * before the first push, rather than leaving the first run to fail and say it.
 */
enum DeployTarget: string
{
    case CloudflarePages = 'cloudflare-pages';
    case Netlify = 'netlify';
    case Vercel = 'vercel';
    case GitHubPages = 'github-pages';
    case Ssh = 'ssh';

    public function label(): string
    {
        return match ($this) {
            self::CloudflarePages => 'Cloudflare Pages',
            self::Netlify => 'Netlify',
            self::Vercel => 'Vercel',
            self::GitHubPages => 'GitHub Pages',
            self::Ssh => 'Your own server, over SSH and rsync',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $target): string => $target->value, self::cases());
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $target) {
            $options[$target->value] = $target->label();
        }

        return $options;
    }

    /**
     * Repository secrets the workflow reads.
     *
     * @return list<string>
     */
    public function secrets(): array
    {
        return match ($this) {
            self::CloudflarePages => ['CLOUDFLARE_API_TOKEN', 'CLOUDFLARE_ACCOUNT_ID'],
            self::Netlify => ['NETLIFY_AUTH_TOKEN', 'NETLIFY_SITE_ID'],
            self::Vercel => ['VERCEL_TOKEN', 'VERCEL_ORG_ID', 'VERCEL_PROJECT_ID'],
            self::GitHubPages => [],
            self::Ssh => ['LANDING_SSH_HOST', 'LANDING_SSH_USER', 'LANDING_SSH_KEY', 'LANDING_SSH_PATH'],
        };
    }

    /**
     * What has to be done outside the repository for the first run to pass.
     */
    public function note(): string
    {
        return match ($this) {
            self::CloudflarePages => 'Create the Pages project once (wrangler pages project create, or the dashboard) — the name is in the workflow.',
            self::Netlify => 'Create the site once in Netlify and copy its Site ID.',
            self::Vercel => 'Run `vercel link` in the landing directory once to get the org and project IDs (.vercel/project.json).',
            self::GitHubPages => 'Set Settings → Pages → Source to "GitHub Actions". Serving from a project page (user.github.io/repo) also needs `base` in astro.config.mjs.',
            self::Ssh => 'LANDING_SSH_KEY is a private key whose public half is in the server user\'s authorized_keys. LANDING_SSH_PORT is optional and defaults to 22.',
        };
    }
}
