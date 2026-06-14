<?php
/**
 * Plugin Name:       Sitemap Whitelist for Yoast
 * Plugin URI:        https://lowermedia.net/plugins/sitemap-whitelist-for-yoast
 * Description:       Whitelist-based indexing and Yoast SEO sitemap filtering. Only URLs in your whitelist stay indexable and appear in Yoast-generated sitemaps.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  wordpress-seo
 * Author:            9ete
 * Author URI:        https://lowermedia.net
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sitemap-whitelist-for-yoast
 * Domain Path:       /languages
 *
 * @package Sitemap_Whitelist_For_Yoast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Sitemap_Whitelist_For_Yoast' ) ) :

	/**
	 * Sitemap Whitelist for Yoast
	 */
	final class Sitemap_Whitelist_For_Yoast {

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
		 * Per-user transient key prefix for one-time admin notices.
		 *
		 * @var string
		 */
		const NOTICE_TRANSIENT_PREFIX = 'swy_notice_';

		/**
		 * Maximum accepted CSV upload size, in bytes (5 MB).
		 *
		 * @var int
		 */
		const MAX_CSV_BYTES = 5242880;

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
		 * Constructor. Intentionally side-effect-free: building the singleton
		 * registers nothing, so it is safe to instantiate in tests. Call
		 * register() to wire the WordPress hooks.
		 */
		private function __construct() {}

		/**
		 * Register WordPress hooks.
		 *
		 * @return void
		 */
		public function register() {
			// Admin UI is always available so the settings screen and the
			// "Yoast is required" notice can render.
			add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
			add_action( 'admin_init', array( $this, 'handle_admin_post' ) );

			if ( $this->is_yoast_active() ) {
				// Yoast sitemap + robots filters.
				add_filter( 'wpseo_robots', array( $this, 'filter_wpseo_robots' ), 20 );
				add_filter( 'wpseo_sitemap_entry', array( $this, 'filter_wpseo_sitemap_entry' ), 20, 3 );
			} else {
				// Yoast inactive: the plugin is a no-op; tell admins why.
				add_action( 'admin_notices', array( $this, 'render_yoast_missing_notice' ) );
			}
		}

		/**
		 * Whether Yoast SEO (free or Premium) is active.
		 *
		 * Resolved at plugins_loaded so Yoast's main file — which loads after
		 * this plugin alphabetically — has already defined WPSEO_VERSION when
		 * present. The is_plugin_active() fallback is used in admin/test
		 * contexts where the constant may not be loaded.
		 *
		 * @return bool
		 */
		private function is_yoast_active() {
			if ( defined( 'WPSEO_VERSION' ) ) {
				return true;
			}
			if ( function_exists( 'is_plugin_active' ) ) {
				return is_plugin_active( 'wordpress-seo/wp-seo.php' )
					|| is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' );
			}
			return false;
		}

		/**
		 * Admin notice shown when Yoast SEO is inactive.
		 *
		 * @return void
		 */
		public function render_yoast_missing_notice() {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'Sitemap Whitelist for Yoast needs the Yoast SEO plugin to be active. Its sitemap and indexing filters are paused until Yoast SEO is activated.', 'sitemap-whitelist-for-yoast' )
			);
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

			<?php $this->render_admin_notice(); ?>

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
				<li>
					<?php
					/* translators: %d: total number of lines pasted into the whitelist. */
					echo esc_html( sprintf( __( 'Total lines: %d', 'sitemap-whitelist-for-yoast' ), (int) $counts['total_lines'] ) );
					?>
				</li>
				<li>
					<?php
					/* translators: %d: number of valid URLs in the whitelist. */
					echo esc_html( sprintf( __( 'Valid URLs: %d', 'sitemap-whitelist-for-yoast' ), (int) $counts['valid_count'] ) );
					?>
				</li>
				<li>
					<?php
					/* translators: %d: number of duplicate URLs removed on save. */
					echo esc_html( sprintf( __( 'Duplicates removed on save: %d', 'sitemap-whitelist-for-yoast' ), (int) $counts['duplicate_count'] ) );
					?>
				</li>
				<li>
					<?php
					/* translators: %d: number of invalid URLs in the whitelist. */
					echo esc_html( sprintf( __( 'Invalid URLs: %d', 'sitemap-whitelist-for-yoast' ), (int) $counts['invalid_count'] ) );
					?>
				</li>
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

				<label class="screen-reader-text" for="smwy_whitelist_urls">
						<?php echo esc_html__( 'Whitelisted URLs, one per line', 'sitemap-whitelist-for-yoast' ); ?>
					</label>
				<textarea
					id="smwy_whitelist_urls"
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

				<label class="screen-reader-text" for="smwy_whitelist_csv">
						<?php echo esc_html__( 'CSV file to import', 'sitemap-whitelist-for-yoast' ); ?>
					</label>
				<input type="file" id="smwy_whitelist_csv" name="smwy_whitelist_csv" accept=".csv,text/csv" required />

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

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Routing value only; each dispatched handler verifies its nonce via verify_nonce_or_die() before any state change.
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

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_nonce_or_die() above.
			$raw = isset( $_POST['smwy_whitelist_urls'] ) ? sanitize_textarea_field( wp_unslash( $_POST['smwy_whitelist_urls'] ) ) : '';

			$lines = $this->explode_lines( $raw );
			$urls  = $this->normalize_and_dedupe_urls( $lines );

			update_option( self::OPTION_URLS, implode( "\n", $urls ), false );
			$this->update_meta();

			wp_safe_redirect( $this->queue_notice( 'saved', count( $urls ) ) );
			exit;
		}

		/**
		 * Handle CSV import.
		 *
		 * @return void
		 */
		private function handle_import_csv() {
			$this->verify_nonce_or_die( 'smwy_whitelist_import' );

			$redirect = admin_url( 'options-general.php?page=sitemap-whitelist-for-yoast' );

			// Sanitize every $_FILES member up front. Nonce verified above.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$file = isset( $_FILES['smwy_whitelist_csv'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_FILES['smwy_whitelist_csv'] ) ) : array();

			$tmp  = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
			$name = isset( $file['name'] ) ? $file['name'] : '';
			$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
			$err  = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
			$ext  = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );

			// Validate: clean upload, a genuine HTTP upload (not an arbitrary
			// path), a .csv extension, and a sane non-zero size.
			if ( UPLOAD_ERR_OK !== $err
				|| '' === $tmp
				|| ! is_uploaded_file( $tmp )
				|| 'csv' !== $ext
				|| $size <= 0
				|| $size > self::MAX_CSV_BYTES
			) {
				wp_safe_redirect( $redirect );
				exit;
			}

			$imported = $this->parse_csv_urls( $tmp );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above; presence check only.
			$merge = ! empty( $_POST['smwy_merge'] );

			$this->apply_imported_urls( $imported, $merge );
		}

		/**
		 * Merge (or replace) imported URL candidates into the whitelist, persist
		 * the normalized result, and redirect with an "imported" notice.
		 *
		 * @param string[] $imported Raw URL candidates from the CSV.
		 * @param bool     $merge    Merge with the existing whitelist when true.
		 * @return void
		 */
		private function apply_imported_urls( $imported, $merge ) {
			$existing = $merge
				? $this->explode_lines( (string) get_option( self::OPTION_URLS, '' ) )
				: array();

			$urls = $this->normalize_and_dedupe_urls( array_merge( $existing, (array) $imported ) );

			update_option( self::OPTION_URLS, implode( "\n", $urls ), false );
			$this->update_meta();

			wp_safe_redirect( $this->queue_notice( 'imported', count( $urls ) ) );
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

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming CSV to php://output for a download response; WP_Filesystem cannot write to the output stream.
			$output = fopen( 'php://output', 'w' );
			if ( false === $output ) {
				exit;
			}

			// Pass an explicit escape ('') — omitting it is deprecated as of PHP 8.4.
			fputcsv( $output, array( 'loc' ), ',', '"', '' );
			foreach ( $urls as $url ) {
				fputcsv( $output, array( $url ), ',', '"', '' );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the php://output stream opened above.
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

			wp_safe_redirect( $this->queue_notice( 'cleared', 0 ) );
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
		 * Queue a one-time success notice for the current user and return the
		 * settings-page URL to redirect to.
		 *
		 * @param string $code  Notice code: saved|imported|cleared.
		 * @param int    $count Affected URL count, where relevant.
		 * @return string
		 */
		private function queue_notice( $code, $count = 0 ) {
			set_transient(
				self::NOTICE_TRANSIENT_PREFIX . get_current_user_id(),
				array(
					'code'  => (string) $code,
					'count' => (int) $count,
				),
				MINUTE_IN_SECONDS
			);
			return admin_url( 'options-general.php?page=sitemap-whitelist-for-yoast' );
		}

		/**
		 * Render (and consume) the current user's one-time admin notice.
		 *
		 * @return void
		 */
		private function render_admin_notice() {
			$key    = self::NOTICE_TRANSIENT_PREFIX . get_current_user_id();
			$notice = get_transient( $key );
			if ( empty( $notice ) || ! is_array( $notice ) ) {
				return;
			}
			delete_transient( $key );

			$code  = isset( $notice['code'] ) ? (string) $notice['code'] : '';
			$count = isset( $notice['count'] ) ? (int) $notice['count'] : 0;

			switch ( $code ) {
				case 'saved':
					$message = sprintf(
						/* translators: %d: number of whitelisted URLs. */
						_n(
							'Whitelist saved. %d URL is now indexable and included in the Yoast sitemap.',
							'Whitelist saved. %d URLs are now indexable and included in the Yoast sitemap.',
							$count,
							'sitemap-whitelist-for-yoast'
						),
						$count
					);
					break;
				case 'imported':
					$message = sprintf(
						/* translators: %d: number of whitelisted URLs after import. */
						_n(
							'Import complete. %d URL is now in the whitelist.',
							'Import complete. %d URLs are now in the whitelist.',
							$count,
							'sitemap-whitelist-for-yoast'
						),
						$count
					);
					break;
				case 'cleared':
					$message = __( 'Whitelist cleared. Yoast filtering is a no-op until you add URLs.', 'sitemap-whitelist-for-yoast' );
					break;
				default:
					return;
			}

			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $message )
			);
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

			// First try match by relative path (for lower env whitelists).
			$parsed = wp_parse_url( $current );
			$path   = isset( $parsed['path'] ) ? (string) $parsed['path'] : '';
			$path   = $this->normalize_path( $path );
			if ( '' !== $path && isset( $allowed[ $path ] ) ) {
				return $robots;
			}

			// Fallback: match by absolute URL.
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

			// Rebuild the directive list: drop any standalone "index" (it would
			// contradict noindex), guarantee "noindex", and keep follow/nofollow
			// plus any other directives Yoast set (e.g. max-snippet).
			$robots_str = is_string( $robots ) ? strtolower( trim( $robots ) ) : '';
			$parts      = array_values(
				array_filter(
					array_map( 'trim', explode( ',', $robots_str ) ),
					static function ( $token ) {
						return '' !== $token && 'index' !== $token;
					}
				)
			);

			if ( ! in_array( 'noindex', $parts, true ) ) {
				array_unshift( $parts, 'noindex' );
			}

			if ( ! in_array( 'follow', $parts, true ) && ! in_array( 'nofollow', $parts, true ) ) {
				$parts[] = 'follow';
			}

			return implode( ', ', $parts );
		}

		/**
		 * Yoast sitemap entry filter: exclude non-whitelisted URLs.
		 *
		 * @param array  $url  URL entry (expects ['loc']).
		 * @param string $type Type.
		 * @param mixed  $obj  Object.
		 * @return array|false
		 */
		public function filter_wpseo_sitemap_entry( $url, $type, $obj ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $type and $obj are part of the wpseo_sitemap_entry filter signature.
			if ( empty( $url ) || ! is_array( $url ) || empty( $url['loc'] ) ) {
				return $url;
			}

			$allowed = $this->get_whitelist_set();
			if ( empty( $allowed ) ) {
				// Safety: do not empty sitemaps if whitelist is empty.
				return $url;
			}

			$loc_raw = (string) $url['loc'];

			// Match by relative path if whitelist contains paths.
			$parsed = wp_parse_url( $loc_raw );
			$path   = isset( $parsed['path'] ) ? (string) $parsed['path'] : '';
			$path   = $this->normalize_path( $path );
			if ( '' !== $path && isset( $allowed[ $path ] ) ) {
				return $url;
			}

			// Otherwise match by absolute URL.
			$loc = $this->normalize_url( $loc_raw );
			if ( ! empty( $loc ) && isset( $allowed[ $loc ] ) ) {
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

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading an uploaded CSV row-by-row; WP_Filesystem has no streaming CSV reader.
			$handle = fopen( $filepath, 'r' );
			if ( false === $handle ) {
				return $urls;
			}

			$header    = null;
			$loc_index = null;
			$first_row = true;

			while ( true ) {
				// Pass an explicit escape ('') — omitting it is deprecated as of PHP 8.4.
				$row = fgetcsv( $handle, 0, ',', '"', '' );
				if ( false === $row ) {
					break;
				}
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

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the CSV file handle opened above.
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
				'total_lines'     => $total_lines,
				'valid_count'     => count( $valid ),
				'invalid_count'   => count( $invalid ),
				'duplicate_count' => max( 0, count( $normalized ) - count( $unique ) ),
				'invalid_samples' => array_slice( $invalid, 0, 10 ),
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
				// Support relative paths for lower environments (e.g. "/pricing").
				$u_trim = trim( (string) $u );
				if ( '' !== $u_trim && '/' === $u_trim[0] ) {
					$p = $this->normalize_path( $u_trim );
					if ( '' !== $p ) {
						$out[] = $p;
					}
					continue;
				}

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
		 * Normalize a relative path.
		 *
		 * - Ensures leading slash
		 * - Collapses duplicate slashes
		 * - Removes trailing slash except for root "/"
		 *
		 * @param string $path Raw path.
		 * @return string
		 */
		private function normalize_path( $path ) {
			$path = trim( (string) $path );
			if ( '' === $path ) {
				return '';
			}

			// Strip query/fragment if someone pasted it.
			$path = preg_replace( '/[?#].*$/', '', $path );

			if ( '' === $path ) {
				return '';
			}

			if ( '/' !== $path[0] ) {
				$path = '/' . $path;
			}

			$path = preg_replace( '#/+#', '/', $path );
			if ( null === $path || '' === $path ) {
				$path = '/';
			}

			if ( '/' === $path ) {
				return '/';
			}

			return untrailingslashit( $path );
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

	if ( ! defined( 'SWY_SKIP_BOOTSTRAP' ) ) {
		// Defer to plugins_loaded so Yoast (which loads after this plugin
		// alphabetically) is detectable by the register() guard.
		add_action( 'plugins_loaded', array( Sitemap_Whitelist_For_Yoast::instance(), 'register' ) );
	}

endif;
