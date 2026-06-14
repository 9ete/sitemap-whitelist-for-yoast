<?php
/**
 * The plugin file header declares the metadata wp.org and WP core require.
 *
 * @package Sitemap_Whitelist_For_Yoast
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class PluginHeaderTest extends TestCase {

	private function header(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/sitemap-whitelist-for-yoast.php' );
	}

	public function test_declares_yoast_as_a_required_plugin(): void {
		$this->assertMatchesRegularExpression(
			'/^\s*\*\s*Requires Plugins:\s*wordpress-seo\b/m',
			$this->header()
		);
	}

	public function test_has_exactly_one_version_header(): void {
		preg_match_all( '/^\s*\*\s*Version:\s*\S/m', $this->header(), $matches );
		$this->assertCount( 1, $matches[0], 'Exactly one Version header is expected.' );
	}

	public function test_declares_version_1_0_0(): void {
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Version:\s*1\.0\.0\b/m', $this->header() );
	}

	public function test_declares_php_and_wp_floors(): void {
		$header = $this->header();
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Requires at least:\s*\d/m', $header );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Requires PHP:\s*\d/m', $header );
	}

	public function test_declares_license_and_uris(): void {
		$header = $this->header();
		$this->assertMatchesRegularExpression( '/^\s*\*\s*License:\s*GPL/m', $header );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*License URI:/m', $header );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Plugin URI:/m', $header );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Author URI:/m', $header );
	}
}
