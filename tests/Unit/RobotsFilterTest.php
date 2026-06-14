<?php
/**
 * wpseo_robots filter — noindex enforcement for non-whitelisted front-end URLs.
 *
 * @package Sitemap_Whitelist_For_Yoast
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class RobotsFilterTest extends TestCase {

	private $plugin;

	protected function setUp(): void {
		swy_test_reset();
		$this->plugin                   = Sitemap_Whitelist_For_Yoast::instance();
		$_SERVER['HTTP_HOST']           = 'site.com';
		$_SERVER['REQUEST_URI']         = '/page';
	}

	protected function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] );
	}

	private function whitelist( string $raw ): void {
		update_option( 'sitemap_whitelist_for_yoast_urls', $raw, false );
	}

	public function test_empty_whitelist_is_a_noop(): void {
		$this->assertSame( 'index, follow', $this->plugin->filter_wpseo_robots( 'index, follow' ) );
	}

	public function test_whitelisted_url_stays_indexable(): void {
		$this->whitelist( 'https://site.com/page' );
		$this->assertSame( 'index, follow', $this->plugin->filter_wpseo_robots( 'index, follow' ) );
	}

	public function test_relative_path_whitelist_keeps_current_indexable(): void {
		$this->whitelist( '/page' );
		$this->assertSame( 'index, follow', $this->plugin->filter_wpseo_robots( 'index, follow' ) );
	}

	public function test_non_whitelisted_url_is_noindexed_string_form(): void {
		$this->whitelist( 'https://site.com/other' );
		$this->assertStringContainsString( 'noindex', $this->plugin->filter_wpseo_robots( 'index, follow' ) );
	}

	public function test_noindex_output_drops_contradictory_index_token(): void {
		$this->whitelist( 'https://site.com/other' );
		$parts = array_map( 'trim', explode( ',', $this->plugin->filter_wpseo_robots( 'index, follow' ) ) );
		$this->assertContains( 'noindex', $parts );
		$this->assertNotContains( 'index', $parts, '"index" must not coexist with "noindex"' );
		$this->assertContains( 'follow', $parts );
	}

	public function test_preserves_other_robots_directives(): void {
		$this->whitelist( 'https://site.com/other' );
		$parts = array_map( 'trim', explode( ',', $this->plugin->filter_wpseo_robots( 'max-snippet:-1, index, follow' ) ) );
		$this->assertContains( 'noindex', $parts );
		$this->assertContains( 'max-snippet:-1', $parts );
		$this->assertNotContains( 'index', $parts );
	}

	public function test_non_whitelisted_url_is_noindexed_array_form(): void {
		$this->whitelist( 'https://site.com/other' );
		$result = $this->plugin->filter_wpseo_robots( array( 'index' => 'index', 'follow' => 'follow' ) );
		$this->assertIsArray( $result );
		$this->assertSame( 'noindex', $result['index'] );
	}

	public function test_admin_context_is_bypassed(): void {
		$GLOBALS['swy_test_state']['is_admin'] = true;
		$this->whitelist( 'https://site.com/other' );
		$this->assertSame( 'index, follow', $this->plugin->filter_wpseo_robots( 'index, follow' ) );
	}

	public function test_ajax_request_is_bypassed(): void {
		$GLOBALS['swy_test_state']['doing_ajax'] = true;
		$this->whitelist( 'https://site.com/other' );
		$this->assertSame( 'index, follow', $this->plugin->filter_wpseo_robots( 'index, follow' ) );
	}

	public function test_json_request_is_bypassed(): void {
		$GLOBALS['swy_test_state']['is_json'] = true;
		$this->whitelist( 'https://site.com/other' );
		$this->assertSame( 'index, follow', $this->plugin->filter_wpseo_robots( 'index, follow' ) );
	}
}
