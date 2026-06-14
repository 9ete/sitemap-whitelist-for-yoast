<?php
/**
 * analyze_urls() — the "whitelist health" counters shown on the settings page.
 *
 * @package Sitemap_Whitelist_For_Yoast
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class AnalyzeUrlsTest extends TestCase {

	private $plugin;

	protected function setUp(): void {
		swy_test_reset();
		$this->plugin = Sitemap_Whitelist_For_Yoast::instance();
	}

	private function analyze( array $lines ): array {
		return (array) swy_invoke( $this->plugin, 'analyze_urls', array( $lines ) );
	}

	public function test_counts_total_valid_invalid_and_duplicates(): void {
		$counts = $this->analyze(
			array(
				'https://site.com/a',
				'https://site.com/a',  // duplicate after normalization
				'https://site.com/b',
				'ftp://x.com/y',       // normalizes but fails http validation → invalid
			)
		);

		$this->assertSame( 4, $counts['total_lines'] );
		$this->assertSame( 3, $counts['valid_count'] );   // a, a, b
		$this->assertSame( 1, $counts['invalid_count'] );
		$this->assertSame( 1, $counts['duplicate_count'] );
		$this->assertSame( array( 'ftp://x.com/y' ), $counts['invalid_samples'] );
	}

	public function test_unparseable_lines_are_dropped_before_the_validity_check(): void {
		$counts = $this->analyze( array( 'totally not a url' ) );
		$this->assertSame( 1, $counts['total_lines'] );
		$this->assertSame( 0, $counts['valid_count'] );
		$this->assertSame( 0, $counts['invalid_count'] );
	}

	public function test_invalid_samples_are_capped_at_ten(): void {
		$lines = array();
		for ( $i = 0; $i < 15; $i++ ) {
			$lines[] = 'ftp://x.com/' . $i;
		}
		$counts = $this->analyze( $lines );
		$this->assertSame( 15, $counts['invalid_count'] );
		$this->assertCount( 10, $counts['invalid_samples'] );
	}
}
