<?php
/*
Autoptimize SpeedUp; minify & cache each JS/ CSS separately + warm the cache
*/

function ao_js_snippetcache($jsin,$scriptname) {
    if (strpos($scriptname,"min.js")===false) {
        $md5hash = "snippet_".md5($jsin);
        $ccheck = new autoptimizeCache($md5hash,'js');
        if($ccheck->check()) {
                $scriptsrc = $ccheck->retrieve();
        } else {
            if(class_exists('JSMin')) {
                    $tmp_jscode = trim(JSMin::minify($jsin));
                    if (!empty($tmp_jscode)) {
                            $scriptsrc = $tmp_jscode;
                            unset($tmp_jscode);
                            $ccheck->cache($scriptsrc,'text/javascript');
                    } else {
                            $scriptsrc=$jsin;
                    }
            } else {
                    $scriptsrc=$jsin;
            }
        }
        unset($ccheck);
    } else {
        // do some housekeeping here to remove comments & linebreaks and stuff
        $scriptsrc=preg_replace("#^\s*\/\/.*$#Um","",$jsin);
        $scriptsrc=preg_replace("#^\s*\/\*[^!].*\*\/\s?#Us","",$scriptsrc);
        $scriptsrc=preg_replace("#(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+#", "\n", $scriptsrc);

        if ((substr($scriptsrc,-1,1)!==";")&&(substr($scriptsrc,-1,1)!=="}")) {
            $scriptsrc.=";";
        }

        if (get_option("autoptimize_js_trycatch")==="on") {
            $scriptsrc="try{".$scriptsrc."}catch(e){}";
        }
    }
    return $scriptsrc;
}

function ao_css_snippetcache($cssin,$filename) {
    $md5hash = "snippet_".md5($cssin);
    $ccheck = new autoptimizeCache($md5hash,'css');
    if($ccheck->check()) {
        $stylesrc = $ccheck->retrieve();
    } else {
        if (strpos($filename,"min.css")===false) {
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
            $stylesrc=autoptimizeStyles::fixurls($filename,$stylesrc);
        }
        if (!empty($filename)) {
            // don't cache inline CSS to avoid risk of cache-explosion
            $ccheck->cache($stylesrc,'text/css');
        }
        unset($ccheck);
    }
    return $stylesrc;
}

add_filter('autoptimize_css_individual_style','ao_css_snippetcache',10,2);
add_filter('autoptimize_js_individual_script','ao_js_snippetcache',10,2);
