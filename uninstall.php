<?php
/**
 * Uninstall cleanup for Sitemap Whitelist for Yoast.
 *
 * Runs only when the plugin is deleted from the WordPress admin. Removes the
 * options the plugin stores. The per-user "swy_notice_*" notices are short-lived
 * transients (60-second TTL) and expire on their own, so no explicit cleanup is
 * needed for them.
 *
 * @package Sitemap_Whitelist_For_Yoast
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'sitemap_whitelist_for_yoast_urls' );
delete_option( 'sitemap_whitelist_for_yoast_meta' );
