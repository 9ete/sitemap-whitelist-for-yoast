<?php
/**
 * register() wiring, including the Yoast-active guard.
 *
 * @package Sitemap_Whitelist_For_Yoast
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class RegistrationTest extends TestCase {

	protected function setUp(): void {
		swy_test_reset();
	}

	/** Mark Yoast SEO as active for the duration of a test. */
	private function activate_yoast(): void {
		$GLOBALS['swy_test_state']['active_plugins'] = array( 'wordpress-seo/wp-seo.php' );
	}

	private function hooks(): array {
		return array_column( $GLOBALS['swy_test_hooks'], 'hook' );
	}

	public function test_admin_hooks_are_wired_regardless_of_yoast(): void {
		Sitemap_Whitelist_For_Yoast::instance()->register();
		$this->assertContains( 'admin_menu', $this->hooks() );
		$this->assertContains( 'admin_init', $this->hooks() );
	}

	public function test_yoast_filters_wired_when_yoast_active(): void {
		$this->activate_yoast();
		Sitemap_Whitelist_For_Yoast::instance()->register();

		$hooks = $this->hooks();
		$this->assertContains( 'wpseo_robots', $hooks );
		$this->assertContains( 'wpseo_sitemap_entry', $hooks );
		$this->assertNotContains( 'admin_notices', $hooks, 'No missing-Yoast notice when Yoast is active.' );
	}

	public function test_yoast_filters_skipped_and_notice_wired_when_inactive(): void {
		// active_plugins is empty by default → Yoast inactive.
		Sitemap_Whitelist_For_Yoast::instance()->register();

		$hooks = $this->hooks();
		$this->assertNotContains( 'wpseo_robots', $hooks );
		$this->assertNotContains( 'wpseo_sitemap_entry', $hooks );
		$this->assertContains( 'admin_notices', $hooks, 'A missing-Yoast admin notice should be queued.' );
	}
}
