<?php 
// flush as many page cache plugin's caches as possible
// hyper cache and gator cache hook into AO, so we don't need to :-)

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function autoptimize_flush_pagecache() {
    if(function_exists('wp_cache_clear_cache')) {
        if (is_multisite()) {
            $blog_id = get_current_blog_id();
            wp_cache_clear_cache($blog_id);
        } else {
            wp_cache_clear_cache();
        }
    } else if ( has_action('cachify_flush_cache') ) {
        do_action('cachify_flush_cache');
    } else if ( function_exists('w3tc_pgcache_flush') ) {
        w3tc_pgcache_flush();
    } else if ( function_exists('wp_fast_cache_bulk_delete_all') ) {
        wp_fast_cache_bulk_delete_all(); // still to retest
    } else if (class_exists("WpFastestCache")) {
        $wpfc = new WpFastestCache();
        $wpfc -> deleteCache();
    } else if ( class_exists("c_ws_plugin__qcache_purging_routines") ) {
        c_ws_plugin__qcache_purging_routines::purge_cache_dir(); // quick cache, still to retest
    } else if ( class_exists("zencache") ) {
        zencache::clear();
    } else if ( class_exists("comet_cache") ) {
        comet_cache::clear();
    } else if ( class_exists("WpeCommon") ) {
        if ( apply_filters('autoptimize_flush_wpengine_aggressive', false) ) {
            if ( method_exists( "WpeCommon", "purge_memcached" ) ) {
                WpeCommon::purge_memcached();
            }
            if ( method_exists( "WpeCommon", "clear_maxcdn_cache" ) ) {  
                WpeCommon::clear_maxcdn_cache();
            }
        }
        if ( method_exists( "WpeCommon", "purge_varnish_cache" ) ) {
            WpeCommon::purge_varnish_cache();   
        }
    } else if ( function_exists('sg_cachepress_purge_cache') ) {
        sg_cachepress_purge_cache();
    } else if(file_exists(WP_CONTENT_DIR.'/wp-cache-config.php') && function_exists('prune_super_cache')){
        // fallback for WP-Super-Cache
        global $cache_path;
        if (is_multisite()) {
            $blog_id = get_current_blog_id();
            prune_super_cache( get_supercache_dir( $blog_id ), true );
            prune_super_cache( $cache_path . 'blogs/', true );
        } else {
            prune_super_cache($cache_path.'supercache/',true);
            prune_super_cache($cache_path,true);
        }
    }
}