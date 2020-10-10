<?php
/**
 * Main configuration logic.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeConfig
{
    /**
     * Options.
     *
     * @var array
     */
    private $config = null;

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    static private $instance = null;

    /**
     * Options.
     *
     * @var bool
     */
    private $settings_screen_do_remote_http = true;

    /**
     * Singleton.
     */
    private function __construct()
    {
        if ( is_admin() ) {
            // Add the admin page and settings.
            if ( autoptimizeOptionWrapper::is_ao_active_for_network() ) {
                add_action( 'network_admin_menu', array( $this, 'addmenu' ) );
            }

            add_action( 'admin_menu', array( $this, 'addmenu' ) );
            add_action( 'admin_init', array( $this, 'registersettings' ) );
            add_action( 'admin_init', array( 'PAnD', 'init' ) );

            // Set meta info.
            if ( function_exists( 'plugin_row_meta' ) ) {
                // 2.8 and higher.
                add_filter( 'plugin_row_meta', array( $this, 'setmeta' ), 10, 2 );
            } elseif ( function_exists( 'post_class' ) ) {
                // 2.7 and lower.
                $plugin = plugin_basename( AUTOPTIMIZE_PLUGIN_DIR . 'autoptimize.php' );
                add_filter( 'plugin_action_links_' . $plugin, array( $this, 'setmeta' ) );
            }

            // Clean cache?
            if ( autoptimizeOptionWrapper::get_option( 'autoptimize_cache_clean' ) ) {
                autoptimizeCache::clearall();
                autoptimizeOptionWrapper::update_option( 'autoptimize_cache_clean', 0 );
            }

            $this->settings_screen_do_remote_http = apply_filters( 'autoptimize_settingsscreen_remotehttp', $this->settings_screen_do_remote_http );
        }

        // Adds the Autoptimize Toolbar to the Admin bar.
        // (we load outside the is_admin check so it's also displayed on the frontend toolbar).
        $toolbar = new autoptimizeToolbar();
    }

    /**
     * Instantiates aoconfig.
     *
     * @return autoptimizeConfig
     */
    static public function instance()
    {
        // Only one instance.
        if ( null === self::$instance ) {
            self::$instance = new autoptimizeConfig();
        }

        return self::$instance;
    }

    public function show_network_message() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'Autoptimize Settings', 'autoptimize' ); ?></h1>
            <?php echo $this->ao_admin_tabs(); ?>
            <p style="font-size:120%;"><?php echo apply_filters( 'autoptimize_filter_settingsscreen_multisite_network_message', __( 'Autoptimize is enabled and configured on a WordPress network level. Please contact your network administrator if you need Autoptimize settings changed.', 'autoptimize' ) ); ?></p>
        </div>
        <?php
    }

    public function show_config()
    {
        $conf = self::instance();
        ?>
<style>
/* title and button */
#ao_title_and_button:after {content:''; display:block; clear:both;}

/* form */
.itemDetail {
    background: #fff;
    border: 1px solid #ccc;
    padding: 15px;
    margin: 15px 10px 10px 0;
}
.itemTitle {
    margin-top: 0;
}

input[type=url]:invalid {color: red; border-color:red;} .form-table th{font-weight:normal;}
#autoptimize_main .cb_label {display: block; padding-left: 25px; text-indent: -25px;}

/* rss block */
#futtta_feed ul{list-style:outside;}
#futtta_feed {font-size:medium; margin:0px 20px;}

/* banner + unslider */
.autoptimize_banner {
    margin: 0 38px;
    padding-bottom: 5px;
}
.autoptimize_banner ul li {
    font-size:medium;
    text-align:center;
}
.unslider {
    position:relative;
}
.unslider-arrow {
    display: block;
    left: unset;
    margin-top: -35px;
    margin-left: 7px;
    margin-right: 7px;
    border-radius: 32px;
    background: rgba(0, 0, 0, 0.10) no-repeat 50% 50%;
    color: rgba(255, 255, 255, 0.8);
    font: normal 20px/1 dashicons;
    speak: none;
    padding: 3px 2px 3px 4px;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}
.unslider-arrow:hover {
    background-color: rgba(0, 0, 0, 0.20);
    color: #FFF;
}
.unslider-arrow.prev {
    padding: 3px 4px 3px 2px;
}
.unslider-arrow.next {
    right: 0px;
}
.unslider-arrow.prev::before {
    content: "\f341";
}
.unslider-arrow.next::before {
    content: "\f345";
}

/* responsive stuff: hide admin-feed on smaller screens */
@media (min-width: 961px) {
    #autoptimize_main {float:left;width:69%;}
    #autoptimize_admin_feed{float:right;width:30%;display:block !important;}
    }
@media (max-width: 960px) {
    #autoptimize_main {width:100%;}
    #autoptimize_admin_feed {width:0%;display:none !important;}
}
@media (max-width: 782px) {
    #autoptimize_main input[type="checkbox"] {margin-left: 10px;}
    #autoptimize_main .cb_label {display: block; padding-left: 45px; text-indent: -45px;}
}
</style>

<div class="wrap">

<!-- Temporary nudge to disable aoccss power-up. -->
<?php if ( autoptimizeUtils::is_plugin_active( 'autoptimize-criticalcss/ao_criticss_aas.php' ) ) { ?>
    <div class="notice-info notice"><p>
        <?php _e( 'Autoptimize now includes the criticalcss.com integration that was previously part of the separate power-up. If you want you can simply disable the power-up and Autoptimize will take over immediately.', 'autoptimize' ); ?>
    </p></div>
<?php } ?>

<div id="autoptimize_main">
    <h1 id="ao_title"><?php _e( 'Autoptimize Settings', 'autoptimize' ); ?></h1>
    <?php echo $this->ao_admin_tabs(); ?>

<form method="post" action="<?php echo admin_url( 'options.php' ); ?>">
<?php settings_fields( 'autoptimize' ); ?>

<ul>

<?php
// Only show enable site configuration in network site option.
if ( is_network_admin() && autoptimizeOptionWrapper::is_ao_active_for_network() ) {
?>
    <li class="itemDetail multiSite">
        <h2 class="itemTitle"><?php _e( 'Multisite Options', 'autoptimize' ); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e( 'Enable site configuration?', 'autoptimize' ); ?></th>
                <td><label class="cb_label"><input type="checkbox" id="autoptimize_enable_site_config" name="autoptimize_enable_site_config" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_enable_site_config' ) ? 'checked="checked" ' : ''; ?>/>
                <?php _e( 'Enable Autoptimize configuration per site.', 'autoptimize' ); ?></label></td>
            </tr>
        </table>
    </li>
<?php } else { ?>
    <input type="hidden" id="autoptimize_enable_site_config" name="autoptimize_enable_site_config" value="on" />
<?php } ?>    

<li class="itemDetail">
<h2 class="itemTitle"><?php _e( 'JavaScript Options', 'autoptimize' ); ?></h2>
<table class="form-table">
<tr valign="top">
<th scope="row"><?php _e( 'Optimize JavaScript Code?', 'autoptimize' ); ?></th>
<td><input type="checkbox" id="autoptimize_js" name="autoptimize_js" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_js' ) ? 'checked="checked" ' : ''; ?>/></td>
</tr>
<tr valign="top" class="js_sub">
<th scope="row"><?php _e( 'Aggregate JS-files?', 'autoptimize' ); ?></th>
<td><label class="cb_label"><input type="checkbox" id="autoptimize_js_aggregate" name="autoptimize_js_aggregate" <?php echo $conf->get( 'autoptimize_js_aggregate' ) ? 'checked="checked" ' : ''; ?>/>
<?php _e( 'Aggregate all linked JS-files to have them loaded non-render blocking? If this option is off, the individual JS-files will remain in place but will be minified.', 'autoptimize' ); ?></label></td>
</tr>
<tr valign="top" class="js_sub js_aggregate">
<th scope="row"><?php _e( 'Also aggregate inline JS?', 'autoptimize' ); ?></th>
<td><label class="cb_label"><input type="checkbox" name="autoptimize_js_include_inline" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_js_include_inline' ) ? 'checked="checked" ' : ''; ?>/>
<?php _e( 'Let Autoptimize also extract JS from the HTML. <strong>Warning</strong>: this can make Autoptimize\'s cache size grow quickly, so only enable this if you know what you\'re doing.', 'autoptimize' ); ?></label></td>
</tr>
<tr valign="top" class="js_sub js_aggregate">
<th scope="row"><?php _e( 'Force JavaScript in &lt;head&gt;?', 'autoptimize' ); ?></th>
<td><label class="cb_label"><input type="checkbox" name="autoptimize_js_forcehead" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_js_forcehead' ) ? 'checked="checked" ' : ''; ?>/>
<?php _e( 'Load JavaScript early, this can potentially fix some JS-errors, but makes the JS render blocking.', 'autoptimize' ); ?></label></td>
</tr>
<?php if ( autoptimizeOptionWrapper::get_option( 'autoptimize_js_justhead' ) ) { ?>
<tr valign="top" class="js_sub js_aggregate">
<th scope="row">
<?php
    _e( 'Look for scripts only in &lt;head&gt;?', 'autoptimize' );
    echo ' <i>' . __( '(deprecated)', 'autoptimize' ) . '</i>';
?>
</th>
<td><label class="cb_label"><input type="checkbox" name="autoptimize_js_justhead" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_js_justhead' ) ? 'checked="checked" ' : ''; ?>/>
<?php _e( 'Mostly useful in combination with previous option when using jQuery-based templates, but might help keeping cache size under control.', 'autoptimize' ); ?></label></td>
</tr>
<?php } ?>
<tr valign="top" class="js_sub">
<th scope="row"><?php _e( 'Exclude scripts from Autoptimize:', 'autoptimize' ); ?></th>
<td><label><input type="text" style="width:100%;" name="autoptimize_js_exclude" value="<?php echo esc_attr( autoptimizeOptionWrapper::get_option( 'autoptimize_js_exclude', 'wp-includes/js/dist/, wp-includes/js/tinymce/, js/jquery/jquery.js' ) ); ?>"/><br />
<?php
echo __( 'A comma-separated list of scripts you want to exclude from being optimized, for example \'whatever.js, another.js\' (without the quotes) to exclude those scripts from being aggregated by Autoptimize.', 'autoptimize' ) . ' ' . __( 'Important: excluded non-minified files are still minified by Autoptimize unless that option under "misc" is disabled.', 'autoptimize' );
?>
</label></td>
</tr>
<tr valign="top" class="js_sub js_aggregate">
<th scope="row"><?php _e( 'Add try-catch wrapping?', 'autoptimize' ); ?></th>
<td><label class="cb_label"><input type="checkbox" name="autoptimize_js_trycatch" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_js_trycatch' ) ? 'checked="checked" ' : ''; ?>/>
<?php _e( 'If your scripts break because of a JS-error, you might want to try this.', 'autoptimize' ); ?></label></td>
</tr>
</table>
</li>

<li class="itemDetail">
<h2 class="itemTitle"><?php _e( 'CSS Options', 'autoptimize' ); ?></h2>
<table class="form-table">
<tr valign="top">
<th scope="row"><?php _e( 'Optimize CSS Code?', 'autoptimize' ); ?></th>
<td><input type="checkbox" id="autoptimize_css" name="autoptimize_css" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_css' ) ? 'checked="checked" ' : ''; ?>/></td>
</tr>
<tr class="css_sub" valign="top">
<th scope="row"><?php _e( 'Aggregate CSS-files?', 'autoptimize' ); ?></th>
<td><label class="cb_label"><input type="checkbox" id="autoptimize_css_aggregate" name="autoptimize_css_aggregate" <?php echo $conf->get( 'autoptimize_css_aggregate' ) ? 'checked="checked" ' : ''; ?>/>
<?php _e( 'Aggregate all linked CSS-files? If this option is off, the individual CSS-files will remain in place but will be minified.', 'autoptimize' ); ?></label></td>
</tr>
<tr valign="top" class="css_sub css_aggregate">
<th scope="row"><?php _e( 'Also aggregate inline CSS?', 'autoptimize' ); ?></th>
<td><label class="cb_label"><input type="checkbox" name="autoptimize_css_include_inline" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_css_include_inline', '1' ) ? 'checked="checked" ' : ''; ?>/>
<?php _e( 'Check this option for Autoptimize to also aggregate CSS in the HTML.', 'autoptimize' ); ?></label></td>
</tr>
<tr class="css_sub css_aggregate" valign="top">
<th scope="row"><?php _e( 'Generate data: URIs for images?', 'autoptimize' ); ?></th>
<td><label class="cb_label"><input type="checkbox" name="autoptimize_css_datauris" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_css_datauris' ) ? 'checked="checked" ' : ''; ?>/>
<?php _e( 'Enable this to include small background-images in the CSS itself instead of as separate downloads.', 'autoptimize' ); ?></label></td>
</tr>
<?php if ( autoptimizeOptionWrapper::get_option( 'autoptimize_css_justhead' ) ) { ?>
<tr valign="top" class="css_sub css_aggregate">
<th scope="row">
<?php
_e( 'Look for styles only in &lt;head&gt;?', 'autoptimize' );
echo ' <i>' . __( '(deprecated)', 'autoptimize' ) . '</i>';
?>
</th>
<td><label class="cb_label"><input type="checkbox" name="autoptimize_css_justhead" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_css_justhead' ) ? 'checked="checked" ' : ''; ?>/>
<?php _e( 'Don\'t autoptimize CSS outside the head-section. If the cache gets big, you might want to enable this.', 'autoptimize' ); ?></label></td>
</tr>
<?php } ?>
<tr valign="top" class="css_sub">
<th scope="row"><?php _e( 'Inline and Defer CSS?', 'autoptimize' ); ?></th>
<td><label class="cb_label"><input type="checkbox" name="autoptimize_css_defer" id="autoptimize_css_defer" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_css_defer' ) ? 'checked="checked" ' : ''; ?>/>
<?php
_e( 'Inline "above the fold CSS" while loading the main autoptimized CSS only after page load. <a href="https://wordpress.org/plugins/autoptimize/faq/" target="_blank">Check the FAQ</a> for more info.', 'autoptimize' );
echo ' ';
$critcss_settings_url = get_admin_url( null, 'options-general.php?page=ao_critcss' );
// translators: links "autoptimize critical CSS" tab.
echo sprintf( __( 'This can be fully automated for different types of pages on the %s tab.', 'autoptimize' ), '<a href="' . $critcss_settings_url . '">CriticalCSS</a>' );
?>
</label></td>
</tr>
<tr valign="top" class="css_sub" id="autoptimize_css_defer_inline">
<th scope="row"></th>
<td><label><textarea rows="10" cols="10" style="width:100%;" placeholder="<?php _e( 'Paste the above the fold CSS here. You can leave this empty when using the automated Critical CSS integration.', 'autoptimize' ); ?>" name="autoptimize_css_defer_inline"><?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_css_defer_inline' ); ?></textarea></label></td>
</tr>
<tr valign="top" class="css_sub css_aggregate">
<th scope="row"><?php _e( 'Inline all CSS?', 'autoptimize' ); ?></th>
<td><label class="cb_label"><input type="checkbox" id="autoptimize_css_inline" name="autoptimize_css_inline" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_css_inline' ) ? 'checked="checked" ' : ''; ?>/>
<?php _e( 'Inlining all CSS is an easy way to stop the CSS from being render-blocking, but is generally not recommended because the size of the HTML increases significantly. Additionally it might push meta-tags down to a position where e.g. Facebook and Whatsapp will not find them any more, breaking thumbnails when sharing.', 'autoptimize' ); ?></label></td>
</tr>
<tr valign="top" class="css_sub">
<th scope="row"><?php _e( 'Exclude CSS from Autoptimize:', 'autoptimize' ); ?></th>
<td><label><input type="text" style="width:100%;" name="autoptimize_css_exclude" value="<?php echo esc_attr( autoptimizeOptionWrapper::get_option( 'autoptimize_css_exclude', 'wp-content/cache/, wp-content/uploads/, admin-bar.min.css, dashicons.min.css' ) ); ?>"/><br />
<?php
echo __( 'A comma-separated list of CSS you want to exclude from being optimized.', 'autoptimize' ) . ' ' . __( 'Important: excluded non-minified files are still minified by Autoptimize unless that option under "misc" is disabled.', 'autoptimize' );
?>
</label></td>
</tr>
</table>
</li>

<li class="itemDetail">
<h2 class="itemTitle"><?php _e( 'HTML Options', 'autoptimize' ); ?></h2>
<table class="form-table">
<tr valign="top">
<th scope="row"><?php _e( 'Optimize HTML Code?', 'autoptimize' ); ?></th>
<td><input type="checkbox" id="autoptimize_html" name="autoptimize_html" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_html' ) ? 'checked="checked" ' : ''; ?>/></td>
</tr>
<tr class="html_sub" valign="top">
<th scope="row"><?php _e( 'Keep HTML comments?', 'autoptimize' ); ?></th>
<td><label class="cb_label"><input type="checkbox" name="autoptimize_html_keepcomments" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_html_keepcomments' ) ? 'checked="checked" ' : ''; ?>/>
<?php _e( 'Enable this if you want HTML comments to remain in the page.', 'autoptimize' ); ?></label></td>
</tr>
</table>
</li>

<li class="itemDetail">
<h2 class="itemTitle"><?php _e( 'CDN Options', 'autoptimize' ); ?></h2>
<table class="form-table">
<tr valign="top">
<th scope="row"><?php _e( 'CDN Base URL', 'autoptimize' ); ?></th>
<td><label><input id="cdn_url" type="text" name="autoptimize_cdn_url" pattern="^(https?:)?\/\/([\da-z\.-]+)\.([\da-z\.]{2,6})([\/\w \.-]*)*(:\d{2,5})?\/?$" style="width:100%" value="<?php echo esc_url( autoptimizeOptionWrapper::get_option( 'autoptimize_cdn_url', '' ), array( 'http', 'https' ) ); ?>" /><br />
<?php _e( 'Enter your CDN root URL to enable CDN for Autoptimized files. The URL can be http, https or protocol-relative (e.g. <code>//cdn.example.com/</code>). This is not needed for Cloudflare.', 'autoptimize' ); ?></label></td>
</tr>
</table>
</li>

<li class="itemDetail">
<h2 class="itemTitle"><?php _e( 'Cache Info', 'autoptimize' ); ?></h2>
<table class="form-table" >
<tr valign="top" >
<th scope="row"><?php _e( 'Cache folder', 'autoptimize' ); ?></th>
<td><?php echo htmlentities( AUTOPTIMIZE_CACHE_DIR ); ?></td>
</tr>
<tr valign="top" >
<th scope="row"><?php _e( 'Can we write?', 'autoptimize' ); ?></th>
<td><?php echo ( autoptimizeCache::cacheavail() ? __( 'Yes', 'autoptimize' ) : __( 'No', 'autoptimize' ) ); ?></td>
</tr>
<tr valign="top" >
<th scope="row"><?php _e( 'Cached styles and scripts', 'autoptimize' ); ?></th>
<td>
    <?php
    $ao_stat_arr = autoptimizeCache::stats();
    if ( ! empty( $ao_stat_arr ) && is_array( $ao_stat_arr ) ) {
        $ao_cache_size = size_format( $ao_stat_arr[1], 2 );
        $details       = '';
        if ( $ao_cache_size > 0 ) {
            $details = ', ~' . $ao_cache_size . ' total';
        }
        // translators: Kilobytes + timestamp shown.
        printf( __( '%1$s files, totalling %2$s (calculated at %3$s)', 'autoptimize' ), $ao_stat_arr[0], $ao_cache_size, date( 'H:i e', $ao_stat_arr[2] ) );
    }
    ?>
</td>
</tr>
</table>
</li>

<li class="itemDetail">
<h2 class="itemTitle"><?php _e( 'Misc Options', 'autoptimize' ); ?></h2>
<table class="form-table">
    <tr valign="top" >
    <th scope="row"><?php _e( 'Save aggregated script/css as static files?', 'autoptimize' ); ?></th>
    <td><label class="cb_label"><input type="checkbox" name="autoptimize_cache_nogzip" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_cache_nogzip', '1' ) ? 'checked="checked" ' : ''; ?>/>
    <?php _e( 'By default files saved are static css/js, uncheck this option if your webserver doesn\'t properly handle the compression and expiry.', 'autoptimize' ); ?></label></td>
    </tr>
    <?php
    $_min_excl_class = '';
    if ( ! $conf->get( 'autoptimize_css_aggregate' ) && ! $conf->get( 'autoptimize_js_aggregate' ) ) {
        $_min_excl_class = 'hidden';
    }
    ?>
    <tr valign="top" id="min_excl_row" class="<?php echo $_min_excl_class; ?>">
        <th scope="row"><?php _e( 'Minify excluded CSS and JS files?', 'autoptimize' ); ?></th>
        <td><label class="cb_label"><input type="checkbox" name="autoptimize_minify_excluded" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_minify_excluded', '1' ) ? 'checked="checked" ' : ''; ?>/>
        <?php _e( 'When aggregating JS or CSS, excluded files that are not minified (based on filename) are by default minified by Autoptimize despite being excluded. Uncheck this option if anything breaks despite excluding.', 'autoptimize' ); ?></label></td>
    </tr>
    <tr valign="top">
        <th scope="row"><?php _e( 'Enable 404 fallbacks?', 'autoptimize' ); ?></th>
        <td><label class="cb_label"><input type="checkbox" name="autoptimize_cache_fallback" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_cache_fallback', '1' ) ? 'checked="checked" ' : ''; ?>/>
        <?php _e( 'Sometimes Autoptimized JS/ CSS is referenced in cached HTML but is already removed, resulting in broken sites. With this option on, Autoptimize will try to redirect those not-found files to "fallback"-versions, keeping the page/ site somewhat intact. In some cases this will require extra web-server level configuration to ensure <code>wp-content/autoptimize_404_handler.php</code> is set to handle 404\'s in <code>wp-content/cache/autoptimize</code>.', 'autoptimize' ); ?></label></td>
    </tr>
    <tr valign="top">
    <th scope="row"><?php _e( 'Also optimize for logged in editors/ administrators?', 'autoptimize' ); ?></th>
    <td><label class="cb_label"><input type="checkbox" name="autoptimize_optimize_logged" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_optimize_logged', '1' ) ? 'checked="checked" ' : ''; ?>/>
    <?php _e( 'By default Autoptimize is also active for logged on editors/ administrators, uncheck this option if you don\'t want Autoptimize to optimize when logged in e.g. to use a pagebuilder.', 'autoptimize' ); ?></label></td>
    </tr>
    <?php
    if ( function_exists( 'is_checkout' ) || function_exists( 'is_cart' ) || function_exists( 'edd_is_checkout' ) || function_exists( 'wpsc_is_cart' ) || function_exists( 'wpsc_is_checkout' ) ) {
    ?>
    <tr valign="top" >
        <th scope="row"><?php _e( 'Also optimize shop cart/ checkout?', 'autoptimize' ); ?></th>
        <td><label class="cb_label"><input type="checkbox" name="autoptimize_optimize_checkout" <?php echo autoptimizeOptionWrapper::get_option( 'autoptimize_optimize_checkout', '0' ) ? 'checked="checked" ' : ''; ?>/>
            <?php _e( 'By default Autoptimize is also active on your shop\'s cart/ checkout, uncheck not to optimize those.', 'autoptimize' ); ?></label>
        </td>
    </tr>
    <?php } ?>
</table>
</li>

</ul>

<p class="submit">
<input type="submit" class="button-secondary" value="<?php _e( 'Save Changes', 'autoptimize' ); ?>" />
<input type="submit" class="button-primary" name="autoptimize_cache_clean" value="<?php _e( 'Save Changes and Empty Cache', 'autoptimize' ); ?>" />
</p>

</form>
</div>
<div id="autoptimize_admin_feed" class="hidden">
    <div class="autoptimize_banner hidden">
    <ul>
    <?php
    if ( $this->settings_screen_do_remote_http ) {
        $ao_banner = get_transient( 'autoptimize_banner' );
        if ( empty( $ao_banner ) ) {
            $banner_resp = wp_remote_get( 'https://misc.optimizingmatters.com/autoptimize_news.html?ao_ver=' . AUTOPTIMIZE_PLUGIN_VERSION );
            if ( ! is_wp_error( $banner_resp ) ) {
                if ( '200' == wp_remote_retrieve_response_code( $banner_resp ) ) {
                    $ao_banner = wp_kses_post( wp_remote_retrieve_body( $banner_resp ) );
                    set_transient( 'autoptimize_banner', $ao_banner, WEEK_IN_SECONDS );
                }
            }
        }
        echo $ao_banner;
    }
    ?>
        <li><?php _e( "Need help? <a href='https://wordpress.org/plugins/autoptimize/faq/'>Check out the FAQ here</a>.", 'autoptimize' ); ?></li>
        <li><?php _e( 'Happy with Autoptimize?', 'autoptimize' ); ?><br /><a href="<?php echo network_admin_url(); ?>plugin-install.php?tab=search&type=author&s=optimizingmatters"><?php _e( 'Try my other plugins!', 'autoptimize' ); ?></a></li>
    </ul>
    </div>
    <div style="margin-left:10px;margin-top:-5px;">
        <h2>
            <?php _e( 'futtta about', 'autoptimize' ); ?>
            <select id="feed_dropdown" >
                <option value="1"><?php _e( 'Autoptimize', 'autoptimize' ); ?></option>
                <option value="2"><?php _e( 'WordPress', 'autoptimize' ); ?></option>
                <option value="3"><?php _e( 'Web Technology', 'autoptimize' ); ?></option>
            </select>
        </h2>
        <div id="futtta_feed">
            <div id="autoptimizefeed">
                <?php $this->get_futtta_feeds( 'http://feeds.feedburner.com/futtta_autoptimize' ); ?>
            </div>
            <div id="wordpressfeed">
                <?php $this->get_futtta_feeds( 'http://feeds.feedburner.com/futtta_wordpress' ); ?>
            </div>
            <div id="webtechfeed">
                <?php $this->get_futtta_feeds( 'http://feeds.feedburner.com/futtta_webtech' ); ?>
            </div>
        </div>
    </div>
    <div style="float:right;margin:50px 15px;"><a href="https://blog.futtta.be/2013/10/21/do-not-donate-to-me/" target="_blank"><img width="100px" height="85px" src="<?php echo plugins_url() . '/' . plugin_basename( dirname( __FILE__ ) ) . '/external/do_not_donate_smallest.png'; ?>" title="<?php _e( 'Do not donate for this plugin!', 'autoptimize' ); ?>"></a></div>
</div>

<script type="text/javascript">
    var feed = new Array;
    feed[1]="autoptimizefeed";
    feed[2]="wordpressfeed";
    feed[3]="webtechfeed";
    cookiename="autoptimize_feed";

    jQuery(document).ready(function() {
        check_ini_state();

        jQuery('#autoptimize_admin_feed').fadeTo("slow",1).show();
        jQuery('.autoptimize_banner').unslider({autoplay:true, delay:3500, infinite: false, arrows:{prev:'<a class="unslider-arrow prev"></a>', next:'<a class="unslider-arrow next"></a>'}}).fadeTo("slow",1).show();

        jQuery( "#feed_dropdown" ).change(function() {
            jQuery("#futtta_feed").fadeTo(0,0);
            jQuery("#futtta_feed").fadeTo("slow",1);
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

        jQuery( "#autoptimize_js_aggregate" ).change(function() {
            if (this.checked && jQuery("#autoptimize_js").prop('checked')) {
                jQuery(".js_aggregate:visible").fadeTo("fast",1);
                jQuery( "#min_excl_row" ).show();
            } else {
                jQuery(".js_aggregate:visible").fadeTo("fast",.33);
                if ( jQuery( "#autoptimize_css_aggregate" ).prop('checked') == false ) {
                    jQuery( "#min_excl_row" ).hide();
                }
            }
        });

        jQuery( "#autoptimize_css" ).change(function() {
            if (this.checked) {
                jQuery(".css_sub:visible").fadeTo("fast",1);
            } else {
                jQuery(".css_sub:visible").fadeTo("fast",.33);
            }
        });

        jQuery( "#autoptimize_css_aggregate" ).change(function() {
            if (this.checked && jQuery("#autoptimize_css").prop('checked')) {
                jQuery(".css_aggregate:visible").fadeTo("fast",1);
                jQuery( "#min_excl_row" ).show();
            } else {
                jQuery(".css_aggregate:visible").fadeTo("fast",.33);
                if ( jQuery( "#autoptimize_js_aggregate" ).prop('checked') == false ) {
                    jQuery( "#min_excl_row" ).hide();
                }
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

        jQuery( "#autoptimize_enable_site_config" ).change(function() {
            if (this.checked) {
                jQuery("li.itemDetail:not(.multiSite)").fadeTo("fast",.33);
            } else {
                jQuery("li.itemDetail:not(.multiSite)").fadeTo("fast",1);
            }
        });

        jQuery("#feed_dropdown").change(function() { show_feed(jQuery("#feed_dropdown").val()) });
        feedid=jQuery.cookie(cookiename);
        if(typeof(feedid) !== "string") feedid=1;
        show_feed(feedid);
    })

    // validate cdn_url.
    var cdn_url=document.getElementById("cdn_url");
    cdn_url_baseCSS=cdn_url.style.cssText;
    if ("validity" in cdn_url) {
        jQuery("#cdn_url").focusout(function (event) {
        if (cdn_url.validity.valid) {
            cdn_url.style.cssText=cdn_url_baseCSS;
        } else {
            cdn_url.style.cssText=cdn_url_baseCSS+"border:1px solid #f00;color:#f00;box-shadow: 0 0 2px #f00;";
        }});
    }

    function check_ini_state() {
        if (!jQuery("#autoptimize_css_defer").prop('checked')) {
            jQuery("#autoptimize_css_defer_inline").hide();
        }
        if (!jQuery("#autoptimize_html").prop('checked')) {
            jQuery(".html_sub:visible").fadeTo('fast',.33);
        }
        if (!jQuery("#autoptimize_css").prop('checked')) {
            jQuery(".css_sub:visible").fadeTo('fast',.33);
        }
        if (!jQuery("#autoptimize_css_aggregate").prop('checked')) {
            jQuery(".css_aggregate:visible").fadeTo('fast',.33);
        }
        if (!jQuery("#autoptimize_js").prop('checked')) {
            jQuery(".js_sub:visible").fadeTo('fast',.33);
        }
        if (!jQuery("#autoptimize_js_aggregate").prop('checked')) {
            jQuery(".js_aggregate:visible").fadeTo('fast',.33);
        }
        if (jQuery("#autoptimize_enable_site_config").prop('checked')) {
            jQuery("li.itemDetail:not(.multiSite)").fadeTo('fast',.33);
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

    public function addmenu()
    {
        if ( is_multisite() && is_network_admin() && autoptimizeOptionWrapper::is_ao_active_for_network() ) {
            // multisite, network admin, ao network activated: add normal settings page at network level.
            $hook = add_submenu_page( 'settings.php', __( 'Autoptimize Options', 'autoptimize' ), 'Autoptimize', 'manage_network_options', 'autoptimize', array( $this, 'show_config' ) );
        } elseif ( is_multisite() && ! is_network_admin() && autoptimizeOptionWrapper::is_ao_active_for_network() && 'on' !== autoptimizeOptionWrapper::get_option( 'autoptimize_enable_site_config' ) ) {
            // multisite, ao network activated, not network admin so site specific settings, but "autoptimize_enable_site_config" is off: show "sorry, ask network admin" message iso options.
            $hook = add_options_page( __( 'Autoptimize Options', 'autoptimize' ), 'Autoptimize', 'manage_options', 'autoptimize', array( $this, 'show_network_message' ) );
        } else {
            // default: show normal options page if not multisite, if multisite but not network activated, if multisite and network activated and "autoptimize_enable_site_config" is on.
            $hook = add_options_page( __( 'Autoptimize Options', 'autoptimize' ), 'Autoptimize', 'manage_options', 'autoptimize', array( $this, 'show_config' ) );
        }

        add_action( 'admin_print_scripts-' . $hook, array( $this, 'autoptimize_admin_scripts' ) );
        add_action( 'admin_print_styles-' . $hook, array( $this, 'autoptimize_admin_styles' ) );
    }

    public function autoptimize_admin_scripts()
    {
        wp_enqueue_script( 'jqcookie', plugins_url( '/external/js/jquery.cookie.min.js', __FILE__ ), array( 'jquery' ), null, true );
        wp_enqueue_script( 'unslider', plugins_url( '/external/js/unslider-min.js', __FILE__ ), array( 'jquery' ), null, true );
    }

    public function autoptimize_admin_styles()
    {
        wp_enqueue_style( 'unslider', plugins_url( '/external/js/unslider.css', __FILE__ ) );
        wp_enqueue_style( 'unslider-dots', plugins_url( '/external/js/unslider-dots.css', __FILE__ ) );
    }

    public function registersettings() {
        register_setting( 'autoptimize', 'autoptimize_html' );
        register_setting( 'autoptimize', 'autoptimize_html_keepcomments' );
        register_setting( 'autoptimize', 'autoptimize_enable_site_config' );
        register_setting( 'autoptimize', 'autoptimize_js' );
        register_setting( 'autoptimize', 'autoptimize_js_aggregate' );
        register_setting( 'autoptimize', 'autoptimize_js_exclude' );
        register_setting( 'autoptimize', 'autoptimize_js_trycatch' );
        register_setting( 'autoptimize', 'autoptimize_js_justhead' );
        register_setting( 'autoptimize', 'autoptimize_js_forcehead' );
        register_setting( 'autoptimize', 'autoptimize_js_include_inline' );
        register_setting( 'autoptimize', 'autoptimize_css' );
        register_setting( 'autoptimize', 'autoptimize_css_aggregate' );
        register_setting( 'autoptimize', 'autoptimize_css_exclude' );
        register_setting( 'autoptimize', 'autoptimize_css_justhead' );
        register_setting( 'autoptimize', 'autoptimize_css_datauris' );
        register_setting( 'autoptimize', 'autoptimize_css_defer' );
        register_setting( 'autoptimize', 'autoptimize_css_defer_inline' );
        register_setting( 'autoptimize', 'autoptimize_css_inline' );
        register_setting( 'autoptimize', 'autoptimize_css_include_inline' );
        register_setting( 'autoptimize', 'autoptimize_cdn_url' );
        register_setting( 'autoptimize', 'autoptimize_cache_clean' );
        register_setting( 'autoptimize', 'autoptimize_cache_nogzip' );
        register_setting( 'autoptimize', 'autoptimize_optimize_logged' );
        register_setting( 'autoptimize', 'autoptimize_optimize_checkout' );
        register_setting( 'autoptimize', 'autoptimize_minify_excluded' );
        register_setting( 'autoptimize', 'autoptimize_cache_fallback' );
    }

    public function setmeta( $links, $file = null )
    {
        // Inspired on http://wpengineer.com/meta-links-for-wordpress-plugins/.
        // Do it only once - saves time.
        static $plugin;
        if ( empty( $plugin ) ) {
            $plugin = plugin_basename( AUTOPTIMIZE_PLUGIN_DIR . 'autoptimize.php' );
        }

        if ( null === $file ) {
            // 2.7 and lower.
            $settings_link = sprintf( '<a href="options-general.php?page=autoptimize">%s</a>', __( 'Settings' ) );
            array_unshift( $links, $settings_link );
        } else {
            // 2.8 and higher.
            // If it's us, add the link.
            if ( $file === $plugin ) {
                $newlink = array( sprintf( '<a href="options-general.php?page=autoptimize">%s</a>', __( 'Settings' ) ) );
                $links   = array_merge( $links, $newlink );
            }
        }

        return $links;
    }

    /**
     * Provides the default options.
     *
     * @return array
     */
    public static function get_defaults()
    {
        static $config = array(
            'autoptimize_html'               => 0,
            'autoptimize_html_keepcomments'  => 0,
            'autoptimize_enable_site_config' => 1,
            'autoptimize_js'                 => 0,
            'autoptimize_js_aggregate'       => 1,
            'autoptimize_js_exclude'         => 'wp-includes/js/dist/, wp-includes/js/tinymce/, js/jquery/jquery.js',
            'autoptimize_js_trycatch'        => 0,
            'autoptimize_js_justhead'        => 0,
            'autoptimize_js_include_inline'  => 0,
            'autoptimize_js_forcehead'       => 0,
            'autoptimize_css'                => 0,
            'autoptimize_css_aggregate'      => 1,
            'autoptimize_css_exclude'        => 'admin-bar.min.css, dashicons.min.css, wp-content/cache/, wp-content/uploads/',
            'autoptimize_css_justhead'       => 0,
            'autoptimize_css_include_inline' => 1,
            'autoptimize_css_defer'          => 0,
            'autoptimize_css_defer_inline'   => '',
            'autoptimize_css_inline'         => 0,
            'autoptimize_css_datauris'       => 0,
            'autoptimize_cdn_url'            => '',
            'autoptimize_cache_nogzip'       => 1,
            'autoptimize_optimize_logged'    => 1,
            'autoptimize_optimize_checkout'  => 0,
            'autoptimize_minify_excluded'    => 1,
            'autoptimize_cache_fallback'     => 1,
        );

        return $config;
    }

    /**
     * Returns default option values for autoptimizeExtra.
     *
     * @return array
     */
    public static function get_ao_extra_default_options()
    {
        $defaults = array(
            'autoptimize_extra_checkbox_field_1' => '0',
            'autoptimize_extra_checkbox_field_0' => '0',
            'autoptimize_extra_radio_field_4'    => '1',
            'autoptimize_extra_text_field_2'     => '',
            'autoptimize_extra_text_field_3'     => '',
            'autoptimize_extra_text_field_7'     => '',
        );

        return $defaults;
    }

    /**
     * Returns default option values for autoptimizeExtra.
     *
     * @return array
     */
    public static function get_ao_imgopt_default_options()
    {
        $defaults = array(
            'autoptimize_imgopt_checkbox_field_1' => '0', // imgopt off.
            'autoptimize_imgopt_select_field_2'   => '2', // quality glossy.
            'autoptimize_imgopt_checkbox_field_3' => '0', // lazy load off.
            'autoptimize_imgopt_checkbox_field_4' => '0', // webp off (might be removed).
            'autoptimize_imgopt_text_field_5'     => '',  // lazy load exclusions empty.
        );
        return $defaults;
    }

    /**
     * Returns preload JS onload handler.
     *
     * @param string $media media attribute value the JS to use.
     *
     * @return string
     */
    public static function get_ao_css_preload_onload( $media = 'all' )
    {
        $preload_onload = apply_filters( 'autoptimize_filter_css_preload_onload', "this.onload=null;this.media='" . $media . "';" );
        return $preload_onload;
    }

    public function get( $key )
    {
        if ( ! is_array( $this->config ) ) {
            // Default config.
            $config = self::get_defaults();

            // Override with user settings.
            foreach ( array_keys( $config ) as $name ) {
                $conf = autoptimizeOptionWrapper::get_option( $name );
                if ( false !== $conf ) {
                    // It was set before!
                    $config[ $name ] = $conf;
                }
            }

            // Save for next call.
            $this->config = apply_filters( 'autoptimize_filter_get_config', $config );
        }

        if ( isset( $this->config[ $key ] ) ) {
            return $this->config[ $key ];
        }

        return false;
    }

    private function get_futtta_feeds( $url ) {
        if ( $this->settings_screen_do_remote_http ) {
            $rss      = fetch_feed( $url );
            $maxitems = 0;

            if ( ! is_wp_error( $rss ) ) {
                $maxitems  = $rss->get_item_quantity( 7 );
                $rss_items = $rss->get_items( 0, $maxitems );
            }
            ?>
            <ul>
                <?php if ( 0 == $maxitems ) : ?>
                    <li><?php _e( 'No items', 'autoptimize' ); ?></li>
                <?php else : ?>
                    <?php foreach ( $rss_items as $item ) : ?>
                        <li>
                            <a href="<?php echo esc_url( $item->get_permalink() ); ?>"
                                <?php // translators: the variable contains a date. ?>
                                title="<?php printf( __( 'Posted %s', 'autoptimize' ), $item->get_date( 'j F Y | g:i a' ) ); ?>">
                                <?php echo esc_html( $item->get_title() ); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        <?php
        }
    }

    static function ao_admin_tabs()
    {
        // based on http://wordpress.stackexchange.com/a/58826 .
        $tabs        = apply_filters( 'autoptimize_filter_settingsscreen_tabs', array( 'autoptimize' => __( 'JS, CSS  &amp; HTML', 'autoptimize' ) ) );
        $tab_content = '';
        $tabs_count  = count( $tabs );
        if ( $tabs_count > 1 ) {
            if ( isset( $_GET['page'] ) ) {
                $current_id = $_GET['page'];
            } else {
                $current_id = 'autoptimize';
            }
            $tab_content .= '<h2 class="nav-tab-wrapper">';
            foreach ( $tabs as $tab_id => $tab_name ) {
                if ( $current_id == $tab_id ) {
                    $class = ' nav-tab-active';
                } else {
                    $class = '';
                }
                $tab_content .= '<a class="nav-tab' . $class . '" href="?page=' . $tab_id . '">' . $tab_name . '</a>';
            }
            $tab_content .= '</h2>';
        } else {
            $tab_content = '<hr/>';
        }

        return $tab_content;
    }

    /**
     * Returns true if in admin (and not in admin-ajax.php!)
     *
     * @return bool
     */
    public static function is_admin_and_not_ajax()
    {
        return ( is_admin() && ! self::doing_ajax() );
    }

    /**
     * Returns true if doing ajax.
     *
     * @return bool
     */
    protected static function doing_ajax()
    {
        if ( function_exists( 'wp_doing_ajax' ) ) {
            return wp_doing_ajax();
        }
        return ( defined( 'DOING_AJAX' ) && DOING_AJAX );
    }

    /**
     * Returns true menu or tab is to be shown.
     *
     * @return bool
     */
    public static function should_show_menu_tabs() {
        if ( ! is_multisite() || is_network_admin() || 'on' === autoptimizeOptionWrapper::get_option( 'autoptimize_enable_site_config' ) ) {
            return true;
        } else {
            return false;
        }
    }
}
