<?php
/**
 * WP tests bootstrap.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $tmp_dir = getenv( 'TMPDIR' );
    if ( ! empty( $tmp_dir ) ) {
        $_tests_dir = rtrim( $tmp_dir, '/' ) . '/wordpress-tests-lib';
        if ( ! is_dir( $_tests_dir ) ) {
            $_tests_dir = null;
        }
    }
}

if ( ! $_tests_dir ) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
    define( 'AUTOPTIMIZE_INIT_EARLIER', true );

    /*
    $active_plugins = array('autoptimize/autoptimize.php');
    update_option( 'active_plugins', $active_plugins );
    */

    update_option( 'autoptimize_js', 1 );
    update_option( 'autoptimize_css', 1 );
    update_option( 'autoptimize_html', 0 );
    update_option( 'autoptimize_cdn_url', 'http://cdn.example.org' );
    update_option( 'autoptimize_cache_nogzip', 1 );

    add_filter( 'autoptimize_css_include_inline', function( $include_inline ) {
        return true;
    });

    require dirname( dirname( __FILE__ ) ) . '/autoptimize-beta.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
