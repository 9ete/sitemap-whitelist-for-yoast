<?php
/**
 * Smoke test: the plugin loads under the stub harness and exposes its surface.
 *
 * @package Sitemap_Whitelist_For_Yoast
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase {

	protected function setUp(): void {
		swy_test_reset();
	}

	public function test_plugin_class_is_loaded(): void {
		$this->assertTrue( class_exists( 'Sitemap_Whitelist_For_Yoast' ) );
	}

	public function test_instance_is_a_singleton(): void {
		$a = Sitemap_Whitelist_For_Yoast::instance();
		$b = Sitemap_Whitelist_For_Yoast::instance();
		$this->assertSame( $a, $b );
	}

	public function test_yoast_filter_callbacks_are_public(): void {
		$this->assertTrue( method_exists( 'Sitemap_Whitelist_For_Yoast', 'filter_wpseo_robots' ) );
		$this->assertTrue( method_exists( 'Sitemap_Whitelist_For_Yoast', 'filter_wpseo_sitemap_entry' ) );
	}

	public function test_abspath_guard_is_defined(): void {
		$this->assertTrue( defined( 'ABSPATH' ) );
	}
}
