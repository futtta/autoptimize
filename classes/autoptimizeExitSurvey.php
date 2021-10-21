<?php
/**
 * Add exit-survey logic to plugins-page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeExitSurvey
{
    function __construct() {
        global $pagenow;

        if ( $pagenow != 'plugins.php' ) {
            return;
        }

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_survey_scripts' ) );
        add_action( 'admin_footer', array( $this, 'render_survey_model' ) );
    }

    function enqueue_survey_scripts() {
        wp_enqueue_script( 'ao_exit_survey',  plugins_url( '/static/exit-survey/exit-survey.js', __FILE__ ), array(
            'jquery'
        ), AUTOPTIMIZE_PLUGIN_VERSION );

        wp_enqueue_style( 'ao_exit_survey', plugins_url( '/static/exit-survey/exit-survey.css', __FILE__ ), null, AUTOPTIMIZE_PLUGIN_VERSION );
    }

    function render_survey_model() {
        include_once 'static/exit-survey/exit-survey-model.php';
    }
}
