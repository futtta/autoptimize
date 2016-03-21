<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class autoptimizeConfig {
	private $config = null;
	static private $instance = null;

	//Singleton: private construct
	private function __construct() {
		if( is_admin() ) {
			//Add the admin page and settings
			add_action('admin_menu',array($this,'addmenu'));
			add_action('admin_init',array($this,'registersettings'));

			//Set meta info
			if(function_exists('plugin_row_meta')) {
				//2.8+
				add_filter('plugin_row_meta',array($this,'setmeta'),10,2);
			} elseif(function_exists('post_class')) {
				//2.7
				$plugin = plugin_basename(AUTOPTIMIZE_PLUGIN_DIR.'/autoptimize.php');
				add_filter('plugin_action_links_'.$plugin,array($this,'setmeta'));
			}

			//Clean cache?
			if(get_option('autoptimize_cache_clean')) {
				autoptimizeCache::clearall();
				update_option('autoptimize_cache_clean',0);
			}
		}
	}
	
	static public function instance() {
		//Only one instance
		if (self::$instance == null) {
			self::$instance = new autoptimizeConfig();
		}
		
		return self::$instance;
    	}
	
	public function show() {
?>
<style>input[type=url]:invalid {color: red; border-color:red;} .form-table th{font-weight:100;} #futtta_feed ul{list-style:outside;} #futtta_feed {font-size:medium; margin:0px 20px;} #ao_hide_adv,#ao_show_adv{float:right;margin-top:10px;margin-right:10px;}</style>

<div class="wrap">

<h1><?php _e('Autoptimize Settings','autoptimize'); ?></h1>

<?php if (version_compare(PHP_VERSION, '5.3.0') < 0) { ?>
<div class="notice-error notice"><?php _e('<p><strong>You are using a very old version of PHP</strong> (5.2.x or older) which has <a href="http://blog.futtta.be/2016/03/15/why-would-you-still-be-on-php-5-2/" target="_blank">serious security and performance issues</a>. Please ask your hoster to provide you with an upgrade path to 5.6 or 7.0</p>','autoptimize'); ?></div>
<?php } ?>

<div style="float:left;width:70%;">

<?php echo $this->ao_admin_tabs(); ?>

<?php 
if (get_option('autoptimize_show_adv','0')=='1') {
	?>
	<a href="javascript:void(0);" id="ao_show_adv" class="button" style="display:none;"><?php _e("Show advanced settings","autoptimize") ?></a>
	<a href="javascript:void(0);" id="ao_hide_adv" class="button"><?php _e("Hide advanced settings","autoptimize") ?></a>
	<style>.ao_adv {display:table-row};</style>
	<?php
} else {
	?>
	<a href="javascript:void(0);" id="ao_show_adv" class="button"><?php _e("Show advanced settings","autoptimize") ?></a>
	<a href="javascript:void(0);" id="ao_hide_adv" class="button" style="display:none;"><?php _e("Hide advanced settings","autoptimize") ?></a>
	<?php
}
?>

<form method="post" action="options.php">
<?php settings_fields('autoptimize'); ?>

<h2><?php _e('HTML Options','autoptimize'); ?></h2>
<table class="form-table">
<tr valign="top">
<th scope="row"><?php _e('Optimize HTML Code?','autoptimize'); ?></th>
<td><input type="checkbox" id="autoptimize_html" name="autoptimize_html" <?php echo get_option('autoptimize_html')?'checked="checked" ':''; ?>/></td>
</tr>
<tr class="html_sub" valign="top">
<th scope="row"><?php _e('Keep HTML comments?','autoptimize'); ?></th>
<td><label for="autoptimize_html_keepcomments"><input type="checkbox" name="autoptimize_html_keepcomments" <?php echo get_option('autoptimize_html_keepcomments')?'checked="checked" ':''; ?>/>
<?php _e('Enable this if you want HTML comments to remain in the page.','autoptimize'); ?></label></td>
</tr>
</table>

<h2><?php _e('JavaScript Options','autoptimize'); ?></h2>
<table class="form-table"> 
<tr valign="top">
<th scope="row"><?php _e('Optimize JavaScript Code?','autoptimize'); ?></th>
<td><input type="checkbox" id="autoptimize_js" name="autoptimize_js" <?php echo get_option('autoptimize_js')?'checked="checked" ':''; ?>/></td>
</tr>
<tr valign="top" class="hidden js_sub ao_adv">
<th scope="row"><?php _e('Force JavaScript in &lt;head&gt;?','autoptimize'); ?></th>
<td><label for="autoptimize_js_forcehead"><input type="checkbox" name="autoptimize_js_forcehead" <?php echo get_option('autoptimize_js_forcehead','1')?'checked="checked" ':''; ?>/>
<?php _e('Load JavaScript early, reducing the chance of JS-errors but making it render blocking. You can disable this if you\'re not aggregating inline JS and you want JS to be deferred.','autoptimize'); ?></label></td>
</tr>
<?php if (get_option('autoptimize_js_justhead')) { ?>
<tr valign="top" class="hidden js_sub ao_adv">
<th scope="row"><?php _e('Look for scripts only in &lt;head&gt;?','autoptimize');  _e(' <i>(deprecated)</i>','autoptimize'); ?></th>
<td><label for="autoptimize_js_justhead"><input type="checkbox" name="autoptimize_js_justhead" <?php echo get_option('autoptimize_js_justhead')?'checked="checked" ':''; ?>/>
<?php _e('Mostly useful in combination with previous option when using jQuery-based templates, but might help keeping cache size under control.','autoptimize'); ?></label></td>
</tr>
<?php } ?>
<tr valign="top" class="hidden js_sub ao_adv">
<th scope="row"><?php _e('Also aggregate inline JS?','autoptimize'); ?></th>
<td><label for="autoptimize_js_include_inline"><input type="checkbox" name="autoptimize_js_include_inline" <?php echo get_option('autoptimize_js_include_inline')?'checked="checked" ':''; ?>/>
<?php _e('Check this option for Autoptimize to also aggregate JS in the HTML. If this option is not enabled, you might have to "force JavaScript in head".','autoptimize'); ?></label></td>
</tr>
<tr valign="top" class="hidden js_sub ao_adv">
<th scope="row"><?php _e('Exclude scripts from Autoptimize:','autoptimize'); ?></th>
<td><label for="autoptimize_js_exclude"><input type="text" style="width:100%;" name="autoptimize_js_exclude" value="<?php echo get_option('autoptimize_js_exclude',"s_sid,smowtion_size,sc_project,WAU_,wau_add,comment-form-quicktags,edToolbar,ch_client,seal.js"); ?>"/><br />
<?php _e('A comma-seperated list of scripts you want to exclude from being optimized, for example \'whatever.js, another.js\' (without the quotes) to exclude those scripts from being aggregated and minimized by Autoptimize.','autoptimize'); ?></label></td>
</tr>
<tr valign="top" class="hidden js_sub ao_adv">
<th scope="row"><?php _e('Add try-catch wrapping?','autoptimize'); ?></th>
<td><label for="autoptimize_js_trycatch"><input type="checkbox" name="autoptimize_js_trycatch" <?php echo get_option('autoptimize_js_trycatch')?'checked="checked" ':''; ?>/>
<?php _e('If your scripts break because of a JS-error, you might want to try this.','autoptimize'); ?></label></td>
</tr>
</table>

<h2><?php _e('CSS Options','autoptimize'); ?></h2>
<table class="form-table"> 
<tr valign="top">
<th scope="row"><?php _e('Optimize CSS Code?','autoptimize'); ?></th>
<td><input type="checkbox" id="autoptimize_css" name="autoptimize_css" <?php echo get_option('autoptimize_css')?'checked="checked" ':''; ?>/></td>
</tr>
<tr class="hidden css_sub ao_adv" valign="top">
<th scope="row"><?php _e('Generate data: URIs for images?','autoptimize'); ?></th>
<td><label for="autoptimize_css_datauris"><input type="checkbox" name="autoptimize_css_datauris" <?php echo get_option('autoptimize_css_datauris')?'checked="checked" ':''; ?>/>
<?php _e('Enable this to include small background-images in the CSS itself instead of as seperate downloads.','autoptimize'); ?></label></td>
</tr>
<tr class="hidden css_sub ao_adv" valign="top">
<th scope="row"><?php _e('Remove Google Fonts?','autoptimize'); ?></th>
<td><label for="autoptimize_css_datauris"><input type="checkbox" name="autoptimize_css_nogooglefont" <?php echo get_option('autoptimize_css_nogooglefont')?'checked="checked" ':''; ?>/>
<?php _e('Check this if you don\'t need or want Google Fonts being loaded.','autoptimize'); ?></label></td>
</tr>
<?php if (get_option('autoptimize_css_justhead')) { ?>
<tr valign="top" class="hidden css_sub ao_adv">
<th scope="row"><?php _e('Look for styles only in &lt;head&gt;?','autoptimize'); _e(' <i>(deprecated)</i>','autoptimize'); ?></th>
<td><label for="autoptimize_css_justhead"><input type="checkbox" name="autoptimize_css_justhead" <?php echo get_option('autoptimize_css_justhead')?'checked="checked" ':''; ?>/>
<?php _e('Don\'t autoptimize CSS outside the head-section. If the cache gets big, you might want to enable this.','autoptimize'); ?></label></td>
</tr>
<?php } ?>
<tr valign="top" class="hidden css_sub ao_adv">
<th scope="row"><?php _e('Also aggregate inline CSS?','autoptimize'); ?></th>
<td><label for="autoptimize_css_include_inline"><input type="checkbox" name="autoptimize_css_include_inline" <?php echo get_option('autoptimize_css_include_inline')?'checked="checked" ':''; ?>/>
<?php _e('Check this option for Autoptimize to also aggregate CSS in the HTML.','autoptimize'); ?></label></td>
</tr>
<tr valign="top" class="hidden css_sub ao_adv">
<th scope="row"><?php _e('Inline and Defer CSS?','autoptimize'); ?></th>
<td><label for="autoptimize_css_defer"><input type="checkbox" name="autoptimize_css_defer" id="autoptimize_css_defer" <?php echo get_option('autoptimize_css_defer')?'checked="checked" ':''; ?>/>
<?php _e('Inline "above the fold CSS" while loading the main autoptimized CSS only after page load. <a href="http://wordpress.org/plugins/autoptimize/faq/" target="_blank">Check the FAQ</a> before activating this option!','autoptimize'); ?></label></td>
</tr>
<tr valign="top" class="hidden css_sub ao_adv" id="autoptimize_css_defer_inline">
<th scope="row"></th>
<td><label for="autoptimize_css_defer_inline"><textarea rows="10" cols="10" style="width:100%;" placeholder="<?php _e('Paste the above the fold CSS here.','autoptimize'); ?>" name="autoptimize_css_defer_inline"><?php echo get_option('autoptimize_css_defer_inline'); ?></textarea></label></td>
</tr>
<tr valign="top" class="hidden ao_adv css_sub">
<th scope="row"><?php _e('Inline all CSS?','autoptimize'); ?></th>
<td><label for="autoptimize_css_inline"><input type="checkbox" id="autoptimize_css_inline" name="autoptimize_css_inline" <?php echo get_option('autoptimize_css_inline')?'checked="checked" ':''; ?>/>
<?php _e('Inlining all CSS can improve performance for sites with a low pageviews/ visitor-rate, but may slow down performance otherwise.','autoptimize'); ?></label></td>
</tr>
<tr valign="top" class="hidden ao_adv css_sub">
<th scope="row"><?php _e('Exclude CSS from Autoptimize:','autoptimize'); ?></th>
<td><label for="autoptimize_css_exclude"><input type="text" style="width:100%;" name="autoptimize_css_exclude" value="<?php echo get_option('autoptimize_css_exclude','admin-bar.min.css, dashicons.min.css'); ?>"/><br />
<?php _e('A comma-seperated list of CSS you want to exclude from being optimized.','autoptimize'); ?></label></td>
</tr>
</table>

<h2><?php _e('CDN Options','autoptimize'); ?></h2>
<table class="form-table"> 
<tr valign="top">
<th scope="row"><?php _e('CDN Base URL','autoptimize'); ?></th>
<td><label for="autoptimize_url"><input id="cdn_url" type="url" name="autoptimize_cdn_url" pattern="^(https?:)?\/\/([\da-z\.-]+)\.([\da-z\.]{2,6})([\/\w \.-]*)*(:\d{2,5})?\/?$" style="width:100%" value="<?php $it = get_option('autoptimize_cdn_url','');echo htmlentities($it); ?>" /><br />
<?php _e('Enter your CDN blog root directory URL if you want to enable CDN for images referenced in the CSS.','autoptimize'); ?></label></td>
</tr>
</table>

<h2 class="hidden ao_adv"><?php _e('Cache Info','autoptimize'); ?></h2>
<table class="form-table" > 
<tr valign="top" class="hidden ao_adv">
<th scope="row"><?php _e('Cache folder','autoptimize'); ?></th>
<td><?php echo htmlentities(AUTOPTIMIZE_CACHE_DIR); ?></td>
</tr>
<tr valign="top" class="hidden ao_adv">
<th scope="row"><?php _e('Can we write?','autoptimize'); ?></th>
<td><?php echo (autoptimizeCache::cacheavail() ? __('Yes','autoptimize') : __('No','autoptimize')); ?></td>
</tr>
<tr valign="top" class="hidden ao_adv">
<th scope="row"><?php _e('Cached styles and scripts','autoptimize'); ?></th>
<td><?php
	$AOstatArr=autoptimizeCache::stats(); 
	$AOcacheSize=round($AOstatArr[1]/1024);
	echo $AOstatArr[0].__(' files, totalling ','autoptimize').$AOcacheSize.__(' Kbytes (calculated at ','autoptimize').date("H:i e", $AOstatArr[2]).')';
?></td>
</tr>
<tr valign="top" class="hidden ao_adv">
<th scope="row"><?php _e('Save aggregated script/css as static files?','autoptimize'); ?></th>
<td><label for="autoptimize_cache_nogzip"><input type="checkbox" name="autoptimize_cache_nogzip" <?php echo get_option('autoptimize_cache_nogzip','1')?'checked="checked" ':''; ?>/>
<?php _e('By default files saved are static css/js, uncheck this option if your webserver doesn\'t properly handle the compression and expiry.','autoptimize'); ?></label></td>
</tr>
</table>
<input type="hidden" id="autoptimize_show_adv" name="autoptimize_show_adv" value="<?php echo get_option('autoptimize_show_adv','0'); ?>">

<p class="submit">
<input type="submit" class="button-secondary" value="<?php _e('Save Changes','autoptimize') ?>" />
<input type="submit" class="button-primary" name="autoptimize_cache_clean" value="<?php _e('Save Changes and Empty Cache','autoptimize') ?>" />
</p>

</form>
</div>
<style>.autoptimize_banner ul li {font-size:medium;text-align:center;} .unslider-arrow {left:unset;}</style>
<div class="autoptimize_banner">
	<ul>
        <?php
        if (apply_filters('autoptimize_settingsscreen_remotehttp',true)) {
            $AO_banner=get_transient("autoptimize_banner");
            if (empty($AO_banner)) {
                $banner_resp = wp_remote_get("http://optimizingmatters.com/autoptimize_news.html");
                if (!is_wp_error($banner_resp)) {
                    if (wp_remote_retrieve_response_code($banner_resp)=="200") {
                        $AO_banner = wp_kses_post(wp_remote_retrieve_body($banner_resp));
                        set_transient("autoptimize_banner",$AO_banner,DAY_IN_SECONDS);
                    }
                }
            }
            echo $AO_banner;
        }
        ?>
        <li><?php _e("Need help? <a href='https://wordpress.org/plugins/autoptimize/faq/'>Check out the FAQ</a> or post your question on <a href='http://wordpress.org/support/plugin/autoptimize'>the support-forum</a>.","autoptimize"); ?></li>
        <li><?php _e("Happy with Autoptimize?","autoptimize"); ?><br /><a href="<?php echo network_admin_url(); ?>plugin-install.php?tab=search&type=author&s=optimizingmatters"><?php _e("Try my other plugins!","autoptimize"); ?></a></li>
	</ul>
</div>
<div style="float:right;width:30%" id="autoptimize_admin_feed">
        <div style="margin-left:10px;margin-top:-5px;">
                <h2>
                        <?php _e("futtta about","autoptimize") ?>
                        <select id="feed_dropdown" >
                                <option value="1"><?php _e("Autoptimize","autoptimize") ?></option>
                                <option value="2"><?php _e("WordPress","autoptimize") ?></option>
                                <option value="3"><?php _e("Web Technology","autoptimize") ?></option>
                        </select>
                </h2>
                <div id="futtta_feed">
       			<div id="autoptimizefeed">
				<?php $this->getFutttaFeeds("http://feeds.feedburner.com/futtta_autoptimize"); ?>
			</div>
			<div id="wordpressfeed">
				<?php $this->getFutttaFeeds("http://feeds.feedburner.com/futtta_wordpress"); ?>
			</div>
			<div id="webtechfeed">
				<?php $this->getFutttaFeeds("http://feeds.feedburner.com/futtta_webtech"); ?>
			</div>
                </div>
        </div>
	<div style="float:right;margin:50px 15px;"><a href="http://blog.futtta.be/2013/10/21/do-not-donate-to-me/" target="_blank"><img width="100px" height="85px" src="<?php echo content_url(); ?>/plugins/autoptimize/classes/external/do_not_donate_smallest.png" title="<?php _e("Do not donate for this plugin!"); ?>"></a></div>
</div>

<script type="text/javascript">
	var feed = new Array;
	feed[1]="autoptimizefeed";
	feed[2]="wordpressfeed";
	feed[3]="webtechfeed";
	cookiename="autoptimize_feed";

	jQuery(document).ready(function() {
		check_ini_state();
		
		jQuery('.autoptimize_banner').unslider({autoplay:true, delay:5000});
		
		jQuery( "#ao_show_adv" ).click(function() {
			jQuery( "#ao_show_adv" ).hide();
			jQuery( "#ao_hide_adv" ).show();
			jQuery( ".ao_adv" ).show("slow");
			if (jQuery("#autoptimize_css").attr('checked')) {
				jQuery(".css_sub:visible").fadeTo("fast",1);
				if (!jQuery("#autoptimize_css_defer").attr('checked')) {
					jQuery("#autoptimize_css_defer_inline").hide();
				}
			}
			if (jQuery("#autoptimize_js").attr('checked')) {
				jQuery(".js_sub:visible").fadeTo("fast",1);
			}
			check_ini_state()
			jQuery( "input#autoptimize_show_adv" ).val("1");
		});

		jQuery( "#ao_hide_adv" ).click(function() {
			jQuery( "#ao_hide_adv" ).hide();
			jQuery( "#ao_show_adv" ).show();
			jQuery( ".ao_adv" ).hide("slow");
                        if (!jQuery("#autoptimize_css").attr('checked')) {
                                jQuery(".css_sub:visible").fadeTo("fast",.33);
                        }
                        if (!jQuery("#autoptimize_js").attr('checked')) {
                                jQuery(".js_sub:visible").fadeTo("fast",.33);
                        }
                        check_ini_state()
			jQuery( "input#autoptimize_show_adv" ).val("0");
		});

		jQuery( "#autoptimize_html" ).change(function() {
			if (this.checked) {
				jQuery(".html_sub:visible").fadeTo("fast",1);
			} else {
				jQuery(".html_sub:visible").fadeTo("fast",.33);
			}
		});

                jQuery( "#autoptimize_js" ).change(function() {
                        if (this.checked) {
                                jQuery(".js_sub:visible").fadeTo("fast",1);
                        } else {
                                jQuery(".js_sub:visible").fadeTo("fast",.33);
                        }
                });

                jQuery( "#autoptimize_css" ).change(function() {
                        if (this.checked) {
                                jQuery(".css_sub:visible").fadeTo("fast",1);
                        } else {
                                jQuery(".css_sub:visible").fadeTo("fast",.33);
                        }
                });
		
		jQuery( "#autoptimize_css_inline" ).change(function() {
			if (this.checked) {
				jQuery("#autoptimize_css_defer").prop("checked",false);
				jQuery("#autoptimize_css_defer_inline").hide("slow");
			}
		});
		
		jQuery( "#autoptimize_css_defer" ).change(function() {
			if (this.checked) {
				jQuery("#autoptimize_css_inline").prop("checked",false);
				jQuery("#autoptimize_css_defer_inline").show("slow");
			} else {
				jQuery("#autoptimize_css_defer_inline").hide("slow");
			}
		});
		
		jQuery("#feed_dropdown").change(function() { show_feed(jQuery("#feed_dropdown").val()) });
		feedid=jQuery.cookie(cookiename);
		if(typeof(feedid) !== "string") feedid=1;
		show_feed(feedid);
	})

	function check_ini_state() {
		if (!jQuery("#autoptimize_css_defer").attr('checked')) {
			jQuery("#autoptimize_css_defer_inline").hide();
		}
		if (!jQuery("#autoptimize_html").attr('checked')) {
			jQuery(".html_sub:visible").fadeTo('fast',.33);
		}
                if (!jQuery("#autoptimize_css").attr('checked')) {
                        jQuery(".css_sub:visible").fadeTo('fast',.33);
                }
                if (!jQuery("#autoptimize_js").attr('checked')) {
                        jQuery(".js_sub:visible").fadeTo('fast',.33);
                }
	}

	function show_feed(id) {
		jQuery('#futtta_feed').children().hide();
		jQuery('#'+feed[id]).show();
		jQuery("#feed_dropdown").val(id);
		jQuery.cookie(cookiename,id,{ expires: 365 });
	}
</script>
</div>

<?php
	}
	
	public function addmenu() {
		$hook=add_options_page(__('Autoptimize Options','autoptimize'),'Autoptimize','manage_options','autoptimize',array($this,'show'));
        	add_action( 'admin_print_scripts-'.$hook,array($this,'autoptimize_admin_scripts'));
        	add_action( 'admin_print_styles-'.$hook,array($this,'autoptimize_admin_styles'));
	}

	public function autoptimize_admin_scripts() {
		wp_enqueue_script('jqcookie', plugins_url('/external/js/jquery.cookie.min.js', __FILE__), array('jquery'),null,true);
		wp_enqueue_script('unslider', plugins_url('/external/js/unslider-min.js', __FILE__), array('jquery'),null,true);
	}

	public function autoptimize_admin_styles() {
		wp_enqueue_style('unslider', plugins_url('/external/js/unslider.css', __FILE__));
		wp_enqueue_style('unslider-dots', plugins_url('/external/js/unslider-dots.css', __FILE__));
	}


	public function registersettings() {
		register_setting('autoptimize','autoptimize_html');
		register_setting('autoptimize','autoptimize_html_keepcomments');
		register_setting('autoptimize','autoptimize_js');
		register_setting('autoptimize','autoptimize_js_exclude');
		register_setting('autoptimize','autoptimize_js_trycatch');
		register_setting('autoptimize','autoptimize_js_justhead');
		register_setting('autoptimize','autoptimize_js_forcehead');
		register_setting('autoptimize','autoptimize_js_include_inline');
		register_setting('autoptimize','autoptimize_css');
		register_setting('autoptimize','autoptimize_css_exclude');
		register_setting('autoptimize','autoptimize_css_justhead');
		register_setting('autoptimize','autoptimize_css_datauris');
		register_setting('autoptimize','autoptimize_css_defer');
		register_setting('autoptimize','autoptimize_css_defer_inline');
		register_setting('autoptimize','autoptimize_css_inline');
		register_setting('autoptimize','autoptimize_css_include_inline');
		register_setting('autoptimize','autoptimize_css_nogooglefont');
		register_setting('autoptimize','autoptimize_cdn_url');
		register_setting('autoptimize','autoptimize_cache_clean');
		register_setting('autoptimize','autoptimize_cache_nogzip');
		register_setting('autoptimize','autoptimize_show_adv');
	}
	
	public function setmeta($links,$file=null) {
		//Inspired on http://wpengineer.com/meta-links-for-wordpress-plugins/
		//Do it only once - saves time
		static $plugin;
		if(empty($plugin))
			$plugin = plugin_basename(AUTOPTIMIZE_PLUGIN_DIR.'/autoptimize.php');
		
		if($file===null) {
			//2.7
			$settings_link = sprintf('<a href="options-general.php?page=autoptimize">%s</a>', __('Settings'));
			array_unshift($links,$settings_link);
		} else {
			//2.8
			//If it's us, add the link
			if($file === $plugin) {
				$newlink = array(sprintf('<a href="options-general.php?page=autoptimize">%s</a>',__('Settings')));
				$links = array_merge($links,$newlink);
			}
		}
		
		return $links;
	}
	
	public function get($key) {		
		if(!is_array($this->config)) {
			//Default config
			$config = array('autoptimize_html' => 0,
				'autoptimize_html_keepcomments' => 0,
				'autoptimize_js' => 0,
				'autoptimize_js_exclude' => "s_sid, smowtion_size, sc_project, WAU_, wau_add, comment-form-quicktags, edToolbar, ch_client, seal.js",
				'autoptimize_js_trycatch' => 0,
				'autoptimize_js_justhead' => 0,
				'autoptimize_js_include_inline' => 0,
				'autoptimize_js_forcehead' => 1,
				'autoptimize_css' => 0,
				'autoptimize_css_exclude' => "admin-bar.min.css, dashicons.min.css",
				'autoptimize_css_justhead' => 0,
				'autoptimize_css_include_inline' => 0,
				'autoptimize_css_defer' => 0,
				'autoptimize_css_defer_inline' => "",
				'autoptimize_css_inline' => 0,
				'autoptimize_css_datauris' => 0,
				'autoptimize_css_nogooglefont' => 0,
				'autoptimize_cdn_url' => "",
				'autoptimize_cache_nogzip' => 1,
				'autoptimize_show_adv' => 0
				);
			
			//Override with user settings
			foreach(array_keys($config) as $name) {
				$conf = get_option($name);
				if($conf!==false) {
					//It was set before!
					$config[$name] = $conf;
				}
			}
			
			//Save for next question
			$this->config = $config;
		}
		
		if(isset($this->config[$key]))
			return $this->config[$key];
		
		return false;
	}

	private function getFutttaFeeds($url) {
		if (apply_filters('autoptimize_settingsscreen_remotehttp',true)) {
			$rss = fetch_feed( $url );
			$maxitems = 0;
	
			if ( ! is_wp_error( $rss ) ) {
				$maxitems = $rss->get_item_quantity( 7 ); 
				$rss_items = $rss->get_items( 0, $maxitems );
			}
			?>
			<ul>
				<?php if ( $maxitems == 0 ) : ?>
					<li><?php _e( 'No items', 'autoptimize' ); ?></li>
				<?php else : ?>
					<?php foreach ( $rss_items as $item ) : ?>
						<li>
							<a href="<?php echo esc_url( $item->get_permalink() ); ?>"
								title="<?php printf( __( 'Posted %s', 'autoptimize' ), $item->get_date('j F Y | g:i a') ); ?>">
								<?php echo esc_html( $item->get_title() ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				<?php endif; ?>
			</ul>
			<?php
		}
	}
    
    // based on http://wordpress.stackexchange.com/a/58826
    static function ao_admin_tabs(){
		$tabs = apply_filters('autoptimize_filter_settingsscreen_tabs',array('autoptimize' => __('Main','autoptimize')));
        $tabContent="";

        if (count($tabs)>1) {
			if(isset($_GET['page'])){
				$currentId = $_GET['page'];
			} else {
				$currentId = "autoptimize";
			}

            $tabContent .= "<h2 class=\"nav-tab-wrapper\">";
            foreach($tabs as $tabId => $tabName){
                if($currentId == $tabId){
                    $class = " nav-tab-active";
                } else{
                    $class = "";
                }
                $tabContent .= '<a class="nav-tab'.$class.'" href="?page='.$tabId.'">'.$tabName.'</a>';
            }
            $tabContent .= "</h2>";
        } else {
            $tabContent = "<hr/>";
        }
        
        return $tabContent;
    }
}
