<?php
/**
 * wpseo_sitemap_entry filter — which sitemap entries survive.
 *
 * Covers the critical empty-whitelist safety (must be a no-op, never an empty
 * sitemap) and type-agnostic matching across all Yoast sitemap types.
 *
 * @package Sitemap_Whitelist_For_Yoast
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class SitemapEntryFilterTest extends TestCase {

	private $plugin;

	protected function setUp(): void {
		swy_test_reset();
		$this->plugin = Sitemap_Whitelist_For_Yoast::instance();
	}

	private function whitelist( string $raw ): void {
		update_option( 'sitemap_whitelist_for_yoast_urls', $raw, false );
	}

	private function filter( array $entry, string $type = 'post' ) {
		return $this->plugin->filter_wpseo_sitemap_entry( $entry, $type, null );
	}

	public function test_empty_whitelist_is_a_noop_and_never_drops(): void {
		// No option set → empty whitelist → every entry must pass through.
		$entry = array( 'loc' => 'https://site.com/anything' );
		$this->assertSame( $entry, $this->filter( $entry ) );
	}

	public function test_whitelisted_absolute_url_is_kept(): void {
		$this->whitelist( 'https://site.com/keep' );
		$entry = array( 'loc' => 'https://site.com/keep' );
		$this->assertSame( $entry, $this->filter( $entry ) );
	}

	public function test_non_whitelisted_url_is_dropped(): void {
		$this->whitelist( 'https://site.com/keep' );
		$this->assertFalse( $this->filter( array( 'loc' => 'https://site.com/drop' ) ) );
	}

	public function test_trailing_slash_is_normalized_before_matching(): void {
		$this->whitelist( 'https://site.com/keep' );
		$this->assertNotFalse( $this->filter( array( 'loc' => 'https://site.com/keep/' ) ) );
	}

	public function test_relative_path_whitelist_matches_absolute_entry(): void {
		$this->whitelist( '/keep' );
		$this->assertNotFalse( $this->filter( array( 'loc' => 'https://site.com/keep' ) ) );
		$this->assertFalse( $this->filter( array( 'loc' => 'https://site.com/other' ) ) );
	}

	public function test_homepage_entry_matches(): void {
		$this->whitelist( 'https://site.com/' );
		$this->assertNotFalse( $this->filter( array( 'loc' => 'https://site.com/' ) ) );
	}

	/**
	 * The plugin matches by URL/path only, so behavior must be identical for
	 * every Yoast sitemap type (posts, pages, taxonomies, archives, authors).
	 *
	 * @dataProvider sitemapTypes
	 */
	public function test_matching_is_type_agnostic( string $type ): void {
		$this->whitelist( 'https://site.com/keep' );
		$this->assertNotFalse( $this->filter( array( 'loc' => 'https://site.com/keep' ), $type ), "kept for {$type}" );
		$this->assertFalse( $this->filter( array( 'loc' => 'https://site.com/drop' ), $type ), "dropped for {$type}" );
	}

	public static function sitemapTypes(): array {
		return array(
			'post'         => array( 'post' ),
			'page'         => array( 'page' ),
			'taxonomy'     => array( 'term' ),
			'author'       => array( 'author' ),
			'archive'      => array( 'post_archive' ),
		);
	}

	public function test_malformed_entry_passes_through_unchanged(): void {
		$this->whitelist( 'https://site.com/keep' );
		$this->assertSame( array(), $this->filter( array() ) );
		$empty_loc = array( 'loc' => '' );
		$this->assertSame( $empty_loc, $this->filter( $empty_loc ) );
	}
}
