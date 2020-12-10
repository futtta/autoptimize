<?php
/**
 * Critical CSS base file (initializes all ccss files).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeCriticalCSSBase {
    /**
     * Main plugin filepath.
     * Used for activation/deactivation/uninstall hooks.
     *
     * @var string
     */
    protected $filepath = null;

    public function __construct()
    {
        // define constant, but only once.
        if ( ! defined( 'AO_CCSS_DIR' ) ) {
            // Define a constant with the directory to store critical CSS in.
            if ( is_multisite() ) {
                $blog_id = get_current_blog_id();
                define( 'AO_CCSS_DIR', WP_CONTENT_DIR . '/uploads/ao_ccss/' . $blog_id . '/' );
            } else {
                define( 'AO_CCSS_DIR', WP_CONTENT_DIR . '/uploads/ao_ccss/' );
            }
        }
        if ( ! defined( 'AO_CCSS_VER' ) ) {
            // Define plugin version.
            define( 'AO_CCSS_VER', 'AO_' . AUTOPTIMIZE_PLUGIN_VERSION );

            // Define constants for criticalcss.com base path and API endpoints.
            // fixme: AO_CCSS_URL should be read from the autoptimize availability json stored as option.
            define( 'AO_CCSS_URL', 'https://criticalcss.com' );
            define( 'AO_CCSS_API', AO_CCSS_URL . '/api/premium/' );
            define( 'AO_CCSS_SLEEP', 10 );
        }

        // Define support files locations, in case they are not already defined.
        if ( ! defined( 'AO_CCSS_LOCK' ) ) {
            define( 'AO_CCSS_LOCK', AO_CCSS_DIR . 'queue.lock' );
        }
        if ( ! defined( 'AO_CCSS_LOG' ) ) {
            define( 'AO_CCSS_LOG', AO_CCSS_DIR . 'queuelog.html' );
        }
        if ( ! defined( 'AO_CCSS_DEBUG' ) ) {
            define( 'AO_CCSS_DEBUG', AO_CCSS_DIR . 'queue.json' );
        }

        $this->filepath = __FILE__;

        $this->setup();
        $this->load_requires();
    }

    public function setup()
    {
        // get all options.
        $all_options = $this->fetch_options();
        foreach ( $all_options as $option => $value ) {
            ${$option} = $value;
        }

        // make sure the 10 minutes cron schedule is added.
        add_filter( 'cron_schedules', array( $this, 'ao_ccss_interval' ) );

        // check if we need to upgrade.
        $this->check_upgrade();

        // make sure ao_ccss_queue is scheduled OK if an API key is set.
        if ( isset( $ao_ccss_key ) && ! empty( $ao_ccss_key ) && ! wp_next_scheduled( 'ao_ccss_queue' ) ) {
            wp_schedule_event( time(), apply_filters( 'ao_ccss_queue_schedule', 'ao_ccss' ), 'ao_ccss_queue' );
        }
    }

    public function load_requires() {
        // Required libs, core is always needed.
        $criticalcss_core = new autoptimizeCriticalCSSCore();

        if ( defined( 'WP_CLI' ) || defined( 'DOING_CRON' ) || is_admin() ) {
            // TODO: also include if overridden somehow to force queue processing to be executed?
            $criticalcss_cron = new autoptimizeCriticalCSSCron();
        }

        if ( is_admin() ) {
            $criticalcss_settings = new autoptimizeCriticalCSSSettings();
        } else {
            // enqueuing only done when not wp-admin.
            $criticalcss_enqueue = new autoptimizeCriticalCSSEnqueue();
        }
    }

    public static function fetch_options() {
        static $autoptimize_ccss_options = null;

        if ( null === $autoptimize_ccss_options ) {
            // not cached yet, fetching from WordPress options.
            $autoptimize_ccss_options['ao_css_defer']          = autoptimizeOptionWrapper::get_option( 'autoptimize_css_defer' );
            $autoptimize_ccss_options['ao_css_defer_inline']   = autoptimizeOptionWrapper::get_option( 'autoptimize_css_defer_inline' );
            $autoptimize_ccss_options['ao_ccss_rules_raw']     = get_option( 'autoptimize_ccss_rules', false );
            $autoptimize_ccss_options['ao_ccss_additional']    = get_option( 'autoptimize_ccss_additional' );
            $autoptimize_ccss_options['ao_ccss_queue_raw']     = get_option( 'autoptimize_ccss_queue', false );
            $autoptimize_ccss_options['ao_ccss_viewport']      = get_option( 'autoptimize_ccss_viewport', false );
            $autoptimize_ccss_options['ao_ccss_finclude']      = get_option( 'autoptimize_ccss_finclude', false );
            $autoptimize_ccss_options['ao_ccss_rtimelimit']    = get_option( 'autoptimize_ccss_rtimelimit', '30' );
            $autoptimize_ccss_options['ao_ccss_noptimize']     = get_option( 'autoptimize_ccss_noptimize', false );
            $autoptimize_ccss_options['ao_ccss_debug']         = get_option( 'autoptimize_ccss_debug', false );
            $autoptimize_ccss_options['ao_ccss_key']           = get_option( 'autoptimize_ccss_key' );
            $autoptimize_ccss_options['ao_ccss_keyst']         = get_option( 'autoptimize_ccss_keyst' );
            $autoptimize_ccss_options['ao_ccss_loggedin']      = get_option( 'autoptimize_ccss_loggedin', '1' );
            $autoptimize_ccss_options['ao_ccss_forcepath']     = get_option( 'autoptimize_ccss_forcepath', '1' );
            $autoptimize_ccss_options['ao_ccss_servicestatus'] = get_option( 'autoptimize_service_availablity' );
            $autoptimize_ccss_options['ao_ccss_deferjquery']   = get_option( 'autoptimize_ccss_deferjquery', false );
            $autoptimize_ccss_options['ao_ccss_domain']        = get_option( 'autoptimize_ccss_domain' );
            $autoptimize_ccss_options['ao_ccss_unloadccss']    = get_option( 'autoptimize_ccss_unloadccss', false );

            if ( strpos( $autoptimize_ccss_options['ao_ccss_domain'], 'http' ) === false && strpos( $autoptimize_ccss_options['ao_ccss_domain'], 'uggc' ) === 0 ) {
                $autoptimize_ccss_options['ao_ccss_domain'] = str_rot13( $autoptimize_ccss_options['ao_ccss_domain'] );
            } elseif ( strpos( $autoptimize_ccss_options['ao_ccss_domain'], 'http' ) !== false ) {
                // not rot13'ed yet, do so now (goal; avoid migration plugins change the bound domain).
                update_option( 'autoptimize_ccss_domain', str_rot13( $autoptimize_ccss_options['ao_ccss_domain'] ) );
            }

            // Setup the rules array.
            if ( empty( $autoptimize_ccss_options['ao_ccss_rules_raw'] ) ) {
                $autoptimize_ccss_options['ao_ccss_rules']['paths'] = array();
                $autoptimize_ccss_options['ao_ccss_rules']['types'] = array();
            } else {
                $autoptimize_ccss_options['ao_ccss_rules'] = json_decode( $autoptimize_ccss_options['ao_ccss_rules_raw'], true );
            }

            // Setup the queue array.
            if ( empty( $autoptimize_ccss_options['ao_ccss_queue_raw'] ) ) {
                $autoptimize_ccss_options['ao_ccss_queue'] = array();
            } else {
                $autoptimize_ccss_options['ao_ccss_queue'] = json_decode( $autoptimize_ccss_options['ao_ccss_queue_raw'], true );
            }

            // Override API key if constant is defined.
            if ( defined( 'AUTOPTIMIZE_CRITICALCSS_API_KEY' ) ) {
                $autoptimize_ccss_options['ao_ccss_key'] = AUTOPTIMIZE_CRITICALCSS_API_KEY;
            }
        }

        return $autoptimize_ccss_options;
    }

    public function on_upgrade() {
        global $ao_ccss_key;

        // Create the cache directory if it doesn't exist already.
        if ( ! file_exists( AO_CCSS_DIR ) ) {
            mkdir( AO_CCSS_DIR, 0755, true );
        }

        // Create a scheduled event for the queue.
        if ( isset( $ao_ccss_key ) && ! empty( $ao_ccss_key ) && ! wp_next_scheduled( 'ao_ccss_queue' ) ) {
            wp_schedule_event( time(), apply_filters( 'ao_ccss_queue_schedule', 'ao_ccss' ), 'ao_ccss_queue' );
        }

        // Create a scheduled event for log maintenance.
        if ( isset( $ao_ccss_key ) && ! empty( $ao_ccss_key ) && ! wp_next_scheduled( 'ao_ccss_maintenance' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'ao_ccss_maintenance' );
        }
    }

    public function check_upgrade() {
        $db_version = get_option( 'autoptimize_ccss_version', '' );
        if ( AO_CCSS_VER !== $db_version ) {
            // check schedules & re-schedule if needed.
            $this->on_upgrade();
            // and update db_version.
            update_option( 'autoptimize_ccss_version', AO_CCSS_VER );
        }
    }

    public function ao_ccss_interval( $schedules ) {
        // Let interval be configurable.
        if ( ! defined( 'AO_CCSS_DEBUG_INTERVAL' ) ) {
            $intsec = 600;
        } else {
            $intsec = AO_CCSS_DEBUG_INTERVAL;
            if ( $intsec >= 120 ) {
                $inttxt = $intsec / 60 . ' minutes';
            } else {
                $inttxt = $intsec . ' second(s)';
            }
            autoptimizeCriticalCSSCore::ao_ccss_log( 'Using custom WP-Cron interval of ' . $inttxt, 3 );
        }

        // Attach interval to schedule.
        $schedules['ao_ccss'] = array(
            'interval' => $intsec,
            'display'  => __( 'Autoptimize CriticalCSS' ),
        );
        return $schedules;
    }
}
