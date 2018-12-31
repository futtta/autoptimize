<?php
/**
 * Handles optimizing images.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeImages
{
    /**
     * Options.
     *
     * @var array
     */
    protected $options = array();

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    protected static $instance = null;

    public function __construct( array $options = array() )
    {
        // If options are not provided, fetch them.
        if ( empty( $options ) ) {
            $options = $this->fetch_options();
        }

        $this->set_options( $options );
    }

    public function set_options( array $options )
    {
        $this->options = $options;

        return $this;
    }

    public static function fetch_options()
    {
        $value = get_option( 'autoptimize_imgopt_settings' );
        if ( empty( $value ) ) {
            // Fallback to returning defaults when no stored option exists yet.
            $value = autoptimizeConfig::get_ao_imgopt_default_options();
        }

        // get service availability and add it to the options-array.
        $value['availabilities'] = get_option( 'autoptimize_service_availablity' );

        if ( empty( $value['availabilities'] ) ) {
            $value['availabilities'] = autoptimizeUtils::check_service_availability( true );
        }

        return $value;
    }

    public static function imgopt_active()
    {
        // function to quickly check if imgopt is active, used below but also in
        // autoptimizeMain.php to start ob_ even if no HTML, JS or CSS optimizing is done
        // and does not use/ request the availablity data (which could slow things down).
        static $imgopt_active = null;

        if ( null === $imgopt_active ) {
            $opts = get_option( 'autoptimize_imgopt_settings', '' );
            if ( ! empty( $opts ) && is_array( $opts ) && array_key_exists( 'autoptimize_imgopt_checkbox_field_1', $opts ) && ! empty( $opts['autoptimize_imgopt_checkbox_field_1'] ) && '1' === $opts['autoptimize_imgopt_checkbox_field_1'] ) {
                $imgopt_active = true;
            } else {
                $imgopt_active = false;
            }
        }

        return $imgopt_active;
    }

    /**
     * Helper for getting a singleton instance. While being an
     * anti-pattern generally, it comes in handy for now from a
     * readability/maintainability perspective, until we get some
     * proper dependency injection going.
     *
     * @return self
     */
    public static function instance()
    {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function run()
    {
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'imgopt_admin_menu' ) );
            add_filter( 'autoptimize_filter_settingsscreen_tabs', array( $this, 'add_imgopt_tab' ), 9 );
        } else {
            $this->run_on_frontend();
        }
    }

    public function run_on_frontend() {
        if ( ! $this->should_run() ) {
            if ( $this->should_lazyload() ) {
                add_filter(
                    'autoptimize_html_after_minify',
                    array( $this, 'filter_lazyload_images' ),
                    10,
                    1
                );
                add_action(
                    'wp_footer',
                    array( $this, 'add_lazyload_js' ),
                    10,
                    0
                );
            }
            return;
        }

        $active = false;

        if ( apply_filters( 'autoptimize_filter_imgopt_do', true ) ) {
            add_filter(
                'autoptimize_html_after_minify',
                array( $this, 'filter_optimize_images' ),
                10,
                1
            );
            $active = true;
        }

        if ( apply_filters( 'autoptimize_filter_imgopt_do_css', true ) ) {
            add_filter(
                'autoptimize_filter_base_replace_cdn',
                array( $this, 'filter_optimize_css_images' ),
                10,
                1
            );
            $active = true;
        }

        if ( $active ) {
            add_filter(
                'autoptimize_extra_filter_tobepreconn',
                array( $this, 'filter_preconnect_imgopt_url' ),
                10,
                1
            );
        }

        if ( $this->should_lazyload() ) {
            add_action( 'wp_footer', array( $this, 'add_lazyload_js' ) );
        }
    }


    /**
     * Basic checks before we can run.
     *
     * @return bool
     */
    protected function should_run()
    {
        $opts              = $this->options;
        $service_not_down  = ( 'down' !== $opts['availabilities']['extra_imgopt']['status'] );
        $not_launch_status = ( 'launch' !== $opts['availabilities']['extra_imgopt']['status'] );

        $do_cdn      = true;
        $_userstatus = $this->get_imgopt_provider_userstatus();
        if ( -2 == $_userstatus['Status'] ) {
            $do_cdn = false;
        }

        if (
            $this->imgopt_active()
            && $do_cdn
            && $service_not_down
            && ( $not_launch_status || $this->launch_ok() )
        ) {
            return true;
        }
        return false;
    }

    /**
     * Determines and returns the service launch status.
     *
     * @return bool
     */
    public function launch_ok()
    {
        static $launch_status = null;

        if ( null === $launch_status ) {
            $avail_imgopt  = $this->options['availabilities']['extra_imgopt'];
            $magic_number  = intval( substr( md5( parse_url( AUTOPTIMIZE_WP_SITE_URL, PHP_URL_HOST ) ), 0, 3 ), 16 );
            $has_launched  = get_option( 'autoptimize_imgopt_launched', '' );
            $launch_status = false;
            if ( $has_launched || ( is_array( $avail_imgopt ) && array_key_exists( 'launch-threshold', $avail_imgopt ) && $magic_number < $avail_imgopt['launch-threshold'] ) ) {
                $launch_status = true;
                if ( ! $has_launched ) {
                    update_option( 'autoptimize_imgopt_launched', 'on' );
                }
            }
        }

        return $launch_status;
    }

    public static function launch_ok_wrapper()
    {
        // needed for "plug" notice in autoptimizeMain.php.
        $self = new self();
        return $self->launch_ok();
    }

    public function get_imgopt_host()
    {
        static $imgopt_host = null;

        if ( null === $imgopt_host ) {
            $imgopt_host  = 'https://cdn.shortpixel.ai/';
            $avail_imgopt = $this->options['availabilities']['extra_imgopt'];
            if ( ! empty( $avail_imgopt ) && array_key_exists( 'hosts', $avail_imgopt ) && is_array( $avail_imgopt['hosts'] ) ) {
                $imgopt_host = array_rand( array_flip( $avail_imgopt['hosts'] ) );
            }
        }

        return $imgopt_host;
    }

    public static function get_imgopt_host_wrapper()
    {
        // needed for CI tests.
        $self = new self();
        return $self->get_imgopt_host();
    }

    public function get_imgopt_provider_userstatus() {
        static $_provider_userstatus = null;

        if ( is_null( $_provider_userstatus ) ) {
            $_stat = get_option( 'autoptimize_imgopt_provider_stat', '' );
            if ( is_array( $_stat ) ) {
                if ( array_key_exists( 'Status', $_stat ) ) {
                    $_provider_userstatus['Status'] = $_stat['Status'];
                } else {
                    // if no stats then we assume all is well.
                    $_provider_userstatus['Status'] = 2;
                }
                if ( array_key_exists( 'timestamp', $_stat ) ) {
                    $_provider_userstatus['timestamp'] = $_stat['timestamp'];
                } else {
                    // if no timestamp then we return "".
                    $_provider_userstatus['timestamp'] = '';
                }
            }
        }

        return $_provider_userstatus;
    }

    public function get_status_notice()
    {
        if ( $this->imgopt_active() ) {
            $notice = '';
            $stat   = $this->get_imgopt_provider_userstatus();
            $upsell = 'https://shortpixel.com/aospai/af/GWRGFLW109483/' . AUTOPTIMIZE_SITE_DOMAIN;

            if ( is_array( $stat ) ) {
                if ( 1 == $stat['Status'] ) {
                    // translators: "add more credits" will appear in a "a href".
                    $notice = sprintf( __( 'Your ShortPixel image optimization and CDN quota is almost used, make sure you %1$sadd more credits%2$s to avoid slowing down your website.', 'autoptimize' ), '<a rel="noopener noreferrer" href="' . $upsell . '" target="_blank">', '</a>' );
                } elseif ( -1 == $stat['Status'] || -2 == $stat['Status'] ) {
                    // translators: "add more credits" will appear in a "a href".
                    $notice = sprintf( __( 'Your ShortPixel image optimization and CDN quota was used, %1$sadd more credits%2$s to keep fast serving optimized images on your site.', 'autoptimize' ), '<a rel="noopener noreferrer" href="' . $upsell . '" target="_blank">', '</a>' );
                } else {
                    $upsell = 'https://shortpixel.com/g/af/GWRGFLW109483';
                    // translators: "log in to check your account" will appear in a "a href".
                    $notice = sprintf( __( 'Your ShortPixel image optimization and CDN quota are in good shape, %1$slog in to check your account%2$s.', 'autoptimize' ), '<a rel="noopener noreferrer" href="' . $upsell . '" target="_blank">', '</a>' );
                }
                $notice = apply_filters( 'autoptimize_filter_imgopt_notice', $notice );

                return array(
                    'status' => $stat['Status'],
                    'notice' => $notice,
                );
            }
        }
        return false;
    }

    public static function get_service_url_suffix()
    {
        $suffix = '/af/GWRGFLW109483/' . AUTOPTIMIZE_SITE_DOMAIN;

        return $suffix;
    }

    public function query_img_provider_stats()
    {
        if ( ! empty( $this->options['autoptimize_imgopt_checkbox_field_1'] ) ) {
            $url      = '';
            $endpoint = $this->get_imgopt_host() . 'read-domain/';
            $domain   = AUTOPTIMIZE_SITE_DOMAIN;

            // make sure parse_url result makes sense, keeping $url empty if not.
            if ( $domain && ! empty( $domain ) ) {
                $url = $endpoint . $domain;
            }

            $url = apply_filters(
                'autoptimize_filter_imgopt_stat_url',
                $url
            );

            // only do the remote call if $url is not empty to make sure no parse_url
            // weirdness results in useless calls.
            if ( ! empty( $url ) ) {
                $response = wp_remote_get( $url );
                if ( ! is_wp_error( $response ) ) {
                    if ( '200' == wp_remote_retrieve_response_code( $response ) ) {
                        $stats = json_decode( wp_remote_retrieve_body( $response ), true );
                        update_option( 'autoptimize_imgopt_provider_stat', $stats );
                    }
                }
            }
        }
    }

    public static function get_img_provider_stats()
    {
        // wrapper around query_img_provider_stats() so we can get to $this->options from cronjob() in autoptimizeCacheChecker.
        $self = new self();
        return $self->query_img_provider_stats();
    }

    public function get_img_quality_string()
    {
        static $quality = null;

        if ( null === $quality ) {
            $q_array = $this->get_img_quality_array();
            $setting = $this->get_img_quality_setting();
            $quality = apply_filters(
                'autoptimize_filter_imgopt_quality',
                'q_' . $q_array[ $setting ]
            );
        }

        return $quality;
    }

    public function get_img_quality_array()
    {
        static $map = null;

        if ( null === $map ) {
            $map = array(
                '1' => 'lossy',
                '2' => 'glossy',
                '3' => 'lossless',
            );
            $map = apply_filters(
                'autoptimize_filter_imgopt_quality_array',
                $map
            );
        }

        return $map;
    }

    public function get_img_quality_setting()
    {
        static $q = null;

        if ( null === $q ) {
            if ( is_array( $this->options ) && array_key_exists( 'autoptimize_imgopt_select_field_2', $this->options ) ) {
                $setting = $this->options['autoptimize_imgopt_select_field_2'];
            }

            if ( ! isset( $setting ) || empty( $setting ) || ( '1' !== $setting && '3' !== $setting ) ) {
                // default image opt. value is 2 ("glossy").
                $q = '2';
            } else {
                $q = $setting;
            }
        }

        return $q;
    }

    public function filter_preconnect_imgopt_url( array $in )
    {
        $url_parts = parse_url( $this->get_imgopt_base_url() );
        $in[]      = $url_parts['scheme'] . '://' . $url_parts['host'];

        return $in;
    }

    /**
     * Makes sure given url contains the full scheme and hostname
     * in case they're not present already.
     *
     * @param string $in Image url to normalize.
     *
     * @return string
     */
    private function normalize_img_url( $in )
    {
        // Only parse the site url once.
        static $parsed_site_url = null;
        if ( null === $parsed_site_url ) {
            $parsed_site_url = parse_url( site_url() );
        }

        // get CDN domain once.
        static $cdn_domain = null;
        if ( is_null( $cdn_domain ) ) {
            $cdn_url = apply_filters( 'autoptimize_filter_base_cdnurl', get_option( 'autoptimize_cdn_url', '' ) );
            if ( ! empty( $cdn_url ) ) {
                $cdn_domain = parse_url( $cdn_url, PHP_URL_HOST );
            } else {
                $cdn_domain = '';
            }
        }

        /**
         * This method gets called a lot, often for identical urls it seems.
         * `filter_optimize_css_images()` calls us, uses the resulting url and
         * gives it to `can_optimize_image()`, and if that returns trueish
         * then `build_imgopt_url()` is called (which, again, calls this method).
         * Until we dig deeper into whether this all must really happen that
         * way, having an internal cache here helps (to avoid doing repeated
         * identical string operations).
         */
        static $cache = null;
        if ( null === $cache ) {
            $cache = array();
        }

        // Do the work on cache miss only.
        if ( ! isset( $cache[ $in ] ) ) {
            // Default to what was given to us.
            $result = $in;
            if ( autoptimizeUtils::is_protocol_relative( $in ) ) {
                $result = $parsed_site_url['scheme'] . ':' . $in;
            } elseif ( 0 === strpos( $in, '/' ) ) {
                // Root-relative...
                $result = $parsed_site_url['scheme'] . '://' . $parsed_site_url['host'];
                // Add the path for subfolder installs.
                if ( isset( $parsed_site_url['path'] ) ) {
                    $result .= $parsed_site_url['path'];
                }
                $result .= $in;
            } elseif ( ! empty( $cdn_domain ) && strpos( $in, $cdn_domain ) !== 0 ) {
                $result = str_replace( $cdn_domain, $parsed_site_url['host'], $in );
            }

            $result = apply_filters( 'autoptimize_filter_imgopt_normalized_url', $result );

            // Store in cache.
            $cache[ $in ] = $result;
        }

        return $cache[ $in ];
    }

    public function filter_optimize_css_images( $in )
    {
        $in = $this->normalize_img_url( $in );

        if ( $this->can_optimize_image( $in ) ) {
            return $this->build_imgopt_url( $in, '', '' );
        } else {
            return $in;
        }
    }

    private function get_imgopt_base_url()
    {
        static $imgopt_base_url = null;

        if ( null === $imgopt_base_url ) {
            $imgopt_host     = $this->get_imgopt_host();
            $quality         = $this->get_img_quality_string();
            $ret_val         = apply_filters( 'autoptimize_filter_imgopt_wait', 'ret_img' ); // values: ret_wait, ret_img, ret_json, ret_blank.
            $imgopt_base_url = $imgopt_host . 'client/' . $quality . ',' . $ret_val;
            $imgopt_base_url = apply_filters( 'autoptimize_filter_imgopt_base_url', $imgopt_base_url );
        }

        return $imgopt_base_url;
    }

    private function can_optimize_image( $url )
    {
        static $cdn_url      = null;
        static $nopti_images = null;

        if ( null === $cdn_url ) {
            $cdn_url = apply_filters(
                'autoptimize_filter_base_cdnurl',
                get_option( 'autoptimize_cdn_url', '' )
            );
        }

        if ( null === $nopti_images ) {
            $nopti_images = apply_filters( 'autoptimize_filter_imgopt_noptimize', '' );
        }

        $site_host  = AUTOPTIMIZE_SITE_DOMAIN;
        $url        = $this->normalize_img_url( $url );
        $url_parsed = parse_url( $url );

        if ( array_key_exists( 'host', $url_parsed ) && $url_parsed['host'] !== $site_host && empty( $cdn_url ) ) {
            return false;
        } elseif ( ! empty( $cdn_url ) && strpos( $url, $cdn_url ) === false && array_key_exists( 'host', $url_parsed ) && $url_parsed['host'] !== $site_host ) {
            return false;
        } elseif ( strpos( $url, '.php' ) !== false ) {
            return false;
        } elseif ( str_ireplace( array( '.png', '.gif', '.jpg', '.jpeg', '.webp' ), '', $url_parsed['path'] ) === $url_parsed['path'] ) {
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
        // sanitize width and height.
        if ( strpos( $width, '%' ) !== false ) {
            $width = 0;
        }
        if ( strpos( $height, '%' ) !== false ) {
            $height = 0;
        }
        $width  = (int) $width;
        $height = (int) $height;

        $filtered_url = apply_filters(
            'autoptimize_filter_imgopt_build_url',
            $orig_url,
            $width,
            $height
        );

        // If filter modified the url, return that.
        if ( $filtered_url !== $orig_url ) {
            return $filtered_url;
        }

        $orig_url        = $this->normalize_img_url( $orig_url );
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
        $this->replace_img_callback( $matches, 150, 150 );
    }

    public function replace_img_callback( $matches, $width = 0, $height = 0 )
    {
        if ( $this->can_optimize_image( $matches[1] ) ) {
            return str_replace( $matches[1], $this->build_imgopt_url( $matches[1], $width, $height ), $matches[0] );
        } else {
            return $matches[0];
        }
    }

    public function filter_optimize_images( $in )
    {
        /*
         * potential future functional improvements:
         *
         * picture element.
         * filter for critical CSS.
         */
        $to_replace = array();

        // extract img tags.
        if ( preg_match_all( '#<img[^>]*src[^>]*>#Usmi', $in, $matches ) ) {
            foreach ( $matches[0] as $tag ) {
                $orig_tag = $tag;
                $imgopt_w = '';
                $imgopt_h = '';

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
                            } elseif ( $this->should_lazyload() && ! array_key_exists( $orig_tag, $to_replace ) ) {
                                // keep tag in replacement array so it can be forced to lazyload.
                                $to_replace[ $orig_tag ] = $orig_tag;
                            }
                        }
                    }
                }

                // proceed with img src.
                // first reset and then get width and height and add to $imgopt_size.
                $imgopt_w = '';
                $imgopt_h = '';
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
                        } elseif ( $this->should_lazyload() && ! array_key_exists( $orig_tag, $to_replace ) ) {
                            // keep image in replacement array so it can be forced to lazyload.
                            $to_replace[ $orig_tag ] = $orig_tag;
                        }
                    }
                }
            }
        }

        // add lazyload attribs.
        if ( $this->should_lazyload() ) {
            foreach ( $to_replace as $orig_tag => $tag ) {
                if ( str_ireplace( $this->get_lazyload_exclusions(), '', $tag ) === $tag ) {
                    $to_replace[ $orig_tag ] = $this->add_lazyload( $tag );
                }
            }
        }

        // and replace all.
        $out = str_replace( array_keys( $to_replace ), array_values( $to_replace ), $in );

        // img thumbnails in e.g. woocommerce.
        if ( strpos( $out, 'data-thumb' ) !== false && apply_filters( 'autoptimize_filter_imgopt_datathumbs', true ) ) {
            $out = preg_replace_callback(
                '/\<div(?:[^>]?)\sdata-thumb\=(?:\"|\')(.+?)(?:\"|\')(?:[^>]*)?\>/s',
                array( $this, 'replace_data_thumbs' ),
                $out
            );
        }

        // background-image in inline style.
        if ( strpos( $out, 'background-image:' ) !== false && apply_filters( 'autoptimize_filter_imgopt_backgroundimages', true ) ) {
            $out = preg_replace_callback(
                '/style=(?:"|\').*?background-image:\s?url\((?:"|\')?([^"\')]*)(?:"|\')?\)/s',
                array( $this, 'replace_img_callback' ),
                $out
            );
        }

        return $out;
    }

    public function get_imgopt_status_notice() {
        if ( $this->imgopt_active() ) {
            $_imgopt_notice = '';
            $_stat          = get_option( 'autoptimize_imgopt_provider_stat', '' );
            $_site_host     = AUTOPTIMIZE_SITE_DOMAIN;
            $_imgopt_upsell = 'https://shortpixel.com/aospai/af/GWRGFLW109483/' . $_site_host;

            if ( is_array( $_stat ) ) {
                if ( 1 == $_stat['Status'] ) {
                    // translators: "add more credits" will appear in a "a href".
                    $_imgopt_notice = sprintf( __( 'Your ShortPixel image optimization and CDN quota is almost used, make sure you %1$sadd more credits%2$s to avoid slowing down your website.', 'autoptimize' ), '<a href="' . $_imgopt_upsell . '" target="_blank">', '</a>' );
                } elseif ( -1 == $_stat['Status'] || -2 == $_stat['Status'] ) {
                    // translators: "add more credits" will appear in a "a href".
                    $_imgopt_notice            = sprintf( __( 'Your ShortPixel image optimization and CDN quota was used, %1$sadd more credits%2$s to keep fast serving optimized images on your site', 'autoptimize' ), '<a href="' . $_imgopt_upsell . '" target="_blank">', '</a>' );
                    $_imgopt_stats_refresh_url = add_query_arg( array(
                        'page'                => 'autoptimize_imgopt',
                        'refreshImgProvStats' => '1',
                    ), admin_url( 'options-general.php' ) );
                    if ( $_stat && array_key_exists( 'timestamp', $_stat ) && ! empty( $_stat['timestamp'] ) ) {
                        $_imgopt_stats_last_run = __( 'based on status at ', 'autoptimize' ) . date_i18n( get_option( 'time_format' ), $_stat['timestamp'] );
                    } else {
                        $_imgopt_stats_last_run = __( 'based on previously fetched data', 'autoptimize' );
                    }
                    $_imgopt_notice .= ' (' . $_imgopt_stats_last_run . ', ';
                    // translators: "here to refresh" links to the Autoptimize Extra page and forces a refresh of the img opt stats.
                    $_imgopt_notice .= sprintf( __( 'click %1$shere to refresh%2$s', 'autoptimize' ), '<a href="' . $_imgopt_stats_refresh_url . '">', '</a>).' );
                } else {
                    $_imgopt_upsell = 'https://shortpixel.com/g/af/GWRGFLW109483';
                    // translators: "log in to check your account" will appear in a "a href".
                    $_imgopt_notice = sprintf( __( 'Your ShortPixel image optimization and CDN quota are in good shape, %1$slog in to check your account%2$s.', 'autoptimize' ), '<a href="' . $_imgopt_upsell . '" target="_blank">', '</a>' );
                }
                $_imgopt_notice = apply_filters( 'autoptimize_filter_imgopt_notice', $_imgopt_notice );

                return array(
                    'status' => $_stat['Status'],
                    'notice' => $_imgopt_notice,
                );
            }
        }
        return false;
    }

    public static function get_imgopt_status_notice_wrapper() {
        // needed for notice being shown in autoptimizeCacheChecker.php.
        $self = new self();
        return $self->get_imgopt_status_notice();
    }

    public static function should_lazyload() {
        static $lazyload_return = null;

        if ( is_null( $lazyload_return ) ) {
            $self = new self();
            if ( ! empty( $self->options['autoptimize_imgopt_checkbox_field_3'] ) ) {
                $lazyload_return = true;
            } else {
                $lazyload_return = false;
            }
        }

        return $lazyload_return;
    }

    public function should_webp() {
        static $webp_return = null;

        if ( is_null( $webp_return ) ) {
            // webp only works if imgopt and lazyload are also active.
            if ( ! empty( $this->options['autoptimize_imgopt_checkbox_field_4'] ) && ! empty( $this->options['autoptimize_imgopt_checkbox_field_3'] ) && $this->imgopt_active() ) {
                $webp_return = true;
            } else {
                $webp_return = false;
            }
        }

        return $webp_return;
    }

    public function filter_lazyload_images( $in )
    {
        // only used is image optimization is NOT active but lazyload is.
        $to_replace = array();

        // extract img tags and add lazyload attribs.
        if ( preg_match_all( '#<img[^>]*src[^>]*>#Usmi', $in, $matches ) ) {
            foreach ( $matches[0] as $tag ) {
                $to_replace[ $tag ] = $this->add_lazyload( $tag );
            }
            $out = str_replace( array_keys( $to_replace ), array_values( $to_replace ), $in );
        } else {
            $out = $in;
        }

        return $out;
    }

    public function add_lazyload( $tag ) {
        // adds actual lazyload-attributes to a script node.
        $target_class = 'lazyload ';

        if ( $this->should_webp() ) {
            $target_class .= 'webp ';
        }

        if ( str_ireplace( $this->get_lazyload_exclusions(), '', $tag ) === $tag ) {
            if ( strpos( $tag, 'class=' ) !== false ) {
                $tag = preg_replace( '/(\sclass\s?=\s?("|\'))/', '$1' . $target_class, $tag );
            } else {
                $tag = str_replace( '<img ', '<img class="' . trim( $target_class ) .   '" ', $tag );
            }

            $placeholder = apply_filters( 'autoptimize_filter_imgopt_lazyload_placeholder', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mPcuGOBMQAGaQI+RTWDqQAAAABJRU5ErkJggg==' );
            $tag         = str_replace( ' src=', ' src="' . $placeholder . '" data-src=', $tag );
            $tag         = str_replace( ' srcset=', ' data-srcset=', $tag );
        }

        return $tag;
    }

    public function add_lazyload_js() {
        // adds lazyload JS to footer, using echo because wp_enqueue_script seems not to support pushing attributes (async).
        echo apply_filters( 'autoptimize_filter_imgopt_lazyload_jsconfig', '<script data-noptimize=\'1\'>window.lazySizesConfig=window.lazySizesConfig||{};window.lazySizesConfig.loadMode=0;</script>' );
        echo '<script data-noptimize=\'1\' async src=\'' . plugins_url( 'external/js/lazysizes.min.js', __FILE__ ) . '\'></script>';
    }

    public function get_lazyload_exclusions() {
        // returns array of strings that if found in an <img tag will stop the img from being lazy-loaded.
        return apply_filters( 'autoptimize_filter_imgopt_lazyload_exclude_array', array( 'skip-lazy', 'data-no-lazy', 'notlazy', 'rev-slidebg', 'data-src', 'data-srcset' ) );
    }

    /**
     * Admin page logic below.
     */
    public function imgopt_admin_menu()
    {
        add_submenu_page(
            null,
            'autoptimize_imgopt',
            'autoptimize_imgopt',
            'manage_options',
            'autoptimize_imgopt',
            array( $this, 'imgopt_options_page' )
        );
        register_setting( 'autoptimize_imgopt_settings', 'autoptimize_imgopt_settings' );
    }

    public function add_imgopt_tab( $in )
    {
        $in = array_merge( $in, array( 'autoptimize_imgopt' => __( 'Images', 'autoptimize' ) ) );

        return $in;
    }

    public function imgopt_options_page()
    {
        // Check querystring for "refreshCacheChecker" and call cachechecker if so.
        if ( array_key_exists( 'refreshImgProvStats', $_GET ) && 1 == $_GET['refreshImgProvStats'] ) {
            $this->query_img_provider_stats();
        }

        $options       = $this->fetch_options();
        $sp_url_suffix = $this->get_service_url_suffix();
        ?>
    <style>
        #ao_settings_form {background: white;border: 1px solid #ccc;padding: 1px 15px;margin: 15px 10px 10px 0;}
        #ao_settings_form .form-table th {font-weight: normal;}
        #autoptimize_imgopt_descr{font-size: 120%;}
    </style>
    <div class="wrap">
    <h1><?php _e( 'Autoptimize Settings', 'autoptimize' ); ?></h1>
        <?php echo autoptimizeConfig::ao_admin_tabs(); ?>
        <?php if ( 'down' === $options['availabilities']['extra_imgopt']['status'] ) { ?>
            <div class="notice-warning notice"><p>
            <?php
            // translators: "Autoptimize support forum" will appear in a "a href".
            echo sprintf( __( 'The image optimization service is currently down, image optimization will be skipped until further notice. Check the %1$sAutoptimize support forum%2$s for more info.', 'autoptimize' ), '<a href="https://wordpress.org/support/plugin/autoptimize/" target="_blank">', '</a>' );
            ?>
            </p></div>
        <?php } ?>

        <?php if ( 'launch' === $options['availabilities']['extra_imgopt']['status'] && ! autoptimizeImages::instance()->launch_ok() ) { ?>
            <div class="notice-warning notice"><p>
            <?php _e( 'The image optimization service is launching, but not yet available for this domain, it should become available in the next couple of days.', 'autoptimize' ); ?>
            </p></div>
        <?php } ?>
    <form id='ao_settings_form' action='options.php' method='post'>
        <?php settings_fields( 'autoptimize_imgopt_settings' ); ?>
        <h2><?php _e( 'Image optimization', 'autoptimize' ); ?></h2>
        <span id='autoptimize_imgopt_descr'><?php _e( 'Optimize all your images with one click of a checkbox!', 'autoptimize' ); ?></span>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e( 'Optimize Images', 'autoptimize' ); ?></th>
                <td>
                    <label><input id='autoptimize_imgopt_checkbox' type='checkbox' name='autoptimize_imgopt_settings[autoptimize_imgopt_checkbox_field_1]' <?php if ( ! empty( $options['autoptimize_imgopt_checkbox_field_1'] ) && '1' === $options['autoptimize_imgopt_checkbox_field_1'] ) { echo 'checked="checked"'; } ?> value='1'><?php _e( 'Optimize images on the fly and serve them from a CDN.', 'autoptimize' ); ?></label>
                    <?php
                    // show shortpixel status.
                    $_notice = autoptimizeImages::instance()->get_status_notice();
                    if ( $_notice ) {
                        switch ( $_notice['status'] ) {
                            case 2:
                                $_notice_color = 'green';
                                break;
                            case 1:
                                $_notice_color = 'orange';
                                break;
                            case -1:
                                $_notice_color = 'red';
                                break;
                            case -2:
                                $_notice_color = 'red';
                                break;
                            default:
                                $_notice_color = 'green';
                        }
                        echo apply_filters( 'autoptimize_filter_imgopt_settings_status', '<p><strong><span style="color:' . $_notice_color . ';">' . __( 'Shortpixel status: ', 'autoptimize' ) . '</span></strong>' . $_notice['notice'] . '</p>' );
                    } else {
                        // translators: link points to shortpixel.
                        $upsell_msg_1 = '<p>' . sprintf( __( 'Get more Google love and improve your website\'s loading speed by having the images optimized on the fly by %1$sShortPixel%2$s and then cached and served fast from a CDN.', 'autoptimize' ), '<a href="https://shortpixel.com/aospai' . $sp_url_suffix . '" target="_blank">', '</a>' );
                        if ( 'launch' === $options['availabilities']['extra_imgopt']['status'] ) {
                            $upsell_msg_2 = __( 'For a limited time only, this service is offered free for all Autoptimize users, <b>don\'t miss the chance to test it</b> and see how much it could improve your site\'s speed.', 'autoptimize' );
                        } else {
                            // translators: link points to shortpixel.
                            $upsell_msg_2 = sprintf( __( '%1$sSign-up now%2$s to receive a 1 000 bonus + 50&#37; more image optimization credits regardless of the traffic used. More image optimizations can be purchased starting with $4.99.', 'autoptimize' ), '<a href="https://shortpixel.com/aospai' . $sp_url_suffix . '" target="_blank">', '</a>' );
                        }
                        echo apply_filters( 'autoptimize_imgopt_imgopt_settings_copy', $upsell_msg_1 . ' ' . $upsell_msg_2 . '</p>' );
                    }
                    // translators: link points to shortpixel FAQ.
                    $faqcopy = sprintf( __( '<strong>Questions</strong>? Have a look at the %1$sShortPixel FAQ%2$s!', 'autoptimize' ), '<strong><a href="https://shortpixel.helpscoutdocs.com/category/60-shortpixel-ai-cdn" target="_blank">', '</strong></a>' );
                    // translators: links points to shortpixel TOS & Privacy Policy.
                    $toscopy = sprintf( __( 'Usage of this feature is subject to Shortpixel\'s %1$sTerms of Use%2$s and %3$sPrivacy policy%4$s.', 'autoptimize' ), '<a href="https://shortpixel.com/tos' . $sp_url_suffix . '" target="_blank">', '</a>', '<a href="https://shortpixel.com/pp' . $sp_url_suffix . '" target="_blank">', '</a>' );
                    echo apply_filters( 'autoptimize_imgopt_imgopt_settings_tos', '<p>' . $faqcopy . ' ' . $toscopy . '</p>' );
                    ?>
                </td>
            </tr>
            <tr id='autoptimize_imgopt_quality' <?php if ( ! array_key_exists( 'autoptimize_imgopt_checkbox_field_1', $options ) || ( isset( $options['autoptimize_imgopt_checkbox_field_1'] ) && '1' !== $options['autoptimize_imgopt_checkbox_field_1'] ) ) { echo 'class="hidden"'; } ?>>
                <th scope="row"><?php _e( 'Image Optimization quality', 'autoptimize' ); ?></th>
                <td>
                    <label>
                    <select name='autoptimize_imgopt_settings[autoptimize_imgopt_select_field_2]'>
                        <?php
                        $_imgopt_array = autoptimizeImages::instance()->get_img_quality_array();
                        $_imgopt_val   = autoptimizeImages::instance()->get_img_quality_setting();

                        foreach ( $_imgopt_array as $key => $value ) {
                            echo '<option value="' . $key . '"';
                            if ( $_imgopt_val == $key ) {
                                echo ' selected';
                            }
                            echo '>' . ucfirst( $value ) . '</option>';
                        }
                        echo "\n";
                        ?>
                    </select>
                    </label>
                    <p>
                        <?php
                            // translators: link points to shortpixel image test page.
                            echo apply_filters( 'autoptimize_imgopt_imgopt_quality_copy', sprintf( __( 'You can %1$stest compression levels here%2$s.', 'autoptimize' ), '<a href="https://shortpixel.com/oic' . $sp_url_suffix . '" target="_blank">', '</a>' ) );
                        ?>
                    </p>
                </td>
            </tr>
            <tr class='hidden' id='autoptimize_imgopt_webp' <?php if ( ! array_key_exists( 'autoptimize_imgopt_checkbox_field_1', $options ) || ( isset( $options['autoptimize_imgopt_checkbox_field_1'] ) && '1' !== $options['autoptimize_imgopt_checkbox_field_1'] ) ) { echo 'class="hidden"'; } ?>>
                <th scope="row"><?php _e( 'Load webp in supported browsers?', 'autoptimize' ); ?></th>
                <td>
                    <label><input type='checkbox' id='autoptimize_imgopt_webp_checkbox' name='autoptimize_imgopt_settings[autoptimize_imgopt_checkbox_field_4]' <?php if ( ! empty( $options['autoptimize_imgopt_checkbox_field_4'] ) && '1' === $options['autoptimize_imgopt_checkbox_field_3'] ) { echo 'checked="checked"'; } ?> value='1'><?php _e( 'Allow image optimization to load webp-images in browsers that support it (requires lazy load to be active).', 'autoptimize' ); ?></label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Lazy-load images?', 'autoptimize' ); ?></th>
                <td>
                    <label><input type='checkbox' id='autoptimize_imgopt_lazyload_checkbox' name='autoptimize_imgopt_settings[autoptimize_imgopt_checkbox_field_3]' <?php if ( ! empty( $options['autoptimize_imgopt_checkbox_field_3'] ) && '1' === $options['autoptimize_imgopt_checkbox_field_3'] ) { echo 'checked="checked"'; } ?> value='1'><?php _e( 'Image lazy-loading will delay the loading of non-visible images to allow the browser to optimally load all resources for the "above the fold"-page first.', 'autoptimize' ); ?></label>
                </td>
            </tr>
        </table>
        <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Save Changes', 'autoptimize' ); ?>" /></p>
    </form>
    <script>
        jQuery(document).ready(function() {
            jQuery( "#autoptimize_imgopt_checkbox" ).change(function() {
                if (this.checked) {
                    jQuery("#autoptimize_imgopt_quality").show("slow");
                    // jQuery("#autoptimize_imgopt_webp").show("slow");
                } else {
                    jQuery("#autoptimize_imgopt_quality").hide("slow");
                    jQuery("#autoptimize_imgopt_webp").hide("slow");
                }
            });
            jQuery( "#autoptimize_imgopt_webp_checkbox" ).change(function() {
                if (this.checked) {
                    jQuery("#autoptimize_imgopt_lazyload_checkbox")[0].checked = true;
                }
            });
            jQuery( "#autoptimize_imgopt_lazyload_checkbox" ).change(function() {
                if (!this.checked) {
                    jQuery("#autoptimize_imgopt_webp_checkbox")[0].checked = false;
                }
            });
        });
    </script>
        <?php
    }
}
