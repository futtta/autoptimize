<?php
/*
* Autoptimize SpeedUp; minify & cache each JS/ CSS separately
* new in Autoptimize 2.2
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function ao_js_snippetcacher($jsin,$jsfilename) {
    $md5hash = "snippet_".md5($jsin);
    $ccheck = new autoptimizeCache($md5hash,'js');
    if($ccheck->check()) {
        $scriptsrc = $ccheck->retrieve();
    } else {
        if ( (strpos($jsfilename,"min.js") === false) && ( strpos($jsfilename,"js/jquery/jquery.js") === false ) && ( str_replace(apply_filters('autoptimize_filter_js_consider_minified',false), '', $jsfilename) === $jsfilename ) ) {
            if(class_exists('JSMin')) {
                $tmp_jscode = trim(JSMin::minify($jsin));
                if (!empty($tmp_jscode)) {
                        $scriptsrc = $tmp_jscode;
                        unset($tmp_jscode);
                } else {
                        $scriptsrc=$jsin;
                }
            } else {
                $scriptsrc=$jsin;
            }
        } else {
            // do some housekeeping here to remove comments & linebreaks and stuff
            $scriptsrc=preg_replace("#^\s*\/\/.*$#Um","",$jsin);
            $scriptsrc=preg_replace("#^\s*\/\*[^!].*\*\/\s?#Us","",$scriptsrc);
            $scriptsrc=preg_replace("#(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+#", "\n", $scriptsrc);
        }

        if ( (substr($scriptsrc,-1,1)!==";") && (substr($scriptsrc,-1,1)!=="}") ) {
            $scriptsrc.=";";
        }

        if ( !empty($jsfilename) && str_replace( apply_filters('autoptimize_filter_js_speedup_cache',false), '', $jsfilename ) === $jsfilename ) {
            // don't cache inline CSS or if filter says no
            $ccheck->cache($scriptsrc,'text/javascript');
        }
    }
    unset($ccheck);

    return $scriptsrc;
}

function ao_css_snippetcacher($cssin,$cssfilename) {
    $md5hash = "snippet_".md5($cssin);
    $ccheck = new autoptimizeCache($md5hash,'css');
    if($ccheck->check()) {
        $stylesrc = $ccheck->retrieve();
    } else {
        if ( ( strpos($cssfilename,"min.css") === false ) && ( str_replace( apply_filters('autoptimize_filter_css_consider_minified',false), '', $cssfilename ) === $cssfilename ) ) {
            if (class_exists('Minify_CSS_Compressor')) {
                $tmp_code = trim(Minify_CSS_Compressor::process($cssin));
            } else if(class_exists('CSSmin')) {
                $cssmin = new CSSmin();
                if (method_exists($cssmin,"run")) {
                    $tmp_code = trim($cssmin->run($cssin));
                } elseif (@is_callable(array($cssmin,"minify"))) {
                    $tmp_code = trim(CssMin::minify($cssin));
                }
            }

            if (!empty($tmp_code)) {
                $stylesrc = $tmp_code;
                unset($tmp_code);
            } else {
                $stylesrc = $cssin;
            }
        } else {
            // .min.css -> no heavy-lifting, just some cleanup
            $stylesrc=preg_replace("#^\s*\/\*[^!].*\*\/\s?#Us","",$cssin);
            $stylesrc=preg_replace("#(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+#", "\n", $stylesrc);
            $stylesrc=autoptimizeStyles::fixurls($cssfilename,$stylesrc);
        }
        if ( !empty($cssfilename) && ( str_replace( apply_filters('autoptimize_filter_css_speedup_cache',false), '', $cssfilename ) === $cssfilename ) ) {
            // only cache CSS if not inline and allowed by filter
            $ccheck->cache($stylesrc,'text/css');
        }
    }
    unset($ccheck);
    return $stylesrc;
}

function ao_css_speedup_cleanup($cssin) {
	// speedupper results in aggregated CSS not being minified, so the filestart-marker AO adds when aggregating need to be removed
	return trim(str_replace(array('/*FILESTART*/','/*FILESTART2*/'),'',$cssin));
}

function ao_js_speedup_cleanup($jsin) {
	// cleanup
	return trim($jsin);
}

// conditionally attach filters
if ( apply_filters('autoptimize_css_do_minify',true) ) {
    add_filter('autoptimize_css_individual_style','ao_css_snippetcacher',10,2);
    add_filter('autoptimize_css_after_minify','ao_css_speedup_cleanup',10,1);
}
if ( apply_filters('autoptimize_js_do_minify',true) ) {
    add_filter('autoptimize_js_individual_script','ao_js_snippetcacher',10,2);
    add_filter('autoptimize_js_after_minify','ao_js_speedup_cleanup',10,1);
}
