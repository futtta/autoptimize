<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeCriticalCSSSettings {
    public function __construct()
    {
        $this->run();
    }

    protected function enabled()
    {
        return apply_filters( 'autoptimize_filter_show_criticalcsss_tabs', true );
    }
    
    public function run()
    {
        if ( $this->enabled() ) {
            add_filter( 'autoptimize_filter_settingsscreen_tabs', array( $this, 'add_critcss_tabs' ), 8, 1 );
        }

        if ( is_multisite() && is_network_admin() && autoptimizeOptionWrapper::is_ao_active_for_network() ) {
            add_action( 'network_admin_menu', array( $this, 'add_critcss_admin_menu' ) );
        } else {
            add_action( 'admin_menu', array( $this, 'add_critcss_admin_menu' ) );
        }
    }

    public function add_critcss_tabs( $in )
    {
        $in = array_merge( $in, array(
            'ao_critcss' => '⚡' . __( 'Critical CSS', 'autoptimize' ),
            )
        );

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
            <?php echo '<h2>' . __( "Improve your first paint times!", 'autoptimize' ) . '</h2>'; ?>
            <?php echo '<div>' . __( 'Improving first paint times is an important part of performance optimization. ', 'autoptimize' ) . '</div>'; ?>
        </div>
    </div>
    <?php
    }
}