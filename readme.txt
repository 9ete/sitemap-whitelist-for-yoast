=== Sitemap Whitelist for Yoast ===
Contributors: 9ete
Tags: sitemap, noindex, indexing, seo, whitelist
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keep only the URLs you choose indexable and in your Yoast SEO sitemaps. A simple whitelist that forces noindex on everything else.

== Description ==

**Sitemap Whitelist for Yoast** flips Yoast SEO's indexing model from "index everything except what I exclude" to "index only what I list." You paste (or import) a list of URLs, and the plugin makes sure that **only** those URLs are:

* **Indexable** — every other front-end URL is forced to `noindex` (links are still followed), and
* **Included in Yoast-generated sitemaps** — non-whitelisted entries are dropped from the sitemap.

It does not disable or replace Yoast SEO. It hooks two Yoast filters (`wpseo_robots` and `wpseo_sitemap_entry`) and otherwise stays out of the way.

= Why use it? =

Large sites, staging mirrors, and content-heavy installs often expose far more URLs to search engines than intended. Instead of hunting down every post type, taxonomy, and archive to noindex, you declare the short list of pages that *should* rank and let everything else fall away.

= Key features =

* URL whitelist managed from a single **Settings → Sitemap Whitelist** screen.
* Works uniformly across every Yoast sitemap type — posts, pages, taxonomies, archives, and authors — because matching is by URL/path, not content type.
* Accepts absolute URLs (`https://example.com/pricing`) and relative paths (`/pricing`) for portability across environments.
* Normalizes and de-duplicates entries on save (lower-cases scheme/host, strips query strings and trailing slashes, keeps the homepage slash).
* CSV import (with a `loc` column or first-URL-per-row detection) and CSV export.
* A "whitelist health" summary showing valid, invalid, and duplicate counts.
* **Safe by default:** an empty whitelist is a no-op — it never produces an empty sitemap or noindexes your whole site.

== Installation ==

1. Make sure **Yoast SEO** is installed and active (this plugin extends it).
2. Upload the `sitemap-whitelist-for-yoast` folder to `/wp-content/plugins/`, or install it from **Plugins → Add New → Upload Plugin**.
3. Activate the plugin through the **Plugins** screen.
4. Go to **Settings → Sitemap Whitelist** and add the URLs that should remain indexable.

== Frequently Asked Questions ==

= Does this require Yoast SEO? =

Yes. The plugin declares Yoast SEO as a required plugin and filters its robots and sitemap output. If Yoast is not active, the plugin does nothing and shows an admin notice.

= What happens if the whitelist is empty? =

Nothing changes. An empty whitelist is treated as "not configured," so your site keeps Yoast's normal behavior — the plugin will never noindex everything or empty your sitemap by accident.

= Can I whitelist by relative path? =

Yes. Lines beginning with `/` (for example `/pricing`) match the corresponding URL on the current site, which makes the same list portable between staging and production.

= Are non-whitelisted pages removed or just hidden from search? =

They are not deleted or blocked. They are set to `noindex, follow` on the front end and excluded from Yoast sitemaps, so search engines drop them from the index while visitors and internal links still work.

= How do I import a list of URLs? =

Use the **Import CSV** form. If your file has a column headed `loc`, that column is used; otherwise the first cell in each row that looks like a URL is taken. You can merge with or replace the existing list.

== Screenshots ==

1. The Sitemap Whitelist settings screen — paste URLs, import/export CSV, and clear the list.
2. The "whitelist health" summary with valid, invalid, and duplicate counts.

== Changelog ==

= 1.0.0 =
* Initial release.
* Whitelist-based indexing: only listed URLs stay indexable; everything else is forced to `noindex, follow`.
* Yoast sitemap filtering: only whitelisted URLs appear in Yoast-generated sitemaps.
* Settings screen with textarea management, CSV import/export, clear, and a whitelist-health summary.
* Absolute-URL and relative-path matching, with normalization and de-duplication.
* Empty-whitelist safety (no-op), Yoast-inactive guard, and full translation support.

== Upgrade Notice ==

= 1.0.0 =
First public release.
