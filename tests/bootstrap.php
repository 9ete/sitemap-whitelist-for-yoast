<?php
/**
 * PHPUnit bootstrap.
 *
 * Defines the minimal set of WordPress function/class stubs the plugin needs so
 * its single class can be loaded and unit-tested without a full WordPress
 * install. The plugin is loaded with SWY_SKIP_BOOTSTRAP defined so it does NOT
 * auto-register hooks at include time — tests instantiate and register
 * explicitly with controlled state.
 *
 * @package Sitemap_Whitelist_for_Yoast
 */

declare( strict_types=1 );

define( 'ABSPATH', sys_get_temp_dir() . '/swy-fake-wp/' );
define( 'SWY_SKIP_BOOTSTRAP', true );

// ---------------------------------------------------------------------------
// Controllable test state.
// ---------------------------------------------------------------------------

$GLOBALS['swy_test_options']    = array();
$GLOBALS['swy_test_hooks']      = array();
$GLOBALS['swy_test_transients'] = array();
$GLOBALS['swy_test_state']      = array(
	'is_admin'       => false,
	'is_ssl'         => true,
	'doing_ajax'     => false,
	'is_json'        => false,
	'can'            => true,
	'nonce_valid'    => true,
	'current_user'   => 'tester',
	'user_id'        => 1,
	'active_plugins' => array(),
);

/**
 * Reset all mutable test state between tests.
 */
function swy_test_reset(): void {
	$GLOBALS['swy_test_options']    = array();
	$GLOBALS['swy_test_hooks']      = array();
	$GLOBALS['swy_test_transients'] = array();
	$GLOBALS['swy_test_state']      = array(
		'is_admin'       => false,
		'is_ssl'         => true,
		'doing_ajax'     => false,
		'is_json'        => false,
		'can'            => true,
		'nonce_valid'    => true,
		'current_user'   => 'tester',
		'user_id'        => 1,
		'active_plugins' => array(),
	);
}

/** Thrown by wp_safe_redirect / wp_die stubs so handler flow is testable without exit. */
class SWY_Redirect_Exception extends \Exception {
	public string $location = '';
}
class SWY_Die_Exception extends \Exception {}

if ( ! class_exists( 'WP_User' ) ) {
	class WP_User {
		public $user_login = 'tester';
		public function __construct( $login = 'tester' ) {
			$this->user_login = $login;
		}
	}
}

// ---------------------------------------------------------------------------
// Hooks.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) {
		$GLOBALS['swy_test_hooks'][] = array( 'type' => 'action', 'hook' => $hook, 'priority' => $priority );
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
		$GLOBALS['swy_test_hooks'][] = array( 'type' => 'filter', 'hook' => $hook, 'priority' => $priority );
		return true;
	}
}
if ( ! function_exists( 'remove_action' ) ) {
	function remove_action( $hook, $cb, $priority = 10 ) {
		return true;
	}
}

// ---------------------------------------------------------------------------
// Options.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['swy_test_options'] )
			? $GLOBALS['swy_test_options'][ $name ]
			: $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['swy_test_options'][ $name ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['swy_test_options'][ $name ] );
		return true;
	}
}

// ---------------------------------------------------------------------------
// i18n + escaping.
// ---------------------------------------------------------------------------

if ( ! function_exists( '__' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}
if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $text ) {
		return str_replace( array( "'", '"', "\n", "\r" ), array( "\\'", '\\"', '\\n', '' ), (string) $text );
	}
}

// ---------------------------------------------------------------------------
// Sanitization.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		$str = (string) $str;
		$str = preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( $str ) );
		return trim( (string) $str );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		// Strip tags but preserve newlines (unlike sanitize_text_field).
		$str = strip_tags( (string) $str );
		$str = preg_replace( '/[ \t]+/', ' ', $str );
		return trim( (string) $str );
	}
}
if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $name ) {
		return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $name );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

// ---------------------------------------------------------------------------
// URL helpers.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $text ) {
		return rtrim( (string) $text, '/\\' );
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $text ) {
		return untrailingslashit( $text ) . '/';
	}
}
if ( ! function_exists( 'wp_http_validate_url' ) ) {
	function wp_http_validate_url( $url ) {
		$url = (string) $url;
		$parsed = parse_url( $url );
		if ( empty( $parsed['scheme'] ) || ! in_array( strtolower( $parsed['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}
		if ( empty( $parsed['host'] ) ) {
			return false;
		}
		return $url;
	}
}

// ---------------------------------------------------------------------------
// Auth / nonce / context flags.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) {
		return (bool) $GLOBALS['swy_test_state']['can'];
	}
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return $GLOBALS['swy_test_state']['nonce_valid'] ? 1 : false;
	}
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		$field = '<input type="hidden" name="' . esc_attr( $name ) . '" value="testnonce" />';
		if ( $echo ) {
			echo $field; // phpcs:ignore
		}
		return $field;
	}
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return (bool) $GLOBALS['swy_test_state']['is_admin'];
	}
}
if ( ! function_exists( 'is_ssl' ) ) {
	function is_ssl() {
		return (bool) $GLOBALS['swy_test_state']['is_ssl'];
	}
}
if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() {
		return (bool) $GLOBALS['swy_test_state']['doing_ajax'];
	}
}
if ( ! function_exists( 'wp_is_json_request' ) ) {
	function wp_is_json_request() {
		return (bool) $GLOBALS['swy_test_state']['is_json'];
	}
}

// ---------------------------------------------------------------------------
// Misc WP.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql', $gmt = 0 ) {
		return '2026-01-01 00:00:00';
	}
}
if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() {
		return new WP_User( $GLOBALS['swy_test_state']['current_user'] );
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302 ) {
		$e = new SWY_Redirect_Exception( 'redirect' );
		$e->location = (string) $location;
		throw $e;
	}
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ) {
		throw new SWY_Die_Exception( is_string( $message ) ? $message : 'wp_die' );
	}
}
if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers() {}
}
if ( ! function_exists( 'add_options_page' ) ) {
	function add_options_page( $page_title, $menu_title, $cap, $slug, $cb ) {
		return $slug;
	}
}
if ( ! function_exists( 'add_settings_error' ) ) {
	function add_settings_error( $setting, $code, $message, $type = 'error' ) {}
}
if ( ! function_exists( 'is_plugin_active' ) ) {
	function is_plugin_active( $plugin ) {
		return in_array( $plugin, $GLOBALS['swy_test_state']['active_plugins'], true );
	}
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated = false, $path = false ) {
		return true;
	}
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return ( 1 === (int) $number ) ? $single : $plural;
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return (int) ( $GLOBALS['swy_test_state']['user_id'] ?? 1 );
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration = 0 ) {
		$GLOBALS['swy_test_transients'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return array_key_exists( $key, $GLOBALS['swy_test_transients'] )
			? $GLOBALS['swy_test_transients'][ $key ]
			: false;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['swy_test_transients'][ $key ] );
		return true;
	}
}

/**
 * Invoke a private/protected method on the plugin singleton (or any object)
 * so the pure-logic helpers can be unit-tested directly without widening the
 * production API surface.
 *
 * @param object $object Target object.
 * @param string $method Method name.
 * @param array  $args   Positional arguments.
 * @return mixed
 */
function swy_invoke( object $object, string $method, array $args = array() ) {
	$ref = new ReflectionMethod( $object, $method );
	// setAccessible() is required < 8.1 but a no-op since, and deprecated in 8.5.
	if ( PHP_VERSION_ID < 80100 ) {
		$ref->setAccessible( true );
	}
	return $ref->invokeArgs( $object, $args );
}

// ---------------------------------------------------------------------------
// Load the plugin under test.
// ---------------------------------------------------------------------------

require dirname( __DIR__ ) . '/sitemap-whitelist-for-yoast.php';
