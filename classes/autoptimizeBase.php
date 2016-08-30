<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

abstract class autoptimizeBase {
    protected $content = '';
    protected $tagWarning = false;
    
    public function __construct($content) {
        $this->content = $content;
    }
    
    //Reads the page and collects tags
    abstract public function read($justhead);
    
    //Joins and optimizes collected things
    abstract public function minify();
    
    //Caches the things
    abstract public function cache();
    
    //Returns the content
    abstract public function getcontent();
    
    //Converts an URL to a full path
    protected function getpath($url) {
        $url=apply_filters( 'autoptimize_filter_cssjs_alter_url', $url);
        
        if (strpos($url,'%')!==false) {
            $url=urldecode($url);
        }

        $siteHost=parse_url(AUTOPTIMIZE_WP_SITE_URL,PHP_URL_HOST);
        
        // normalize
        if (strpos($url,'//')===0) {
            if (is_ssl()) {
                $url = "https:".$url;
            } else {
                $url = "http:".$url;
            }
        } else if ((strpos($url,'//')===false) && (strpos($url,$siteHost)===false)) {
            if (AUTOPTIMIZE_WP_SITE_URL === $siteHost) {
                $url = AUTOPTIMIZE_WP_SITE_URL.$url;
            } else {
                $subdir_levels=substr_count(preg_replace("/https?:\/\//","",AUTOPTIMIZE_WP_SITE_URL),"/");
                $url = AUTOPTIMIZE_WP_SITE_URL.str_repeat("/..",$subdir_levels).$url;
            }
        }

        // first check; hostname wp site should be hostname of url
        $thisHost=@parse_url($url,PHP_URL_HOST);
        if ($thisHost !== $siteHost) {
            /* 
            * first try to get all domains from WPML (if available)
            * then explicitely declare $this->cdn_url as OK as well
            * then apply own filter autoptimize_filter_cssjs_multidomain takes an array of hostnames
            * each item in that array will be considered part of the same WP multisite installation
            */
            $multidomains = array();
            
            $multidomainsWPML = apply_filters('wpml_setting', array(), 'language_domains');
            if (!empty($multidomainsWPML)) {
                $multidomains = array_map(array($this,"ao_getDomain"),$multidomainsWPML);
            }
            
            if (!empty($this->cdn_url)) {
                $multidomains[]=parse_url($this->cdn_url,PHP_URL_HOST);
            }
            
            $multidomains = apply_filters('autoptimize_filter_cssjs_multidomain', $multidomains);
            
            if (!empty($multidomains)) {
                if (in_array($thisHost,$multidomains)) {
                    $url=str_replace($thisHost, parse_url(AUTOPTIMIZE_WP_SITE_URL,PHP_URL_HOST), $url);
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
        
        // try to remove "wp root url" from url while not minding http<>https
        $tmp_ao_root = preg_replace('/https?:/','',AUTOPTIMIZE_WP_ROOT_URL);
        $tmp_url = preg_replace('/https?:/','',$url);
        $path = str_replace($tmp_ao_root,'',$tmp_url);
        
        // final check; if path starts with :// or //, this is not a URL in the WP context and we have to assume we can't aggregate
        if (preg_match('#^:?//#',$path)) {
            /** External script/css (adsense, etc) */
            return false;
        }

        $path = str_replace('//','/',WP_ROOT_DIR.$path);
        return $path;
    }

    // needed for WPML-filter
    protected function ao_getDomain($in) {
        // make sure the url starts with something vaguely resembling a protocol
        if ((strpos($in,"http")!==0) && (strpos($in,"//")!==0)) {
            $in="http://".$in;
        }
        
        // do the actual parse_url
        $out = parse_url($in,PHP_URL_HOST);
        
        // fallback if parse_url does not understand the url is in fact a url
        if (empty($out)) $out=$in;
        
        return $out;
    }


    // logger
    protected function ao_logger($logmsg,$appendHTML=true) {
        if ($appendHTML) {
            $logmsg="<!--noptimize--><!-- ".$logmsg." --><!--/noptimize-->";
            $this->content.=$logmsg;
        } else {
            error_log("Autoptimize: ".$logmsg);
        }
    }

    // hide everything between noptimize-comment tags
    protected function hide_noptimize($noptimize_in) {
        if ( preg_match( '/<!--\s?noptimize\s?-->/', $noptimize_in ) ) { 
            $noptimize_out = preg_replace_callback(
                '#<!--\s?noptimize\s?-->.*?<!--\s?/\s?noptimize\s?-->#is',
                create_function(
                    '$matches',
                    'return "%%NOPTIMIZE%%".base64_encode($matches[0])."%%NOPTIMIZE%%";'
                ),
                $noptimize_in
            );
        } else {
            $noptimize_out = $noptimize_in;
        }
        return $noptimize_out;
    }
    
    // unhide noptimize-tags
    protected function restore_noptimize($noptimize_in) {
        if ( strpos( $noptimize_in, '%%NOPTIMIZE%%' ) !== false ) { 
            $noptimize_out = preg_replace_callback(
                '#%%NOPTIMIZE%%(.*?)%%NOPTIMIZE%%#is',
                create_function(
                    '$matches',
                    'return base64_decode($matches[1]);'
                ),
                $noptimize_in
            );
        } else {
            $noptimize_out = $noptimize_in;
        }
        return $noptimize_out;
    }

    protected function hide_iehacks($iehacks_in) {
        if ( strpos( $iehacks_in, '<!--[if' ) !== false ) { 
            $iehacks_out = preg_replace_callback(
                '#<!--\[if.*?\[endif\]-->#is',
                create_function(
                    '$matches',
                    'return "%%IEHACK%%".base64_encode($matches[0])."%%IEHACK%%";'
                ),
                $iehacks_in
            );
        } else {
            $iehacks_out = $iehacks_in;
        }
        return $iehacks_out;
    }

    protected function restore_iehacks($iehacks_in) {
        if ( strpos( $iehacks_in, '%%IEHACK%%' ) !== false ) { 
            $iehacks_out = preg_replace_callback(
                '#%%IEHACK%%(.*?)%%IEHACK%%#is',
                create_function(
                    '$matches',
                    'return base64_decode($matches[1]);'
                ),
                $iehacks_in
            );
        } else {
            $iehacks_out=$iehacks_in;
        }
        return $iehacks_out;
    }

    protected function hide_comments($comments_in) {
        if ( strpos( $comments_in, '<!--' ) !== false ) {
            $comments_out = preg_replace_callback(
                '#<!--.*?-->#is',
                create_function(
                    '$matches',
                    'return "%%COMMENTS%%".base64_encode($matches[0])."%%COMMENTS%%";'
                ),
                $comments_in
            );
        } else {
            $comments_out = $comments_in;
        }
        return $comments_out;
    }

    protected function restore_comments($comments_in) {
        if ( strpos( $comments_in, '%%COMMENTS%%' ) !== false ) {
            $comments_out = preg_replace_callback(
                '#%%COMMENTS%%(.*?)%%COMMENTS%%#is',
                create_function(
                    '$matches',
                    'return base64_decode($matches[1]);'
                ),
                $comments_in
            );
        } else {
            $comments_out=$comments_in;
        }
        return $comments_out;
    }

    protected function url_replace_cdn( $url ) {
        // API filter to change base CDN URL
        $cdn_url = apply_filters( 'autoptimize_filter_base_cdnurl', $this->cdn_url );

        if ( !empty($cdn_url) )  {
            // prepend domain-less absolute URL's
            if ( ( substr( $url, 0, 1 ) === '/' ) && ( substr( $url, 1, 1 ) !== '/' ) ) {
                $url = rtrim( $cdn_url, '/' ) . $url;
            } else {
                // get wordpress base URL
                $WPSiteBreakdown = parse_url( AUTOPTIMIZE_WP_SITE_URL );
                $WPBaseUrl       = $WPSiteBreakdown['scheme'] . '://' . $WPSiteBreakdown['host'];
                if ( ! empty( $WPSiteBreakdown['port'] ) ) {
                    $WPBaseUrl .= ":" . $WPSiteBreakdown['port'];
                }
                // replace full url's with scheme
                $tmp_url = str_replace( $WPBaseUrl, rtrim( $cdn_url, '/' ), $url );
                if ( $tmp_url === $url ) {
                    // last attempt; replace scheme-less URL's
                    $url = str_replace( preg_replace( '/https?:/', '', $WPBaseUrl ), rtrim( $cdn_url, '/' ), $url );
                } else {
                    $url = $tmp_url;
                }
            }
        }

        // allow API filter to alter URL after CDN replacement
        $url = apply_filters( 'autoptimize_filter_base_replace_cdn', $url );
        return $url;
    }

    protected function inject_in_html($payload,$replaceTag) {
        if (strpos($this->content,$replaceTag[0])!== false) {
            if ($replaceTag[1]==="after") {
                $replaceBlock=$replaceTag[0].$payload;
            } else if ($replaceTag[1]==="replace"){
                $replaceBlock=$payload;
            } else {
                $replaceBlock=$payload.$replaceTag[0];
            }
            $this->content = substr_replace($this->content,$replaceBlock,strpos($this->content,$replaceTag[0]),strlen($replaceTag[0]));
        } else {
            $this->content .= $payload;
            if (!$this->tagWarning) {
                $this->content .= "<!--noptimize--><!-- Autoptimize found a problem with the HTML in your Theme, tag \"".str_replace(array("<",">"),"",$replaceTag[0])."\" missing --><!--/noptimize-->";
                $this->tagWarning=true;
            }
        }
    }
    
    protected function isremovable($tag, $removables) {
        foreach ($removables as $match) {
            if (strpos($tag,$match)!==false) {
                return true;
            }
        }
        return false;
    }
    
    // inject already minified code in optimized JS/CSS
    protected function inject_minified($in) {
        if ( strpos( $in, '%%INJECTLATER%%' ) !== false ) {
            $out = preg_replace_callback(
                '#%%INJECTLATER%%(.*?)%%INJECTLATER%%#is',
                create_function(
                    '$matches',
                    '$filepath=base64_decode(strtok($matches[1],"|"));
                    $filecontent=file_get_contents($filepath);

                    // remove BOM
                    $filecontent = preg_replace("#\x{EF}\x{BB}\x{BF}#","",$filecontent);

                    // remove comments and blank lines
                    if (substr($filepath,-3,3)===".js") {
                        $filecontent=preg_replace("#^\s*\/\/.*$#Um","",$filecontent);
                    }

                    $filecontent=preg_replace("#^\s*\/\*[^!].*\*\/\s?#Um","",$filecontent);
                    $filecontent=preg_replace("#(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+#", "\n", $filecontent);

                    // specific stuff for JS-files
                    if (substr($filepath,-3,3)===".js") {
                        if ((substr($filecontent,-1,1)!==";")&&(substr($filecontent,-1,1)!=="}")) {
                            $filecontent.=";";
                        }

                        if (get_option("autoptimize_js_trycatch")==="on") {
                            $filecontent="try{".$filecontent."}catch(e){}";
                        }
                    } else if ((substr($filepath,-4,4)===".css")) {
                        $filecontent=autoptimizeStyles::fixurls($filepath,$filecontent);
                    }

                    // return 
                    return "\n".$filecontent;'
                ),
                $in
            );
        } else {
            $out = $in;
        }
        return $out;
    }
}
