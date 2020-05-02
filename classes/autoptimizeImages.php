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
        $value = autoptimizeOptionWrapper::get_option( 'autoptimize_imgopt_settings' );
        if ( empty( $value ) ) {
            // Fallback to returning defaults when no stored option exists yet.
            $value = autoptimizeConfig::get_ao_imgopt_default_options();
        }

        // get service availability and add it to the options-array.
        $value['availabilities'] = autoptimizeOptionWrapper::get_option( 'autoptimize_service_availablity' );

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
            $opts = autoptimizeOptionWrapper::get_option( 'autoptimize_imgopt_settings', '' );
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
            if ( is_multisite() && is_network_admin() && autoptimizeOptionWrapper::is_ao_active_for_network() ) {
                add_action( 'network_admin_menu', array( $this, 'imgopt_admin_menu' ) );
            } else {
                add_action( 'admin_menu', array( $this, 'imgopt_admin_menu' ) );
            }
            add_filter( 'autoptimize_filter_settingsscreen_tabs', array( $this, 'add_imgopt_tab' ), 9 );
        } else {
            add_action( 'wp', array( $this, 'run_on_frontend' ) );
        }
    }

    public function run_on_frontend() {
        if ( ! $this->should_run() ) {
            if ( $this->should_lazyload() ) {
                add_filter(
                    'wp_lazy_loading_enabled',
                    '__return_false'
                );
                add_filter(
                    'autoptimize_html_after_minify',
                    array( $this, 'filter_lazyload_images' ),
                    10,
                    1
                );
                add_action(
                    'wp_footer',
                    array( $this, 'add_lazyload_js_footer' ),
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
            add_filter(
                'wp_lazy_loading_enabled',
                '__return_false'
            );
            add_action(
                'wp_footer',
                array( $this, 'add_lazyload_js_footer' ),
                10,
                0
            );
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
        if ( isset( $_userstatus['Status'] ) && ( -2 == $_userstatus['Status'] || -3 == $_userstatus['Status'] ) ) {
            // don't even attempt to put images on CDN if heavily exceeded threshold or if site not reachable.
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

    public function get_imgopt_host()
    {
        static $imgopt_host = null;

        if ( null === $imgopt_host ) {
            $imgopt_host  = 'https://cdn.shortpixel.ai/';
            $avail_imgopt = $this->options['availabilities']['extra_imgopt'];
            if ( ! empty( $avail_imgopt ) && array_key_exists( 'hosts', $avail_imgopt ) && is_array( $avail_imgopt['hosts'] ) ) {
                $imgopt_host = array_rand( array_flip( $avail_imgopt['hosts'] ) );
            }
            $imgopt_host = apply_filters( 'autoptimize_filter_imgopt_host', $imgopt_host );
        }

        return $imgopt_host;
    }

    public static function get_imgopt_host_wrapper()
    {
        // needed for CI tests.
        $self = new self();
        return $self->get_imgopt_host();
    }

    public static function get_service_url_suffix()
    {
        $suffix = '/af/GWRGFLW109483/' . AUTOPTIMIZE_SITE_DOMAIN;

        return $suffix;
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
            $cdn_url = $this->get_cdn_url();
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
            // Default to (the trimmed version of) what was given to us.
            $result = trim( $in );

            // Some silly plugins wrap background images in html-encoded quotes, so remove those from the img url.
            if ( strpos( $result, '&quot;' ) !== false ) {
                $result = str_replace( '&quot;', '', $result );
            }

            if ( autoptimizeUtils::is_protocol_relative( $result ) ) {
                $result = $parsed_site_url['scheme'] . ':' . $result;
            } elseif ( 0 === strpos( $result, '/' ) ) {
                // Root-relative...
                $result = $parsed_site_url['scheme'] . '://' . $parsed_site_url['host'] . $result;
            } elseif ( ! empty( $cdn_domain ) && strpos( $result, $cdn_domain ) !== 0 ) {
                $result = str_replace( $cdn_domain, $parsed_site_url['host'], $result );
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
                autoptimizeOptionWrapper::get_option( 'autoptimize_cdn_url', '' )
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
        return $this->replace_img_callback( $matches, 150, 150 );
    }

    public function replace_img_callback( $matches, $width = 0, $height = 0 )
    {
        $_normalized_img_url = $this->normalize_img_url( $matches[1] );
        if ( $this->can_optimize_image( $matches[1] ) ) {
            return str_replace( $matches[1], $this->build_imgopt_url( $_normalized_img_url, $width, $height ), $matches[0] );
        } else {
            return $matches[0];
        }
    }

    public function filter_optimize_images( $in )
    {
        /*
         * potential future functional improvements:
         *
         * filter for critical CSS.
         */
        $to_replace = array();

        // hide noscript tags to avoid nesting noscript tags (as lazyloaded images add noscript).
        if ( $this->should_lazyload() ) {
            $in = autoptimizeBase::replace_contents_with_marker_if_exists(
                'SCRIPT',
                '<script',
                '#<(?:no)?script.*?<\/(?:no)?script>#is',
                $in
            );
        }

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
                            if ( isset( $indiv_srcset_parts[1] ) && rtrim( $indiv_srcset_parts[1], 'w' ) !== $indiv_srcset_parts[1] ) {
                                $imgopt_w = rtrim( $indiv_srcset_parts[1], 'w' );
                            }
                            if ( $this->can_optimize_image( $indiv_srcset_parts[0] ) ) {
                                $imgopt_url = $this->build_imgopt_url( $indiv_srcset_parts[0], $imgopt_w, '' );
                                $tag        = str_replace( $indiv_srcset_parts[0], $imgopt_url, $tag );
                            }
                        }
                    }
                }

                // proceed with img src.
                // get width and height and add to $imgopt_size.
                $_get_size = $this->get_size_from_tag( $tag );
                $imgopt_w  = $_get_size['width'];
                $imgopt_h  = $_get_size['height'];

                // then start replacing images src.
                if ( preg_match_all( '#src=(?:"|\')(?!data)(.*)(?:"|\')#Usmi', $tag, $urls, PREG_SET_ORDER ) ) {
                    foreach ( $urls as $url ) {
                        $full_src_orig = $url[0];
                        $url           = $url[1];
                        if ( $this->can_optimize_image( $url ) ) {
                            $imgopt_url      = $this->build_imgopt_url( $url, $imgopt_w, $imgopt_h );
                            $full_imgopt_src = str_replace( $url, $imgopt_url, $full_src_orig );
                            $tag             = str_replace( $full_src_orig, $full_imgopt_src, $tag );
                        }
                    }
                }

                // do lazyload stuff.
                if ( $this->should_lazyload( $in ) && ! empty( $url ) ) {
                    // first do lpiq placeholder logic.
                    if ( strpos( $url, $this->get_imgopt_host() ) === 0 ) {
                        // if all img src have been replaced during srcset, we have to extract the
                        // origin url from the imgopt one to be able to set a lqip placeholder.
                        $_url = substr( $url, strpos( $url, '/http' ) + 1 );
                    } else {
                        $_url = $url;
                    }

                    $_url = $this->normalize_img_url( $_url );

                    $placeholder = '';
                    if ( $this->can_optimize_image( $_url ) && apply_filters( 'autoptimize_filter_imgopt_lazyload_dolqip', true ) ) {
                        $lqip_w = '';
                        $lqip_h = '';
                        if ( isset( $imgopt_w ) && ! empty( $imgopt_w ) ) {
                            $lqip_w = ',w_' . $imgopt_w;
                        }
                        if ( isset( $imgopt_h ) && ! empty( $imgopt_h ) ) {
                            $lqip_h = ',h_' . $imgopt_h;
                        }
                        $placeholder = $this->get_imgopt_host() . 'client/q_lqip,ret_wait' . $lqip_w . $lqip_h . '/' . $_url;
                    }
                    // then call add_lazyload-function with lpiq placeholder if set.
                    $tag = $this->add_lazyload( $tag, $placeholder );
                }

                // and add tag to array for later replacement.
                if ( $tag !== $orig_tag ) {
                    $to_replace[ $orig_tag ] = $tag;
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
                '/style=(?:"|\')[^<>]*?background-image:\s?url\((?:"|\')?([^"\')]*)(?:"|\')?\)/',
                array( $this, 'replace_img_callback' ),
                $out
            );
        }

        // lazyload: restore noscript tags + lazyload picture source tags and bgimage.
        if ( $this->should_lazyload() ) {
            $out = autoptimizeBase::restore_marked_content(
                'SCRIPT',
                $out
            );

            $out = $this->process_picture_tag( $out, true, true );
            $out = $this->process_bgimage( $out );
        } else {
            $out = $this->process_picture_tag( $out, true, false );
        }

        return $out;
    }

    public function get_size_from_tag( $tag ) {
        // reusable function to extract widht and height from an image tag
        // enforcing a filterable maximum width and height (default 4999X4999).
        $width  = '';
        $height = '';

        if ( preg_match( '#width=("|\')(.*)("|\')#Usmi', $tag, $_width ) ) {
            if ( strpos( $_width[2], '%' ) === false ) {
                $width = (int) $_width[2];
            }
        }
        if ( preg_match( '#height=("|\')(.*)("|\')#Usmi', $tag, $_height ) ) {
            if ( strpos( $_height[2], '%' ) === false ) {
                $height = (int) $_height[2];
            }
        }

        // check for and enforce (filterable) max sizes.
        $_max_width = apply_filters( 'autoptimize_filter_imgopt_max_width', 4999 );
        if ( $width > $_max_width ) {
            $_width = $_max_width;
            $height = $_width / $width * $height;
            $width  = $_width;
        }
        $_max_height = apply_filters( 'autoptimize_filter_imgopt_max_height', 4999 );
        if ( $height > $_max_height ) {
            $_height = $_max_height;
            $width   = $_height / $height * $width;
            $height  = $_height;
        }

        return array(
            'width'  => $width,
            'height' => $height,
        );
    }

    /**
     * Lazyload functions
     */
    public static function should_lazyload_wrapper() {
        // needed in autoptimizeMain.php.
        $self = new self();
        return $self->should_lazyload();
    }

    public function should_lazyload( $context = '' ) {
        if ( ! empty( $this->options['autoptimize_imgopt_checkbox_field_3'] ) && false === $this->check_nolazy() ) {
            $lazyload_return = true;
        } else {
            $lazyload_return = false;
        }
        $lazyload_return = apply_filters( 'autoptimize_filter_imgopt_should_lazyload', $lazyload_return, $context );

        return $lazyload_return;
    }

    public function check_nolazy() {
        if ( array_key_exists( 'ao_nolazy', $_GET ) && '1' === $_GET['ao_nolazy'] ) {
            return true;
        } else {
            return false;
        }
    }

    public function filter_lazyload_images( $in )
    {
        // only used is image optimization is NOT active but lazyload is.
        $to_replace = array();

        // hide (no)script tags to avoid nesting noscript tags (as lazyloaded images add noscript).
        $out = autoptimizeBase::replace_contents_with_marker_if_exists(
            'SCRIPT',
            '<script',
            '#<(?:no)?script.*?<\/(?:no)?script>#is',
            $in
        );

        // extract img tags and add lazyload attribs.
        if ( preg_match_all( '#<img[^>]*src[^>]*>#Usmi', $out, $matches ) ) {
            foreach ( $matches[0] as $tag ) {
                if ( $this->should_lazyload( $out ) ) {
                    $to_replace[ $tag ] = $this->add_lazyload( $tag );
                }
            }
            $out = str_replace( array_keys( $to_replace ), array_values( $to_replace ), $out );
        }

        // and also lazyload picture tag.
        $out = $this->process_picture_tag( $out, false, true );

        // and inline style blocks with background-image.
        $out = $this->process_bgimage( $out );

        // restore noscript tags.
        $out = autoptimizeBase::restore_marked_content(
            'SCRIPT',
            $out
        );

        return $out;
    }

    public function add_lazyload( $tag, $placeholder = '' ) {
        // adds actual lazyload-attributes to an image node.
        if ( str_ireplace( $this->get_lazyload_exclusions(), '', $tag ) === $tag ) {
            $tag = $this->maybe_fix_missing_quotes( $tag );

            // store original tag for use in noscript version.
            $noscript_tag = '<noscript>' . autoptimizeUtils::remove_id_from_node( $tag ) . '</noscript>';

            $lazyload_class = apply_filters( 'autoptimize_filter_imgopt_lazyload_class', 'lazyload' );

            // insert lazyload class.
            $tag = $this->inject_classes_in_tag( $tag, "$lazyload_class " );

            if ( ! $placeholder || empty( $placeholder ) ) {
                // get image width & heigth for placeholder fun (and to prevent content reflow).
                $_get_size = $this->get_size_from_tag( $tag );
                $width     = $_get_size['width'];
                $height    = $_get_size['height'];
                if ( false === $width || empty( $width ) ) {
                    $width = 210; // default width for SVG placeholder.
                }
                if ( false === $height || empty( $height ) ) {
                    $height = $width / 3 * 2; // if no height, base it on width using the 3/2 aspect ratio.
                }

                // insert the actual lazyload stuff.
                // see https://css-tricks.com/preventing-content-reflow-from-lazy-loaded-images/ for great read on why we're using empty svg's.
                $placeholder = apply_filters( 'autoptimize_filter_imgopt_lazyload_placeholder', $this->get_default_lazyload_placeholder( $width, $height ) );
            }

            $tag = preg_replace( '/(\s)src=/', ' src=\'' . $placeholder . '\' data-src=', $tag );
            $tag = preg_replace( '/(\s)srcset=/', ' data-srcset=', $tag );

            // move sizes to data-sizes unless filter says no.
            if ( apply_filters( 'autoptimize_filter_imgopt_lazyload_move_sizes', true ) ) {
                $tag = str_replace( ' sizes=', ' data-sizes=', $tag );
            }

            // add the noscript-tag from earlier.
            $tag = $noscript_tag . $tag;
            $tag = apply_filters( 'autoptimize_filter_imgopt_lazyloaded_img', $tag );
        }

        return $tag;
    }

    public function add_lazyload_js_footer() {
        if ( false === autoptimizeMain::should_buffer() ) {
            return;
        }

        // The JS will by default be excluded form autoptimization but this can be changed with a filter.
        $noptimize_flag = '';
        if ( apply_filters( 'autoptimize_filter_imgopt_lazyload_js_noptimize', true ) ) {
            $noptimize_flag = ' data-noptimize="1"';
        }

        $lazysizes_js = plugins_url( 'external/js/lazysizes.min.js?ao_version=' . AUTOPTIMIZE_PLUGIN_VERSION, __FILE__ );
        $cdn_url      = $this->get_cdn_url();
        if ( ! empty( $cdn_url ) ) {
            $lazysizes_js = str_replace( AUTOPTIMIZE_WP_SITE_URL, $cdn_url, $lazysizes_js );
        }

        $type_js = '';
        if ( apply_filters( 'autoptimize_filter_cssjs_addtype', false ) ) {
            $type_js = ' type="text/javascript"';
        }

        // Adds lazyload CSS & JS to footer, using echo because wp_enqueue_script seems not to support pushing attributes (async).
        echo apply_filters( 'autoptimize_filter_imgopt_lazyload_cssoutput', '<style>.lazyload,.lazyloading{opacity:0;}.lazyloaded{opacity:1;transition:opacity 300ms;}</style><noscript><style>.lazyload{display:none;}</style></noscript>' );
        echo apply_filters( 'autoptimize_filter_imgopt_lazyload_jsconfig', '<script' . $type_js . $noptimize_flag . '>window.lazySizesConfig=window.lazySizesConfig||{};window.lazySizesConfig.loadMode=1;</script>' );
        echo apply_filters( 'autoptimize_filter_imgopt_lazyload_js', '<script async' . $type_js . $noptimize_flag . ' src=\'' . $lazysizes_js . '\'></script>' );

        // And add webp detection and loading JS.
        if ( $this->should_webp() ) {
            $_webp_detect = "function c_webp(A){var n=new Image;n.onload=function(){var e=0<n.width&&0<n.height;A(e)},n.onerror=function(){A(!1)},n.src='data:image/webp;base64,UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA=='}function s_webp(e){window.supportsWebP=e}c_webp(s_webp);";
            $_webp_load   = "document.addEventListener('lazybeforeunveil',function({target:c}){supportsWebP&&['data-src','data-srcset'].forEach(function(a){attr=c.getAttribute(a),null!==attr&&c.setAttribute(a,attr.replace(/\/client\//,'/client/to_webp,'))})});";
            echo apply_filters( 'autoptimize_filter_imgopt_webp_js', '<script' . $type_js . $noptimize_flag . '>' . $_webp_detect . $_webp_load . '</script>' );
        }
    }

    public function get_cdn_url() {
        // getting CDN url here to avoid having to make bigger changes to autoptimizeBase.
        static $cdn_url = null;

        if ( null === $cdn_url ) {
            $cdn_url = autoptimizeOptionWrapper::get_option( 'autoptimize_cdn_url', '' );
            $cdn_url = autoptimizeUtils::tweak_cdn_url_if_needed( $cdn_url );
            $cdn_url = apply_filters( 'autoptimize_filter_base_cdnurl', $cdn_url );
        }

        return $cdn_url;
    }

    public function get_lazyload_exclusions() {
        // returns array of strings that if found in an <img tag will stop the img from being lazy-loaded.
        static $exclude_lazyload_array = null;

        if ( null === $exclude_lazyload_array ) {
            $options = $this->options;

            // set default exclusions.
            $exclude_lazyload_array = array( 'skip-lazy', 'data-no-lazy', 'notlazy', 'data-src', 'data-srcset', 'data:image/', 'data-lazyload', 'rev-slidebg', 'loading="eager"' );

            // add from setting.
            if ( array_key_exists( 'autoptimize_imgopt_text_field_5', $options ) ) {
                $exclude_lazyload_option = $options['autoptimize_imgopt_text_field_5'];
                if ( ! empty( $exclude_lazyload_option ) ) {
                    $exclude_lazyload_array = array_merge( $exclude_lazyload_array, array_filter( array_map( 'trim', explode( ',', $options['autoptimize_imgopt_text_field_5'] ) ) ) );
                }
            }

            // and filter for developer-initiated changes.
            $exclude_lazyload_array = apply_filters( 'autoptimize_filter_imgopt_lazyload_exclude_array', $exclude_lazyload_array );
        }

        return $exclude_lazyload_array;
    }

    public function inject_classes_in_tag( $tag, $target_class ) {
        if ( strpos( $tag, 'class=' ) !== false ) {
            $tag = preg_replace( '/(\sclass\s?=\s?("|\'))/', '$1' . $target_class, $tag );
        } else {
            $tag = preg_replace( '/(<img)\s/', '$1 class="' . trim( $target_class ) . '" ', $tag );
        }

        return $tag;
    }

    public function get_default_lazyload_placeholder( $imgopt_w, $imgopt_h ) {
        return 'data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20viewBox=%220%200%20' . $imgopt_w . '%20' . $imgopt_h . '%22%3E%3C/svg%3E';
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

    public function process_picture_tag( $in, $imgopt = false, $lazy = false ) {
        // check if "<picture" is present and if filter allows us to process <picture>.
        if ( strpos( $in, '<picture' ) === false || apply_filters( 'autoptimize_filter_imgopt_dopicture', true ) === false ) {
            return $in;
        }

        $_exclusions     = $this->get_lazyload_exclusions();
        $to_replace_pict = array();

        // extract and process each picture-node.
        preg_match_all( '#<picture.*</picture>#Usmi', $in, $_pictures, PREG_SET_ORDER );
        foreach ( $_pictures as $_picture ) {
            $_picture = $this->maybe_fix_missing_quotes( $_picture );
            if ( strpos( $_picture[0], '<source ' ) !== false && preg_match_all( '#<source .*srcset=(?:"|\')(?!data)(.*)(?:"|\').*>#Usmi', $_picture[0], $_sources, PREG_SET_ORDER ) !== false ) {
                foreach ( $_sources as $_source ) {
                    $_picture_replacement = $_source[0];

                    // should we optimize the image?
                    if ( $imgopt && $this->can_optimize_image( $_source[1] ) ) {
                        $_picture_replacement = str_replace( $_source[1], $this->build_imgopt_url( $_source[1] ), $_picture_replacement );
                    }
                    // should we lazy-load?
                    if ( $lazy && $this->should_lazyload() && str_ireplace( $_exclusions, '', $_picture_replacement ) === $_picture_replacement ) {
                        $_picture_replacement = str_replace( ' srcset=', ' data-srcset=', $_picture_replacement );
                    }
                    $to_replace_pict[ $_source[0] ] = $_picture_replacement;
                }
            }
        }

        // and return the fully procesed $in.
        $out = str_replace( array_keys( $to_replace_pict ), array_values( $to_replace_pict ), $in );

        return $out;
    }

    public function process_bgimage( $in ) {
        if ( strpos( $in, 'background-image:' ) !== false && apply_filters( 'autoptimize_filter_imgopt_lazyload_backgroundimages', true ) ) {
            $out = preg_replace_callback(
                '/(<(?:article|aside|body|div|footer|header|p|section|table)[^>]*)\sstyle=(?:"|\')[^<>]*?background-image:\s?url\((?:"|\')?([^"\')]*)(?:"|\')?\)[^>]*/',
                array( $this, 'lazyload_bgimg_callback' ),
                $in
            );
            return $out;
        }
        return $in;
    }

    public function lazyload_bgimg_callback( $matches ) {
        if ( str_ireplace( $this->get_lazyload_exclusions(), '', $matches[0] ) === $matches[0] ) {
            // get placeholder & lazyload class strings.
            $placeholder    = apply_filters( 'autoptimize_filter_imgopt_lazyload_placeholder', $this->get_default_lazyload_placeholder( 500, 300 ) );
            $lazyload_class = apply_filters( 'autoptimize_filter_imgopt_lazyload_class', 'lazyload' );
            // replace background-image URL with SVG placeholder.
            $out = str_replace( $matches[2], $placeholder, $matches[0] );
            // add data-bg attribute with real background-image URL for lazyload to pick up.
            $out = str_replace( $matches[1], $matches[1] . ' data-bg="' . trim( str_replace( "\r\n", '', $matches[2] ) ) . '"', $out );
            // add lazyload class to tag.
            $out = $this->inject_classes_in_tag( $out, "$lazyload_class " );
            return $out;
        }
        return $matches[0];
    }

    public function maybe_fix_missing_quotes( $tag_in ) {
        // W3TC's Minify_HTML class removes quotes around attribute value, this re-adds them for the class and width/height attributes so we can lazyload properly.
        if ( file_exists( WP_PLUGIN_DIR . '/w3-total-cache/w3-total-cache.php' ) && class_exists( 'Minify_HTML' ) && apply_filters( 'autoptimize_filter_imgopt_fixquotes', true ) ) {
            $tag_out = preg_replace( '/class\s?=([^("|\')]*)(\s|>)/U', 'class=\'$1\'$2', $tag_in );
            $tag_out = preg_replace( '/\s(width|height)=(?:"|\')?([^\s"\'>]*)(?:"|\')?/', ' $1=\'$2\'', $tag_out );
            return $tag_out;
        } else {
            return $tag_in;
        }
    }

    /**
     * Admin page logic and related functions below.
     */
    public function imgopt_admin_menu()
    {
        // no acces if multisite and not network admin and no site config allowed.
        if ( autoptimizeConfig::should_show_menu_tabs() ) {
            add_submenu_page(
                null,
                'autoptimize_imgopt',
                'autoptimize_imgopt',
                'manage_options',
                'autoptimize_imgopt',
                array( $this, 'imgopt_options_page' )
            );
        }
        register_setting( 'autoptimize_imgopt_settings', 'autoptimize_imgopt_settings' );
    }

    public function add_imgopt_tab( $in )
    {
        if ( autoptimizeConfig::should_show_menu_tabs() ) {
            $in = array_merge( $in, array( 'autoptimize_imgopt' => __( 'Images', 'autoptimize' ) ) );
        }

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
    <script>document.title = "Autoptimize: <?php _e( 'Images', 'autoptimize' ); ?> " + document.title;</script>
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

        <?php if ( class_exists( 'Jetpack' ) && method_exists( 'Jetpack', 'get_active_modules' ) && in_array( 'photon', Jetpack::get_active_modules() ) ) { ?>
            <div class="notice-warning notice"><p>
            <?php
            // translators: "disable  Jetpack's site accelerator for images" will appear in a "a href" linking to the jetpack settings page.
            echo sprintf( __( 'Please %1$sdisable Jetpack\'s site accelerator for images%2$s to be able to use Autoptomize\'s advanced image optimization features below.', 'autoptimize' ), '<a href="admin.php?page=jetpack#/settings">', '</a>' );
            ?>
            </p></div>
        <?php } ?>
    <form id='ao_settings_form' action='<?php echo admin_url( 'options.php' ); ?>' method='post'>
        <?php settings_fields( 'autoptimize_imgopt_settings' ); ?>
        <h2><?php _e( 'Image optimization', 'autoptimize' ); ?></h2>
        <span id='autoptimize_imgopt_descr'><?php _e( 'Make your site significantly faster by just ticking a couple of checkboxes to optimize and lazy load your images, WebP support included!', 'autoptimize' ); ?></span>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e( 'Optimize Images', 'autoptimize' ); ?></th>
                <td>
                    <label><input id='autoptimize_imgopt_checkbox' type='checkbox' name='autoptimize_imgopt_settings[autoptimize_imgopt_checkbox_field_1]' <?php if ( ! empty( $options['autoptimize_imgopt_checkbox_field_1'] ) && '1' === $options['autoptimize_imgopt_checkbox_field_1'] ) { echo 'checked="checked"'; } ?> value='1'><?php _e( 'Optimize images on the fly and serve them from Shortpixel\'s global CDN.', 'autoptimize' ); ?></label>
                    <?php
                    // show shortpixel status.
                    $_notice = autoptimizeImages::instance()->get_imgopt_status_notice();
                    if ( $_notice ) {
                        switch ( $_notice['status'] ) {
                            case 2:
                                $_notice_color = 'green';
                                break;
                            case 1:
                                $_notice_color = 'orange';
                                break;
                            case -1:
                            case -2:
                            case -3:
                                $_notice_color = 'red';
                                break;
                            default:
                                $_notice_color = 'green';
                        }
                        echo apply_filters( 'autoptimize_filter_imgopt_settings_status', '<p><strong><span style="color:' . $_notice_color . ';">' . __( 'Shortpixel status: ', 'autoptimize' ) . '</span></strong>' . $_notice['notice'] . '</p>' );
                    } else {
                        // translators: link points to shortpixel.
                        $upsell_msg_1 = '<p>' . sprintf( __( 'Get more Google love and improve your website\'s loading speed by having your publicly available images optimized on the fly (also in the "next-gen" WebP image format) by %1$sShortPixel%2$s and then cached and served fast from Shortpixel\'s global CDN.', 'autoptimize' ), '<a href="https://shortpixel.com/aospai' . $sp_url_suffix . '" target="_blank">', '</a>' );
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
                    $faqcopy = $faqcopy . ' ' . __( 'Only works for sites/ images that are publicly available.', 'autoptimize' );
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
            <tr id='autoptimize_imgopt_webp' <?php if ( ! array_key_exists( 'autoptimize_imgopt_checkbox_field_1', $options ) || ( isset( $options['autoptimize_imgopt_checkbox_field_1'] ) && '1' !== $options['autoptimize_imgopt_checkbox_field_1'] ) ) { echo 'class="hidden"'; } ?>>
                <th scope="row"><?php _e( 'Load WebP in supported browsers?', 'autoptimize' ); ?></th>
                <td>
                    <label><input type='checkbox' id='autoptimize_imgopt_webp_checkbox' name='autoptimize_imgopt_settings[autoptimize_imgopt_checkbox_field_4]' <?php if ( ! empty( $options['autoptimize_imgopt_checkbox_field_4'] ) && '1' === $options['autoptimize_imgopt_checkbox_field_3'] ) { echo 'checked="checked"'; } ?> value='1'><?php _e( 'Automatically serve "next-gen" WebP image format to any browser that supports it (requires lazy load to be active).', 'autoptimize' ); ?></label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Lazy-load images?', 'autoptimize' ); ?></th>
                <td>
                    <label><input type='checkbox' id='autoptimize_imgopt_lazyload_checkbox' name='autoptimize_imgopt_settings[autoptimize_imgopt_checkbox_field_3]' <?php if ( ! empty( $options['autoptimize_imgopt_checkbox_field_3'] ) && '1' === $options['autoptimize_imgopt_checkbox_field_3'] ) { echo 'checked="checked"'; } ?> value='1'><?php _e( 'Image lazy-loading will delay the loading of non-visible images to allow the browser to optimally load all resources for the "above the fold"-page first.', 'autoptimize' ); ?></label>
                </td>
            </tr>
            <tr id='autoptimize_imgopt_lazyload_exclusions' <?php if ( ! array_key_exists( 'autoptimize_imgopt_checkbox_field_3', $options ) || ( isset( $options['autoptimize_imgopt_checkbox_field_3'] ) && '1' !== $options['autoptimize_imgopt_checkbox_field_3'] ) ) { echo 'class="hidden"'; } ?>>
                <th scope="row"><?php _e( 'Lazy-load exclusions', 'autoptimize' ); ?></th>
                <td>
                    <label><input type='text' style='width:80%' id='autoptimize_imgopt_lazyload_exclusions' name='autoptimize_imgopt_settings[autoptimize_imgopt_text_field_5]' value='<?php if ( ! empty( $options['autoptimize_imgopt_text_field_5'] ) ) { echo esc_attr( $options['autoptimize_imgopt_text_field_5'] ); } ?>'><br /><?php _e( 'Comma-separated list of to be excluded image classes or filenames.', 'autoptimize' ); ?></label>
                </td>
            </tr>
        </table>
        <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Save Changes', 'autoptimize' ); ?>" /></p>
    </form>
    <script>
        jQuery(document).ready(function() {
            jQuery("#autoptimize_imgopt_checkbox").change(function() {
                if (this.checked) {
                    jQuery("#autoptimize_imgopt_quality").show("slow");
                    jQuery("#autoptimize_imgopt_webp").show("slow");
                } else {
                    jQuery("#autoptimize_imgopt_quality").hide("slow");
                    jQuery("#autoptimize_imgopt_webp").hide("slow");
                }
            });
            jQuery("#autoptimize_imgopt_webp_checkbox").change(function() {
                if (this.checked) {
                    jQuery("#autoptimize_imgopt_lazyload_checkbox")[0].checked = true;
                    jQuery("#autoptimize_imgopt_lazyload_exclusions").show("slow");
                }
            });
            jQuery("#autoptimize_imgopt_lazyload_checkbox").change(function() {
                if (this.checked) {
                    jQuery("#autoptimize_imgopt_lazyload_exclusions").show("slow");
                } else {
                    jQuery("#autoptimize_imgopt_lazyload_exclusions").hide("slow");
                    jQuery("#autoptimize_imgopt_webp_checkbox")[0].checked = false;
                }
            });
        });
    </script>
        <?php
    }

    /**
     * Ïmg opt status as used on dashboard.
     */
    public function get_imgopt_status_notice() {
        if ( $this->imgopt_active() ) {
            $_imgopt_notice  = '';
            $_stat           = autoptimizeOptionWrapper::get_option( 'autoptimize_imgopt_provider_stat', '' );
            $_site_host      = AUTOPTIMIZE_SITE_DOMAIN;
            $_imgopt_upsell  = 'https://shortpixel.com/aospai/af/GWRGFLW109483/' . $_site_host;
            $_imgopt_assoc   = 'https://shortpixel.helpscoutdocs.com/article/94-how-to-associate-a-domain-to-my-account';
            $_imgopt_unreach = 'https://shortpixel.helpscoutdocs.com/article/148-why-are-my-images-redirected-from-cdn-shortpixel-ai';

            if ( is_array( $_stat ) ) {
                if ( 1 == $_stat['Status'] ) {
                    // translators: "add more credits" will appear in a "a href".
                    $_imgopt_notice = sprintf( __( 'Your ShortPixel image optimization and CDN quota is almost used, make sure you %1$sadd more credits%2$s to avoid slowing down your website.', 'autoptimize' ), '<a href="' . $_imgopt_upsell . '" target="_blank">', '</a>' );
                } elseif ( -1 == $_stat['Status'] || -2 == $_stat['Status'] ) {
                    // translators: "add more credits" will appear in a "a href".
                    $_imgopt_notice = sprintf( __( 'Your ShortPixel image optimization and CDN quota was used, %1$sadd more credits%2$s to keep fast serving optimized images on your site', 'autoptimize' ), '<a href="' . $_imgopt_upsell . '" target="_blank">', '</a>' );
                    // translators: "associate your domain" will appear in a "a href".
                    $_imgopt_notice = $_imgopt_notice . ' ' . sprintf( __( 'If you already have enough credits then you may need to %1$sassociate your domain%2$s to your Shortpixel account.', 'autoptimize' ), '<a rel="noopener noreferrer" href="' . $_imgopt_assoc . '" target="_blank">', '</a>' );
                } elseif ( -3 == $_stat['Status'] ) {
                    // translators: "check the documentation here" will appear in a "a href".
                    $_imgopt_notice = sprintf( __( 'It seems ShortPixel image optimization is not able to fetch images from your site, %1$scheck the documentation here%2$s for more information', 'autoptimize' ), '<a href="' . $_imgopt_unreach . '" target="_blank">', '</a>' );
                } else {
                    $_imgopt_upsell = 'https://shortpixel.com/g/af/GWRGFLW109483';
                    // translators: "log in to check your account" will appear in a "a href".
                    $_imgopt_notice = sprintf( __( 'Your ShortPixel image optimization and CDN quota are in good shape, %1$slog in to check your account%2$s.', 'autoptimize' ), '<a href="' . $_imgopt_upsell . '" target="_blank">', '</a>' );
                }

                // add info on freshness + refresh link if status is not 2 (good shape).
                if ( 2 != $_stat['Status'] ) {
                    $_imgopt_stats_refresh_url = add_query_arg( array(
                        'page'                => 'autoptimize_imgopt',
                        'refreshImgProvStats' => '1',
                    ), admin_url( 'options-general.php' ) );
                    if ( $_stat && array_key_exists( 'timestamp', $_stat ) && ! empty( $_stat['timestamp'] ) ) {
                        $_imgopt_stats_last_run = __( 'based on status at ', 'autoptimize' ) . date_i18n( autoptimizeOptionWrapper::get_option( 'time_format' ), $_stat['timestamp'] );
                    } else {
                        $_imgopt_stats_last_run = __( 'based on previously fetched data', 'autoptimize' );
                    }
                    $_imgopt_notice .= ' (' . $_imgopt_stats_last_run . ', ';
                    // translators: "here to refresh" links to the Autoptimize Extra page and forces a refresh of the img opt stats.
                    $_imgopt_notice .= sprintf( __( 'click %1$shere to refresh%2$s', 'autoptimize' ), '<a href="' . $_imgopt_stats_refresh_url . '">', '</a>).' );
                }

                // and make the full notice filterable.
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

    /**
     * Get img provider stats (used to display notice).
     */
    public function query_img_provider_stats() {
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
                        autoptimizeOptionWrapper::update_option( 'autoptimize_imgopt_provider_stat', $stats );
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
            $has_launched  = autoptimizeOptionWrapper::get_option( 'autoptimize_imgopt_launched', '' );
            $launch_status = false;
            if ( $has_launched || ( is_array( $avail_imgopt ) && array_key_exists( 'launch-threshold', $avail_imgopt ) && $magic_number < $avail_imgopt['launch-threshold'] ) ) {
                $launch_status = true;
                if ( ! $has_launched ) {
                    autoptimizeOptionWrapper::update_option( 'autoptimize_imgopt_launched', 'on' );
                }
            }
        }

        return $launch_status;
    }

    public static function launch_ok_wrapper() {
        // needed for "plug" notice in autoptimizeMain.php.
        $self = new self();
        return $self->launch_ok();
    }

    public function get_imgopt_provider_userstatus() {
        static $_provider_userstatus = null;

        if ( is_null( $_provider_userstatus ) ) {
            $_stat = autoptimizeOptionWrapper::get_option( 'autoptimize_imgopt_provider_stat', '' );
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
            } else {
                // no provider_stat yet, assume/ return all OK.
                $_provider_userstatus['Status']    = 2;
                $_provider_userstatus['timestamp'] = '';
            }
        }

        return $_provider_userstatus;
    }
}
