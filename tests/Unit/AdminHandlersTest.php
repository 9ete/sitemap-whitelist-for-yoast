<?php
/**
 * Admin POST handlers: persistence, success notices, nonce + capability gates.
 *
 * @package Sitemap_Whitelist_For_Yoast
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class AdminHandlersTest extends TestCase {

	protected function setUp(): void {
		swy_test_reset();
		$GLOBALS['swy_test_state']['is_admin'] = true;
	}

	protected function tearDown(): void {
		$_POST  = array();
		$_FILES = array();
	}

	/** Dispatch handle_admin_post() and return the redirect Location. */
	private function dispatch(): string {
		try {
			Sitemap_Whitelist_For_Yoast::instance()->handle_admin_post();
		} catch ( SWY_Redirect_Exception $e ) {
			return $e->location;
		}
		$this->fail( 'Expected a redirect from handle_admin_post().' );
	}

	public function test_save_persists_normalizes_and_queues_saved_notice(): void {
		$_POST = array(
			'smwy_action'          => 'save_textarea',
			'smwy_whitelist_nonce' => 'nonce',
			'smwy_whitelist_urls'  => "https://example.com/a\nhttps://example.com/b\nhttps://example.com/a",
		);
		$location = $this->dispatch();

		$this->assertStringContainsString( 'page=sitemap-whitelist-for-yoast', $location );
		$this->assertSame(
			"https://example.com/a\nhttps://example.com/b",
			get_option( 'sitemap_whitelist_for_yoast_urls' )
		);

		$notice = get_transient( 'swy_notice_1' );
		$this->assertIsArray( $notice );
		$this->assertSame( 'saved', $notice['code'] );
		$this->assertSame( 2, $notice['count'] );
	}

	public function test_clear_empties_option_and_queues_cleared_notice(): void {
		update_option( 'sitemap_whitelist_for_yoast_urls', 'https://example.com/a', false );
		$_POST = array(
			'smwy_action'          => 'clear',
			'smwy_whitelist_nonce' => 'nonce',
		);
		$this->dispatch();

		$this->assertSame( '', get_option( 'sitemap_whitelist_for_yoast_urls' ) );
		$notice = get_transient( 'swy_notice_1' );
		$this->assertIsArray( $notice );
		$this->assertSame( 'cleared', $notice['code'] );
	}

	public function test_invalid_nonce_dies(): void {
		$GLOBALS['swy_test_state']['nonce_valid'] = false;
		$_POST = array(
			'smwy_action'          => 'save_textarea',
			'smwy_whitelist_nonce' => 'bad',
			'smwy_whitelist_urls'  => 'https://example.com/a',
		);
		$this->expectException( SWY_Die_Exception::class );
		Sitemap_Whitelist_For_Yoast::instance()->handle_admin_post();
	}

	public function test_missing_capability_is_a_noop(): void {
		$GLOBALS['swy_test_state']['can'] = false;
		$_POST = array(
			'smwy_action'          => 'save_textarea',
			'smwy_whitelist_nonce' => 'nonce',
			'smwy_whitelist_urls'  => 'https://example.com/a',
		);
		Sitemap_Whitelist_For_Yoast::instance()->handle_admin_post();
		$this->assertFalse( get_option( 'sitemap_whitelist_for_yoast_urls', false ) );
	}

	/**
	 * The full upload path is guarded by is_uploaded_file() (untestable in
	 * CLI), so the merge/replace logic is exercised directly.
	 */
	public function test_apply_imported_urls_merges_and_queues_notice(): void {
		update_option( 'sitemap_whitelist_for_yoast_urls', 'https://example.com/existing', false );

		try {
			swy_invoke(
				Sitemap_Whitelist_For_Yoast::instance(),
				'apply_imported_urls',
				array( array( 'https://example.com/new' ), true )
			);
			$this->fail( 'Expected a redirect.' );
		} catch ( SWY_Redirect_Exception $e ) {
			$this->assertStringContainsString( 'page=sitemap-whitelist-for-yoast', $e->location );
		}

		$saved = get_option( 'sitemap_whitelist_for_yoast_urls' );
		$this->assertStringContainsString( 'https://example.com/existing', $saved );
		$this->assertStringContainsString( 'https://example.com/new', $saved );

		$notice = get_transient( 'swy_notice_1' );
		$this->assertIsArray( $notice );
		$this->assertSame( 'imported', $notice['code'] );
		$this->assertSame( 2, $notice['count'] );
	}

	public function test_apply_imported_urls_replaces_when_not_merging(): void {
		update_option( 'sitemap_whitelist_for_yoast_urls', 'https://example.com/existing', false );

		try {
			swy_invoke(
				Sitemap_Whitelist_For_Yoast::instance(),
				'apply_imported_urls',
				array( array( 'https://example.com/new' ), false )
			);
		} catch ( SWY_Redirect_Exception $e ) {
			$this->assertTrue( true );
		}

		$this->assertSame( 'https://example.com/new', get_option( 'sitemap_whitelist_for_yoast_urls' ) );
	}
}
