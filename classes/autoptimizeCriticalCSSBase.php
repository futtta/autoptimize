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
        if ( ! defined( 'AO_CCSS_VER' ) ) {
            // Define plugin version
            define( 'AO_CCSS_VER', '1.18.1-in-AO' );

            // Define a constant with the directory to store critical CSS in
            if (is_multisite()) {
              $blog_id = get_current_blog_id();
              define( 'AO_CCSS_DIR', WP_CONTENT_DIR . '/uploads/ao_ccss/' . $blog_id . '/' );
            } else {
              define( 'AO_CCSS_DIR', WP_CONTENT_DIR . '/uploads/ao_ccss/' );
            }
        
            // Define support files locations
            define( 'AO_CCSS_LOCK',  AO_CCSS_DIR . 'queue.lock' );
            define( 'AO_CCSS_LOG',   AO_CCSS_DIR . 'queuelog.html' );
            define( 'AO_CCSS_DEBUG', AO_CCSS_DIR . 'queue.json' );
        
            // Define constants for criticalcss.com base path and API endpoints
            // fixme: AO_CCSS_URL should be read from the autoptimize availability json stored as option.
            define( 'AO_CCSS_URL', 'https://criticalcss.com' );
            define( 'AO_CCSS_API', AO_CCSS_URL . '/api/premium/' );
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
    }
    
    public function load_requires() {
        // Required libs, core is always needed.
        $criticalCssCore = new autoptimizeCriticalCSSCore();
        
        if ( defined( 'DOING_CRON' ) || is_admin() ) {
            // TODO: also include if overridden somehow to force queue processing to be executed?
            require_once( 'critcss-inc/cron.php' );
        }

        if ( is_admin() ) {
            $criticalCssSettings = new autoptimizeCriticalCSSSettings();
            require_once( 'critcss-inc/core_ajax.php' );
        } else {
            // enqueuing only done when not wp-admin.
            // require_once( 'critcss-inc/core_enqueue.php' );
            $ccssEnqueue = new autoptimizeCriticalCSSEnqueue();
        }
    }

    public static function fetch_options() {
        // Get options
        $autoptimize_ccss_options['ao_css_defer']          = get_option( 'autoptimize_css_defer'         );
        $autoptimize_ccss_options['ao_css_defer_inline']   = get_option( 'autoptimize_css_defer_inline'  );
        $autoptimize_ccss_options['ao_ccss_rules_raw']     = get_option( 'autoptimize_ccss_rules'        , FALSE);
        $autoptimize_ccss_options['ao_ccss_additional']    = get_option( 'autoptimize_ccss_additional'   );
        $autoptimize_ccss_options['ao_ccss_queue_raw']     = get_option( 'autoptimize_ccss_queue'        , FALSE);
        $autoptimize_ccss_options['ao_ccss_viewport']      = get_option( 'autoptimize_ccss_viewport'     , FALSE);
        $autoptimize_ccss_options['ao_ccss_finclude']      = get_option( 'autoptimize_ccss_finclude'     , FALSE);
        $autoptimize_ccss_options['ao_ccss_rlimit']        = get_option( 'autoptimize_ccss_rlimit  '     , '5' );
        $autoptimize_ccss_options['ao_ccss_noptimize']     = get_option( 'autoptimize_ccss_noptimize'    , FALSE);
        $autoptimize_ccss_options['ao_ccss_debug']         = get_option( 'autoptimize_ccss_debug'        , FALSE);
        $autoptimize_ccss_options['ao_ccss_key']           = get_option( 'autoptimize_ccss_key'          );
        $autoptimize_ccss_options['ao_ccss_keyst']         = get_option( 'autoptimize_ccss_keyst'        );
        $autoptimize_ccss_options['ao_ccss_loggedin']      = get_option( 'autoptimize_ccss_loggedin'     , '1' );
        $autoptimize_ccss_options['ao_ccss_forcepath']     = get_option( 'autoptimize_ccss_forcepath'    , '1' );
        $autoptimize_ccss_options['ao_ccss_servicestatus'] = get_option( 'autoptimize_ccss_servicestatus' );
        $autoptimize_ccss_options['ao_ccss_deferjquery']   = get_option( 'autoptimize_ccss_deferjquery'  , FALSE);
        $autoptimize_ccss_options['ao_ccss_domain']        = get_option( 'autoptimize_ccss_domain'       );

        if ( strpos( $autoptimize_ccss_options['ao_ccss_domain'], 'http') === false && strpos( $autoptimize_ccss_options['ao_ccss_domain'], 'uggc') === 0 ) {
            $autoptimize_ccss_options['ao_ccss_domain'] = str_rot13( $autoptimize_ccss_options['ao_ccss_domain'] );
        } else if ( strpos( $autoptimize_ccss_options['ao_ccss_domain'], 'http') !== false ) {
            // not rot13'ed yet, do so now (goal; avoid migration plugins change the bound domain).
            update_option( 'autoptimize_ccss_domain', str_rot13( $autoptimize_ccss_options['ao_ccss_domain'] ) );
        }

        // Setup the rules array
        if ( empty( $autoptimize_ccss_options['ao_ccss_rules_raw'] ) ) {
          $autoptimize_ccss_options['ao_ccss_rules']['paths'] = [];
          $autoptimize_ccss_options['ao_ccss_rules']['types'] = [];
        } else {
          $autoptimize_ccss_options['ao_ccss_rules'] = json_decode( $autoptimize_ccss_options['ao_ccss_rules_raw'], TRUE);
        }

        // Setup the queue array
        if ( empty( $autoptimize_ccss_options['ao_ccss_queue_raw'] ) ) {
          $autoptimize_ccss_options['ao_ccss_queue'] = [];
        } else {
          $autoptimize_ccss_options['ao_ccss_queue'] = json_decode( $autoptimize_ccss_options['ao_ccss_queue_raw'], TRUE);
        }

        return $autoptimize_ccss_options;
    }

    public function on_upgrade() {
        // Create the cache directory if it doesn't exist already
        if( ! file_exists( AO_CCSS_DIR ) ) {
            mkdir( AO_CCSS_DIR, 0755, true );
        }

        // Create a scheduled event for the queue
        if (!wp_next_scheduled( 'ao_ccss_queue' ) ) {
            wp_schedule_event(time(), apply_filters( 'ao_ccss_queue_schedule', 'ao_ccss'), 'ao_ccss_queue' );
        }

        // Create a scheduled event for log maintenance
        if ( ! wp_next_scheduled( 'ao_ccss_maintenance' ) ) {
            wp_schedule_event(time(), 'twicedaily', 'ao_ccss_maintenance' );
        }

        // Scheduled event to fetch service status.
        if ( ! wp_next_scheduled( 'ao_ccss_servicestatus' ) ) {
            wp_schedule_event( time(), 'daily', 'ao_ccss_servicestatus' );
        }
    }

    function ao_ccss_deactivation() {
        /*
         * TODO: move deactivatoin to AO deactivation functoin.
         */

        // delete_option( 'autoptimize_ccss_rules' );
        // delete_option( 'autoptimize_ccss_additional' );
        // delete_option( 'autoptimize_ccss_queue' );
        // delete_option( 'autoptimize_ccss_viewport' );
        // delete_option( 'autoptimize_ccss_finclude' );
        // delete_option( 'autoptimize_ccss_rlimit' );
        // delete_option( 'autoptimize_ccss_noptimize' );
        // delete_option( 'autoptimize_ccss_debug' );
        // delete_option( 'autoptimize_ccss_key' );
        // delete_option( 'autoptimize_ccss_keyst' );
        // delete_option( 'autoptimize_ccss_version' );
        // delete_option( 'autoptimize_ccss_loggedin' );
        // delete_option( 'autoptimize_ccss_forcepath' );
        // delete_option( 'autoptimize_ccss_servicestatus' );
        // delete_option( 'autoptimize_ccss_deferjquery' );
        // delete_option( 'autoptimize_ccss_domain' );

        // Remove scheduled events
        wp_clear_scheduled_hook( 'ao_ccss_queue' );
        wp_clear_scheduled_hook( 'ao_ccss_maintenance' );
        wp_clear_scheduled_hook( 'ao_ccss_servicestatus' );

        // Remove cached files and directory
        array_map( 'unlink', glob( AO_CCSS_DIR . '*.{css,html,json,log,zip,lock}', GLOB_BRACE ) );
        rmdir( AO_CCSS_DIR );
    }
    
    public function check_upgrade() {
        $db_version = get_option( 'autoptimize_ccss_version', '' );
        if ( $db_version !== AO_CCSS_VER) {
            // check schedules & re-schedule if needed.
            $this->on_upgrade();
            // and update db_version.
            update_option( 'autoptimize_ccss_version', AO_CCSS_VER) ;
        }
    }

    public function ao_ccss_interval($schedules) {
        // Let interval be configurable
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

        // Attach interval to schedule
        $schedules['ao_ccss'] = array(
            'interval' => $intsec,
            'display' => __( 'Autoptimize CriticalCSS' )
        );
        return $schedules;
    }
}