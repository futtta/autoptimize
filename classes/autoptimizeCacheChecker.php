<?php
/**
 * CacheChecker - new in AO 2.0
 *
 * Daily cronned job (filter to change freq. + filter to disable).
 * Checks if cachesize is > 0.5GB (size is filterable), if so, an option is set which controls showing an admin notice.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeCacheChecker
{
    const SCHEDULE_HOOK = 'ao_cachechecker';

    public function __construct()
    {
    }

    public function run()
    {
        $this->add_hooks();
    }

    public function add_hooks()
    {
        if ( is_admin() ) {
            add_action( 'plugins_loaded', array( $this, 'setup' ) );
        }
        add_action( self::SCHEDULE_HOOK, array( $this, 'cronjob' ) );
        add_action( 'admin_notices', array( $this, 'show_admin_notice' ) );
    }

    public function setup()
    {
        $do_cache_check = (bool) apply_filters( 'autoptimize_filter_cachecheck_do', true );
        $schedule       = wp_get_schedule( self::SCHEDULE_HOOK );
        $frequency      = apply_filters( 'autoptimize_filter_cachecheck_frequency', 'daily' );
        if ( ! in_array( $frequency, array( 'hourly', 'daily', 'weekly', 'monthly' ) ) ) {
            $frequency = 'daily';
        }
        if ( $do_cache_check && ( ! $schedule || $schedule !== $frequency ) ) {
            wp_schedule_event( time(), $frequency, self::SCHEDULE_HOOK );
        } elseif ( $schedule && ! $do_cache_check ) {
            wp_clear_scheduled_hook( self::SCHEDULE_HOOK );
        }
    }

    public function cronjob()
    {
        // Check cachesize and act accordingly.
        $max_size       = (int) apply_filters( 'autoptimize_filter_cachecheck_maxsize', 536870912 );
        $do_cache_check = (bool) apply_filters( 'autoptimize_filter_cachecheck_do', true );
        $stat_array     = autoptimizeCache::stats();
        $cache_size     = round( $stat_array[1] );
        if ( ( $cache_size > $max_size ) && ( $do_cache_check ) ) {
            update_option( 'autoptimize_cachesize_notice', true );
            if ( apply_filters( 'autoptimize_filter_cachecheck_sendmail', true ) ) {
                $site_url  = esc_url( site_url() );
                $ao_mailto = apply_filters( 'autoptimize_filter_cachecheck_mailto', get_option( 'admin_email', '' ) );

                $ao_mailsubject = __( 'Autoptimize cache size warning', 'autoptimize' ) . ' (' . $site_url . ')';
                $ao_mailbody    = __( 'Autoptimize\'s cache size is getting big, consider purging the cache. Have a look at https://wordpress.org/plugins/autoptimize/faq/ to see how you can keep the cache size under control.', 'autoptimize' ) . ' (site: ' . $site_url . ')';

                if ( ! empty( $ao_mailto ) ) {
                    $ao_mailresult = wp_mail( $ao_mailto, $ao_mailsubject, $ao_mailbody );
                    if ( ! $ao_mailresult ) {
                        error_log( 'Autoptimize could not send cache size warning mail.' );
                    }
                }
            }
        }

        // Check if 3rd party services (e.g. image proxy) are up.
        autoptimizeUtils::check_service_availability();

        // Check image optimization stats.
        autoptimizeExtra::get_img_provider_stats();

        // Nukes advanced cache clearing artifacts if they exists...
        autoptimizeCache::delete_advanced_cache_clear_artifacts();
    }

    public function show_admin_notice()
    {
        // fixme: make notices dismissable.
        if ( (bool) get_option( 'autoptimize_cachesize_notice', false ) ) {
            echo '<div class="notice notice-warning"><p>';
            _e( '<strong>Autoptimize\'s cache size is getting big</strong>, consider purging the cache. Have a look at <a href="https://wordpress.org/plugins/autoptimize/faq/" target="_blank" rel="noopener noreferrer">the Autoptimize FAQ</a> to see how you can keep the cache size under control.', 'autoptimize' );
            echo '</p></div>';
            update_option( 'autoptimize_cachesize_notice', false );
        }

        // Notice for image proxy usage, only if Image Optimization is active.
        $_extra_options = get_option( 'autoptimize_extra_settings', '' );
        if ( ! empty( $_extra_options ) && is_array( $_extra_options ) && array_key_exists( 'autoptimize_extra_checkbox_field_5', $_extra_options ) && ! empty( $_extra_options['autoptimize_extra_checkbox_field_5'] ) ) {
            $_imgopt_notice = '';
            $_stat          = get_option( 'autoptimize_imgopt_provider_stat', '' );
            $_site_host     = parse_url( AUTOPTIMIZE_WP_SITE_URL, PHP_URL_HOST );
            $_imgopt_upsell = 'https://shortpixel.com/proxycredits/'.$_site_host; // fixme: not the final URL!

            $_stat['Status']=1;
            if ( is_array( $_stat ) ) {
                if ( 1 === $_stat['Status'] ) {
                    // translators: "adding credits" will appear in a "a href".
                    $_imgopt_notice = sprintf( __( 'You are nearing the threshold of Shortpixel\'s free image optimization tier, consider %1$sadding credits%2$s to make sure image optimization continues to work.', 'autoptimize' ), '<a href="'.$_imgopt_upsell.'" target="_blank">', '</a>' );
                } elseif ( -1 === $_stat['Status'] ) {
                    // translators: "add credits to re-enable image optimization" will appear in a "a href".
                    $_imgopt_notice = sprintf( __( 'You are over Shortpixel\'s free image optimization tier threshold, %1$sadd credits to re-enable image optimization%2$s.', 'autoptimize' ), '<a href="'.$_imgopt_upsell.'" target="_blank">', '</a>' );
                }
            }
            $_imgopt_notice = apply_filters( 'autoptimize_filter_imgopt_warning', $_imgopt_notice );

            if ( $_imgopt_notice ) {
                echo '<div class="notice notice-warning"><p>' . $_imgopt_notice . '</p></div>';
            }
        }
    }
}
