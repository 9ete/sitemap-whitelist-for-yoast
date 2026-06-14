<?php
/**
 * URL/path normalization and dedupe — the core of whitelist matching.
 *
 * @package Sitemap_Whitelist_For_Yoast
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class NormalizeUrlTest extends TestCase {

	private $plugin;

	protected function setUp(): void {
		swy_test_reset();
		$this->plugin = Sitemap_Whitelist_For_Yoast::instance();
	}

	private function url( string $u ): string {
		return (string) swy_invoke( $this->plugin, 'normalize_url', array( $u ) );
	}

	private function path( string $p ): string {
		return (string) swy_invoke( $this->plugin, 'normalize_path', array( $p ) );
	}

	private function dedupe( array $a ): array {
		return (array) swy_invoke( $this->plugin, 'normalize_and_dedupe_urls', array( $a ) );
	}

	public function test_lowercases_scheme_and_host_but_keeps_path_case(): void {
		$this->assertSame( 'https://example.com/Foo/Bar', $this->url( 'HTTPS://Example.COM/Foo/Bar' ) );
	}

	public function test_homepage_keeps_trailing_slash(): void {
		$this->assertSame( 'https://example.com/', $this->url( 'https://example.com' ) );
		$this->assertSame( 'https://example.com/', $this->url( 'https://example.com/' ) );
	}

	public function test_strips_trailing_slash_for_non_homepage(): void {
		$this->assertSame( 'https://example.com/pricing', $this->url( 'https://example.com/pricing/' ) );
	}

	public function test_drops_query_and_fragment(): void {
		$this->assertSame( 'https://example.com/p', $this->url( 'https://example.com/p?utm=1#frag' ) );
	}

	public function test_collapses_duplicate_slashes(): void {
		$this->assertSame( 'https://example.com/a/b', $this->url( 'https://example.com//a///b/' ) );
	}

	public function test_rejects_input_without_scheme_or_host(): void {
		$this->assertSame( '', $this->url( 'not a url' ) );
		$this->assertSame( '', $this->url( '/relative/path' ) );
	}

	public function test_path_gets_leading_slash_and_no_trailing(): void {
		$this->assertSame( '/pricing', $this->path( 'pricing/' ) );
		$this->assertSame( '/pricing', $this->path( '/pricing' ) );
	}

	public function test_root_path_is_preserved(): void {
		$this->assertSame( '/', $this->path( '/' ) );
	}

	public function test_path_strips_query_and_fragment(): void {
		$this->assertSame( '/p', $this->path( '/p?x=1' ) );
		$this->assertSame( '/p', $this->path( '/p#frag' ) );
	}

	public function test_dedupe_normalizes_uniquifies_sorts_and_drops_invalid(): void {
		$in = array(
			'https://EX.com/b',
			'https://ex.com/a',
			'https://ex.com/a',  // duplicate after normalization
			'/pricing/',         // relative path
			'ftp://ex.com/y',    // non-http scheme → dropped
			'garbage',           // unparseable → dropped
			'',                  // empty → dropped
		);
		$this->assertSame(
			array( '/pricing', 'https://ex.com/a', 'https://ex.com/b' ),
			$this->dedupe( $in )
		);
	}
}
