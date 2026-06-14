<?php
/**
 * parse_csv_urls() — extract URLs from an uploaded CSV.
 *
 * @package Sitemap_Whitelist_For_Yoast
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class CsvParseTest extends TestCase {

	private $plugin;

	protected function setUp(): void {
		swy_test_reset();
		$this->plugin = Sitemap_Whitelist_For_Yoast::instance();
	}

	private function parse( string $contents ): array {
		$file = tempnam( sys_get_temp_dir(), 'swycsv' );
		file_put_contents( $file, $contents );
		$result = (array) swy_invoke( $this->plugin, 'parse_csv_urls', array( $file ) );
		unlink( $file );
		return $result;
	}

	public function test_uses_loc_column_when_present(): void {
		$result = $this->parse( "name,loc\nHome,https://site.com/a\nAbout,https://site.com/b\n" );
		$this->assertSame( array( 'https://site.com/a', 'https://site.com/b' ), $result );
	}

	public function test_falls_back_to_first_url_cell_when_no_loc_column(): void {
		$result = $this->parse( "foo,bar\nx,https://site.com/a\nhttps://site.com/b,y\n" );
		$this->assertContains( 'https://site.com/a', $result );
		$this->assertContains( 'https://site.com/b', $result );
	}

	public function test_handles_a_headerless_single_column(): void {
		$result = $this->parse( "https://site.com/a\nhttps://site.com/b\n" );
		$this->assertContains( 'https://site.com/a', $result );
		$this->assertContains( 'https://site.com/b', $result );
	}

	public function test_loc_column_returns_raw_cells_validation_is_downstream(): void {
		// parse_csv_urls extracts the loc column verbatim; non-URL values are
		// dropped later by normalize_and_dedupe_urls(), not here.
		$result = $this->parse( "loc\nnot-a-url\nhttps://site.com/a\n" );
		$this->assertSame( array( 'not-a-url', 'https://site.com/a' ), $result );
	}
}
