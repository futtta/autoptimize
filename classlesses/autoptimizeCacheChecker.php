<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* 
 * cachechecker code
 * new in AO 2.0
 * 
 * daily cronned job (filter to change freq. + filter to disable)
 * checks if cachesize is > 0.5GB (filter to change maxsize)
 * if so an option is set
 * if that option is set, notice on admin is shown
 * 
 */

if (is_admin()) {
    add_action('plugins_loaded','ao_cachechecker_setup');
}

function ao_cachechecker_setup() {
    $doCacheCheck = (bool) apply_filters( 'autoptimize_filter_cachecheck_do', true);
    $cacheCheckSchedule = wp_get_schedule( 'ao_cachechecker' );
    $AOCCfreq = apply_filters('autoptimize_filter_cachecheck_frequency','daily');
    if (!in_array($AOCCfreq,array('hourly','daily','monthly'))) {
        $AOCCfreq='daily';
    }
    if ( $doCacheCheck && ( !$cacheCheckSchedule || $cacheCheckSchedule !== $AOCCfreq ) ) {
        wp_schedule_event(time(), $AOCCfreq, 'ao_cachechecker');
    } else if ( $cacheCheckSchedule && !$doCacheCheck ) {
        wp_clear_scheduled_hook( 'ao_cachechecker' );
    }
}

add_action('ao_cachechecker', 'ao_cachechecker_cronjob');
function ao_cachechecker_cronjob() {
    $maxSize = (int) apply_filters( "autoptimize_filter_cachecheck_maxsize", 512000);
    $doCacheCheck = (bool) apply_filters( "autoptimize_filter_cachecheck_do", true);
    $statArr=autoptimizeCache::stats(); 
    $cacheSize=round($statArr[1]/1024);
    if (($cacheSize>$maxSize) && ($doCacheCheck)) {
        update_option("autoptimize_cachesize_notice",true);
        if (apply_filters('autoptimize_filter_cachecheck_sendmail',true)) {
            $ao_mailto=apply_filters('autoptimize_filter_cachecheck_mailto',get_option('admin_email',''));
            $ao_mailsubject=__('Autoptimize cache size warning','autoptimize');
            $ao_mailbody=__('Autoptimize\'s cache size is getting big, consider purging the cache. Have a look at https://wordpress.org/plugins/autoptimize/faq/ to see how you can keep the cache size under control.', 'autoptimize');
            
            if (!empty($ao_mailto)) {
                $ao_mailresult=wp_mail($ao_mailto,$ao_mailsubject,$ao_mailbody);
                if (!$ao_mailresult) {
                    error_log("Autoptimize could not send cache size warning mail.");
                }
            }
        }
    }
}

add_action('admin_notices', 'autoptimize_cachechecker_notice');
function autoptimize_cachechecker_notice() {
    if ((bool) get_option("autoptimize_cachesize_notice",false)) {
        echo '<div class="notice notice-warning"><p>';
        _e('<strong>Autoptimize\'s cache size is getting big</strong>, consider purging the cache. Have a look at <a href="https://wordpress.org/plugins/autoptimize/faq/" target="_blank">the Autoptimize FAQ</a> to see how you can keep the cache size under control.', 'autoptimize' );
        echo '</p></div>';
        update_option("autoptimize_cachesize_notice",false);
    }
}
