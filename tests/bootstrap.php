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

    // For overriding cache dirs and whatnot. Kinda works if you keep a few things in mind.
    if ( getenv('CUSTOM_CONSTANTS' ) ) {
        define( 'AUTOPTIMIZE_CACHE_CHILD_DIR', '/c/ao/' );
        $pathname = WP_CONTENT_DIR . AUTOPTIMIZE_CACHE_CHILD_DIR;
        if ( is_multisite() && apply_filters( 'autoptimize_separate_blog_caches', true ) ) {
            $blog_id   = get_current_blog_id();
            $pathname .= $blog_id . '/';
        }
        define( 'AUTOPTIMIZE_CACHE_DIR', $pathname );

        $custom_site_url = 'http://localhost/wordpress';
        define( 'AUTOPTIMIZE_WP_SITE_URL', $custom_site_url );
        add_filter( 'site_url', function( $url, $path, $scheme, $blog_id ) use ( $custom_site_url ) {
            return $custom_site_url;
        }, 10, 4 );
        add_filter( 'content_url', function( $url, $path ) use ( $custom_site_url ) {
            return $custom_site_url . '/wp-content';
        }, 10, 2 );
        define( 'AO_TEST_SUBFOLDER_INSTALL', true );

        define( 'CUSTOM_CONSTANTS_USED', true );
    } else {
        define( 'CUSTOM_CONSTANTS_USED', false );
        define( 'AO_TEST_SUBFOLDER_INSTALL', false );
    }

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

    require dirname( dirname( __FILE__ ) ) . '/autoptimize.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
