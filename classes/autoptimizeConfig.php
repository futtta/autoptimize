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
				$plugin = plugin_basename(WP_PLUGIN_DIR.'/autoptimize/autoptimize.php');
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
<style>input[type=url]:invalid {color: red; border-color:red;} .form-table th{font-weight:100;}</style>

<div class="wrap">

<h2><?php _e('Autoptimize Settings','autoptimize'); ?></h2>

<div style="float:left;width:70%;">
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

<h3><?php _e('HTML Options','autoptimize'); ?></h3>
<table class="form-table">
<tr valign="top">
<th scope="row"><?php _e('Optimize HTML Code?','autoptimize'); ?></th>
<td><input type="checkbox" id="autoptimize_html" name="autoptimize_html" <?php echo get_option('autoptimize_html')?'checked="checked" ':''; ?>/></td>
</tr>
<tr class="html_sub" valign="top">
<th scope="row"><?php _e('Keep HTML comments?','autoptimize'); ?></th>
<td><label for="autoptimize_html_keepcomments"><input type="checkbox" name="autoptimize_html_keepcomments" <?php echo get_option('autoptimize_html_keepcomments')?'checked="checked" ':''; ?>/>
<?php _e('Enable this if you want HTML comments to remain in the page, needed for e.g. AdSense to function properly.','autoptimize'); ?></label></td>
</tr>
</table>

<h3><?php _e('JavaScript Options','autoptimize'); ?></h3>
<table class="form-table"> 
<tr valign="top">
<th scope="row"><?php _e('Optimize JavaScript Code?','autoptimize'); ?></th>
<td><input type="checkbox" id="autoptimize_js" name="autoptimize_js" <?php echo get_option('autoptimize_js')?'checked="checked" ':''; ?>/></td>
</tr>
<tr valign="top" class="hidden js_sub ao_adv">
<th scope="row"><?php _e('Force JavaScript in &lt;head&gt;?','autoptimize'); ?></th>
<td><label for="autoptimize_js_forcehead"><input type="checkbox" name="autoptimize_js_forcehead" <?php echo get_option('autoptimize_js_forcehead')?'checked="checked" ':''; ?>/>
<?php _e('For performance reasons it is better to include JavaScript at the bottom of HTML, but this sometimes breaks things. Especially useful for jQuery-based themes.','autoptimize'); ?></label></td>
</tr>
<tr valign="top" class="hidden js_sub ao_adv">
<th scope="row"><?php _e('Look for scripts only in &lt;head&gt;?','autoptimize'); ?></th>
<td><label for="autoptimize_js_justhead"><input type="checkbox" name="autoptimize_js_justhead" <?php echo get_option('autoptimize_js_justhead')?'checked="checked" ':''; ?>/>
<?php _e('Mostly usefull in combination with previous option when using jQuery-based templates, but might help keeping cache size under control.','autoptimize'); ?></label></td>
</tr>
<tr valign="top" class="hidden js_sub ao_adv">
<th scope="row"><?php _e('Exclude scripts from Autoptimize:','autoptimize'); ?></th>
<td><label for="autoptimize_js_exclude"><input type="text" style="width:100%;" name="autoptimize_js_exclude" value="<?php echo get_option('autoptimize_js_exclude',"s_sid,smowtion_size,sc_project,WAU_,wau_add,comment-form-quicktags,edToolbar,ch_client,nonce,post_id"); ?>"/><br />
<?php _e('A comma-seperated list of scripts you want to exclude from being optimized, for example \'whatever.js, another.js\' (without the quotes) to exclude those scripts from being aggregated and minimized by Autoptimize.','autoptimize'); ?></label></td>
</tr>
<tr valign="top" class="hidden js_sub ao_adv">
<th scope="row"><?php _e('Add try-catch wrapping?','autoptimize'); ?></th>
<td><label for="autoptimize_js_trycatch"><input type="checkbox" name="autoptimize_js_trycatch" <?php echo get_option('autoptimize_js_trycatch')?'checked="checked" ':''; ?>/>
<?php _e('If your scripts break because of an script error, you might want to try this.','autoptimize'); ?></label></td>
</tr>
</table>

<h3><?php _e('CSS Options','autoptimize'); ?></h3>
<table class="form-table"> 
<tr valign="top">
<th scope="row"><?php _e('Optimize CSS Code?','autoptimize'); ?></th>
<td><input type="checkbox" id="autoptimize_css" name="autoptimize_css" <?php echo get_option('autoptimize_css')?'checked="checked" ':''; ?>/></td>
</tr>
<tr class="css_sub" valign="top">
<th scope="row"><?php _e('Generate data: URIs for images?','autoptimize'); ?></th>
<td><label for="autoptimize_css_datauris"><input type="checkbox" name="autoptimize_css_datauris" <?php echo get_option('autoptimize_css_datauris')?'checked="checked" ':''; ?>/>
<?php _e('Enable this to include small background-images in the CSS itself instead of as seperate downloads.','autoptimize'); ?></label></td>
</tr>
<tr valign="top" class="hidden css_sub ao_adv">
<th scope="row"><?php _e('Look for styles only in &lt;head&gt;?','autoptimize'); ?></th>
<td><label for="autoptimize_css_justhead"><input type="checkbox" name="autoptimize_css_justhead" <?php echo get_option('autoptimize_css_justhead')?'checked="checked" ':''; ?>/>
<?php _e('Don\'t autoptimize CSS outside the head-section. If the cache gets big, you might want to enable this.','autoptimize'); ?></label></td>
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

<h3><?php _e('CDN Options','autoptimize'); ?></h3>
<table class="form-table"> 
<tr valign="top">
<th scope="row"><?php _e('CDN Base URL','autoptimize'); ?></th>
<td><label for="autoptimize_url"><input id="cdn_url" type="url" name="autoptimize_cdn_url" pattern="^(https?:)?\/\/([\da-z\.-]+)\.([\da-z\.]{2,6})([\/\w \.-]*)*\/?$" style="width:100%" value="<?php $it = get_option('autoptimize_cdn_url','');echo htmlentities($it); ?>" /><br />
<?php _e('Enter your CDN blog root directory URL if you want to enable CDN for images referenced in the CSS.','autoptimize'); ?></label></td>
</tr>
</table>

<h3 class="hidden ao_adv"><?php _e('Cache Info','autoptimize'); ?></h3>
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
<td><?php echo autoptimizeCache::stats(); ?></td>
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
<div style="float:right;width:30%" id="autoptimize_admin_feed">
        <div style="margin-left:10px;margin-top:-5px;">
                <h3>
                        <?php _e("futtta about","autoptimize") ?>
                        <select id="feed_dropdown" >
                                <option value="1"><?php _e("Autoptimize","autoptimize") ?></option>
                                <option value="2"><?php _e("WordPress","autoptimize") ?></option>
                                <option value="3"><?php _e("Web Technology","autoptimize") ?></option>
                        </select>
                </h3>
                <div id="futtta_feed"></div>
        </div>
	<div style="float:right;margin:50px 15px;"><a href="http://blog.futtta.be/2013/10/21/do-not-donate-to-me/" target="_blank"><img width="100px" height="85px" src="<?php echo content_url(); ?>/plugins/autoptimize/classes/external/do_not_donate_smallest.png" title="<?php _e("Do not donate for this plugin!"); ?>"></a></div>
</div>

<script type="text/javascript">
	var feed = new Array;
	feed[1]="http://feeds.feedburner.com/futtta_autoptimize";
	feed[2]="http://feeds.feedburner.com/futtta_wordpress";
	feed[3]="http://feeds.feedburner.com/futtta_webtech";
	cookiename="autoptimize_feed";

	jQuery(document).ready(function() {
		check_ini_state();
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
  		jQuery('#futtta_feed').rssfeed(feed[id], {
			<?php if ( is_ssl() ) echo "ssl: true,"; ?>
    			limit: 4,
			date: true,
			header: false
  		});
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
		wp_enqueue_script('jqzrssfeed', plugins_url('/external/js/jquery.zrssfeed.min.js', __FILE__), array('jquery'),null,true);
		wp_enqueue_script('jqcookie', plugins_url('/external/js/jquery.cookie.min.js', __FILE__), array('jquery'),null,true);
	}

	public function autoptimize_admin_styles() {
        	wp_enqueue_style('zrssfeed', plugins_url('/external/js/jquery.zrssfeed.css', __FILE__));
	}

	
	public function registersettings() {
		register_setting('autoptimize','autoptimize_html');
		register_setting('autoptimize','autoptimize_html_keepcomments');
		register_setting('autoptimize','autoptimize_js');
		register_setting('autoptimize','autoptimize_js_exclude');
		register_setting('autoptimize','autoptimize_js_trycatch');
		register_setting('autoptimize','autoptimize_js_justhead');
		register_setting('autoptimize','autoptimize_js_forcehead');
		register_setting('autoptimize','autoptimize_css');
		register_setting('autoptimize','autoptimize_css_exclude');
		register_setting('autoptimize','autoptimize_css_justhead');
		register_setting('autoptimize','autoptimize_css_datauris');
		register_setting('autoptimize','autoptimize_css_defer');
		register_setting('autoptimize','autoptimize_css_defer_inline');
		register_setting('autoptimize','autoptimize_css_inline');
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
			$plugin = plugin_basename(WP_PLUGIN_DIR.'/autoptimize/autoptimize.php');
		
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
				'autoptimize_js_exclude' => "s_sid, smowtion_size, sc_project, WAU_, wau_add, comment-form-quicktags, edToolbar, ch_client, nonce, post_id",
				'autoptimize_js_trycatch' => 0,
				'autoptimize_js_justhead' => 0,
				'autoptimize_js_forcehead' => 0,
				'autoptimize_css' => 0,
				'autoptimize_css_exclude' => "admin-bar.min.css, dashicons.min.css",
				'autoptimize_css_justhead' => 0,
				'autoptimize_css_defer' => 0,
				'autoptimize_css_defer_inline' => "",
				'autoptimize_css_inline' => 0,
				'autoptimize_css_datauris' => 0,
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
}
