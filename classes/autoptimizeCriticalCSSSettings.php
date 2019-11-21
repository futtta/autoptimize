<?php
/**
 * Temporary options page for AO26, will integrate CCSS functionality in next release.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeCriticalCSSSettings {
    /**
     * Options.
     *
     * @var bool
     */
    private $settings_screen_do_remote_http = true;

    public function __construct()
    {
        $this->settings_screen_do_remote_http = apply_filters( 'autoptimize_settingsscreen_remotehttp', $this->settings_screen_do_remote_http );
        $this->run();
    }

    protected function enabled()
    {
        return apply_filters( 'autoptimize_filter_show_criticalcsss_tabs', true );
    }

    public function run()
    {
        if ( $this->enabled() ) {
            add_filter( 'autoptimize_filter_settingsscreen_tabs', array( $this, 'add_critcss_tabs' ), 10, 1 );
        }

        if ( is_multisite() && is_network_admin() && autoptimizeOptionWrapper::is_ao_active_for_network() ) {
            add_action( 'network_admin_menu', array( $this, 'add_critcss_admin_menu' ) );
        } else {
            add_action( 'admin_menu', array( $this, 'add_critcss_admin_menu' ) );
        }
    }

    public function add_critcss_tabs( $in )
    {
        $in = array_merge( $in, array( 'ao_critcss' => '⚡ ' . __( 'Critical CSS', 'autoptimize' ) ) );

        return $in;
    }

    public function add_critcss_admin_menu()
    {
        if ( $this->enabled() ) {
            add_submenu_page( null, 'Critical CSS', 'Critical CSS', 'manage_options', 'ao_critcss', array( $this, 'ao_criticalcsssettings_page' ) );
        }
    }

    public function ao_criticalcsssettings_page()
    {
    ?>
    <style>
        .ao_settings_div {background: white;border: 1px solid #ccc;padding: 1px 15px;margin: 15px 10px 10px 0;}
        .ao_settings_div .form-table th {font-weight: normal;}
    </style>
    <script>document.title = "Autoptimize: <?php _e( 'Critical CSS', 'autoptimize' ); ?> " + document.title;</script>
    <div class="wrap">
        <h1><?php _e( 'Autoptimize Settings', 'autoptimize' ); ?></h1>
        <?php echo autoptimizeConfig::ao_admin_tabs(); ?>
        <div class="ao_settings_div">
            <?php
            $ccss_explanation = '';

            // get the HTML with the explanation of what critical CSS is.
            if ( $this->settings_screen_do_remote_http ) {
                $ccss_explanation = get_transient( 'ccss_explain_ao26' );
                if ( empty( $ccss_explanation ) ) {
                    $ccss_expl_resp = wp_remote_get( 'https://misc.optimizingmatters.com/autoptimize_ccss_explain_ao26.html?ao_ver=' . AUTOPTIMIZE_PLUGIN_VERSION );
                    if ( ! is_wp_error( $ccss_expl_resp ) ) {
                        if ( '200' == wp_remote_retrieve_response_code( $ccss_expl_resp ) ) {
                            $ccss_explanation = wp_kses_post( wp_remote_retrieve_body( $ccss_expl_resp ) );
                            set_transient( 'ccss_explain_ao26', $ccss_explanation, WEEK_IN_SECONDS );
                        }
                    }
                }
            }

            // placeholder text in case HTML is empty.
            if ( empty( $ccss_explanation ) ) {
                $ccss_explanation = '<h2>Fix render-blocking CSS!</h2><p>Significantly improve your first-paint times by making CSS non-render-blocking.</p><br /><a href="./plugin-install.php?s=autoptimize+criticalcss&tab=search&type=term" class="button">Install the "Autoptimize Critical CSS Power-Up"!</a>';
            }

            // and echo it.
            echo $ccss_explanation . '<p>&nbsp;</p>';
            ?>
        </div>
    </div>
    <?php
    }
}
