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
        $this->run();
    }

    /**
     * Runs multiple compatibility snippets to ensure important plugins work out of the box.
     * 
     */
    public function run()
    {
        // Edit with Elementor in frontend admin menu (so for editors/ administrators) needs JS opt. disabled to appear & function.
        if ( defined( 'ELEMENTOR_VERSION' ) && is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
            add_filter( 'autoptimize_filter_js_noptimize', '__return_true' );
        }
    }
}
