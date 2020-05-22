<?php
/**
 * Explain what CCSS is (visible if no API key is stored).
 */

/**
 * Actual function that explains.
 */
function ao_ccss_render_explain() {
    ?>
    <style>
        .ao_settings_div {background: white;border: 1px solid #ccc;padding: 1px 15px;margin: 15px 10px 10px 0;}
        .ao_settings_div .form-table th {font-weight: normal;}
    </style>
    <script>document.title = "Autoptimize: <?php _e( 'Critical CSS', 'autoptimize' ); ?> " + document.title;</script>
    <ul id="explain-panel">
        <div class="ao_settings_div">
            <?php
            $ccss_explanation = '';

            // get the HTML with the explanation of what critical CSS is.
            if ( apply_filters( 'autoptimize_settingsscreen_remotehttp', true ) ) {
                $ccss_explanation = get_transient( 'ao_ccss_explain' );
                if ( empty( $ccss_explanation ) ) {
                    $ccss_expl_resp = wp_remote_get( 'https://misc.optimizingmatters.com/autoptimize_ccss_explain.html?ao_ver=' . AUTOPTIMIZE_PLUGIN_VERSION );
                    if ( ! is_wp_error( $ccss_expl_resp ) ) {
                        if ( '200' == wp_remote_retrieve_response_code( $ccss_expl_resp ) ) {
                            $ccss_explanation = wp_kses_post( wp_remote_retrieve_body( $ccss_expl_resp ) );
                            set_transient( 'ao_ccss_explain', $ccss_explanation, WEEK_IN_SECONDS );
                        }
                    }
                }
            }

            // placeholder text in case HTML is empty.
            if ( empty( $ccss_explanation ) ) {
                $ccss_explanation = '<h2>Fix render-blocking CSS!</h2><p>Significantly improve your first-paint times by making CSS non-render-blocking.</p><p>The next step is to sign up at <a href="https://criticalcss.com/?aff=1" target="_blank">https://criticalcss.com</a> (this is a premium service, priced 2 GBP/month for membership and 5 GBP/month per domain) <strong>and get the API key, which you can copy from <a href="https://criticalcss.com/account/api-keys?aff=1" target="_blank">the API-keys page</a></strong> and paste below.</p><p>If you have any questions or need support, head on over to <a href="https://wordpress.org/support/plugin/autoptimize" target="_blank">our support forum</a> and we\'ll help you get up and running in no time!</p>';
            }

            // and echo it.
            echo $ccss_explanation;
            ?>
        </div>
        </ul>
    <?php
}
