<?php
/**
 * Critical CSS Core logic: 
 * gets called by AO core, checks the rules and if a matching rule is found returns the associated CCSS.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeCriticalCSSCore {
    public function __construct()
    {
        // fetch all options at once and populate them individually explicitely as globals.
        $all_options = autoptimizeCriticalCSSBase::fetch_options();
        foreach ( $all_options as $_option => $_value ) {
            global ${$_option};
            ${$_option} = $_value;
        }

        $this->run();
    }

    public function run() {
        global $ao_css_defer;
        global $ao_ccss_deferjquery;
        global $ao_ccss_key;

        // add all filters to do CCSS if key present.
        if ( $ao_css_defer && isset( $ao_ccss_key ) && ! empty( $ao_ccss_key ) ) {
          // Set AO behavior: disable minification to avoid double minifying and caching
          add_filter('autoptimize_filter_css_critcss_minify', '__return_false');
          add_filter('autoptimize_filter_css_defer_inline', array( $this, 'ao_ccss_frontend' ), 10, 1);

          // Add the action to enqueue jobs for CriticalCSS cron
          add_action('autoptimize_action_css_hash', array('autoptimizeCriticalCSSEnqueue','ao_ccss_enqueue'), 10, 1);

          // conditionally add the filter to defer jquery and others.
          if ( $ao_ccss_deferjquery ) {
            add_filter( 'autoptimize_html_after_minify', array( $this, 'ao_ccss_defer_jquery' ), 11, 1 );
          }

          // Order paths by length, as longest ones have greater priority in the rules
          if (!empty($ao_ccss_rules['paths'])) {
            $keys = array_map('strlen', array_keys($ao_ccss_rules['paths']));
            array_multisort($keys, SORT_DESC, $ao_ccss_rules['paths']);
          }

          // Add an array with default WordPress's conditional tags
          // NOTE: these tags are sorted
          global $ao_ccss_types;
          $ao_ccss_types = $this->get_ao_ccss_core_types();

          // Extend conditional tags on pugin initalization
          add_action( 'init', array( $this, 'ao_ccss_extend_types' ) );
        }
    }

    public function ao_ccss_frontend( $inlined ) {
      // Apply CriticalCSS to frontend pages
      // Attach types and settings arrays
      global $ao_ccss_types;
      global $ao_ccss_rules;
      global $ao_ccss_additional; 
      global $ao_ccss_loggedin;
      global $ao_ccss_debug;
      global $ao_ccss_keyst;
      $no_ccss = "";

      // Only if keystatus is OK and option to add CCSS for logged on users is on or user is not logged in
      if ( ( $ao_ccss_keyst && $ao_ccss_keyst == 2 ) && ( $ao_ccss_loggedin || ! is_user_logged_in() ) ) {
        // Check for a valid CriticalCSS based on path to return its contents
        // NOTE: implements section 4, id 1.1 of the specs (for paths)
        $req_path = strtok(urldecode($_SERVER['REQUEST_URI']),'?');
        if (!empty($ao_ccss_rules['paths'])) {
          foreach ($ao_ccss_rules['paths'] as $path => $rule) {
            // explicit match OR partial match if MANUAL rule
            if ( $req_path == $path || ( $rule['hash'] == FALSE && $rule['file'] != FALSE && strpos( $req_path, str_replace( site_url(), '', $path) ) !== FALSE ) ) {
              if (file_exists(AO_CCSS_DIR . $rule['file'])) {
                $_ccss_contents = file_get_contents(AO_CCSS_DIR . $rule['file']);
                if ($_ccss_contents != "none") {
                  if ($ao_ccss_debug) {
                    $_ccss_contents = '/* PATH: '.$path.' hash: '.$rule['hash'].' file: '.$rule['file'].' */ ' . $_ccss_contents;
                  }
                  return apply_filters('autoptimize_filter_ccss_core_ccss', $_ccss_contents . $ao_ccss_additional);
                } else {
                  $no_ccss = "none";
                }
              }
            }
          }
        }

        // Check for a valid CriticalCSS based on conditional tags to return its contents
        // NOTE: implements section 4, id 1.1 of the specs (for types)
        if (!empty($ao_ccss_rules['types']) && $no_ccss !== "none") {
          // order types-rules by the order of the original $ao_ccss_types array so as not to depend on the order in which rules were added.
          $ao_ccss_rules['types'] = array_replace(array_intersect_key( array_flip($ao_ccss_types), $ao_ccss_rules['types'] ),$ao_ccss_rules['types']);
          $is_front_page = is_front_page();

          foreach ($ao_ccss_rules['types'] as $type => $rule) {
            if (in_array($type, $ao_ccss_types) && file_exists(AO_CCSS_DIR . $rule['file'])) {
              $_ccss_contents = file_get_contents(AO_CCSS_DIR . $rule['file']);
              if ( $is_front_page && $type == 'is_front_page' ) {
                if ($_ccss_contents != "none") {
                  if ($ao_ccss_debug) {
                    $_ccss_contents = '/* TYPES: '.$type.' hash: '.$rule['hash'].' file: '.$rule['file'].' */ ' . $_ccss_contents;
                  }
                  return apply_filters('autoptimize_filter_ccss_core_ccss', $_ccss_contents . $ao_ccss_additional);
                } else {
                  $no_ccss = "none";
                }
              } else if (strpos($type, 'custom_post_') === 0 && ! $is_front_page ) {
                if (get_post_type(get_the_ID()) === substr($type, 12)) {
                  if ($_ccss_contents != "none") {
                    if ($ao_ccss_debug) {
                      $_ccss_contents = '/* TYPES: '.$type.' hash: '.$rule['hash'].' file: '.$rule['file'].' */ ' . $_ccss_contents;
                    }
                    return apply_filters('autoptimize_filter_ccss_core_ccss', $_ccss_contents . $ao_ccss_additional);
                  } else {
                    $no_ccss = "none";
                  }
                }
              } elseif (strpos($type, 'template_') === 0 && ! $is_front_page ) {
                if (is_page_template(substr($type, 9))) {
                  if ($_ccss_contents != "none") {
                    if ($ao_ccss_debug) {
                      $_ccss_contents = '/* TYPES: '.$type.' hash: '.$rule['hash'].' file: '.$rule['file'].' */ ' . $_ccss_contents;
                    }
                    return apply_filters('autoptimize_filter_ccss_core_ccss', $_ccss_contents . $ao_ccss_additional);
                  } else {
                    $no_ccss = "none";
                  }
                }
              } else if ( ! $is_front_page ) {
                // all "normal" conditional tags, core + woo + buddypress + edd + bbpress
                // but we have to remove the prefix for the non-core ones for them to function.
                $type = str_replace(array('woo_','bp_','bbp_','edd_'),'',$type);
                if (function_exists($type) && call_user_func($type)) {
                  if ($_ccss_contents != "none") {
                    if ($ao_ccss_debug) {
                      $_ccss_contents = '/* TYPES: '.$type.' hash: '.$rule['hash'].' file: '.$rule['file'].' */ ' . $_ccss_contents;
                    }
                    return apply_filters('autoptimize_filter_ccss_core_ccss', $_ccss_contents . $ao_ccss_additional);
                  } else {
                    $no_ccss = "none";
                  }
                }
              }
            }
          }
        }
      }

      // Finally, inline the default CriticalCSS if any or else the entire CSS for the page
      // This also applies to logged in users if the option to add CCSS for logged in users has been disabled.
      // NOTE: implements section 4, id 1.2 of the specs
      if (!empty($inlined) && $no_ccss !== "none") {
        return apply_filters('autoptimize_filter_ccss_core_ccss', $inlined . $ao_ccss_additional);
      } else {
        add_filter('autoptimize_filter_css_inline', '__return_true');
        return;
      }
    }

    public function ao_ccss_defer_jquery( $in ) {
        // try to defer all JS (main goal being jquery.js as AO by default does not aggregate that)
        if ( ( ! is_user_logged_in() || $ao_ccss_loggedin ) && preg_match_all( '#<script.*>(.*)</script>#Usmi', $in, $matches, PREG_SET_ORDER ) ) {
            foreach( $matches as $match ) {
              if ( ( ! preg_match('/<script.* type\s?=.*>/', $match[0]) || preg_match( '/type\s*=\s*[\'"]?(?:text|application)\/(?:javascript|ecmascript)[\'"]?/i', $match[0] ) ) && $match[1] !== '' && ( strpos( $match[1], 'jQuery' ) !== false || strpos( $match[1], '$' ) !== false ) ) {
                // inline js that requires jquery, wrap deferring JS around it to defer it.
                $new_match = 'var aoDeferInlineJQuery=function(){'.$match[1].'}; if (document.readyState === "loading") {document.addEventListener("DOMContentLoaded", aoDeferInlineJQuery);} else {aoDeferInlineJQuery();}';
                $in = str_replace( $match[1], $new_match, $in );
              } else if ( $match[1] === '' && strpos( $match[0], 'src=' ) !== false && strpos( $match[0], 'defer' ) === false ) {
                // linked non-aggregated JS, defer it.
                $new_match = str_replace( '<script ', '<script defer ', $match[0] );
                $in = str_replace( $match[0], $new_match, $in );
              }
            }
        }
      return $in;
    }

    public function ao_ccss_extend_types() {
      // Extend contidional tags
      // Attach the conditional tags array
      global $ao_ccss_types;

      // in some cases $ao_ccss_types is empty and/or not an array, this should work around that problem.
      if ( empty( $ao_ccss_types ) || ! is_array( $ao_ccss_types ) ) {
        $ao_ccss_types = get_ao_ccss_core_types();
        autoptimizeCriticalCSSCore::ao_ccss_log('Empty types array in extend, refetching array with core conditionals.', 3);
      }

      // Custom Post Types
      $cpts = get_post_types(
        array(
          'public'   => TRUE,
          '_builtin' => FALSE
        ),
        'names',
        'and'
      );
      foreach ($cpts as $cpt) {
        array_unshift ( $ao_ccss_types, 'custom_post_' . $cpt );
      }

      // Templates
      $templates = wp_get_theme()->get_page_templates();
      foreach ($templates as $tplfile => $tplname) {
        array_unshift ( $ao_ccss_types, 'template_' . $tplfile );
      }

      // bbPress tags
      if (function_exists('is_bbpress')) {
        $ao_ccss_types = array_merge(array(
          'bbp_is_bbpress',
          'bbp_is_favorites',
          'bbp_is_forum_archive',
          'bbp_is_replies_created',
          'bbp_is_reply_edit',
          'bbp_is_reply_move',
          'bbp_is_search',
          'bbp_is_search_results',
          'bbp_is_single_forum',
          'bbp_is_single_reply',
          'bbp_is_single_topic',
          'bbp_is_single_user',
          'bbp_is_single_user_edit',
          'bbp_is_single_view',
          'bbp_is_subscriptions',
          'bbp_is_topic_archive',
          'bbp_is_topic_edit',
          'bbp_is_topic_merge',
          'bbp_is_topic_split',
          'bbp_is_topic_tag',
          'bbp_is_topic_tag_edit',
          'bbp_is_topics_created',
          'bbp_is_user_home',
          'bbp_is_user_home_edit'
        ), $ao_ccss_types );
      }

      // BuddyPress tags
      if (function_exists('is_buddypress')) {
        $ao_ccss_types=array_merge( array(
          'bp_is_activation_page',
          'bp_is_activity',
          'bp_is_blogs',
          'bp_is_buddypress',
          'bp_is_change_avatar',
          'bp_is_create_blog',
          'bp_is_friend_requests',
          'bp_is_friends',
          'bp_is_friends_activity',
          'bp_is_friends_screen',
          'bp_is_group_admin_page',
          'bp_is_group_create',
          'bp_is_group_forum',
          'bp_is_group_forum_topic',
          'bp_is_group_home',
          'bp_is_group_invites',
          'bp_is_group_leave',
          'bp_is_group_members',
          'bp_is_group_single',
          'bp_is_groups',
          'bp_is_messages',
          'bp_is_messages_compose_screen',
          'bp_is_messages_conversation',
          'bp_is_messages_inbox',
          'bp_is_messages_sentbox',
          'bp_is_my_activity',
          'bp_is_my_blogs',
          'bp_is_notices',
          'bp_is_profile_edit',
          'bp_is_register_page',
          'bp_is_settings_component',
          'bp_is_user',
          'bp_is_user_profile',
          'bp_is_wire'
        ), $ao_ccss_types );
      }

      // Easy Digital Downloads (EDD) tags
      if (function_exists('edd_is_checkout')) {
        $ao_ccss_types=array_merge( array(
          'edd_is_checkout',
          'edd_is_failed_transaction_page',
          'edd_is_purchase_history_page',
          'edd_is_success_page'
        ), $ao_ccss_types );
      }

      // WooCommerce tags
      if (class_exists('WooCommerce')) {
        $ao_ccss_types = array_merge( array(
          'woo_is_account_page',
          'woo_is_cart',
          'woo_is_checkout',
          'woo_is_product',
          'woo_is_product_category',
          'woo_is_product_tag',
          'woo_is_shop',
          'woo_is_wc_endpoint_url',
          'woo_is_woocommerce'
        ), $ao_ccss_types );
      }

      // don't sort values, order is used to order rules
      // sort($ao_ccss_types);
    }

    public function get_ao_ccss_core_types() {
      return array(
        'is_404',
        'is_archive',
        'is_author',
        // 'is_blog_page',
        'is_category',
        'is_front_page',
        'is_home',
        'is_page',
        'is_post',
        'is_search',
        'is_attachment',
        'is_single',
        'is_sticky',
        'is_paged'
      );
    }

    // Add is_blog_page conditional tag as per
    // https://codex.wordpress.org/Conditional_Tags#The_Blog_Page
    /* if (!function_exists("is_blog_page")) {
      function is_blog_page() {
        if (!is_front_page() && is_home()) {
          return TRUE;
        } else {
          return FALSE;
        }
      }
    }
    add_action('template_redirect', 'is_blog_page'); */

    public static function ao_ccss_key_status($render) {
      // Provide key status
      // Get key and key status
      global $ao_ccss_key;
      global $ao_ccss_keyst;
      $key        = $ao_ccss_key;
      $key_status = $ao_ccss_keyst;

      // Prepare returned variables
      $key_return = array();
      $status     = FALSE;

      // Key exists and its status is valid
      if ( $key && $key_status == 2 ) {

        // Set valid key status
        $status     = 'valid';
        $status_msg = __('Valid');
        $color      = '#46b450'; // Green
        $message    = NULL;

      // Key exists but its validation has failed
      } elseif ($key && $key_status == 1) {

        // Set invalid key status
        $status     = 'invalid';
        $status_msg = __('Invalid');
        $color      = '#dc3232'; // Red
        $message    = __('Your API key is invalid. Please enter a valid <a href="https://criticalcss.com/?aff=1" target="_blank">criticalcss.com</a> key.', 'autoptimize');

      // Key exists but it has no valid status yet
      } elseif ($key && !$key_status) {

        // Perform key validation
        $key_check = ao_ccss_key_validation($key);

        // Key is valid, set valid status
        if ($key_check) {
          $status     = 'valid';
          $status_msg = __('Valid');
          $color      = '#46b450'; // Green
          $message    = NULL;

        // Key is invalid, set invalid status
        } else {
          $status     = 'invalid';
          $status_msg = __('Invalid');
          $color      = '#dc3232'; // Red
          if ( get_option( 'autoptimize_ccss_keyst' ) == 1 ) {
            $message = __( 'Your API key is invalid. Please enter a valid <a href="https://criticalcss.com/?aff=1" target="_blank">criticalcss.com</a> key.', 'autoptimize' );
          } else {
            $message = __( 'Something went wrong when checking your API key, make sure you server can communicate with https://criticalcss.com and/ or try again later.', 'autoptimize' );
          }
        }

      // No key nor status
      } else {

        // Set no key status
        $status     = 'nokey';
        $status_msg = __('None');
        $color      = '#ffb900'; // Yellow
        $message    = __('Please enter a valid <a href="https://criticalcss.com/?aff=1" target="_blank">criticalcss.com</a> API key to start.', 'autoptimize');
      }

      // Fill returned values
      $key_return['status'] = $status;
      // Provide rendering information if required
      if ($render) {
        $key_return['stmsg'] = $status_msg;
        $key_return['color'] = $color;
        $key_return['msg']   = $message;
      }

      // Return key status
      return $key_return;
    }

    public function ao_ccss_key_validation($key) {
      // POST a dummy job to criticalcss.com to check for key validation
      // Prepare home URL for the request
      $src_url = get_home_url();
      $src_url = apply_filters( 'autoptimize_filter_ccss_cron_srcurl', $src_url );

      // Prepare the request
      $url  = esc_url_raw(AO_CCSS_API . 'generate');
      $args = array(
        'headers' => array(
          'User-Agent'    => 'Autoptimize CriticalCSS Power-Up v' . AO_CCSS_VER,
          'Content-type'  => 'application/json; charset=utf-8',
          'Authorization' => 'JWT ' . $key,
          'Connection'    => 'close'
        ),
        // Body must be JSON
        'body' => json_encode(
          array(
            'url'    => $src_url,
            'aff'    => 1,
            'aocssv' => AO_CCSS_VER
          )
        )
      );

      // Dispatch the request and store its response code
      $req  = wp_safe_remote_post($url, $args);
      $code = wp_remote_retrieve_response_code($req);
      $body = json_decode(wp_remote_retrieve_body($req), TRUE);

      // Response is OK
      if ($code == 200) {

        // Set key status as valid and log key check
        update_option('autoptimize_ccss_keyst', 2);
        autoptimizeCriticalCSSCore::ao_ccss_log('criticalcss.com: API key is valid, updating key status', 3);

        // extract job-id from $body and put it in the queue as a P job
        // but only if no jobs and no rules!
        global $ao_ccss_queue;
        global $ao_ccss_rules;

        if ( count( $ao_ccss_queue ) == 0 && count( $ao_ccss_rules['types'] ) == 0 && count( $ao_ccss_rules['paths'] ) == 0 ) {
          if ($body['job']['status'] == 'JOB_QUEUED' || $body['job']['status'] == 'JOB_ONGOING') {
            $jprops['ljid']     = 'firstrun';
            $jprops['rtarget']  = 'types|is_front_page';
            $jprops['ptype']    = 'is_front_page';
            $jprops['hashes'][] = 'dummyhash';
            $jprops['hash']     = 'dummyhash';
            $jprops['file']     = null;
            $jprops['jid']      = $body['job']['id'];
            $jprops['jqstat']   = $body['job']['status'];
            $jprops['jrstat']   = null;
            $jprops['jvstat']   = null;
            $jprops['jctime']   = microtime(TRUE);
            $jprops['jftime']   = null;
            $ao_ccss_queue['/'] = $jprops;
            $ao_ccss_queue_raw = json_encode($ao_ccss_queue);
            update_option('autoptimize_ccss_queue', $ao_ccss_queue_raw, false);
            autoptimizeCriticalCSSCore::ao_ccss_log('Created P job for is_front_page based on API key check response.', 3);
          }
        }

        return TRUE;

      // Response is unauthorized
      } elseif ($code == 401) {

        // Set key status as invalid and log key check
        update_option('autoptimize_ccss_keyst', 1);
        autoptimizeCriticalCSSCore::ao_ccss_log('criticalcss.com: API key is invalid, updating key status', 3);
        return FALSE;

      // Response unkown
      } else {

        // Log key check attempt
        autoptimizeCriticalCSSCore::ao_ccss_log('criticalcss.com: could not check API key status, this is a service error, body follows if any...', 2);
        if (!empty($body)) {
          autoptimizeCriticalCSSCore::ao_ccss_log(print_r($body, TRUE), 2);
        }
        if ( is_wp_error($req) ) {
          autoptimizeCriticalCSSCore::ao_ccss_log( $req->get_error_message(), 2);
        }
        return FALSE;
      }
    }

    public static function ao_ccss_viewport() {
      // Get viewport size
      // Attach viewport option
      global $ao_ccss_viewport;

      // Prepare viewport array
      $viewport = array();

      // Viewport Width
      if (!empty($ao_ccss_viewport['w'])) {
        $viewport['w'] = $ao_ccss_viewport['w'];
      } else {
        $viewport['w'] = '';
      }

      // Viewport Height
      if (!empty($ao_ccss_viewport['h'])) {
        $viewport['h'] = $ao_ccss_viewport['h'];
      } else {
        $viewport['h'] = '';
      }

      return $viewport;
    }

    public static function ao_ccss_check_contents($ccss) {
      // Perform basic exploit avoidance and CSS validation
      if (!empty($ccss)) {
        // Try to avoid code injection
        $blacklist = array("#!/", "function(", "<script", "<?php");
        foreach ($blacklist as $blacklisted) {
          if (strpos($ccss, $blacklisted) !== FALSE) {
            autoptimizeCriticalCSSCore::ao_ccss_log('Critical CSS received contained blacklisted content.', 2);
            return FALSE;
          }
        }

        // Check for most basics CSS structures
        $pinklist = array("{", "}", ":");
        foreach ($pinklist as $needed) {
          if (strpos($ccss, $needed) === FALSE && $ccss !== "none") {
            autoptimizeCriticalCSSCore::ao_ccss_log('Critical CSS received did not seem to contain real CSS.', 2);
            return FALSE;
          }
        }
      }

      // Return true if file critical CSS is sane
      return TRUE;
    }

    public static function ao_ccss_log($msg, $lvl) {
      // Commom logging facility
      // Attach debug option
      global $ao_ccss_debug;

      // Prepare log levels, where accepted $lvl are:
      // 1: II (for info)
      // 2: EE (for error)
      // 3: DD (for debug)
      // Default: UU (for unkown)
      $level = FALSE;
      switch ($lvl) {
        case 1:
          $level = 'II';
          break;
        case 2:
          $level = 'EE';
          break;
        case 3:
          // Output debug messages only if debug mode is enabled
          if ($ao_ccss_debug)
            $level = 'DD';
          break;
        default:
          $level = 'UU';
      }

      // Prepare and write a log message if there's a valid level
      if ($level) {

        // Prepare message
        $message = date('c') . ' - [' . $level . '] ' . htmlentities($msg) . "<br>";

        //Write message to log file
        error_log( $message, 3, AO_CCSS_LOG );
      }
    }
}