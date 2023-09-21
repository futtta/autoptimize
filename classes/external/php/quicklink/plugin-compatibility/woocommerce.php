<?php
/**
 * The plugin compatibility for the WooCommerce plugin
 *
 * @package quicklink
 */

/**
 * The function that extends the default quicklink options for WooCommerce
 *
 * @param  array $options The default Quicklink options.
 *
 * @return array          The extended Quicklink options
 */
function quicklink_woocommerce_compatibility( $options ) {
	// Check if the WooCommerce  exists.
	global $woocommerce;
	$has_woocommerce = (
		isset( $woocommerce )
		&&
		class_exists( 'WooCommerce' )
		&&
		$woocommerce instanceof WooCommerce
	);
	if ( ! $has_woocommerce ) {
		return $options;
	}

	// Do not preload the 'my account' page, as it is usually ressource heavy.
	$myaccount_page_id = get_option( 'woocommerce_myaccount_page_id' );
	if ( $myaccount_page_id ) {
		$wc_ignores[] = '^' . preg_quote( get_permalink( $myaccount_page_id ), '/' );
	}

	// Do not preload the cart, as it is usally ressource heavy.
	$wc_ignores[] = '^' . preg_quote( wc_get_cart_url(), '/' );

	// Do not preload the checkout url for the same reason as above.
	$wc_ignores[] = '^' . preg_quote( wc_get_checkout_url(), '/' );

	// Remove possible empty strings and duplicates from the array.
	$wc_ignores = array_unique( array_filter( $wc_ignores ) );

	$options['ignores'] = array_merge( $options['ignores'], $wc_ignores );

	return $options;
}
add_filter( 'quicklink_options', 'quicklink_woocommerce_compatibility' );
