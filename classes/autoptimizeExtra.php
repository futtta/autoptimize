<?php
/**
 * Handles autoptimizeExtra frontend features + admin options page
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeExtra
{
    /**
     * Options
     *
     * @var array
     */
    protected $options = array();

    /**
     * Creates an instance and calls run().
     *
     * @param array $options Optional. Allows overriding options without having to specify them via admin options page.
     */
    public function __construct( $options = array() )
    {
        if ( empty( $options ) ) {
            $options = $this->fetch_options();
        }

        $this->options = $options;
    }

    public function run()
    {
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'admin_menu' ) );
            add_filter( 'autoptimize_filter_settingsscreen_tabs', array( $this, 'add_extra_tab' ) );
        } else {
            $this->run_on_frontend();
        }
    }

    protected function fetch_options()
    {
        $value = get_option( 'autoptimize_extra_settings' );
        if ( empty( $value ) ) {
            // Fallback to returning defaults when no stored option exists yet.
            $value = autoptimizeConfig::get_ao_extra_default_options();
        }
        // get service availability, default "up" for imageproxy.
        $value['availabilities'] = get_option( 'autoptimize_service_availablity', array( 'extra_imageproxy'=>'up' ) );

        return $value;
    }

    public function disable_emojis()
    {
        // Removing all actions related to emojis!
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );

        // Removes TinyMCE emojis.
        add_filter( 'tiny_mce_plugins', array( $this, 'filter_disable_emojis_tinymce' ) );

        // Removes emoji dns-preftech.
        add_filter( 'wp_resource_hints', array( $this, 'filter_remove_emoji_dns_prefetch' ), 10, 2 );
    }

    public function filter_disable_emojis_tinymce( $plugins )
    {
        if ( is_array( $plugins ) ) {
            return array_diff( $plugins, array( 'wpemoji' ) );
        } else {
            return array();
        }
    }

    public function filter_remove_qs( $src ) {
        if ( strpos( $src, '?ver=' ) ) {
            $src = remove_query_arg( 'ver', $src );
        }

        return $src;
    }

    public function extra_async_js( $in )
    {
        $exclusions = array();
        if ( ! empty( $in ) ) {
            $exclusions = array_fill_keys( array_filter( array_map( 'trim', explode( ',', $in ) ) ), '' );
        }

        $settings = $this->options['autoptimize_extra_text_field_3'];
        $async    = array_fill_keys( array_filter( array_map( 'trim', explode( ',', $settings ) ) ), '' );
        $attr     = apply_filters( 'autoptimize_filter_extra_async', 'async' );
        foreach ( $async as $k => $v ) {
            $async[ $k ] = $attr;
        }

        // Merge exclusions & asyncs in one array and return to AO API.
        $merged = array_merge( $exclusions, $async );

        return $merged;
    }

    protected function run_on_frontend()
    {
        $options = $this->options;

        // Disable emojis if specified.
        if ( ! empty( $options['autoptimize_extra_checkbox_field_1'] ) ) {
            $this->disable_emojis();
        }

        // Remove version query parameters.
        if ( ! empty( $options['autoptimize_extra_checkbox_field_0'] ) ) {
            add_filter( 'script_loader_src', array( $this, 'filter_remove_qs' ), 15, 1 );
            add_filter( 'style_loader_src', array( $this, 'filter_remove_qs' ), 15, 1 );
        }

        // Making sure is_plugin_active() exists when we need it.
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        // Avoiding conflicts of interest when async-javascript plugin is active!
        $async_js_plugin_active = false;
        if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'async-javascript/async-javascript.php' ) ) {
            $async_js_plugin_active = true;
        }
        if ( ! empty( $options['autoptimize_extra_text_field_3'] ) && ! $async_js_plugin_active ) {
            add_filter( 'autoptimize_filter_js_exclude', array( $this, 'extra_async_js' ), 10, 1 );
        }

        // Optimize google fonts!
        if ( ! empty( $options['autoptimize_extra_radio_field_4'] ) && ( '1' !== $options['autoptimize_extra_radio_field_4'] ) ) {
            add_filter( 'wp_resource_hints', array( $this, 'filter_remove_gfonts_dnsprefetch' ), 10, 2 );
            add_filter( 'autoptimize_html_after_minify', array( $this, 'filter_optimize_google_fonts' ), 10, 1 );
            add_filter( 'autoptimize_extra_filter_tobepreconn', array( $this, 'filter_preconnect_google_fonts' ), 10, 1 );
        }

        // Preconnect!
        if ( ! empty( $options['autoptimize_extra_text_field_2'] ) || has_filter( 'autoptimize_extra_filter_tobepreconn' ) ) {
            add_filter( 'wp_resource_hints', array( $this, 'filter_preconnect' ), 10, 2 );
        }

        // Optimize Images!
        if ( ! empty( $options['autoptimize_extra_checkbox_field_5'] ) && 'down' !== $options['availabilities']['extra_imageproxy'] ) {
            if ( apply_filters( 'autoptimize_filter_extra_imgopt_do', true ) ) {
                add_filter( 'autoptimize_html_after_minify', array( $this, 'filter_optimize_images' ), 10, 1 );
                $_imgopt_active = true;
            }
            if ( apply_filters( 'autoptimize_filter_extra_imgopt_do_css', true ) ) {
                add_filter( 'autoptimize_filter_base_replace_cdn', array( $this, 'filter_optimize_css_images' ), 10, 1 );
                $_imgopt_active = true;
            }
            if ( $_imgopt_active ) {
                add_filter( 'autoptimize_extra_filter_tobepreconn', array( $this, 'filter_preconnect_imgopt_url' ), 10, 1 );
            }
        }
    }

    public function filter_remove_emoji_dns_prefetch( $urls, $relation_type )
    {
        $emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/' );

        return $this->filter_remove_dns_prefetch( $urls, $relation_type, $emoji_svg_url );
    }

    public function filter_remove_gfonts_dnsprefetch( $urls, $relation_type )
    {
        return $this->filter_remove_dns_prefetch( $urls, $relation_type, 'fonts.googleapis.com' );
    }

    public function filter_remove_dns_prefetch( $urls, $relation_type, $url_to_remove )
    {
        if ( 'dns-prefetch' === $relation_type ) {
            $cnt = 0;
            foreach ( $urls as $url ) {
                if ( false !== strpos( $url, $url_to_remove ) ) {
                    unset( $urls[ $cnt ] );
                }
                $cnt++;
            }
        }

        return $urls;
    }

    public function filter_optimize_google_fonts( $in )
    {
        // Extract fonts, partly based on wp rocket's extraction code.
        $markup = preg_replace( '/<!--(.*)-->/Uis', '', $in );
        preg_match_all( '#<link(?:\s+(?:(?!href\s*=\s*)[^>])+)?(?:\s+href\s*=\s*([\'"])((?:https?:)?\/\/fonts\.googleapis\.com\/css(?:(?!\1).)+)\1)(?:\s+[^>]*)?>#iU', $markup, $matches );

        $fonts_collection = array();
        if ( ! $matches[2] ) {
            return $in;
        }

        // Store them in $fonts array.
        $i = 0;
        foreach ( $matches[2] as $font ) {
            if ( ! preg_match( '/rel=["\']dns-prefetch["\']/', $matches[0][ $i ] ) ) {
                // Get fonts name.
                $font = str_replace( array( '%7C', '%7c' ), '|', $font );
                $font = explode( 'family=', $font );
                $font = ( isset( $font[1] ) ) ? explode( '&', $font[1] ) : array();
                // Add font to $fonts[$i] but make sure not to pollute with an empty family!
                $_thisfont = array_values( array_filter( explode( '|', reset( $font ) ) ) );
                if ( ! empty( $_thisfont ) ) {
                    $fonts_collection[ $i ]['fonts'] = $_thisfont;
                    // And add subset if any!
                    $subset = ( is_array( $font ) ) ? end( $font ) : '';
                    if ( false !== strpos( $subset, 'subset=' ) ) {
                        $subset                            = explode( 'subset=', $subset );
                        $fonts_collection[ $i ]['subsets'] = explode( ',', $subset[1] );
                    }
                }
                // And remove Google Fonts.
                $in = str_replace( $matches[0][ $i ], '', $in );
            }
            $i++;
        }

        $options      = $this->options;
        $fonts_markup = '';
        if ( '2' === $options['autoptimize_extra_radio_field_4'] ) {
            // Remove Google Fonts.
            unset( $fonts_collection );
            return $in;
        } elseif ( '3' === $options['autoptimize_extra_radio_field_4'] || '5' === $options['autoptimize_extra_radio_field_4'] ) {
            // Aggregate & link!
            $fonts_string  = '';
            $subset_string = '';
            foreach ( $fonts_collection as $font ) {
                $fonts_string .= '|' . trim( implode( '|', $font['fonts'] ), '|' );
                if ( ! empty( $font['subsets'] ) ) {
                    $subset_string .= implode( ',', $font['subsets'] );
                }
            }

            if ( ! empty( $subset_string ) ) {
                $fonts_string = $fonts_string . '#038;subset=' . $subset_string;
            }

            $fonts_string = str_replace( '|', '%7C', ltrim( $fonts_string, '|' ) );

            if ( ! empty( $fonts_string ) ) {
                if ( '5' === $options['autoptimize_extra_radio_field_4'] ) {
                    $rel_string = 'rel="preload" as="style" onload="' . autoptimizeConfig::get_ao_css_preload_onload() . '"';
                } else {
                    $rel_string = 'rel="stylesheet"';
                }
                $fonts_markup = '<link ' . $rel_string . ' id="ao_optimized_gfonts" href="https://fonts.googleapis.com/css?family=' . $fonts_string . '" />';
            }
        } elseif ( '4' === $options['autoptimize_extra_radio_field_4'] ) {
            // Aggregate & load async (webfont.js impl.)!
            $fonts_array = array();
            foreach ( $fonts_collection as $_fonts ) {
                if ( ! empty( $_fonts['subsets'] ) ) {
                    $_subset = implode( ',', $_fonts['subsets'] );
                    foreach ( $_fonts['fonts'] as $key => $_one_font ) {
                        $_one_font               = $_one_font . ':' . $_subset;
                        $_fonts['fonts'][ $key ] = $_one_font;
                    }
                }
                $fonts_array = array_merge( $fonts_array, $_fonts['fonts'] );
            }

            $fonts_markup = '<script data-cfasync="false" id="ao_optimized_gfonts" type="text/javascript">WebFontConfig={google:{families:[\'';
            foreach ( $fonts_array as $fnt ) {
                $fonts_markup .= $fnt . "','";
            }
            $fonts_markup  = trim( trim( $fonts_markup, "'" ), ',' );
            $fonts_markup .= '] },classes:false, events:false, timeout:1500};(function() {var wf = document.createElement(\'script\');wf.src=\'https://ajax.googleapis.com/ajax/libs/webfont/1/webfont.js\';wf.type=\'text/javascript\';wf.async=\'true\';var s=document.getElementsByTagName(\'script\')[0];s.parentNode.insertBefore(wf, s);})();</script>';
        }

        // Replace back in markup.
        $out = substr_replace( $in, $fonts_markup . '<link', strpos( $in, '<link' ), strlen( '<link' ) );
        unset( $fonts_collection );

        // and insert preload polyfill if "link preload" and if the polyfill isn't there yet (courtesy of inline&defer).
        $preload_polyfill = autoptimizeConfig::get_ao_css_preload_polyfill();
        if ( '5' === $options['autoptimize_extra_radio_field_4'] && strpos( $out, $preload_polyfill ) === false ) {
            $out = str_replace( '</body>', $preload_polyfill . '</body>', $out );
        }
        return $out;
    }

    public function filter_preconnect( $hints, $relation_type )
    {
        $options = $this->options;

        // Get settings and store in array.
        $preconns = array_filter( array_map( 'trim', explode( ',', $options['autoptimize_extra_text_field_2'] ) ) );
        $preconns = apply_filters( 'autoptimize_extra_filter_tobepreconn', $preconns );

        // Walk array, extract domain and add to new array with crossorigin attribute.
        foreach ( $preconns as $preconn ) {
            $parsed = parse_url( $preconn );

            if ( is_array( $parsed ) && empty( $parsed['scheme'] ) ) {
                $domain = '//' . $parsed['host'];
            } elseif ( is_array( $parsed ) ) {
                $domain = $parsed['scheme'] . '://' . $parsed['host'];
            }

            if ( ! empty( $domain ) ) {
                $hint = array( 'href' => $domain );
                // Fonts don't get preconnected unless crossorigin flag is set, non-fonts don't get preconnected if origin flag is set
                // so hardcode fonts.gstatic.com to come with crossorigin and have filter to add other domains if needed.
                $crossorigins = apply_filters( 'autoptimize_extra_filter_preconn_crossorigin', array( 'https://fonts.gstatic.com' ) );
                if ( in_array( $domain, $crossorigins ) ) {
                    $hint['crossorigin'] = 'anonymous';
                }
                $new_hints[] = $hint;
            }
        }

        // Merge in WP's preconnect hints.
        if ( 'preconnect' === $relation_type && ! empty( $new_hints ) ) {
            $hints = array_merge( $hints, $new_hints );
        }

        return $hints;
    }

    public function filter_preconnect_google_fonts( $in )
    {
        $in[] = 'https://fonts.gstatic.com';

        if ( '4' === $this->options['autoptimize_extra_radio_field_4'] ) {
            // Preconnect even more hosts for webfont.js!
            $in[] = 'https://ajax.googleapis.com';
            $in[] = 'https://fonts.googleapis.com';
        }

        return $in;
    }

    public function filter_optimize_images( $in )
    {
        /*
         * fixme: functional stuff
         *
         * picture element (could).
         * filter for critical CSS (could).
         * smart switch between shortpixel hosts (won't).
         */

        $imgopt_base_url = $this->get_imgopt_base_url();
        $to_replace      = array();

        // extract img tags.
        if ( preg_match_all( '#<img[^>]*src[^>]*>#Usmi', $in, $matches ) ) {
            foreach ( $matches[0] as $tag ) {
                $orig_tag = $tag;

                // first do (data-)srcsets.
                if ( preg_match_all( '#srcset=("|\')(.*)("|\')#Usmi', $tag, $allsrcsets, PREG_SET_ORDER ) ) {
                    foreach ( $allsrcsets as $srcset ) {
                        $srcset  = $srcset[2];
                        $srcsets = explode( ',', $srcset );
                        foreach ( $srcsets as $indiv_srcset ) {
                            $indiv_srcset_parts = explode( ' ', trim( $indiv_srcset ) );
                            if ( $indiv_srcset_parts[1] && rtrim( $indiv_srcset_parts[1], 'w' ) !== $indiv_srcset_parts[1] ) {
                                $imgopt_w = rtrim( $indiv_srcset_parts[1], 'w' );
                            }
                            if ( $this->can_optimize_image( $indiv_srcset_parts[0] ) ) {
                                $imgopt_url              = $this->build_imgopt_url( $indiv_srcset_parts[0], $imgopt_w, '' );
                                $tag                     = str_replace( $indiv_srcset_parts[0], $imgopt_url, $tag );
                                $to_replace[ $orig_tag ] = $tag;
                            }
                        }
                    }
                }

                // proceed with img src.
                // first get width and height and add to $imgopt_size.
                if ( preg_match( '#width=("|\')(.*)("|\')#Usmi', $tag, $width ) ) {
                    $imgopt_w = $width[2];
                }
                if ( preg_match( '#height=("|\')(.*)("|\')#Usmi', $tag, $height ) ) {
                    $imgopt_h = $height[2];
                }

                // then start replacing images src.
                if ( preg_match_all( '#src=(?:"|\')(?!data)(.*)(?:"|\')#Usmi', $tag, $urls, PREG_SET_ORDER ) ) {
                    foreach ( $urls as $url ) {
                        $full_src_orig = $url[0];
                        $url           = $url[1];
                        if ( $this->can_optimize_image( $url ) ) {
                            $imgopt_url              = $this->build_imgopt_url( $url, $imgopt_w, $imgopt_h );
                            $full_imgopt_src         = str_replace( $url, $imgopt_url, $full_src_orig );
                            $tag                     = str_replace( $full_src_orig, $full_imgopt_src, $tag );
                            $to_replace[ $orig_tag ] = $tag;
                        }
                    }
                }
            }
        }
        $out = str_replace( array_keys( $to_replace ), array_values( $to_replace ), $in );

        // img thumbnails in e.g. woocommerce.
        if ( strpos( $out, 'data-thumb' ) !== false && apply_filters( 'autoptimize_filter_extra_imgopt_datathumbs', true ) ) {
            $out = preg_replace_callback(
                '/\<div(?:[^>]?)\sdata-thumb\=(?:\"|\')(.+?)(?:\"|\')(?:[^>]*)?\>/s',
                array( $this, 'replace_data_thumbs' ),
                $out
            );
        }

        return $out;
    }

    public function filter_optimize_css_images( $in )
    {
        $imgopt_base_url = $this->get_imgopt_base_url();
        $in              = $this->normalize_img_urls( $in );

        if ( $this->can_optimize_image( $in ) ) {
            return $this->build_imgopt_url( $in, '', '' );
        } else {
            return $in;
        }
    }

    private function get_imgopt_base_url()
    {
        static $imgopt_base_url = null;

        if ( is_null( $imgopt_base_url ) ) {
            $quality         = apply_filters( 'autoptimize_filter_extra_imgopt_quality', 'q_glossy' ); // values: q_lossy, q_lossless, q_glossy.
            $ret_val         = apply_filters( 'autoptimize_filter_extra_imgopt_wait', 'ret_img' ); // values: ret_wait, ret_img, ret_json, ret_blank.
            $imgopt_base_url = 'https://api-ai.shortpixel.com/client/' . $quality . ',' . $ret_val;
            $imgopt_base_url = apply_filters( 'autoptimize_filter_extra_imgopt_base_url', $imgopt_base_url );
        }

        return $imgopt_base_url;
    }

    private function can_optimize_image( $url )
    {
        static $cdn_url      = null;
        static $nopti_images = null;

        if ( is_null( $cdn_url ) ) {
            $cdn_url = apply_filters( 'autoptimize_filter_base_cdnurl', get_option( 'autoptimize_cdn_url', '' ) );
        }

        if ( is_null( $nopti_images ) ) {
            $nopti_images = apply_filters( 'autoptimize_filter_extra_imgopt_noptimize', '' );
        }

        $imgopt_base_url = $this->get_imgopt_base_url();
        $site_host       = parse_url( site_url(), PHP_URL_HOST );
        $url_parsed      = parse_url( $url );

        if ( $url_parsed['host'] !== $site_host && ( ! empty( $cdn_url ) && strpos( $url, $cdn_url ) === false ) ) {
            return false;
        } elseif ( strpos( $url, '.php' ) !== false ) {
            return false;
        } elseif ( str_replace( array( '.png', '.gif', '.jpg', '.jpeg' ), '', $url_parsed['path'] ) === $url_parsed['path'] ) {
            // fixme: better check against end of string.
            return false;
        } elseif ( ! empty( $nopti_images ) ) {
            $nopti_images_array = array_filter( array_map( 'trim', explode( ',', $nopti_images ) ) );
            foreach ( $nopti_images_array as $nopti_image ) {
                if ( strpos( $url, $nopti_image ) !== false ) {
                    return false;
                }
            }
        }
        return true;
    }

    private function build_imgopt_url( $orig_url, $width = 0, $height = 0 )
    {
        $filtered_url = apply_filters( 'autoptimize_filter_extra_imgopt_build_url', $orig_url, $width, $height );

        if ( $filtered_url !== $orig_url ) {
            return $filtered_url;
        }

        $orig_url        = $this->normalize_img_urls( $orig_url );
        $imgopt_base_url = $this->get_imgopt_base_url();
        $imgopt_size     = '';

        if ( $width && 0 !== $width ) {
            $imgopt_size = ',w_' . $width;
        }

        if ( $height && 0 !== $height ) {
            $imgopt_size .= ',h_' . $height;
        }

        $url = $imgopt_base_url . $imgopt_size . '/' . $orig_url;

        return $url;
    }

    public function replace_data_thumbs( $matches )
    {
        if ( $this->can_optimize_image( $matches[1] ) ) {
            return str_replace( $matches[1], $this->build_imgopt_url( $matches[1], 150, 150 ), $matches[0] );
        } else {
            return $matches[0];
        }
    }

    public function filter_preconnect_imgopt_url( $in )
    {
        $imgopt_url_array = parse_url( $this->get_imgopt_base_url() );
        $in[]             = $imgopt_url_array['scheme'] . '://' . $imgopt_url_array['host'];

        return $in;
    }

    private function normalize_img_urls( $in )
    {
        $parsed_site_url = parse_url( site_url() );

        if ( strpos( $in, 'http' ) !== 0 && strpos( $in, '//' ) === 0 ) {
            $in = $parsed_site_url['scheme'] . ':' . $in;
        } elseif ( strpos( $in, '/' ) === 0 ) {
            $in = $parsed_site_url['scheme'] . '://' . $parsed_site_url['host'] . $in;
        }

        return $in;
    }

    public function admin_menu()
    {
        add_submenu_page( null, 'autoptimize_extra', 'autoptimize_extra', 'manage_options', 'autoptimize_extra', array( $this, 'options_page' ) );
        register_setting( 'autoptimize_extra_settings', 'autoptimize_extra_settings' );
    }

    public function add_extra_tab( $in )
    {
        $in = array_merge( $in, array( 'autoptimize_extra' => __( 'Extra', 'autoptimize' ) ) );

        return $in;
    }

    public function options_page()
    {
        // Working with actual option values from the database here.
        // That way any saves are still processed as expected, but we can still
        // override behavior by using `new autoptimizeExtra($custom_options)` and not have that custom
        // behavior being persisted in the DB even if save is done here.
        $options = $this->fetch_options();
        $gfonts  = $options['autoptimize_extra_radio_field_4'];
    ?>
    <style>
        #ao_settings_form {background: white;border: 1px solid #ccc;padding: 1px 15px;margin: 15px 10px 10px 0;}
        #ao_settings_form .form-table th {font-weight: 100;}
        #autoptimize_extra_descr{font-size: 120%;}
    </style>
    <div class="wrap">
    <h1><?php _e( 'Autoptimize Settings', 'autoptimize' ); ?></h1>
    <?php echo autoptimizeConfig::ao_admin_tabs(); ?>
    <?php
    if ( 'on' !== get_option( 'autoptimize_js' ) && 'on' !== get_option( 'autoptimize_css' ) && 'on' !== get_option( 'autoptimize_html' ) ) {
        ?>
        <div class="notice-warning notice"><p>
        <?php
        _e( 'Most of below Extra optimizations require at least one of HTML, JS or CSS autoptimizations being active.', 'autoptimize' );
        ?>
        </p></div>
        <?php
    }

    if ( 'down' === $options['availabilities']['extra_imageproxy'] ) {
        ?>
        <div class="notice-warning notice"><p>
        <?php
        echo sprintf( __( 'The image optimization service is currently down, image optimization will be skipped until further notice. Check the %1$sAutoptimize support forum%2$s for more info.', 'autoptimize' ), '<a href="https://wordpress.org/support/plugin/autoptimize/" target="_blank">','</a>' );
        ?>
        </p></div>
        <?php
    }
    ?>
    <form id='ao_settings_form' action='options.php' method='post'>
        <?php settings_fields( 'autoptimize_extra_settings' ); ?>
        <h2><?php _e( 'Extra Auto-Optimizations', 'autoptimize' ); ?></h2>
        <span id='autoptimize_extra_descr'><?php _e( 'The following settings can improve your site\'s performance even more.', 'autoptimize' ); ?></span>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e( 'Google Fonts', 'autoptimize' ); ?></th>
                <td>
                    <input type="radio" name="autoptimize_extra_settings[autoptimize_extra_radio_field_4]" value="1" <?php if ( ! in_array( $gfonts, array( 2, 3, 4 ) ) ) { echo 'checked'; } ?> ><?php _e( 'Leave as is', 'autoptimize' ); ?><br/>
                    <input type="radio" name="autoptimize_extra_settings[autoptimize_extra_radio_field_4]" value="2" <?php checked( 2, $gfonts, true ); ?> ><?php _e( 'Remove Google Fonts', 'autoptimize' ); ?><br/>
                    <input type="radio" name="autoptimize_extra_settings[autoptimize_extra_radio_field_4]" value="3" <?php checked( 3, $gfonts, true ); ?> ><?php _e( 'Combine and link in head (fonts load fast but are render-blocking)', 'autoptimize' ); ?><br/>
                    <input type="radio" name="autoptimize_extra_settings[autoptimize_extra_radio_field_4]" value="5" <?php checked( 5, $gfonts, true ); ?> ><?php _e( 'Combine and preload in head (fonts load late, but are not render-blocking)', 'autoptimize' ); ?><br/>
                    <input type="radio" name="autoptimize_extra_settings[autoptimize_extra_radio_field_4]" value="4" <?php checked( 4, $gfonts, true ); ?> ><?php _e( 'Combine and load fonts asynchronously with <a href="https://github.com/typekit/webfontloader#readme" target="_blank">webfont.js</a>', 'autoptimize' ); ?><br/>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Optimize Images', 'autoptimize' ); ?></th>
                <td>
                    <label><input type='checkbox' name='autoptimize_extra_settings[autoptimize_extra_checkbox_field_5]' <?php if ( ! empty( $options['autoptimize_extra_checkbox_field_5'] ) && '1' === $options['autoptimize_extra_checkbox_field_5'] ) { echo 'checked="checked"'; } ?> value='1'><?php _e( 'Optimizes images using an image optimizing proxy.', 'autoptimize' ); ?></label>
                    <?php echo apply_filters( 'autoptimize_extra_imgopt_settings_copy', '<p>' . __( 'Free service provided by Shortpixel during Autoptimize 2.4 Beta cycle. After the official 2.4 release this will remain free up until a still to be defined threshold per domain, after which additional service can be purchased at Shortpixel. Usage of this feature is subject to Shortpixel\'s', 'autoptimize' ) . ' <a href="https://shortpixel.com/tos" target="_blank">' . __( 'Terms of Use', 'autoptimize' ) . '</a> ' . __( 'and', 'autoptimize' ) . ' <a href="https://shortpixel.com/privacy" target="_blank">Privacy policy</a>.</p>' ); ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Remove emojis', 'autoptimize' ); ?></th>
                <td>
                    <label><input type='checkbox' name='autoptimize_extra_settings[autoptimize_extra_checkbox_field_1]' <?php if ( ! empty( $options['autoptimize_extra_checkbox_field_1'] ) && '1' === $options['autoptimize_extra_checkbox_field_1'] ) { echo 'checked="checked"'; } ?> value='1'><?php _e( 'Removes WordPress\' core emojis\' inline CSS, inline JavaScript, and an otherwise un-autoptimized JavaScript file.', 'autoptimize' ); ?></label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Remove query strings from static resources', 'autoptimize' ); ?></th>
                <td>
                    <label><input type='checkbox' name='autoptimize_extra_settings[autoptimize_extra_checkbox_field_0]' <?php if ( ! empty( $options['autoptimize_extra_checkbox_field_0'] ) && '1' === $options['autoptimize_extra_checkbox_field_0'] ) { echo 'checked="checked"'; } ?> value='1'><?php _e( 'Removing query strings (or more specificaly the <code>ver</code> parameter) will not improve load time, but might improve performance scores.', 'autoptimize' ); ?></label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Preconnect to 3rd party domains <em>(advanced users)</em>', 'autoptimize' ); ?></th>
                <td>
                    <label><input type='text' style='width:80%' name='autoptimize_extra_settings[autoptimize_extra_text_field_2]' value='<?php echo esc_attr( $options['autoptimize_extra_text_field_2'] ); ?>'><br /><?php _e( 'Add 3rd party domains you want the browser to <a href="https://www.keycdn.com/support/preconnect/#primary" target="_blank">preconnect</a> to, separated by comma\'s. Make sure to include the correct protocol (HTTP or HTTPS).', 'autoptimize' ); ?></label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Async Javascript-files <em>(advanced users)</em>', 'autoptimize' ); ?></th>
                <td>
                    <?php
                    if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'async-javascript/async-javascript.php' ) ) {
                        _e( 'You have "Async JavaScript" installed,', 'autoptimize' );
                        $asj_config_url = 'options-general.php?page=async-javascript';
                        echo sprintf( ' <a href="' . $asj_config_url . '">%s</a>', __( 'configuration of async javascript is best done there.', 'autoptimize' ) );
                    } else {
                    ?>
                        <input type='text' style='width:80%' name='autoptimize_extra_settings[autoptimize_extra_text_field_3]' value='<?php echo esc_attr( $options['autoptimize_extra_text_field_3'] ); ?>'>
                        <br />
                        <?php
                            _e( 'Comma-separated list of local or 3rd party JS-files that should loaded with the <code>async</code> flag. JS-files from your own site will be automatically excluded if added here. ', 'autoptimize' );
                            echo sprintf( __( 'Configuration of async javascript is easier and more flexible using the %s plugin.', 'autoptimize' ), '"<a href="https://wordpress.org/plugins/async-javascript" target="_blank">Async Javascript</a>"' );
                            $asj_install_url = network_admin_url() . 'plugin-install.php?s=async+javascript&tab=search&type=term';
                            echo sprintf( ' <a href="' . $asj_install_url . '">%s</a>', __( 'Click here to install and activate it.', 'autoptimize' ) );
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Optimize YouTube videos', 'autoptimize' ); ?></th>
                <td>
                    <?php
                    if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'wp-youtube-lyte/wp-youtube-lyte.php' ) ) {
                        _e( 'Great, you have WP YouTube Lyte installed.', 'autoptimize' );
                        $lyte_config_url = 'options-general.php?page=lyte_settings_page';
                        echo sprintf( ' <a href="' . $lyte_config_url . '">%s</a>', __( 'Click here to configure it.', 'autoptimize' ) );
                    } else {
                        echo sprintf( __( '%s allows you to “lazy load” your videos, by inserting responsive “Lite YouTube Embeds". ', 'autoptimize' ), '<a href="https://wordpress.org/plugins/wp-youtube-lyte" target="_blank">WP YouTube Lyte</a>' );
                        $lyte_install_url = network_admin_url() . 'plugin-install.php?s=lyte&tab=search&type=term';
                        echo sprintf( ' <a href="' . $lyte_install_url . '">%s</a>', __( 'Click here to install and activate it.', 'autoptimize' ) );
                    }
                    ?>
                </td>
            </tr>
        </table>
        <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Save Changes', 'autoptimize' ); ?>" /></p>
    </form>
    <?php
    }
}
