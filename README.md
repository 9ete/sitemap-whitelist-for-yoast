# Sitemap Whitelist for Yoast

Whitelist-based indexing and Yoast SEO sitemap filtering for WordPress. Only the
URLs in your whitelist stay **indexable** and appear in **Yoast-generated
sitemaps** — everything else is forced to `noindex, follow` and dropped from the
sitemap.

Requires the [Yoast SEO](https://wordpress.org/plugins/wordpress-seo/) plugin. It
hooks Yoast's `wpseo_robots` and `wpseo_sitemap_entry` filters and otherwise
stays out of the way.

## Features

- Manage a URL whitelist from **Settings → Sitemap Whitelist**.
- Uniform across every Yoast sitemap type (posts, pages, taxonomies, archives,
  authors) — matching is by URL/path, not content type.
- Absolute URLs (`https://example.com/pricing`) and relative paths (`/pricing`).
- Normalizes + de-duplicates on save; CSV import/export; whitelist-health counts.
- **Safe by default:** an empty whitelist is a no-op (never an empty sitemap).
- Yoast-inactive guard; fully translatable (`languages/*.pot`).

## Installation

Install Yoast SEO, then upload this plugin via **Plugins → Add New → Upload
Plugin**, activate it, and add your URLs under **Settings → Sitemap Whitelist**.

## Development

```bash
composer install
npm install

composer lint          # PHPCS (WordPress Coding Standards)
composer test          # PHPUnit (mocked-WP unit suite)
composer test:e2e      # Cypress e2e (needs a running site)
composer plugin-check   # WordPress Plugin Check against the built dist
composer check         # full gate: lint + test + e2e + plugin-check
composer build-zip     # build the distribution zip
```

Git hooks (`composer install-hooks`) run lint + tests on commit. e2e and
Plugin Check expect an isolated Lando site (`swy.lndo.site`) with WordPress +
Yoast SEO; override `LANDO_PROJECT_DIR` / `CYPRESS_BASE_URL` for other layouts.

## License

[GPL-2.0-or-later](LICENSE).
