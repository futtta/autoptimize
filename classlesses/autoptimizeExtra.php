<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// initialize
if ( is_admin() ) {
    add_action( 'admin_menu', 'autoptimize_extra_admin' );
    add_filter( 'autoptimize_filter_settingsscreen_tabs','add_autoptimize_extra_tab' );
} else {
    autoptimize_extra_init();
}

// get option
function autoptimize_extra_get_options() {
    $_default_val = array("autoptimize_extra_checkbox_field_1"=>"0","autoptimize_extra_checkbox_field_0"=>"0","autoptimize_extra_radio_field_4"=>"1","autoptimize_extra_text_field_2"=>"","autoptimize_extra_text_field_3"=>"");
    $_option_val = get_option( 'autoptimize_extra_settings' );
    if (empty($_option_val)) {
        $_option_val = $_default_val;
    }
    return $_option_val;
}

// frontend init
function autoptimize_extra_init() {
    $autoptimize_extra_options = autoptimize_extra_get_options();

    /* disable emojis */
    if ( !empty($autoptimize_extra_options['autoptimize_extra_checkbox_field_1']) ) {
        autoptimize_extra_disable_emojis();
    }
    
    /* remove version from query string */
    if ( !empty($autoptimize_extra_options['autoptimize_extra_checkbox_field_0']) ) {
        add_filter( 'script_loader_src', 'autoptimize_extra_remove_qs', 15, 1 );
        add_filter( 'style_loader_src', 'autoptimize_extra_remove_qs', 15, 1 );
    }

    /* 
     * async JS
     * 
     * is_plugin_active is not available in frontend by default
     * cfr. https://codex.wordpress.org/Function_Reference/is_plugin_active
     * so we need to source in wp-admin/includes/plugin.php
    */
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    $_asyncJSactive = false;
    if ( function_exists('is_plugin_active') && is_plugin_active('async-javascript/async-javascript.php') ) {
        $_asyncJSactive = true;
    }
    if ( !empty($autoptimize_extra_options['autoptimize_extra_text_field_3']) && $_asyncJSactive === false ) {
        add_filter('autoptimize_filter_js_exclude','autoptimize_extra_async_js',10,1);
    }

    /* optimize google fonts */
    if ( !empty( $autoptimize_extra_options['autoptimize_extra_radio_field_4'] ) && ( $autoptimize_extra_options['autoptimize_extra_radio_field_4'] != "1" ) ) {
        add_filter( 'wp_resource_hints', 'autoptimize_extra_gfonts_remove_dnsprefetch', 10, 2 );        
        if ( $autoptimize_extra_options['autoptimize_extra_radio_field_4'] == "2" ) {
            add_filter('autoptimize_filter_css_removables','autoptimize_extra_remove_gfonts',10,1);
        } else {
            add_filter('autoptimize_html_after_minify','autoptimize_extra_gfonts',10,1);
            add_filter('autoptimize_extra_filter_tobepreconn','autoptimize_extra_preconnectgooglefonts',10,1);
        }
    }
    
    /* preconnect */
    if ( !empty($autoptimize_extra_options['autoptimize_extra_text_field_2']) || has_filter('autoptimize_extra_filter_tobepreconn') ) {
        add_filter( 'wp_resource_hints', 'autoptimize_extra_preconnect', 10, 2 );
    }
}

// disable emoji's functions
function autoptimize_extra_disable_emojis() {
    // all actions related to emojis
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );

    // filter to remove TinyMCE emojis
    add_filter( 'tiny_mce_plugins', 'autoptimize_extra_disable_emojis_tinymce' );

    // and remove dns-prefetch for emoji
    add_filter( 'wp_resource_hints', 'autoptimize_extra_emojis_remove_dns_prefetch', 10, 2 );
}

function autoptimize_extra_disable_emojis_tinymce( $plugins ) {
    if ( is_array( $plugins ) ) {
        return array_diff( $plugins, array( 'wpemoji' ) );
    } else {
        return array();
    }
}

function autoptimize_extra_emojis_remove_dns_prefetch( $urls, $relation_type ) {
    $_emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/' );

    return autoptimize_extra_remove_dns_prefetch( $urls, $relation_type, $_emoji_svg_url );
}

// remove query string function
function autoptimize_extra_remove_qs( $src ) {
    if ( strpos($src, '?ver=') ) {
            $src = remove_query_arg( 'ver', $src );
    }
    return $src;
}

// async function
function autoptimize_extra_async_js($in) {
    $autoptimize_extra_options = autoptimize_extra_get_options();
    
    // get exclusions
    $AO_JSexclArrayIn = array();
    if (!empty($in)) {
        $AO_JSexclArrayIn = array_fill_keys(array_filter(array_map('trim',explode(",",$in))),"");
    }
    
    // get asyncs
    $_fromSetting = $autoptimize_extra_options['autoptimize_extra_text_field_3'];
    $AO_asynced_JS = array_fill_keys(array_filter(array_map('trim',explode(",",$_fromSetting))),"");
    $AO_async_flag = apply_filters('autoptimize_filter_extra_async',"async");
    foreach ($AO_asynced_JS as $JSkey => $JSvalue) {
        $AO_asynced_JS[$JSkey] = $AO_async_flag;
    }
    
    // merge exclusions & asyncs in one array and return to AO API
    $AO_excl_w_async = array_merge( $AO_JSexclArrayIn, $AO_asynced_JS );
    return $AO_excl_w_async;
}

// preconnect function
function autoptimize_extra_preconnect($hints, $relation_type) {
    $autoptimize_extra_options = autoptimize_extra_get_options();
    
    // get setting and store in array
    $_to_be_preconnected = array_filter(array_map('trim',explode(",",$autoptimize_extra_options['autoptimize_extra_text_field_2'])));
    $_to_be_preconnected = apply_filters( 'autoptimize_extra_filter_tobepreconn', $_to_be_preconnected );

    // walk array, extract domain and add to new array with crossorigin attribute
    foreach ($_to_be_preconnected as $_preconn_single) {
        $_preconn_parsed = parse_url($_preconn_single);
        
        if ( is_array($_preconn_parsed) && empty($_preconn_parsed['scheme']) ) {
            $_preconn_domain = "//".$_preconn_parsed['host'];
        } else if ( is_array($_preconn_parsed) ) {
            $_preconn_domain = $_preconn_parsed['scheme']."://".$_preconn_parsed['host'];
        }
        
        if ( !empty($_preconn_domain) ) {
            $_preconn_hint = array('href' => $_preconn_domain);
            // fonts don't get preconnected unless crossorigin flag is set, non-fonts don't get preconnected if origin flag is set
            // so hardcode fonts.gstatic.com to come with crossorigin and have filter to add other domains if needed
            $_preconn_crossorigin = apply_filters( 'autoptimize_extra_filter_preconn_crossorigin', array('https://fonts.gstatic.com') );
            if ( in_array( $_preconn_domain, $_preconn_crossorigin ) ) {
                $_preconn_hint['crossorigin'] = 'anonymous';
            }
            $_new_hints[] = $_preconn_hint;
        }
    }

    // merge in wordpress' preconnect hints
    if ( 'preconnect' === $relation_type && !empty($_new_hints) ) {
        $hints = array_merge($hints, $_new_hints);      
    }
    
    return $hints;
}

// google font functions
function autoptimize_extra_gfonts_remove_dnsprefetch ( $urls, $relation_type ) {
    $_gfonts_url = "fonts.googleapis.com";
    
    return autoptimize_extra_remove_dns_prefetch( $urls, $relation_type, $_gfonts_url );
}

function autoptimize_extra_remove_gfonts($in) { 
    // simply remove google fonts
    return $in.", fonts.googleapis.com"; 
}

function autoptimize_extra_gfonts($in) {
    $autoptimize_extra_options = autoptimize_extra_get_options();
    
    // extract fonts, partly based on wp rocket's extraction code
    $_without_comments = preg_replace( '/<!--(.*)-->/Uis', '', $in );
    preg_match_all( '#<link(?:\s+(?:(?!href\s*=\s*)[^>])+)?(?:\s+href\s*=\s*([\'"])((?:https?:)?\/\/fonts\.googleapis\.com\/css(?:(?!\1).)+)\1)(?:\s+[^>]*)?>#iU', $_without_comments, $matches );

    $i = 0;
    $fontsCollection = array();
    if ( ! $matches[2] ) {
        return $in;
    }
    
    // store them in $fonts array
    foreach ( $matches[2] as $font ) {
        if ( ! preg_match( '/rel=["\']dns-prefetch["\']/', $matches[0][ $i ] ) ) {
            // Get fonts name.
            $font = str_replace( array( '%7C', '%7c' ) , '|', $font );
            $font = explode( 'family=', $font );
            $font = ( isset( $font[1] ) ) ? explode( '&', $font[1] ) : array();
            // Add font to $fonts[$i] but make sure not to pollute with an empty family
            $_thisfont = array_values( array_filter( explode( '|', reset( $font ) ) ) );
            if ( !empty($_thisfont) ) {
                $fontsCollection[$i]["fonts"] = $_thisfont;
                // And add subset if any
                $subset = ( is_array( $font ) ) ? end( $font ) : '';
                if ( false !== strpos( $subset, 'subset=' ) ) {
                    $subset = explode( 'subset=', $subset );
                    $fontsCollection[$i]["subsets"] = explode( ',', $subset[1] );
                }
            }
            // And remove Google Fonts.
            $in = str_replace( $matches[0][ $i ], '', $in );
        }
        $i++;
    }

    $_fontsOut="";
    if ( $autoptimize_extra_options['autoptimize_extra_radio_field_4'] == "3" ) {
        // aggregate & link
        $_fontsString="";
        $_subsetString="";
        foreach ($fontsCollection as $font) {
            $_fontsString .= '|'.trim( implode( '|' , $font["fonts"] ), '|' );
            if ( !empty( $font["subsets"] ) ) {
                $_subsetString .= implode( ',', $font["subsets"] ); 
            }
        }
                    
        if (!empty($_subsetString)) {
            $_fontsString = $_fontsString."#038;subset=".$_subsetString;
        }

        $_fontsString = str_replace( '|', '%7C', ltrim($_fontsString,'|') );
        
        if ( ! empty( $_fontsString ) ) {
            $_fontsOut = '<link rel="stylesheet" id="ao_optimized_gfonts" href="https://fonts.googleapis.com/css?family=' . $_fontsString . '" />';
        }
    } else if ( $autoptimize_extra_options['autoptimize_extra_radio_field_4'] == "4" ) {
        // aggregate & load async (webfont.js impl.)
        $_fontsArray = array();
        foreach ($fontsCollection as $_fonts) {
            if ( !empty( $_fonts["subsets"] ) ) {
                $_subset = implode(",",$_fonts["subsets"]);
                foreach ($_fonts["fonts"] as $key => $_one_font) {
                    $_one_font = $_one_font.":".$_subset;
                    $_fonts["fonts"][$key] = $_one_font;
                } 
            }
            $_fontsArray = array_merge($_fontsArray, $_fonts["fonts"]);
        }
        
        $_fontsOut = '<script data-cfasync="false" type="text/javascript">WebFontConfig={google:{families:[\'';
        foreach ($_fontsArray as $_font) {
            $_fontsOut .= $_font."','";
        }
        $_fontsOut = trim(trim($_fontsOut,"'"),",");
        $_fontsOut .= '] },classes:false, events:false, timeout:1500};(function() {var wf = document.createElement(\'script\');wf.src=\'https://ajax.googleapis.com/ajax/libs/webfont/1/webfont.js\';wf.type=\'text/javascript\';wf.defer=\'true\';var s=document.getElementsByTagName(\'script\')[0];s.parentNode.insertBefore(wf, s);})();</script>';
    }
 
    // inject in HTML
    $out = substr_replace($in, $_fontsOut."<link", strpos($in, "<link"), strlen("<link"));
    return $out;
}

function autoptimize_extra_preconnectgooglefonts($in) {
    $autoptimize_extra_options = autoptimize_extra_get_options();

    // preconnect to fonts.gstatic.com speed up download of static font-files
    $in[] = "https://fonts.gstatic.com";
    if ( $autoptimize_extra_options['autoptimize_extra_radio_field_4'] == "4" ) {
        // and more preconnects for webfont.js
        $in[] = "https://ajax.googleapis.com";
        $in[] = "https://fonts.googleapis.com";
    }
    return $in;
}

function autoptimize_extra_remove_dns_prefetch( $urls, $relation_type, $_remove_url ) {
        if ( 'dns-prefetch' == $relation_type ) {
        $_count=0;
        foreach ($urls as $_url) {
            if ( strpos($_url, $_remove_url) !== false ) {
                unset($urls[$_count]);
            }
            $_count++;
        }
    }

    return $urls;
}

/* admin page functions */
function autoptimize_extra_admin() { 
    add_submenu_page( null, 'autoptimize_extra', 'autoptimize_extra', 'manage_options', 'autoptimize_extra', 'autoptimize_extra_options_page' );
    register_setting( 'autoptimize_extra_settings', 'autoptimize_extra_settings' );
}

function add_autoptimize_extra_tab($in) {
    $in=array_merge($in,array('autoptimize_extra' => __('Extra','autoptimize')));
    return $in;
}

function autoptimize_extra_options_page() { 
    $autoptimize_extra_options = autoptimize_extra_get_options();
    $_googlef = $autoptimize_extra_options['autoptimize_extra_radio_field_4'];
    ?>
    <style>
        #ao_settings_form {background: white;border: 1px solid #ccc;padding: 1px 15px;margin: 15px 10px 10px 0;}
        #ao_settings_form .form-table th {font-weight: 100;}
        #autoptimize_extra_descr{font-size: 120%;}
    </style>
    <div class="wrap">
    <h1><?php _e('Autoptimize Settings','autoptimize'); ?></h1>
    <?php echo autoptimizeConfig::ao_admin_tabs(); ?>
    <form id='ao_settings_form' action='options.php' method='post'>
        <?php settings_fields('autoptimize_extra_settings'); ?>
        <h2><?php _e('Extra Auto-Optimizations','autoptimize'); ?></h2>
        <span id='autoptimize_extra_descr'><?php _e('The following settings can improve your site\'s performance even more.','autoptimize'); ?></span>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Remove emojis','autoptimize'); ?></th>
                <td>
                    <label><input type='checkbox' name='autoptimize_extra_settings[autoptimize_extra_checkbox_field_1]' <?php if (!empty($autoptimize_extra_options['autoptimize_extra_checkbox_field_1']) && 1 == $autoptimize_extra_options['autoptimize_extra_checkbox_field_1']) echo 'checked="checked"'; ?> value='1'><?php _e('Removes WordPress\' core emojis\' inline CSS, inline JavaScript, and an otherwise un-autoptimized JavaScript file.','autoptimize'); ?></label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Remove query strings from static resources','autoptimize'); ?></th>
                <td>
                    <label><input type='checkbox' name='autoptimize_extra_settings[autoptimize_extra_checkbox_field_0]' <?php if (!empty( $autoptimize_extra_options['autoptimize_extra_checkbox_field_0']) && 1 == $autoptimize_extra_options['autoptimize_extra_checkbox_field_0']) echo 'checked="checked"'; ?> value='1'><?php _e('Removing query strings (or more specificaly the <code>ver</code> parameter) will not improve load time, but might improve performance scores.','autoptimize'); ?></label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Google Fonts','autoptimize'); ?></th>
                <td>
                    <input type="radio" name="autoptimize_extra_settings[autoptimize_extra_radio_field_4]" value="1" <?php if (!in_array($_googlef,array(2,3,4))) {echo "checked"; } ?> ><?php _e('Leave as is','autoptimize'); ?><br/>
                    <input type="radio" name="autoptimize_extra_settings[autoptimize_extra_radio_field_4]" value="2" <?php checked(2, $_googlef, true); ?> ><?php _e('Remove Google Fonts','autoptimize'); ?><br/>
                    <input type="radio" name="autoptimize_extra_settings[autoptimize_extra_radio_field_4]" value="3" <?php checked(3, $_googlef, true); ?> ><?php _e('Combine and link in head','autoptimize'); ?><br/>
                    <input type="radio" name="autoptimize_extra_settings[autoptimize_extra_radio_field_4]" value="4" <?php checked(4, $_googlef, true); ?> ><?php _e('Combine and load fonts asynchronously with <a href="https://github.com/typekit/webfontloader#readme" target="_blank">webfont.js</a>','autoptimize'); ?><br/>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Preconnect to 3rd party domains <em>(advanced users)</em>','autoptimize'); ?></th>
                <td>
                    <label><input type='text' style='width:80%' name='autoptimize_extra_settings[autoptimize_extra_text_field_2]' value='<?php echo $autoptimize_extra_options['autoptimize_extra_text_field_2']; ?>'><br /><?php _e('Add 3rd party domains you want the browser to <a href="https://www.keycdn.com/support/preconnect/#primary" target="_blank">preconnect</a> to, separated by comma\'s. Make sure to include the correct protocol (HTTP or HTTPS).','autoptimize'); ?></label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Async Javascript-files <em>(advanced users)</em>','autoptimize'); ?></th>
                <td>
                    <?php if ( function_exists('is_plugin_active') && is_plugin_active('async-javascript/async-javascript.php') ) {
                        _e('You have "Async JavaScript" installed,','autoptimize');
                        $asj_config_url="options-general.php?page=async-javascript";
                        echo sprintf(' <a href="'.$asj_config_url.'">%s</a>', __('configuration of async javascript is best done there.','autoptimize'));
                    } else { ?>
                        <input type='text' style='width:80%' name='autoptimize_extra_settings[autoptimize_extra_text_field_3]' value='<?php echo $autoptimize_extra_options['autoptimize_extra_text_field_3']; ?>'>
                        <br />
                        <?php 
                        _e('Comma-separated list of local or 3rd party JS-files that should loaded with the <code>async</code> flag. JS-files from your own site will be automatically excluded if added here. ','autoptimize');
                        echo sprintf( __('Configuration of async javascript is easier and more flexible using the %s plugin.','autoptimize'), '"<a href="https://wordpress.org/plugins/async-javascript" target="_blank">Async Javascript</a>"');
                        $asj_install_url= network_admin_url()."plugin-install.php?s=async+javascript&tab=search&type=term";
                        echo sprintf(' <a href="'.$asj_install_url.'">%s</a>', __('Click here to install and activate it.','autoptimize'));
                    } ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Optimize YouTube videos','autoptimize'); ?></th>
                <td>
                    <?php if ( function_exists('is_plugin_active') && is_plugin_active('wp-youtube-lyte/wp-youtube-lyte.php') ) {
                        _e('Great, you have WP YouTube Lyte installed.','autoptimize');
                        $lyte_config_url="options-general.php?page=lyte_settings_page";
                        echo sprintf(' <a href="'.$lyte_config_url.'">%s</a>', __('Click here to configure it.','autoptimize'));
                    } else {
                        echo sprintf( __('%s allows you to “lazy load” your videos, by inserting responsive “Lite YouTube Embeds". ','autoptimize'),'<a href="https://wordpress.org/plugins/wp-youtube-lyte" target="_blank">WP YouTube Lyte</a>');
                        $lyte_install_url= network_admin_url()."plugin-install.php?s=lyte&tab=search&type=term";
                        echo sprintf(' <a href="'.$lyte_install_url.'">%s</a>', __('Click here to install and activate it.','autoptimize'));
                    } ?>
                </td>
            </tr>
        </table>
        <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes','autoptimize') ?>" /></p>
    </form>
    <?php
}
