<?php
/**
 * Plugin Name: Sitemap Whitelist for Yoast
 * Description: Whitelist-based indexing + Yoast sitemap filtering. Only URLs in the whitelist are indexable and included in Yoast sitemaps.
 * Version: 1.0.0
 * Author: 9ete
 * License: GPLv2 or later
 * Text Domain: sitemap-whitelist-for-yoast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Sitemap_Whitelist_for_Yoast' ) ) :

/**
 * Sitemap Whitelist for Yoast
 */
final class Sitemap_Whitelist_for_Yoast {

	/**
	 * Option key where we store normalized URLs (one per line).
	 *
	 * @var string
	 */
	const OPTION_URLS = 'sitemap_whitelist_for_yoast_urls';

	/**
	 * Option key for metadata.
	 *
	 * @var string
	 */
	const OPTION_META = 'sitemap_whitelist_for_yoast_meta';

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Admin UI.
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_init', array( $this, 'handle_admin_post' ) );

		// Yoast hooks (only if Yoast is active).
		add_filter( 'wpseo_robots', array( $this, 'filter_wpseo_robots' ), 20 );
		add_filter( 'wpseo_sitemap_entry', array( $this, 'filter_wpseo_sitemap_entry' ), 20, 3 );
	}

	/**
	 * Register admin page.
	 *
	 * @return void
	 */
	public function register_admin_page() {
		add_options_page(
			__( 'Sitemap Whitelist (Yoast)', 'sitemap-whitelist-for-yoast' ),
			__( 'Sitemap Whitelist', 'sitemap-whitelist-for-yoast' ),
			'manage_options',
			'sitemap-whitelist-for-yoast',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$urls_raw   = (string) get_option( self::OPTION_URLS, '' );
		$urls       = $this->explode_lines( $urls_raw );
		$meta       = (array) get_option( self::OPTION_META, array() );
		$updated_at = isset( $meta['updated_at'] ) ? (string) $meta['updated_at'] : '';
		$updated_by = isset( $meta['updated_by'] ) ? (string) $meta['updated_by'] : '';

		$counts = $this->analyze_urls( $urls );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Sitemap Whitelist (Yoast)', 'sitemap-whitelist-for-yoast' ); ?></h1>

			<p>
				<?php echo esc_html__( 'Only URLs in this whitelist will be:', 'sitemap-whitelist-for-yoast' ); ?>
				<strong><?php echo esc_html__( 'indexable (noindex enforced for everything else)', 'sitemap-whitelist-for-yoast' ); ?></strong>
				<?php echo esc_html__( 'and included in Yoast-generated sitemaps.', 'sitemap-whitelist-for-yoast' ); ?>
			</p>

			<?php if ( ! empty( $updated_at ) ) : ?>
				<p>
					<?php
					printf(
						/* translators: 1: date/time, 2: user */
						esc_html__( 'Last updated: %1$s by %2$s', 'sitemap-whitelist-for-yoast' ),
						esc_html( $updated_at ),
						esc_html( $updated_by )
					);
					?>
				</p>
			<?php endif; ?>

			<hr />

			<h2><?php echo esc_html__( 'Current whitelist health', 'sitemap-whitelist-for-yoast' ); ?></h2>

			<ul style="list-style: disc; padding-left: 20px;">
				<li><?php echo esc_html( sprintf( 'Total lines: %d', (int) $counts['total_lines'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( 'Valid URLs: %d', (int) $counts['valid_count'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( 'Duplicates removed on save: %d', (int) $counts['duplicate_count'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( 'Invalid URLs: %d', (int) $counts['invalid_count'] ) ); ?></li>
			</ul>

			<?php if ( ! empty( $counts['invalid_samples'] ) ) : ?>
				<p><strong><?php echo esc_html__( 'Invalid URL samples (first 10):', 'sitemap-whitelist-for-yoast' ); ?></strong></p>
				<ol>
					<?php foreach ( $counts['invalid_samples'] as $bad ) : ?>
						<li><code><?php echo esc_html( $bad ); ?></code></li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>

			<hr />

			<h2><?php echo esc_html__( 'Update whitelist', 'sitemap-whitelist-for-yoast' ); ?></h2>

			<form method="post">
				<?php wp_nonce_field( 'smwy_whitelist_save', 'smwy_whitelist_nonce' ); ?>
				<input type="hidden" name="smwy_action" value="save_textarea" />

				<p>
					<?php echo esc_html__( 'Paste one URL per line. The plugin will normalize URLs (remove trailing slash except for homepage) and remove duplicates.', 'sitemap-whitelist-for-yoast' ); ?>
				</p>

				<textarea
					name="smwy_whitelist_urls"
					rows="16"
					style="width: 100%; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;"
				><?php echo esc_textarea( $urls_raw ); ?></textarea>

				<p>
					<button type="submit" class="button button-primary">
						<?php echo esc_html__( 'Save whitelist', 'sitemap-whitelist-for-yoast' ); ?>
					</button>
				</p>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Import CSV', 'sitemap-whitelist-for-yoast' ); ?></h2>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'smwy_whitelist_import', 'smwy_whitelist_nonce' ); ?>
				<input type="hidden" name="smwy_action" value="import_csv" />

				<p>
					<?php echo esc_html__( 'Upload a CSV. If there is a "loc" column, it will be used. Otherwise, the importer will scan each row and use the first cell containing a URL.', 'sitemap-whitelist-for-yoast' ); ?>
				</p>

				<input type="file" name="smwy_whitelist_csv" accept=".csv,text/csv" required />

				<p style="margin-top: 10px;">
					<label>
						<input type="checkbox" name="smwy_merge" value="1" checked />
						<?php echo esc_html__( 'Merge with existing whitelist (recommended)', 'sitemap-whitelist-for-yoast' ); ?>
					</label>
				</p>

				<p>
					<button type="submit" class="button">
						<?php echo esc_html__( 'Import CSV', 'sitemap-whitelist-for-yoast' ); ?>
					</button>
				</p>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Export / Clear', 'sitemap-whitelist-for-yoast' ); ?></h2>

			<div style="display:flex; gap: 10px; align-items: center; flex-wrap: wrap;">
				<form method="post">
					<?php wp_nonce_field( 'smwy_whitelist_export', 'smwy_whitelist_nonce' ); ?>
					<input type="hidden" name="smwy_action" value="export_csv" />
					<button type="submit" class="button">
						<?php echo esc_html__( 'Export CSV', 'sitemap-whitelist-for-yoast' ); ?>
					</button>
				</form>

				<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Clear the whitelist? This will cause most pages to become noindex.', 'sitemap-whitelist-for-yoast' ) ); ?>');">
					<?php wp_nonce_field( 'smwy_whitelist_clear', 'smwy_whitelist_nonce' ); ?>
					<input type="hidden" name="smwy_action" value="clear" />
					<button type="submit" class="button button-secondary">
						<?php echo esc_html__( 'Clear whitelist', 'sitemap-whitelist-for-yoast' ); ?>
					</button>
				</form>
			</div>

			<hr />

			<h2><?php echo esc_html__( 'Notes', 'sitemap-whitelist-for-yoast' ); ?></h2>
			<ul style="list-style: disc; padding-left: 20px;">
				<li><?php echo esc_html__( 'This plugin does not disable Yoast. It only filters robots + sitemap entries.', 'sitemap-whitelist-for-yoast' ); ?></li>
				<li><?php echo esc_html__( 'If a URL is not in the whitelist, it will be forced to noindex on the front-end.', 'sitemap-whitelist-for-yoast' ); ?></li>
				<li><?php echo esc_html__( 'The sitemap will only contain whitelisted URLs.', 'sitemap-whitelist-for-yoast' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Handle admin POST actions.
	 *
	 * @return void
	 */
	public function handle_admin_post() {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_POST['smwy_action'] ) ? sanitize_text_field( wp_unslash( $_POST['smwy_action'] ) ) : '';
		if ( empty( $action ) ) {
			return;
		}

		// Route.
		if ( 'save_textarea' === $action ) {
			$this->handle_save_textarea();
		} elseif ( 'import_csv' === $action ) {
			$this->handle_import_csv();
		} elseif ( 'export_csv' === $action ) {
			$this->handle_export_csv();
		} elseif ( 'clear' === $action ) {
			$this->handle_clear();
		}
	}

	/**
	 * Handle saving textarea.
	 *
	 * @return void
	 */
	private function handle_save_textarea() {
		$this->verify_nonce_or_die( 'smwy_whitelist_save' );

		$raw = isset( $_POST['smwy_whitelist_urls'] )
			? (string) wp_unslash( $_POST['smwy_whitelist_urls'] )
			: '';

		$lines = $this->explode_lines( $raw );
		$urls  = $this->normalize_and_dedupe_urls( $lines );

		update_option( self::OPTION_URLS, implode( "\n", $urls ), false );
		$this->update_meta();

		wp_safe_redirect( admin_url( 'options-general.php?page=sitemap-whitelist-for-yoast' ) );
		exit;
	}

	/**
	 * Handle CSV import.
	 *
	 * @return void
	 */
	private function handle_import_csv() {
		$this->verify_nonce_or_die( 'smwy_whitelist_import' );

		if ( empty( $_FILES['smwy_whitelist_csv'] ) || ! isset( $_FILES['smwy_whitelist_csv']['tmp_name'] ) ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=sitemap-whitelist-for-yoast' ) );
			exit;
		}

		$file = $_FILES['smwy_whitelist_csv'];

		if ( ! empty( $file['error'] ) ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=sitemap-whitelist-for-yoast' ) );
			exit;
		}

		$tmp_name = (string) $file['tmp_name'];

		$imported = $this->parse_csv_urls( $tmp_name );

		$merge = isset( $_POST['smwy_merge'] ) ? (bool) wp_unslash( $_POST['smwy_merge'] ) : false;

		$existing_raw = (string) get_option( self::OPTION_URLS, '' );
		$existing     = $this->explode_lines( $existing_raw );

		$combined = $merge ? array_merge( $existing, $imported ) : $imported;
		$urls     = $this->normalize_and_dedupe_urls( $combined );

		update_option( self::OPTION_URLS, implode( "\n", $urls ), false );
		$this->update_meta();

		wp_safe_redirect( admin_url( 'options-general.php?page=sitemap-whitelist-for-yoast' ) );
		exit;
	}

	/**
	 * Handle exporting CSV.
	 *
	 * @return void
	 */
	private function handle_export_csv() {
		$this->verify_nonce_or_die( 'smwy_whitelist_export' );

		$urls_raw = (string) get_option( self::OPTION_URLS, '' );
		$urls     = $this->normalize_and_dedupe_urls( $this->explode_lines( $urls_raw ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=sitemap-whitelist-for-yoast.csv' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

		fputcsv( $output, array( 'loc' ) );
		foreach ( $urls as $url ) {
			fputcsv( $output, array( $url ) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle clear.
	 *
	 * @return void
	 */
	private function handle_clear() {
		$this->verify_nonce_or_die( 'smwy_whitelist_clear' );

		update_option( self::OPTION_URLS, '', false );
		$this->update_meta();

		wp_safe_redirect( admin_url( 'options-general.php?page=sitemap-whitelist-for-yoast' ) );
		exit;
	}

	/**
	 * Verify nonce.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function verify_nonce_or_die( $action ) {
		$nonce = isset( $_POST['smwy_whitelist_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['smwy_whitelist_nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'sitemap-whitelist-for-yoast' ) );
		}
	}

	/**
	 * Update metadata.
	 *
	 * @return void
	 */
	private function update_meta() {
		$user = wp_get_current_user();
		$meta = array(
			'updated_at' => current_time( 'mysql' ),
			'updated_by' => $user instanceof WP_User ? $user->user_login : '',
		);
		update_option( self::OPTION_META, $meta, false );
	}

	/**
	 * Yoast robots filter: force noindex if URL not in whitelist.
	 *
	 * @param string|array $robots Robots string/array.
	 * @return string|array
	 */
	public function filter_wpseo_robots( $robots ) {
		if ( is_admin() ) {
			return $robots;
		}

		// Only affect front-end requests.
		if ( wp_doing_ajax() || wp_is_json_request() ) {
			return $robots;
		}

		$current = $this->get_current_url_normalized();
		if ( empty( $current ) ) {
			return $robots;
		}

		$allowed = $this->get_whitelist_set();
		if ( empty( $allowed ) ) {
			// Safety: if whitelist is empty, do not accidentally noindex the entire site.
			return $robots;
		}

		if ( isset( $allowed[ $current ] ) ) {
			return $robots;
		}

		// Enforce noindex but allow links to be followed.
		if ( is_array( $robots ) ) {
			$robots['index'] = 'noindex';
			// Keep follow as-is if present; otherwise default to follow.
			if ( ! isset( $robots['follow'] ) ) {
				$robots['follow'] = 'follow';
			}
			return $robots;
		}

		$robots_str = is_string( $robots ) ? $robots : '';
		$robots_str = strtolower( trim( $robots_str ) );

		if ( false === strpos( $robots_str, 'noindex' ) ) {
			if ( ! empty( $robots_str ) ) {
				$robots_str .= ', ';
			}
			$robots_str .= 'noindex';
		}

		// Ensure follow is present (Yoast often includes it already).
		if ( false === strpos( $robots_str, 'nofollow' ) && false === strpos( $robots_str, 'follow' ) ) {
			$robots_str .= ', follow';
		}

		return $robots_str;
	}

	/**
	 * Yoast sitemap entry filter: exclude non-whitelisted URLs.
	 *
	 * @param array  $url  URL entry (expects ['loc']).
	 * @param string $type Type.
	 * @param mixed  $obj  Object.
	 * @return array|false
	 */
	public function filter_wpseo_sitemap_entry( $url, $type, $obj ) {
		if ( empty( $url ) || ! is_array( $url ) || empty( $url['loc'] ) ) {
			return $url;
		}

		$allowed = $this->get_whitelist_set();
		if ( empty( $allowed ) ) {
			// Safety: do not empty sitemaps if whitelist is empty.
			return $url;
		}

		$loc = $this->normalize_url( (string) $url['loc'] );
		if ( empty( $loc ) ) {
			return false;
		}

		if ( isset( $allowed[ $loc ] ) ) {
			return $url;
		}

		return false;
	}

	/**
	 * Get whitelist as a hash set.
	 *
	 * @return array<string,bool>
	 */
	private function get_whitelist_set() {
		$raw  = (string) get_option( self::OPTION_URLS, '' );
		$urls = $this->normalize_and_dedupe_urls( $this->explode_lines( $raw ) );

		$set = array();
		foreach ( $urls as $u ) {
			$set[ $u ] = true;
		}
		return $set;
	}

	/**
	 * Parse CSV and extract URLs.
	 *
	 * Supports:
	 * - A header column named "loc"
	 * - OR first cell in a row that contains a URL
	 *
	 * @param string $filepath Temp path.
	 * @return string[]
	 */
	private function parse_csv_urls( $filepath ) {
		$urls = array();

		$handle = fopen( $filepath, 'r' );
		if ( false === $handle ) {
			return $urls;
		}

		$header     = null;
		$loc_index  = null;
		$first_row  = true;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( $first_row ) {
				$first_row = false;
				$header    = $row;

				if ( is_array( $header ) ) {
					foreach ( $header as $i => $col ) {
						$col = strtolower( trim( (string) $col ) );
						if ( 'loc' === $col ) {
							$loc_index = (int) $i;
							break;
						}
					}
				}

				// If the first row isn't a header, we still process it below by falling through.
				// Simple heuristic: if we found 'loc', treat first row as header and continue.
				if ( null !== $loc_index ) {
					continue;
				}
			}

			if ( ! is_array( $row ) ) {
				continue;
			}

			$candidate = '';

			if ( null !== $loc_index && isset( $row[ $loc_index ] ) ) {
				$candidate = (string) $row[ $loc_index ];
			} else {
				// Find first cell containing a URL.
				foreach ( $row as $cell ) {
					$cell_str = trim( (string) $cell );
					if ( 0 === stripos( $cell_str, 'http://' ) || 0 === stripos( $cell_str, 'https://' ) ) {
						$candidate = $cell_str;
						break;
					}
				}
			}

			$candidate = trim( $candidate );
			if ( ! empty( $candidate ) ) {
				$urls[] = $candidate;
			}
		}

		fclose( $handle );
		return $urls;
	}

	/**
	 * Analyze URL list for quick reporting.
	 *
	 * @param string[] $urls Lines.
	 * @return array<string,mixed>
	 */
	private function analyze_urls( $urls ) {
		$total_lines = count( $urls );

		$normalized = array();
		$valid      = array();
		$invalid    = array();

		foreach ( $urls as $line ) {
			$n = $this->normalize_url( $line );
			if ( empty( $n ) ) {
				continue;
			}
			$normalized[] = $n;

			if ( wp_http_validate_url( $n ) ) {
				$valid[] = $n;
			} else {
				$invalid[] = $line;
			}
		}

		$unique = array_values( array_unique( $normalized ) );

		return array(
			'total_lines'      => $total_lines,
			'valid_count'      => count( $valid ),
			'invalid_count'    => count( $invalid ),
			'duplicate_count'  => max( 0, count( $normalized ) - count( $unique ) ),
			'invalid_samples'  => array_slice( $invalid, 0, 10 ),
		);
	}

	/**
	 * Normalize and dedupe URLs.
	 *
	 * @param string[] $urls Raw URL lines.
	 * @return string[]
	 */
	private function normalize_and_dedupe_urls( $urls ) {
		$out = array();

		foreach ( $urls as $u ) {
			$n = $this->normalize_url( $u );
			if ( empty( $n ) ) {
				continue;
			}
			// Keep only valid URLs.
			if ( ! wp_http_validate_url( $n ) ) {
				continue;
			}
			$out[] = $n;
		}

		$out = array_values( array_unique( $out ) );
		sort( $out );

		return $out;
	}

	/**
	 * Normalize a URL.
	 *
	 * - Trims spaces
	 * - Removes fragments and query strings
	 * - Lowercases scheme + host
	 * - Normalizes trailing slash (keeps for homepage only)
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private function normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( empty( $url ) ) {
			return '';
		}

		// Clean up obvious accidental doubles like "https://example.com//path".
		// We only normalize path slashes after parsing.
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed ) || empty( $parsed['host'] ) || empty( $parsed['scheme'] ) ) {
			return '';
		}

		$scheme = strtolower( (string) $parsed['scheme'] );
		$host   = strtolower( (string) $parsed['host'] );

		$path = isset( $parsed['path'] ) ? (string) $parsed['path'] : '/';
		$path = preg_replace( '#/+#', '/', $path );
		if ( null === $path || '' === $path ) {
			$path = '/';
		}

		$base = $scheme . '://' . $host;

		// Homepage: keep trailing slash as canonical.
		if ( '/' === $path ) {
			return trailingslashit( $base );
		}

		// All other URLs: no trailing slash.
		return $base . untrailingslashit( $path );
	}

	/**
	 * Get current URL normalized (no query string).
	 *
	 * @return string
	 */
	private function get_current_url_normalized() {
		// WordPress-safe way to build the current URL.
		$scheme = is_ssl() ? 'https' : 'http';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( empty( $host ) ) {
			return '';
		}

		// Remove query string from request URI.
		$path = $uri;
		$qpos = strpos( $path, '?' );
		if ( false !== $qpos ) {
			$path = substr( $path, 0, $qpos );
		}

		$current = $scheme . '://' . $host . $path;
		return $this->normalize_url( $current );
	}

	/**
	 * Explode lines (handles CRLF, CR, LF).
	 *
	 * @param string $text Raw text.
	 * @return string[]
	 */
	private function explode_lines( $text ) {
		$text = (string) $text;
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );

		$lines = explode( "\n", $text );
		$out   = array();

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$out[] = $line;
		}

		return $out;
	}
}

Sitemap_Whitelist_for_Yoast::instance();

endif;