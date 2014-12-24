<?php
/*
Plugin Name: Autoptimize
Plugin URI: http://blog.futtta.be/autoptimize
Description: Optimizes your website, concatenating the CSS and JavaScript code, and compressing it.
Version: 1.9.2
Author: Frank Goossens (futtta)
Author URI: http://blog.futtta.be/
Domain Path: localization/
Text Domain: autoptimize
Released under the GNU General Public License (GPL)
http://www.gnu.org/licenses/gpl.txt
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Load config class
include(WP_PLUGIN_DIR.'/autoptimize/classes/autoptimizeConfig.php');

// Do we gzip when caching (needed early to load autoptimizeCache.php)
define('AUTOPTIMIZE_CACHE_NOGZIP',(bool) get_option('autoptimize_cache_nogzip'));

// Load cache class
include(WP_PLUGIN_DIR.'/autoptimize/classes/autoptimizeCache.php');

// wp-content dir, dirname of AO cache dir and AO-prefix can be overridden in wp-config.php
if (!defined('AUTOPTIMIZE_CACHE_CHILD_DIR')) { define('AUTOPTIMIZE_CACHE_CHILD_DIR','/cache/autoptimize/'); }
if (!defined('AUTOPTIMIZE_WP_CONTENT_NAME')) { define('AUTOPTIMIZE_WP_CONTENT_NAME','/wp-content'); }
if (!defined('AUTOPTIMIZE_CACHEFILE_PREFIX')) { define('AUTOPTIMIZE_CACHEFILE_PREFIX', 'autoptimize_'); }

// Plugin dir constants (plugin url's defined later to accomodate domain mapped sites)
if (is_multisite()) {
	$blog_id = get_current_blog_id();
	define('AUTOPTIMIZE_CACHE_DIR' , WP_CONTENT_DIR.AUTOPTIMIZE_CACHE_CHILD_DIR.$blog_id.'/' );
} else {
	define('AUTOPTIMIZE_CACHE_DIR',WP_CONTENT_DIR.AUTOPTIMIZE_CACHE_CHILD_DIR);
}
define('AUTOPTIMIZE_CACHE_DELAY',true);
define('WP_ROOT_DIR',str_replace(AUTOPTIMIZE_WP_CONTENT_NAME,'',WP_CONTENT_DIR));

// Initialize the cache at least once
$conf = autoptimizeConfig::instance();

/* Check if we're updating, in which case we might need to do stuff and flush the cache
to avoid old versions of aggregated files lingering around */

$autoptimize_version="1.9.2";
$autoptimize_db_version=get_option('autoptimize_version','none');

if ($autoptimize_db_version !== $autoptimize_version) {
	if ($autoptimize_db_version==="none") {
        	add_action('admin_notices', 'autoptimize_install_config_notice');
	} else {
		$autoptimize_major_version=substr($autoptimize_db_version,0,3);
		switch($autoptimize_major_version) {
			case "1.6":
				// from back in the days when I did not yet consider multisite
				// if user was on version 1.6.x, force advanced options to be shown by default
				update_option('autoptimize_show_adv','1');

				// and remove old options
				$to_delete_options=array("autoptimize_cdn_css","autoptimize_cdn_css_url","autoptimize_cdn_js","autoptimize_cdn_js_url","autoptimize_cdn_img","autoptimize_cdn_img_url","autoptimize_css_yui","autoptimize_js_yui");
				foreach ($to_delete_options as $del_opt) {
					delete_option( $del_opt );
				}

				// and notify user to check result
				add_action('admin_notices', 'autoptimize_update_config_notice');
			case "1.7":
				// force 3.8 dashicons in CSS exclude options when upgrading from 1.7 to 1.8
				if ( !is_multisite() ) {
					$css_exclude = get_option('autoptimize_css_exclude');
					if (empty($css_exclude)) {
						$css_exclude = "admin-bar.min.css, dashicons.min.css";
					} else if (strpos($css_exclude,"dashicons.min.css")===false) {
						$css_exclude .= ", dashicons.min.css";
					}
					update_option('autoptimize_css_exclude',$css_exclude);
				} else {
					global $wpdb;
					$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
					$original_blog_id = get_current_blog_id();
					foreach ( $blog_ids as $blog_id ) {
						switch_to_blog( $blog_id );
						$css_exclude = get_option('autoptimize_css_exclude');
						if (empty($css_exclude)) {
							$css_exclude = "admin-bar.min.css, dashicons.min.css";
						} else if (strpos($css_exclude,"dashicons.min.css")===false) {
							$css_exclude .= ", dashicons.min.css";
						}
						update_option('autoptimize_css_exclude',$css_exclude);
					}
					switch_to_blog( $original_blog_id );
				}
		}
	}
	
	autoptimizeCache::clearall();
	update_option('autoptimize_version',$autoptimize_version);
	$autoptimize_db_version=$autoptimize_version;
}

// Load translations
$plugin_dir = basename(dirname(__FILE__));
load_plugin_textdomain('autoptimize','wp-content/plugins/'.$plugin_dir.'/localization',$plugin_dir.'/localization');

function autoptimize_uninstall(){
	autoptimizeCache::clearall();
	
	$delete_options=array("autoptimize_cache_clean", "autoptimize_cache_nogzip", "autoptimize_css", "autoptimize_css_datauris", "autoptimize_css_justhead", "autoptimize_css_defer", "autoptimize_css_defer_inline", "autoptimize_css_inline", "autoptimize_css_exclude", "autoptimize_html", "autoptimize_html_keepcomments", "autoptimize_js", "autoptimize_js_exclude", "autoptimize_js_forcehead", "autoptimize_js_justhead", "autoptimize_js_trycatch", "autoptimize_version", "autoptimize_show_adv", "autoptimize_cdn_url");
	
	if ( !is_multisite() ) {
		foreach ($delete_options as $del_opt) {	delete_option( $del_opt ); }
	} else {
		global $wpdb;
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
		$original_blog_id = get_current_blog_id();
		foreach ( $blog_ids as $blog_id ) 
		{
			switch_to_blog( $blog_id );
			foreach ($delete_options as $del_opt) {	delete_option( $del_opt ); }
		}
		switch_to_blog( $original_blog_id );
	}
}

function autoptimize_install_config_notice() {
	echo '<div class="updated"><p>';
	_e('Thank you for installing and activating Autoptimize. Please configure it under "Settings" -> "Autoptimize" to start improving your site\'s performance.', 'autoptimize' );
	echo '</p></div>';
}

function autoptimize_update_config_notice() {
    echo '<div class="updated"><p>';
	_e('Autoptimize has just been updated. Please <strong>test your site now</strong> and adapt Autoptimize config if needed.', 'autoptimize' );
	echo '</p></div>';
	}

// Set up the buffering
function autoptimize_start_buffering() {
	$ao_noptimize = false;

	// filter you can use to block autoptimization on your own terms
	$ao_noptimize = (bool) apply_filters( 'autoptimize_filter_noptimize', $ao_noptimize );

	// noptimize in qs to get non-optimized page for debugging
	if (array_key_exists("ao_noptimize",$_GET)) {
		if ($_GET["ao_noptimize"]==="1") {
			$ao_noptimize = true;
		}
	}

	if (!is_feed() && !$ao_noptimize && !is_admin()) {

	// Config element
	$conf = autoptimizeConfig::instance();
	
	// Load our base class
	include(WP_PLUGIN_DIR.'/autoptimize/classes/autoptimizeBase.php');
	
	// Load extra classes and set some vars
	if($conf->get('autoptimize_html')) {
		include(WP_PLUGIN_DIR.'/autoptimize/classes/autoptimizeHTML.php');
		// BUG: new minify-html does not support keeping HTML comments, skipping for now
		// if (defined('AUTOPTIMIZE_LEGACY_MINIFIERS')) {
			@include(WP_PLUGIN_DIR.'/autoptimize/classes/external/php/minify-html.php');
		// } else {
		//	@include(WP_PLUGIN_DIR.'/autoptimize/classes/external/php/minify-2.1.7-html.php');
		// }
	}
	
	if($conf->get('autoptimize_js')) {
		include(WP_PLUGIN_DIR.'/autoptimize/classes/autoptimizeScripts.php');
		if (!class_exists('JSMin')) {
			if (defined('AUTOPTIMIZE_LEGACY_MINIFIERS')) {
				@include(WP_PLUGIN_DIR.'/autoptimize/classes/external/php/jsmin-1.1.1.php');
			} else {
				@include(WP_PLUGIN_DIR.'/autoptimize/classes/external/php/minify-2.1.7-jsmin.php');
			}
		}
		define('CONCATENATE_SCRIPTS',false);
		define('COMPRESS_SCRIPTS',false);
	}
	
	if($conf->get('autoptimize_css')) {
		include(WP_PLUGIN_DIR.'/autoptimize/classes/autoptimizeStyles.php');
		if (defined('AUTOPTIMIZE_LEGACY_MINIFIERS')) {
			if (!class_exists('Minify_CSS_Compressor')) {
				@include(WP_PLUGIN_DIR.'/autoptimize/classes/external/php/minify-css-compressor.php');
			}
		} else {
			if (!class_exists('CSSmin')) {
				@include(WP_PLUGIN_DIR.'/autoptimize/classes/external/php/yui-php-cssmin-2.4.8-3_fixes.php');
			}
		}
		define('COMPRESS_CSS',false);
	}
			
	// Now, start the real thing!
	ob_start('autoptimize_end_buffering');
	}
}

// Action on end, this is where the magic happens
function autoptimize_end_buffering($content) {
	if ( stripos($content,"<html") === false || stripos($content,"<xsl:stylesheet") !== false ) { return $content;}

	// load URL constants as late as possible to allow domain mapper to kick in
	if (function_exists("domain_mapping_siteurl")) {
		define('AUTOPTIMIZE_WP_SITE_URL',domain_mapping_siteurl(get_current_blog_id()));
		define('AUTOPTIMIZE_WP_CONTENT_URL',str_replace(get_original_url(AUTOPTIMIZE_WP_SITE_URL),AUTOPTIMIZE_WP_SITE_URL,content_url()));
	} else {
		define('AUTOPTIMIZE_WP_SITE_URL',site_url());
		define('AUTOPTIMIZE_WP_CONTENT_URL',content_url());
	}
	
	if ( is_multisite() ) {
        	$blog_id = get_current_blog_id();
        	define('AUTOPTIMIZE_CACHE_URL',AUTOPTIMIZE_WP_CONTENT_URL.AUTOPTIMIZE_CACHE_CHILD_DIR.$blog_id.'/' );
	} else {
		define('AUTOPTIMIZE_CACHE_URL',AUTOPTIMIZE_WP_CONTENT_URL.AUTOPTIMIZE_CACHE_CHILD_DIR);
	}
	define('AUTOPTIMIZE_WP_ROOT_URL',str_replace(AUTOPTIMIZE_WP_CONTENT_NAME,'',AUTOPTIMIZE_WP_CONTENT_URL));

	// Config element
	$conf = autoptimizeConfig::instance();
	
	// Choose the classes
	$classes = array();
	if($conf->get('autoptimize_js'))
		$classes[] = 'autoptimizeScripts';
	if($conf->get('autoptimize_css'))
		$classes[] = 'autoptimizeStyles';
	if($conf->get('autoptimize_html'))
		$classes[] = 'autoptimizeHTML';
		
	// Set some options
	$classoptions = array(
		'autoptimizeScripts' => array(
			'justhead' => $conf->get('autoptimize_js_justhead'),
			'forcehead' => $conf->get('autoptimize_js_forcehead'),
			'trycatch' => $conf->get('autoptimize_js_trycatch'),
			'js_exclude' => $conf->get('autoptimize_js_exclude'),
			'cdn_url' => $conf->get('autoptimize_cdn_url')
		),
		'autoptimizeStyles' => array(
			'justhead' => $conf->get('autoptimize_css_justhead'),
			'datauris' => $conf->get('autoptimize_css_datauris'),
			'defer' => $conf->get('autoptimize_css_defer'),
			'defer_inline' => $conf->get('autoptimize_css_defer_inline'),
			'inline' => $conf->get('autoptimize_css_inline'),
			'css_exclude' => $conf->get('autoptimize_css_exclude'),
			'cdn_url' => $conf->get('autoptimize_cdn_url')
		),
		'autoptimizeHTML' => array(
			'keepcomments' => $conf->get('autoptimize_html_keepcomments')
		)
	);
		
	// Run the classes
	foreach($classes as $name) {
		$instance = new $name($content);
		if($instance->read($classoptions[$name]))
		{
			$instance->minify();
			$instance->cache();
			$content = $instance->getcontent();
		}
		unset($instance);
	}
	$content = apply_filters( 'autoptimize_html_after_minify', $content );
	return $content;
}

function autoptimize_flush_pagecache($nothing) {
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
                w3tc_pgcache_flush(); // w3 total cache
        } else if ( function_exists('hyper_cache_invalidate') ) {
                hyper_cache_invalidate(); // hypercache
        } else if ( function_exists('wp_fast_cache_bulk_delete_all') ) {
                wp_fast_cache_bulk_delete_all(); // wp fast cache
        } else if (class_exists("WpFastestCache")) {
                $wpfc = new WpFastestCache(); // wp fastest cache
                $wpfc -> deleteCache();
        } else if ( class_exists("c_ws_plugin__qcache_purging_routines") ) {
                c_ws_plugin__qcache_purging_routines::purge_cache_dir(); // quick cache
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
add_action('ao_flush_pagecache','autoptimize_flush_pagecache',10,1);

if(autoptimizeCache::cacheavail()) {
	$conf = autoptimizeConfig::instance();
	if( $conf->get('autoptimize_html') || $conf->get('autoptimize_js') || $conf->get('autoptimize_css') || $conf->get('autoptimize_cdn_js') || $conf->get('autoptimize_cdn_css')) {
		// Hook to wordpress
		add_action('template_redirect','autoptimize_start_buffering',2);
	}
}

register_uninstall_hook(__FILE__, "autoptimize_uninstall");

// Do not pollute other plugins
unset($conf);
