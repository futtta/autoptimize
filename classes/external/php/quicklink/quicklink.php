<?php
/**
 * The main file of the Quicklink for WordPress plugin
 *
 * @package quicklink
 * @version 0.10.0
 *
 * Plugin Name: Quicklink for WordPress
 * Plugin URI: https://wordpress.org/plugins/quicklink/
 * Description: ⚡️ Faster subsequent page-loads by prefetching in-viewport links during idle time.
 * Author: WP Munich
 * Author URI: https://www.wp-munich.com/?utm_source=wporg&utm_medium=plugin_repo&utm_campaign=description&utm_content=quicklink
 * Version: 0.10.0
 * Text Domain: quicklink
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'QUICKLINK_URL', plugin_dir_url( __FILE__ ) );
define( 'QUICKLINK_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Embed the scripts we need for this plugin
 */
function quicklink_enqueue_scripts() {
	// Abort if the response is AMP since custom JavaScript isn't allowed and Quicklink functionality would be part of the AMP runtime.
	if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
		return;
	}

	wp_enqueue_script( 'quicklink', '', array(), '0.10.0', true );

	$options = array(
		// CSS selector for the DOM element to observe for in-viewport links to prefetch.
		'el'        => '',

		// Static array of URLs to prefetch. Deprecated since plugin version 0.8.0.
		'urls'      => array(),

		// Integer for the `requestIdleCallback` timeout. A time in milliseconds by which the browser must execute prefetching. Defaults to 2 seconds.
		'timeout'   => 2000,

		// Function for specifying a timeout. Defaults to `requestIdleCallback`. Can also be swapped out for a custom function like [networkIdleCallback](https://github.com/pastelsky/network-idle-callback) (see demos).
		'timeoutFn' => 'requestIdleCallback',

		// Boolean specifying preferred priority for fetches. Defaults to `false`. `true` will attempt to use the `fetch()` API where supported (rather than rel=prefetch).
		'priority'  => false,

		// Static array of URL hostname strings that are allowed to be prefetched. Defaults to the same domain origin, which prevents _any_ cross-origin requests.
		'origins'   => array(
			wp_parse_url( home_url(), PHP_URL_HOST ),
		),

		// Array of regex strings that further determines if a URL should be prefetched. These execute _after_ origin matching.
		'ignores'   => array(
			// Do not preload feed links.
			preg_quote( 'feed=', '/' ),
			preg_quote( '/feed/', '/' ),

			// Do not preload self, including self with hash.
			'^https?:\/\/[^\/]+' . preg_quote( wp_unslash( $_SERVER['REQUEST_URI'] ), '/' ) . '(#.*)?$', // phpcs:ignore

			// Don't pre-fetch links to the admin since they could be nonce links.
			'^' . preg_quote( admin_url(), '/' ),

			// Don't pre-fetch links to PHP files, like wp-login.php.
			'^' . preg_quote( site_url(), '/' ) . '[^?#]+\.php',

			// Do not preload wp-content items (like downloads).
			preg_quote( wp_parse_url( content_url(), PHP_URL_PATH ), '/' ),

			// Do not preload links with get parameters.
			'.*\?.+',
		),
	);

	/**
	 * Filters Quicklink options.
	 *
	 * @link https://getquick.link/api/
	 * @param array {
	 *     Configuration options for Quicklink.
	 *
	 *     @param string   $el        CSS selector for the DOM element to observe for in-viewport links to prefetch.
	 *     @param int      $limit     The total requests that can be prefetched while observing the $el container.
	 *     @param int      $throttle  The concurrency limit for simultaneous requests while observing the $el container.
	 *     @param int      $timeout   Timeout after which prefetching will occur.
	 *     @param string   $timeoutFn Custom timeout function. Must refer to a named global function in JS.
	 *     @param bool     $priority  Attempt higher priority fetch (low or high). Default false.
	 *     @param string[] $origins   Allowed origins to prefetch (empty allows all). Defaults to host for current home URL.
	 *     @param string[] $ignores   Regular expression patterns to determine whether a URL is ignored. Runs after origin checks.
	 *     @param string   $onError   Custom error handler. Must refer to a named global function in JS.
	 *     @param string[] $urls      Array of URLs to prefetch. Deprecated as of plugin version 0.8.0
	 * }
	 */
	$options = apply_filters( 'quicklink_options', $options );

	/**
	 * Add a deprecation warning for the $options[urls] option.
	 *
	 * @since 0.8.0
	 */
	if ( isset( $options['urls'] ) && ! empty( $options['urls'] ) ) {
		_deprecated_argument(
			__FUNCTION__,
			'0.8.0',
			esc_attr( __( 'The "urls" argument has been deprecated in favor of the dedicated `quicklink_prefetch()` function.', 'quicklink' ) )
		);
	}

	wp_add_inline_script(
		'quicklink',
		sprintf( 'var quicklinkOptions = %s;', wp_json_encode( $options ) ),
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'quicklink_enqueue_scripts' );

/**
 * Add async attribute to Quicklink script tag.
 *
 * @link https://github.com/WordPress/twentynineteen/pull/646
 * @link https://github.com/wprig/wprig/blob/9a7c23d8d3db735259de6c338ddbb7cb7fd0ada1/dev/inc/template-functions.php#L41-L70
 * @link https://core.trac.wordpress.org/ticket/12009
 *
 * @param string $tag    Script tag.
 * @param string $handle Script handle.
 * @return string Script tag.
 */
function quicklink_add_async_attr_to_script_loader_tag( $tag, $handle ) {
	if ( 'quicklink' === $handle && false === strpos( $tag, 'async' ) ) {
		$tag = preg_replace( ':(?=></script>):', ' async', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'quicklink_add_async_attr_to_script_loader_tag', 10, 2 );

/**
 * Add some plugin compatibility files when everything is ready.
 *
 * @return void
 */
function quicklink_plugin_compatibility_files() {
	if ( class_exists( 'WooCommerce' ) ) {
		require_once QUICKLINK_PATH . '/plugin-compatibility/woocommerce.php';
	}
}
add_action( 'init', 'quicklink_plugin_compatibility_files' );

/**
 * Add quicklink to the default scripts to make it available earlier in the runtime.
 *
 * @param WP_Scripts $scripts The WP_Scripts instance.
 *
 * @return void
 */
function quicklink_to_default_scripts( $scripts ) {
	$scripts->add( 'quicklink', QUICKLINK_URL . 'quicklink.min.js', array(), '0.10.0' );
}
add_action( 'wp_default_scripts', 'quicklink_to_default_scripts' );

/**
 * Prefetch a url with quicklink.
 *
 * @param  string  $url         The URL to be prefetched.
 * @param  boolean $is_priority Whether or not the URL should be treated as high priority target.
 *
 * @return void
 */
function quicklink_prefetch( $url, $is_priority = false ) {
	wp_add_inline_script(
		'quicklink',
		sprintf(
			'window.addEventListener( "load", function() { quicklink.prefetch( %s, %s ); } );',
			wp_json_encode( $url ),
			wp_json_encode( $is_priority )
		),
		'after'
	);
}
