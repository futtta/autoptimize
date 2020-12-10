<?php
/**
 * Critical CSS job enqueue logic.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeCriticalCSSEnqueue {
    public function __construct()
    {
        // fetch all options at once and populate them individually explicitely as globals.
        $all_options = autoptimizeCriticalCSSBase::fetch_options();
        foreach ( $all_options as $_option => $_value ) {
            global ${$_option};
            ${$_option} = $_value;
        }
    }

    public static function ao_ccss_enqueue( $hash ) {
        $self = new self();
        // Get key status.
        $key = autoptimizeCriticalCSSCore::ao_ccss_key_status( false );

        // Queue is available to anyone...
        $enqueue = true;

        // ... which are not the ones below.
        if ( is_user_logged_in() || is_feed() || is_404() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || $self->ao_ccss_ua() || 'nokey' == $key['status'] || 'invalid' == $key['status'] || false === apply_filters( 'autoptimize_filter_ccss_enqueue_should_enqueue', true ) ) {
            $enqueue = false;
            autoptimizeCriticalCSSCore::ao_ccss_log( "Job queuing is not available for WordPress's logged in users, feeds, error pages, ajax calls, to criticalcss.com itself or when a valid API key is not found", 3 );
        }

        if ( $enqueue ) {
            // Continue if queue is available
            // Attach required arrays/ vars.
            global $ao_ccss_rules;
            global $ao_ccss_queue_raw;
            global $ao_ccss_queue;
            global $ao_ccss_forcepath;

            // Get request path and page type, and initialize the queue update flag.
            $req_path        = strtok( $_SERVER['REQUEST_URI'], '?' );
            $req_type        = $self->ao_ccss_get_type();
            $job_qualify     = false;
            $target_rule     = false;
            $rule_properties = false;
            $queue_update    = false;

            // Match for paths in rules.
            foreach ( $ao_ccss_rules['paths'] as $path => $props ) {

                // Prepare rule target and log.
                $target_rule = 'paths|' . $path;
                autoptimizeCriticalCSSCore::ao_ccss_log( 'Qualifying path <' . $req_path . '> for job submission by rule <' . $target_rule . '>', 3 );

                // Path match
                // -> exact match needed for AUTO rules
                // -> partial match OK for MANUAL rules (which have empty hash and a file with CCSS).
                if ( $path === $req_path || ( false == $props['hash'] && false != $props['file'] && preg_match( '|' . $path . '|', $req_path ) ) ) {

                    // There's a path match in the rule, so job QUALIFIES with a path rule match.
                    $job_qualify     = true;
                    $rule_properties = $props;
                    autoptimizeCriticalCSSCore::ao_ccss_log( 'Path <' . $req_path . '> QUALIFIED for job submission by rule <' . $target_rule . '>', 3 );

                    // Stop processing other path rules.
                    break;
                }
            }

            // Match for types in rules if no path rule matches and if we're not enforcing paths.
            if ( ! $job_qualify && ( ! $ao_ccss_forcepath || ! in_array( $req_type, apply_filters( 'autoptimize_filter_ccss_coreenqueue_forcepathfortype', array( 'is_page' ) ) ) || ! apply_filters( 'autoptimize_filter_ccss_coreenqueue_ignorealltypes', false ) ) ) {
                foreach ( $ao_ccss_rules['types'] as $type => $props ) {

                    // Prepare rule target and log.
                    $target_rule = 'types|' . $type;
                    autoptimizeCriticalCSSCore::ao_ccss_log( 'Qualifying page type <' . $req_type . '> on path <' . $req_path . '> for job submission by rule <' . $target_rule . '>', 3 );

                    if ( $req_type == $type ) {
                        // Type match.
                        // There's a type match in the rule, so job QUALIFIES with a type rule match.
                        $job_qualify     = true;
                        $rule_properties = $props;
                        autoptimizeCriticalCSSCore::ao_ccss_log( 'Page type <' . $req_type . '> on path <' . $req_path . '> QUALIFIED for job submission by rule <' . $target_rule . '>', 3 );

                        // Stop processing other type rules.
                        break;
                    }
                }
            }

            if ( $job_qualify && ( ( false == $rule_properties['hash'] && false != $rule_properties['file'] ) || strpos( $req_type, 'template_' ) !== false ) ) {
                // If job qualifies but rule hash is false and file isn't false (MANUAL rule) or if template, job does not qualify despite what previous evaluations says.
                $job_qualify = false;
                autoptimizeCriticalCSSCore::ao_ccss_log( 'Job submission DISQUALIFIED by MANUAL rule <' . $target_rule . '> with hash <' . $rule_properties['hash'] . '> and file <' . $rule_properties['file'] . '>', 3 );
            } elseif ( ! $job_qualify && empty( $rule_properties ) ) {
                // But if job does not qualify and rule properties are set, job qualifies as there is no matching rule for it yet
                // Fill-in the new target rule.
                $job_qualify = true;

                // Should we switch to path-base AUTO-rules? Conditions:
                // 1. forcepath option has to be enabled (off by default)
                // 2. request type should be (by default, but filterable) one of is_page (removed for now: woo_is_product or woo_is_product_category).
                if ( ( $ao_ccss_forcepath && in_array( $req_type, apply_filters( 'autoptimize_filter_ccss_coreenqueue_forcepathfortype', array( 'is_page' ) ) ) ) || apply_filters( 'autoptimize_filter_ccss_coreenqueue_ignorealltypes', false ) ) {
                    if ( '/' !== $req_path ) {
                        $target_rule = 'paths|' . $req_path;
                    } else {
                        // Exception; we don't want a path-based rule for "/" as that messes things up, hard-switch this to a type-based is_front_page rule.
                        $target_rule = 'types|' . 'is_front_page';
                    }
                } else {
                    $target_rule = 'types|' . $req_type;
                }
                autoptimizeCriticalCSSCore::ao_ccss_log( 'Job submission QUALIFIED by MISSING rule for page type <' . $req_type . '> on path <' . $req_path . '>, new rule target is <' . $target_rule . '>', 3 );
            } else {
                // Or just log a job qualified by a matching rule.
                autoptimizeCriticalCSSCore::ao_ccss_log( 'Job submission QUALIFIED by AUTO rule <' . $target_rule . '> with hash <' . $rule_properties['hash'] . '> and file <' . $rule_properties['file'] . '>', 3 );
            }

            // Submit job.
            if ( $job_qualify ) {
                if ( ! array_key_exists( $req_path, $ao_ccss_queue ) ) {
                    // This is a NEW job
                    // Merge job into the queue.
                    $ao_ccss_queue[ $req_path ] = $self->ao_ccss_define_job(
                        $req_path,
                        $target_rule,
                        $req_type,
                        $hash,
                        null,
                        null,
                        null,
                        null,
                        true
                    );
                    // Set update flag.
                    $queue_update = true;
                } else {
                    // This is an existing job
                    // The job is still NEW, most likely this is extra CSS file for the same page that needs a hash.
                    if ( 'NEW' == $ao_ccss_queue[ $req_path ]['jqstat'] ) {
                        // Add hash if it's not already in the job.
                        if ( ! in_array( $hash, $ao_ccss_queue[ $req_path ]['hashes'] ) ) {
                            // Push new hash to its array and update flag.
                            $queue_update = array_push( $ao_ccss_queue[ $req_path ]['hashes'], $hash );

                            // Log job update.
                            autoptimizeCriticalCSSCore::ao_ccss_log( 'Hashes UPDATED on local job id <' . $ao_ccss_queue[ $req_path ]['ljid'] . '>, job status NEW, target rule <' . $ao_ccss_queue[ $req_path ]['rtarget'] . '>, hash added: ' . $hash, 3 );

                            // Return from here as the hash array is already updated.
                            return true;
                        }
                    } elseif ( 'NEW' != $ao_ccss_queue[ $req_path ]['jqstat'] && 'JOB_QUEUED' != $ao_ccss_queue[ $req_path ]['jqstat'] && 'JOB_ONGOING' != $ao_ccss_queue[ $req_path ]['jqstat'] ) {
                        // Allow requeuing jobs that are not NEW, JOB_QUEUED or JOB_ONGOING
                        // Merge new job keeping some previous job values.
                        $ao_ccss_queue[ $req_path ] = $self->ao_ccss_define_job(
                            $req_path,
                            $target_rule,
                            $req_type,
                            $hash,
                            $ao_ccss_queue[ $req_path ]['file'],
                            $ao_ccss_queue[ $req_path ]['jid'],
                            $ao_ccss_queue[ $req_path ]['jrstat'],
                            $ao_ccss_queue[ $req_path ]['jvstat'],
                            false
                        );
                        // Set update flag.
                        $queue_update = true;
                    }
                }

                if ( $queue_update ) {
                    // Persist the job to the queue and return.
                    $ao_ccss_queue_raw = json_encode( $ao_ccss_queue );
                    update_option( 'autoptimize_ccss_queue', $ao_ccss_queue_raw, false );
                    return true;
                } else {
                    // Or just return false if no job was added.
                    autoptimizeCriticalCSSCore::ao_ccss_log( 'A job for path <' . $req_path . '> already exist with NEW or PENDING status, skipping job creation', 3 );
                    return false;
                }
            }
        }
    }

    public function ao_ccss_get_type() {
        // Get the type of a page
        // Attach the conditional tags array.
        global $ao_ccss_types;
        global $ao_ccss_forcepath;

        // By default, a page type is false.
        $page_type = false;

        // Iterates over the array to match a type.
        foreach ( $ao_ccss_types as $type ) {
            if ( is_404() ) {
                $page_type = 'is_404';
                break;
            } elseif ( strpos( $type, 'custom_post_' ) !== false && ( ! $ao_ccss_forcepath || ! is_page() ) ) {
                // Match custom post types and not page or page not forced to path-based.
                if ( get_post_type( get_the_ID() ) === substr( $type, 12 ) ) {
                    $page_type = $type;
                    break;
                }
            } elseif ( strpos( $type, 'template_' ) !== false && ( ! $ao_ccss_forcepath || ! is_page() ) ) {
                // Match templates if not page or if page is not forced to path-based.
                if ( is_page_template( substr( $type, 9 ) ) ) {
                    $page_type = $type;
                    break;
                }
            } else {
                // Match all other existing types
                // but remove prefix to be able to check if the function exists & returns true.
                $_type = str_replace( array( 'woo_', 'bp_', 'bbp_', 'edd_' ), '', $type );
                if ( function_exists( $_type ) && call_user_func( $_type ) ) {
                    // Make sure we only return for one page, not for the "paged pages" (/page/2 ..).
                    if ( ! is_page() || ! is_paged() ) {
                        $page_type = $type;
                        break;
                    }
                }
            }
        }

        // Return the page type.
        return $page_type;
    }

    public function ao_ccss_define_job( $path, $target, $type, $hash, $file, $jid, $jrstat, $jvstat, $create ) {
        // Define a job entry to be created or updated
        // Define commom job properties.
        $path            = array();
        $path['ljid']    = $this->ao_ccss_job_id();
        $path['rtarget'] = $target;
        $path['ptype']   = $type;
        $path['hashes']  = array( $hash );
        $path['hash']    = $hash;
        $path['file']    = $file;
        $path['jid']     = $jid;
        $path['jqstat']  = 'NEW';
        $path['jrstat']  = $jrstat;
        $path['jvstat']  = $jvstat;
        $path['jctime']  = microtime( true );
        $path['jftime']  = null;

        // Set operation requested.
        if ( $create ) {
            $operation = 'CREATED';
        } else {
            $operation = 'UPDATED';
        }

        // Log job creation.
        autoptimizeCriticalCSSCore::ao_ccss_log( 'Job ' . $operation . ' with local job id <' . $path['ljid'] . '> for target rule <' . $target . '>', 3 );

        return $path;
    }

    public function ao_ccss_job_id( $length = 6 ) {
        // Generate random strings for the local job ID
        // Based on https://stackoverflow.com/a/4356295 .
        $characters        = '0123456789abcdefghijklmnopqrstuvwxyz';
        $characters_length = strlen( $characters );
        $random_string     = 'j-';
        for ( $i = 0; $i < $length; $i++ ) {
            $random_string .= $characters[ rand( 0, $characters_length - 1 ) ];
        }
        return $random_string;
    }

    public function ao_ccss_ua() {
        // Check for criticalcss.com user agent.
        $agent = '';
        if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $agent = $_SERVER['HTTP_USER_AGENT'];
        }

        // Check for UA and return TRUE when criticalcss.com is the detected UA, false when not.
        $rtn = strpos( $agent, AO_CCSS_URL );
        if ( 0 === $rtn ) {
            $rtn = true;
        } else {
            $rtn = false;
        }
        return ( $rtn );
    }
}
