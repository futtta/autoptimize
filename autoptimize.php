<?php
/*
Plugin Name: Autoptimize
Plugin URI: http://blog.futtta.be/autoptimize
Description: Optimizes your website, concatenating the CSS and JavaScript code, and compressing it.
Version: 2.0.2
Author: Frank Goossens (futtta)
Author URI: http://blog.futtta.be/
Domain Path: localization/
Text Domain: autoptimize
Released under the GNU General Public License (GPL)
http://www.gnu.org/licenses/gpl.txt
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define('AUTOPTIMIZE_PLUGIN_DIR',plugin_dir_path(__FILE__));

// Load config class
include(AUTOPTIMIZE_PLUGIN_DIR.'classes/autoptimizeConfig.php');

// Load toolbar class
include( AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeToolbar.php' );

// Load partners tab if admin
if (is_admin()) {
	include AUTOPTIMIZE_PLUGIN_DIR.'classlesses/autoptimizePartners.php';
}

// Do we gzip when caching (needed early to load autoptimizeCache.php)
define('AUTOPTIMIZE_CACHE_NOGZIP',(bool) get_option('autoptimize_cache_nogzip'));

// Load cache class
include(AUTOPTIMIZE_PLUGIN_DIR.'/classes/autoptimizeCache.php');

// wp-content dir name (automagically set, should not be needed), dirname of AO cache dir and AO-prefix can be overridden in wp-config.php
if (!defined('AUTOPTIMIZE_WP_CONTENT_NAME')) { define('AUTOPTIMIZE_WP_CONTENT_NAME','/'.wp_basename( WP_CONTENT_DIR )); }
if (!defined('AUTOPTIMIZE_CACHE_CHILD_DIR')) { define('AUTOPTIMIZE_CACHE_CHILD_DIR','/cache/autoptimize/'); }
if (!defined('AUTOPTIMIZE_CACHEFILE_PREFIX')) { define('AUTOPTIMIZE_CACHEFILE_PREFIX', 'autoptimize_'); }

// Plugin dir constants (plugin url's defined later to accomodate domain mapped sites)
if (is_multisite() && apply_filters( 'autoptimize_separate_blog_caches' , true )) {
	$blog_id = get_current_blog_id();
	define('AUTOPTIMIZE_CACHE_DIR', WP_CONTENT_DIR.AUTOPTIMIZE_CACHE_CHILD_DIR.$blog_id.'/' );
} else {
	define('AUTOPTIMIZE_CACHE_DIR', WP_CONTENT_DIR.AUTOPTIMIZE_CACHE_CHILD_DIR);
}
define('AUTOPTIMIZE_CACHE_DELAY',true);
define('WP_ROOT_DIR',str_replace(AUTOPTIMIZE_WP_CONTENT_NAME,'',WP_CONTENT_DIR));

// Initialize the cache at least once
$conf = autoptimizeConfig::instance();

/* Check if we're updating, in which case we might need to do stuff and flush the cache
to avoid old versions of aggregated files lingering around */

$autoptimize_version="2.0.0";
$autoptimize_db_version=get_option('autoptimize_version','none');

if ($autoptimize_db_version !== $autoptimize_version) {
	if ($autoptimize_db_version==="none") {
    add_action('admin_notices', 'autoptimize_install_config_notice');
	} else {
		// updating, include the update-code
		include(AUTOPTIMIZE_PLUGIN_DIR.'/classlesses/autoptimizeUpdateCode.php');
	}

	update_option('autoptimize_version',$autoptimize_version);
	$autoptimize_db_version=$autoptimize_version;
}

// Load translations
load_plugin_textdomain('autoptimize',false,plugin_basename(dirname( __FILE__ )).'/localization');

function autoptimize_uninstall(){
	autoptimizeCache::clearall();

	$delete_options=array("autoptimize_cache_clean", "autoptimize_cache_nogzip", "autoptimize_css", "autoptimize_css_datauris", "autoptimize_css_justhead", "autoptimize_css_defer", "autoptimize_css_defer_inline", "autoptimize_css_inline", "autoptimize_css_exclude", "autoptimize_html", "autoptimize_html_keepcomments", "autoptimize_js", "autoptimize_js_exclude", "autoptimize_js_forcehead", "autoptimize_js_justhead", "autoptimize_js_trycatch", "autoptimize_version", "autoptimize_show_adv", "autoptimize_cdn_url", "autoptimize_cachesize_notice","autoptimize_css_include_inline","autoptimize_js_include_inline","autoptimize_css_nogooglefont");

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

	if ( wp_get_schedule( 'ao_cachechecker' ) ) {
		wp_clear_scheduled_hook( 'ao_cachechecker' );
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

function autoptimize_cache_unavailable_notice() {
	echo '<div class="error"><p>';
	_e('Autoptimize cannot write to the cache directory (default: /wp-content/cache/autoptimize), please fix to enable CSS/ JS optimization!', 'autoptimize' );
	echo '</p></div>';
}


// Set up the buffering
function autoptimize_start_buffering() {
	$ao_noptimize = false;

	// noptimize in qs to get non-optimized page for debugging
	if (array_key_exists("ao_noptimize",$_GET)) {
		if ( ($_GET["ao_noptimize"]==="1") && (apply_filters('autoptimize_filter_honor_qs_noptimize',true)) ) {
			$ao_noptimize = true;
		}
	}

	// check for DONOTMINIFY constant as used by e.g. WooCommerce POS
  if (defined('DONOTMINIFY') && (constant('DONOTMINIFY')===true || constant('DONOTMINIFY')==="true")) {
          $ao_noptimize = true;
  }

	// filter you can use to block autoptimization on your own terms
	$ao_noptimize = (bool) apply_filters( 'autoptimize_filter_noptimize', $ao_noptimize );

	if (!is_feed() && !$ao_noptimize && !is_admin() && !is_customize_preview()) {
		// Config element
		$conf = autoptimizeConfig::instance();

		// Load our base class
		include(AUTOPTIMIZE_PLUGIN_DIR.'classes/autoptimizeBase.php');

		// Load extra classes and set some vars
		if($conf->get('autoptimize_html')) {
			include(AUTOPTIMIZE_PLUGIN_DIR.'classes/autoptimizeHTML.php');
			// BUG: new minify-html does not support keeping HTML comments, skipping for now
			// if (defined('AUTOPTIMIZE_LEGACY_MINIFIERS')) {
				@include(AUTOPTIMIZE_PLUGIN_DIR.'classes/external/php/minify-html.php');
			// } else {
			//	@include(AUTOPTIMIZE_PLUGIN_DIR.'classes/external/php/minify-2.1.7-html.php');
			// }
		}

		if($conf->get('autoptimize_js')) {
			include(AUTOPTIMIZE_PLUGIN_DIR.'classes/autoptimizeScripts.php');
			if (!class_exists('JSMin')) {
				if (defined('AUTOPTIMIZE_LEGACY_MINIFIERS')) {
					@include(AUTOPTIMIZE_PLUGIN_DIR.'classes/external/php/jsmin-1.1.1.php');
				} else {
					@include(AUTOPTIMIZE_PLUGIN_DIR.'classes/external/php/minify-2.3.1-jsmin.php');
				}
			}
			if ( ! defined( 'CONCATENATE_SCRIPTS' )) {
				define('CONCATENATE_SCRIPTS',false);
			}
			if ( ! defined( 'COMPRESS_SCRIPTS' )) {
				define('COMPRESS_SCRIPTS',false);
			}
		}

		if($conf->get('autoptimize_css')) {
			include(AUTOPTIMIZE_PLUGIN_DIR.'classes/autoptimizeStyles.php');
			if (defined('AUTOPTIMIZE_LEGACY_MINIFIERS')) {
				if (!class_exists('Minify_CSS_Compressor')) {
					@include(AUTOPTIMIZE_PLUGIN_DIR.'classes/external/php/minify-css-compressor.php');
				}
			} else {
				if (!class_exists('CSSmin')) {
					@include(AUTOPTIMIZE_PLUGIN_DIR.'classes/external/php/yui-php-cssmin-2.4.8-4_fgo.php');
				}
			}
			if ( ! defined( 'COMPRESS_CSS' )) {
				define('COMPRESS_CSS',false);
			}
		}

		// Now, start the real thing!
		ob_start('autoptimize_end_buffering');
	}
}

// Action on end, this is where the magic happens
function autoptimize_end_buffering($content) {
	if ( ((stripos($content,"<html") === false) && (stripos($content,"<!DOCTYPE html") === false)) || preg_match('/<html[^>]*(?:amp|⚡)/',$content) === 1 || stripos($content,"<xsl:stylesheet") !== false ) { return $content; }
    
	// load URL constants as late as possible to allow domain mapper to kick in
	if (function_exists("domain_mapping_siteurl")) {
		define('AUTOPTIMIZE_WP_SITE_URL',domain_mapping_siteurl(get_current_blog_id()));
		define('AUTOPTIMIZE_WP_CONTENT_URL',str_replace(get_original_url(AUTOPTIMIZE_WP_SITE_URL),AUTOPTIMIZE_WP_SITE_URL,content_url()));
	} else {
		define('AUTOPTIMIZE_WP_SITE_URL',site_url());
		define('AUTOPTIMIZE_WP_CONTENT_URL',content_url());
	}

	if ( is_multisite() && apply_filters( 'autoptimize_separate_blog_caches' , true ) ) {
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
			'cdn_url' => $conf->get('autoptimize_cdn_url'),
			'include_inline' => $conf->get('autoptimize_js_include_inline')
		),
		'autoptimizeStyles' => array(
			'justhead' => $conf->get('autoptimize_css_justhead'),
			'datauris' => $conf->get('autoptimize_css_datauris'),
			'defer' => $conf->get('autoptimize_css_defer'),
			'defer_inline' => $conf->get('autoptimize_css_defer_inline'),
			'inline' => $conf->get('autoptimize_css_inline'),
			'css_exclude' => $conf->get('autoptimize_css_exclude'),
			'cdn_url' => $conf->get('autoptimize_cdn_url'),
			'include_inline' => $conf->get('autoptimize_css_include_inline'),
			'nogooglefont' => $conf->get('autoptimize_css_nogooglefont')
		),
		'autoptimizeHTML' => array(
			'keepcomments' => $conf->get('autoptimize_html_keepcomments')
		)
	);

	$content = apply_filters( 'autoptimize_filter_html_before_minify', $content );
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

if ( autoptimizeCache::cacheavail() ) {
	$conf = autoptimizeConfig::instance();
	if( $conf->get('autoptimize_html') || $conf->get('autoptimize_js') || $conf->get('autoptimize_css') ) {
		// Hook to wordpress
        if (defined('AUTOPTIMIZE_INIT_EARLIER')) {
            add_action('init','autoptimize_start_buffering',-1);
        } else {
			add_action('template_redirect','autoptimize_start_buffering',2);
        }
	}
} else {
	add_action('admin_notices', 'autoptimize_cache_unavailable_notice');
}

register_uninstall_hook(__FILE__, "autoptimize_uninstall");
include_once('classlesses/autoptimizeCacheChecker.php');

// Do not pollute other plugins
unset($conf);
