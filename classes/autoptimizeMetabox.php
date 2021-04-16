<?php
/**
 * Handles meta box to disable optimizations.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeMetabox
{
    public function __construct()
    {
        $this->run();
    }

    public function run()
    {
        add_action( 'add_meta_boxes', array( $this, 'ao_metabox_add_box' ) );
        add_action( 'save_post', array( $this, 'ao_metabox_save' ) );
    }

    public function ao_metabox_add_box()
    {
        $screens = array( 
            'post',
            'page',
            // add extra types e.g. product or ... ?
        );

        foreach ( $screens as $screen ) {
            add_meta_box(
                'ao_metabox',
                __( 'Autoptimize this page', 'autoptimize' ),
                array( $this, 'ao_metabox_content' ),
                $screen,
                'side'
            );
        }
    }

    /**
     * Prints the box content.
     * 
     * @param WP_Post $post The object for the current post/page.
     */
    function ao_metabox_content( $post ) {
        wp_nonce_field( 'ao_metabox', 'ao_metabox_nonce' );

        $ao_opt_value = get_post_meta( $post->ID, 'ao_post_optimize', true );

        if ( empty( $ao_opt_value ) ) {
            $ao_opt_value = $this->get_metabox_default_values();
        }
        
        $_ao_meta_sub_opacity = '';
        if ( 'on' !== $ao_opt_value['ao_post_optimize'] ) {
            $_ao_meta_sub_opacity = 'opacity:.33;';
        }
        ?>
        <p >
            <input type="checkbox" id="autoptimize_post_optimize" class="ao_meta_main" name="ao_post_optimize" <?php echo 'on' !== $ao_opt_value['ao_post_optimize'] ? '' : 'checked="checked" '; ?> />
            <label for="autoptimize_post_optimize">
                 <?php _e( 'Optimize this page?', 'autoptimize' ); ?>
            </label>
        </p>
        <?php 
        $_ao_meta_js_style = '';
        if ( 'on' !== get_option( 'autoptimize_js', false ) ) {
            $_ao_meta_js_style = 'display:none;';
        }
        echo '<p class="ao_meta_sub" style="' . $_ao_meta_sub_opacity . $_ao_meta_js_style . '">';
        ?>
        <input type="checkbox" id="autoptimize_post_optimize_js" name="ao_post_js_optimize" <?php echo 'on' !== $ao_opt_value['ao_post_js_optimize'] ? '' : 'checked="checked" '; ?> />
            <label for="autoptimize_post_optimize_js">
                 <?php _e( 'Optimize JS?', 'autoptimize' ); ?>
            </label>
        </p>
        <?php 
        $_ao_meta_css_style = '';
        if ( 'on' !== get_option( 'autoptimize_css', false ) ) {
            $_ao_meta_css_style = 'display:none;';
        }
        echo '<p class="ao_meta_sub" style="' . $_ao_meta_sub_opacity . $_ao_meta_css_style . '">';
        ?>
        <input type="checkbox" id="autoptimize_post_optimize_css" name="ao_post_css_optimize" <?php echo 'on' !== $ao_opt_value['ao_post_css_optimize'] ? '' : 'checked="checked" '; ?> />
            <label for="autoptimize_post_optimize_css">
                 <?php _e( 'Optimize CSS?', 'autoptimize' ); ?>
            </label>
        </p>
        <?php 
        $_ao_meta_ccss_style = '';
        if ( 'on' !== get_option( 'autoptimize_css_defer', false ) ) {
            $_ao_meta_ccss_style = 'display:none;';
        }
        if ( 'on' !== $ao_opt_value['ao_post_css_optimize'] ) {
            $_ao_meta_ccss_style .= 'opacity:.33;';
        }
        echo '<p class="ao_meta_sub ao_meta_sub_css" style="' . $_ao_meta_sub_opacity . $_ao_meta_ccss_style . '">';
        ?>
            <input type="checkbox" id="autoptimize_post_ccss" name="ao_post_ccss" <?php echo 'on' !== $ao_opt_value['ao_post_ccss'] ? '' : 'checked="checked" '; ?> />
            <label for="autoptimize_post_ccss">
                 <?php _e( 'Inline critical CSS?', 'autoptimize' ); ?>
            </label>
        </p>
        <?php 
        $_ao_meta_lazyload_style = '';
        if ( false === autoptimizeImages::should_lazyload_wrapper() ) {
            $_ao_meta_lazyload_style = 'display:none;';
        }
        echo '<p class="ao_meta_sub" style="' . $_ao_meta_sub_opacity . $_ao_meta_lazyload_style . '">';
        ?>
            <input type="checkbox" id="autoptimize_post_lazyload" name="ao_post_lazyload" <?php echo 'on' !== $ao_opt_value['ao_post_lazyload'] ? '' : 'checked="checked" '; ?> />
            <label for="autoptimize_post_lazyload">
                 <?php _e( 'Lazyload images?', 'autoptimize' ); ?>
            </label>
        </p>
        <script>
            if ( typeof jQuery !== 'undefined' ) {
                jQuery( "#autoptimize_post_optimize" ).change(function() {
                    if (this.checked) {
                        jQuery(".ao_meta_sub:visible").fadeTo("fast",1);
                    } else {
                        jQuery(".ao_meta_sub:visible").fadeTo("fast",.33);
                    }
                });
                jQuery( "#autoptimize_post_optimize_css" ).change(function() {
                    if (this.checked) {
                        jQuery(".ao_meta_sub_css:visible").fadeTo("fast",1);
                    } else {
                        jQuery(".ao_meta_sub_css:visible").fadeTo("fast",.33);
                    }
                });
                }
        </script>
        <?php
    }

    /**
     * When the post is saved, saves our custom data.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function ao_metabox_save( $post_id ) {
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }

        // check if from our own data.
        if ( ! isset( $_POST['ao_metabox_nonce'] ) ) {
            return $post_id;
        }

        // Check if our nonce is set and verify if valid.
        $nonce = $_POST['ao_metabox_nonce'];
        if ( ! wp_verify_nonce( $nonce, 'ao_metabox' ) ) {
            return $post_id;
        }

        // Check the user's permissions.
        if ( 'page' === $_POST['post_type'] ) {
            if ( ! current_user_can( 'edit_page', $post_id ) ) {
                return $post_id;
            }
        } else {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return $post_id;
            }
        }

      // OK, we can have a look at the actual data now.
      // Sanitize user input.
      foreach ( array( 'ao_post_optimize', 'ao_post_js_optimize', 'ao_post_css_optimize', 'ao_post_ccss', 'ao_post_lazyload' ) as $opti_type ) {
          if ( isset( $_POST[$opti_type] ) ) {
              $ao_meta_result[$opti_type] = 'on';
          } else {
              $ao_meta_result[$opti_type] = '';
          }
      }

      // Update the meta field in the database.
      update_post_meta( $post_id, 'ao_post_optimize', $ao_meta_result );
    }

    public function get_metabox_default_values() {
        $ao_metabox_defaults = array(
            'ao_post_optimize'     => 'on',
            'ao_post_js_optimize'  => 'on',
            'ao_post_css_optimize'  => 'on',
            'ao_post_ccss'         => 'on',
            'ao_post_lazyload'     => 'on',
        );
        return $ao_metabox_defaults;
    }
}
