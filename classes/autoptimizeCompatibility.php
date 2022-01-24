<?php
/**
 * Multiple compatibility snippets to ensure important/ stubborn plugins work out of the box.
 * 
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeCompatibility
{
    /**
     * Constructor.
     *
     */
    public function __construct()
    {
        if ( ! is_admin() ) {
            $this->conf = autoptimizeConfig::instance();
            $this->run();
        }
    }

    /**
     * Runs multiple compatibility snippets to ensure important plugins work out of the box.
     * 
     */
    public function run()
    {
        // Edit with Elementor in frontend admin menu (so for editors/ administrators) needs JS opt. disabled to appear & function.
        if ( defined( 'ELEMENTOR_VERSION' ) && is_user_logged_in() && current_user_can( 'edit_posts' ) && apply_filters( 'autoptimize_filter_compatibility_editelementor_active', true ) ) {
            add_filter( 'autoptimize_filter_js_noptimize', '__return_true' );
        }
        
        // revslider; jQuery should not be deferred + exclude all revslider JS.
        if ( defined( 'RS_REVISION' ) && $this->conf->get( 'autoptimize_js' ) && true == $this->revslider_compat_helper() && apply_filters( 'autoptimize_filter_compatibility_revslider_active', true ) ) {
            add_filter( 'autoptimize_filter_js_exclude', function( $js_excl, $html ) {
                $revslider_excl = 'revslider, setREVStartSize, jquery.min.js';
                if ( false !== strpos( $html, 'setREVStartSize' ) ) {
                    if ( is_array( $js_excl ) ) {
                        $js_excl = implode( ',', $js_excl );
                    }
                    
                    $js_excl .= ',' . $revslider_excl;
                }
                return $js_excl;
            }, 10, 2 );
        }
        
        // stop divi from purging cache -> likely in autoptimizeCache.php instead.
    }
    
    public function revslider_compat_helper() {
        if ( ( $this->conf->get( 'autoptimize_js_aggregate' ) || apply_filters( 'autoptimize_filter_js_dontaggregate', false ) ) && apply_filters( 'autoptimize_js_include_inline', $this->conf->get( 'autoptimize_js_include_inline' ) ) ) {
            // if all files including inline JS are aggregated we don't need to exclude revslider stuff.
            return false;
        } else if ( apply_filters( 'autoptimize_filter_js_defer_not_aggregate', $this->conf->get( 'autoptimize_js_defer_not_aggregate' ) ) && apply_filters( 'autoptimize_js_filter_defer_inline', $this->conf->get( 'autoptimize_js_defer_inline' ) ) ) {
            // and when not aggregating but deferring all excluding is not needed either.
            return false;
        }
        // in all other cases we need to exclude the revslider stuff.
        return true;
    }
}
