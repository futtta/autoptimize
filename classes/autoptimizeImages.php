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
        // If options are not provided, grab them from autoptimizeExtra, as
        // that's what we're relying on to do image optimizations for now...
        if ( empty( $options ) ) {
            $options = autoptimizeExtra::fetch_options();
        }

        $this->set_options( $options );
    }

    public function set_options( array $options )
    {
        $this->options = $options;

        return $this;
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
        if ( ! $this->should_run() ) {
            return;
        }

        $active = false;

        if ( apply_filters( 'autoptimize_filter_extra_imgopt_do', true ) ) {
            add_filter(
                'autoptimize_html_after_minify',
                array( $this, 'filter_optimize_images' ),
                10,
                1
            );
            $active = true;
        }

        if ( apply_filters( 'autoptimize_filter_extra_imgopt_do_css', true ) ) {
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

        $do_cdn     = true;
        $_userstatus = $this->get_imgopt_provider_userstatus();
        if ( -2 == $_userstatus['Status'] ) {
            $do_cdn = false;
        }

        if (
            ! empty( $opts['autoptimize_extra_checkbox_field_5'] )
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
        $opts = $this->options;
        if ( ! empty( $opts ) && is_array( $opts ) && array_key_exists( 'autoptimize_extra_checkbox_field_5', $opts ) && ! empty( $opts['autoptimize_extra_checkbox_field_5'] ) ) {
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
        if ( ! empty( $this->options['autoptimize_extra_checkbox_field_5'] ) ) {
            $url      = '';
            $endpoint = $this->get_imgopt_host() . 'read-domain/';
            $domain   = AUTOPTIMIZE_SITE_DOMAIN;

            // make sure parse_url result makes sense, keeping $url empty if not.
            if ( $domain && ! empty( $domain ) ) {
                $url = $endpoint . $domain;
            }

            $url = apply_filters(
                'autoptimize_filter_extra_imgopt_stat_url',
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

    public function get_img_quality_string()
    {
        static $quality = null;

        if ( null === $quality ) {
            $q_array = $this->get_img_quality_array();
            $setting = $this->get_img_quality_setting();
            $quality = apply_filters(
                'autoptimize_filter_extra_imgopt_quality',
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
                'autoptimize_filter_extra_imgopt_quality_array',
                $map
            );
        }

        return $map;
    }

    public function get_img_quality_setting()
    {
        static $q = null;

        if ( null === $q ) {
            if ( is_array( $this->options ) && array_key_exists( 'autoptimize_extra_select_field_6', $this->options ) ) {
                $_setting = $this->options['autoptimize_extra_select_field_6'];
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

            $result = apply_filters( 'autoptimize_filter_extra_imgopt_normalized_url', $result );

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
            $ret_val         = apply_filters( 'autoptimize_filter_extra_imgopt_wait', 'ret_img' ); // values: ret_wait, ret_img, ret_json, ret_blank.
            $imgopt_base_url = $imgopt_host . 'client/' . $quality . ',' . $ret_val;
            $imgopt_base_url = apply_filters( 'autoptimize_filter_extra_imgopt_base_url', $imgopt_base_url );
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
            $nopti_images = apply_filters( 'autoptimize_filter_extra_imgopt_noptimize', '' );
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
            'autoptimize_filter_extra_imgopt_build_url',
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
        if ( $this->can_optimize_image( $matches[1] ) ) {
            return str_replace( $matches[1], $this->build_imgopt_url( $matches[1], 150, 150 ), $matches[0] );
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
}
