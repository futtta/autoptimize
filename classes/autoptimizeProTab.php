<?php
/**
 * Handles adding "more tools" tab in AO admin settings page which promotes (future) AO
 * addons and/or affiliate services.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeProTab
{
    /**
     * Random title string.
     *
     * @var string
     */
    protected $rnd_title = null;

    public function __construct()
    {
        // alternate between tab title every 5 minutes.
        if ( floor( date( "i", time() ) / 5 ) %2 === 0 ) {
            $this->rnd_title = esc_html__( 'Page Cache', 'autoptimize' );
        } else {
            $this->rnd_title = esc_html__( 'Pro Boosters', 'autoptimize' );
        }

        $this->run();
    }

    public function run()
    {
        if ( $this->enabled() ) {
            add_filter( 'autoptimize_filter_settingsscreen_tabs', array( $this, 'add_pro_tabs' ), 10, 1 );
        }
        if ( is_multisite() && is_network_admin() && autoptimizeOptionWrapper::is_ao_active_for_network() ) {
            add_action( 'network_admin_menu', array( $this, 'add_admin_menu' ) );
        } else {
            add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        }
    }

    protected function enabled()
    {
        return apply_filters( 'autoptimize_filter_show_partner_tabs', true );
    }

    public function add_pro_tabs( $in )
    {
        $in = array_merge(
            $in,
            array(
                'ao_protab' => '&#x1F680; ' . $this->rnd_title
            )
        );

        return $in;
    }

    public function add_admin_menu()
    {
        if ( $this->enabled() ) {
            add_submenu_page( '', 'AO pro', 'AO pro', 'manage_options', 'ao_protab', array( $this, 'ao_pro_page' ) );
        }
    }

    public function ao_pro_page()
    {
        ?>
    <style>
        .ao_settings_div {background: white;border: 1px solid #ccc;padding: 1px 15px;margin: 15px 10px 10px 0;font-size: 120% !important; padding-bottom:20px;}
        .ao_settings_div p {font-size:110%;}
        
        #aoprocontainer{width:100%;overflow:hidden;}
        #aoprotxt{width:68%;float:left;}
        #aoprobuy { background:#ba4102;text-align:center;border-radius:25px; }
        #aoprobuy p {margin:.25em 1em}
        #aoprobuy p#cta {font-size:150%;}
        #aoproimg {width:28%;float:right;}
        
        @media (max-width:699px) { 
            #aoproimg{display:none;}
            #aoprotxt{width:100% !important;}
            #aoprobuy{font-size:70%;}
        }
    </style>
    <script>document.title = "Autoptimize: <?php echo $this->rnd_title ?> " + document.title;</script>
    <div class="wrap">
        <h1><?php apply_filters( 'autoptimize_filter_settings_is_pro', false ) ? esc_html_e( 'Autoptimize Pro Settings', 'autoptimize' ) : esc_html_e( 'Autoptimize Settings', 'autoptimize' ); ?></h1>
        <?php
            echo autoptimizeConfig::ao_admin_tabs();
            $aopro_explanation = '';

            $_transient    = 'aopro_explain';
            $_explain_html = 'https://misc.optimizingmatters.com/aopro_explain.html?ao_ver=';
            
            // get the HTML with the explanation of what AOPro is.
            if ( apply_filters( 'autoptimize_settingsscreen_remotehttp', true ) ) {
                $aopro_explanation = get_transient( $_transient );
                if ( empty( $aopro_explanation ) ) {
                    $ccss_expl_resp = wp_remote_get( $_explain_html . AUTOPTIMIZE_PLUGIN_VERSION );
                    if ( ! is_wp_error( $ccss_expl_resp ) ) {
                        if ( '200' == wp_remote_retrieve_response_code( $ccss_expl_resp ) ) {
                            $aopro_explanation = wp_kses_post( wp_remote_retrieve_body( $ccss_expl_resp ) );
                            set_transient( $_transient, $aopro_explanation, WEEK_IN_SECONDS );
                        }
                    }
                }
            }

            // placeholder text in case HTML is empty.
            if ( empty( $aopro_explanation ) ) {
                // translators: h2, strong but also 2 links.
                $aopro_explanation = sprintf( esc_html__( '%1$sAdd more power to Autoptimize with Pro!%2$s%3$sAs a user of Autoptimize you understand %5$sthe importance of having a fast site%6$s. Autoptimize Pro is a premium Power-Up extending AO by adding %5$simage optimization, CDN, automatic critical CSS rules generation and page caching but also providing extra “booster” options%6$s, all in one handy subscription to make your site even faster!%4$s%3$sHave a look at %7$shttps://autoptimize.com/pro/%8$s for more info or %9$sclick here to buy now%10$s!%4$s', 'autoptimize' ), '<h2>', '</h2>', '<p>', '</p>', '<strong>', '</strong>', '<a href="https://autoptimize.com/pro/" target="_blank">', '</a>', '<a href="https://checkout.freemius.com/mode/dialog/plugin/10906/plan/18508/?currency=auto" target="_blank">', '</a>' );
            } else {
                // we were able to fetch the explenation, so add the JS to show correct language.
                $aopro_explanation .= "<script>jQuery('.ao_i18n').hide();d=document;lang=d.getElementsByTagName('html')[0].getAttribute('lang').substring(0,2);if(d.getElementById(lang)!= null){jQuery('#'+lang).show();}else{jQuery('#default').show();}</script>";
            }
            ?>
            <div class="ao_settings_div">
            <?php
                // and echo it.
                echo $aopro_explanation;
            ?>
            </div>
    </div>
        <?php
    }
}
