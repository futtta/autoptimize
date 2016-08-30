<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class autoptimizeStyles extends autoptimizeBase {
    private $css = array();
    private $csscode = array();
    private $url = array();
    private $restofcontent = '';
    private $mhtml = '';
    private $datauris = false;
    private $hashmap = array();
    private $alreadyminified = false;
    private $inline = false;
    private $defer = false;
    private $defer_inline = false;
    private $whitelist = '';
    private $cssinlinesize = '';
    private $cssremovables = array();
    private $include_inline = false;
    private $inject_min_late = '';

    //Reads the page and collects style tags
    public function read($options) {
        $noptimizeCSS = apply_filters( 'autoptimize_filter_css_noptimize', false, $this->content );
        if ($noptimizeCSS) return false;

        $whitelistCSS = apply_filters( 'autoptimize_filter_css_whitelist', '' );
        if (!empty($whitelistCSS)) {
            $this->whitelist = array_filter(array_map('trim',explode(",",$whitelistCSS)));
        }
        
        if ($options['nogooglefont'] == true) {
            $removableCSS = "fonts.googleapis.com";
        } else {
            $removableCSS = "";
        }
        $removableCSS = apply_filters( 'autoptimize_filter_css_removables', $removableCSS);
        if (!empty($removableCSS)) {
            $this->cssremovables = array_filter(array_map('trim',explode(",",$removableCSS)));
        }

        $this->cssinlinesize = apply_filters('autoptimize_filter_css_inlinesize',256);

        // filter to "late inject minified CSS", default to true for now (it is faster)
        $this->inject_min_late = apply_filters('autoptimize_filter_css_inject_min_late',true);

        // Remove everything that's not the header
        if ( apply_filters('autoptimize_filter_css_justhead',$options['justhead']) == true ) {
            $content = explode('</head>',$this->content,2);
            $this->content = $content[0].'</head>';
            $this->restofcontent = $content[1];
        }

        // include inline?
        if( apply_filters('autoptimize_css_include_inline',$options['include_inline']) == true ) {
            $this->include_inline = true;
        }
        
        // what CSS shouldn't be autoptimized
        $excludeCSS = $options['css_exclude'];
        $excludeCSS = apply_filters( 'autoptimize_filter_css_exclude', $excludeCSS );
        if ($excludeCSS!=="") {
            $this->dontmove = array_filter(array_map('trim',explode(",",$excludeCSS)));
        } else {
            $this->dontmove = "";
        }

        // should we defer css?
        // value: true/ false
        $this->defer = $options['defer'];
        $this->defer = apply_filters( 'autoptimize_filter_css_defer', $this->defer );

        // should we inline while deferring?
        // value: inlined CSS
        $this->defer_inline = $options['defer_inline'];
        $this->defer_inline = apply_filters( 'autoptimize_filter_css_defer_inline', $this->defer_inline );

        // should we inline?
        // value: true/ false
        $this->inline = $options['inline'];
        $this->inline = apply_filters( 'autoptimize_filter_css_inline', $this->inline );
        
        // get cdn url
        $this->cdn_url = $options['cdn_url'];
        
        // Store data: URIs setting for later use
        $this->datauris = $options['datauris'];
        
        // noptimize me
        $this->content = $this->hide_noptimize($this->content);
        
        // exclude (no)script, as those may contain CSS which should be left as is
        if ( strpos( $this->content, '<script' ) !== false ) { 
            $this->content = preg_replace_callback(
                '#<(?:no)?script.*?<\/(?:no)?script>#is',
                create_function(
                    '$matches',
                    'return "%%SCRIPT%%".base64_encode($matches[0])."%%SCRIPT%%";'
                ),
                $this->content
            );
        }

        // Save IE hacks
        $this->content = $this->hide_iehacks($this->content);

        // hide comments
        $this->content = $this->hide_comments($this->content);
        
        // Get <style> and <link>
        if(preg_match_all('#(<style[^>]*>.*</style>)|(<link[^>]*stylesheet[^>]*>)#Usmi',$this->content,$matches)) {
            foreach($matches[0] as $tag) {
                if ($this->isremovable($tag,$this->cssremovables)) {
                    $this->content = str_replace($tag,'',$this->content);
                } else if ($this->ismovable($tag)) {
                    // Get the media
                    if(strpos($tag,'media=')!==false) {
                        preg_match('#media=(?:"|\')([^>]*)(?:"|\')#Ui',$tag,$medias);
                        $medias = explode(',',$medias[1]);
                        $media = array();
                        foreach($medias as $elem) {
                            if (empty($elem)) { $elem="all"; }
                            $media[] = $elem;
                        }
                    } else {
                        // No media specified - applies to all
                        $media = array('all');
                    }
                    $media = apply_filters( 'autoptimize_filter_css_tagmedia',$media,$tag );
                
                    if(preg_match('#<link.*href=("|\')(.*)("|\')#Usmi',$tag,$source)) {
                        // <link>
                        $url = current(explode('?',$source[2],2));
                        $path = $this->getpath($url);
                        
                        if($path!==false && preg_match('#\.css$#',$path)) {
                            // Good link
                            $this->css[] = array($media,$path);
                        }else{
                            // Link is dynamic (.php etc)
                            $tag = '';
                        }
                    } else {
                        // inline css in style tags can be wrapped in comment tags, so restore comments
                        $tag = $this->restore_comments($tag);
                        preg_match('#<style.*>(.*)</style>#Usmi',$tag,$code);

                        // and re-hide them to be able to to the removal based on tag
                        $tag = $this->hide_comments($tag);

                        if ( $this->include_inline ) {
                            $code = preg_replace('#^.*<!\[CDATA\[(?:\s*\*/)?(.*)(?://|/\*)\s*?\]\]>.*$#sm','$1',$code[1]);
                            $this->css[] = array($media,'INLINE;'.$code);
                        } else {
                            $tag = '';
                        }
                    }
                    
                    // Remove the original style tag
                    $this->content = str_replace($tag,'',$this->content);
                }
            }
            return true;
        }
        // Really, no styles?
        return false;
    }
    
    // Joins and optimizes CSS
    public function minify() {
        foreach($this->css as $group) {
            list($media,$css) = $group;
            if(preg_match('#^INLINE;#',$css)) {
                // <style>
                $css = preg_replace('#^INLINE;#','',$css);
                $css = $this->fixurls(ABSPATH.'/index.php',$css);
                $tmpstyle = apply_filters( 'autoptimize_css_individual_style', $css, "" );
                if ( has_filter('autoptimize_css_individual_style') && !empty($tmpstyle) ) {
                    $css=$tmpstyle;
                    $this->alreadyminified=true;
                }
            } else {
                //<link>
                if($css !== false && file_exists($css) && is_readable($css)) {
                    $cssPath = $css;
                    $css = $this->fixurls($cssPath,file_get_contents($cssPath));
                    $css = preg_replace('/\x{EF}\x{BB}\x{BF}/','',$css);
                    $tmpstyle = apply_filters( 'autoptimize_css_individual_style', $css, $cssPath );
                    if (has_filter('autoptimize_css_individual_style') && !empty($tmpstyle)) {
                        $css=$tmpstyle;
                        $this->alreadyminified=true;
                    } else if ($this->can_inject_late($cssPath,$css)) {
                        $css="%%INJECTLATER%%".base64_encode($cssPath)."|".md5($css)."%%INJECTLATER%%";
                    }
                } else {
                    // Couldn't read CSS. Maybe getpath isn't working?
                    $css = '';
                }
            }
            
            foreach($media as $elem) {
                if(!isset($this->csscode[$elem]))
                    $this->csscode[$elem] = '';
                $this->csscode[$elem] .= "\n/*FILESTART*/".$css;
            }
        }
        
        // Check for duplicate code
        $md5list = array();
        $tmpcss = $this->csscode;
        foreach($tmpcss as $media => $code) {
            $md5sum = md5($code);
            $medianame = $media;
            foreach($md5list as $med => $sum) {
                // If same code
                if($sum === $md5sum) {
                    //Add the merged code
                    $medianame = $med.', '.$media;
                    $this->csscode[$medianame] = $code;
                    $md5list[$medianame] = $md5list[$med];
                    unset($this->csscode[$med], $this->csscode[$media]);
                    unset($md5list[$med]);
                }
            }
            $md5list[$medianame] = $md5sum;
        }
        unset($tmpcss);
        
        // Manage @imports, while is for recursive import management
        foreach ($this->csscode as &$thiscss) {
            // Flag to trigger import reconstitution and var to hold external imports
            $fiximports = false;
            $external_imports = "";

            // remove comments to avoid importing commented-out imports
            $thiscss_nocomments=preg_replace('#/\*.*\*/#Um','',$thiscss);
            while(preg_match_all('#@import.*(?:;|$)#Um',$thiscss_nocomments,$matches)) {
                foreach($matches[0] as $import)    {
                    if ($this->isremovable($import,$this->cssremovables)) {
                        $thiscss = str_replace($import,'',$thiscss);
                        $import_ok = true;
                    } else {
                        $url = trim(preg_replace('#^.*((?:https?:|ftp:)?//.*\.css).*$#','$1',trim($import))," \t\n\r\0\x0B\"'");
                        $path = $this->getpath($url);
                        $import_ok = false;
                        if (file_exists($path) && is_readable($path)) {
                            $code = addcslashes($this->fixurls($path,file_get_contents($path)),"\\");
                            $code = preg_replace('/\x{EF}\x{BB}\x{BF}/','',$code);
                            $tmpstyle = apply_filters( 'autoptimize_css_individual_style', $code, "" );
                            if ( has_filter('autoptimize_css_individual_style') && !empty($tmpstyle)) {
                                $code=$tmpstyle;
                                $this->alreadyminified=true;
                            } else if ($this->can_inject_late($path,$code)) {
                                $code="%%INJECTLATER%%".base64_encode($path)."|".md5($code)."%%INJECTLATER%%";
                            }
                            
                            if(!empty($code)) {
                                $tmp_thiscss = preg_replace('#(/\*FILESTART\*/.*)'.preg_quote($import,'#').'#Us','/*FILESTART2*/'.$code.'$1',$thiscss);
                                if (!empty($tmp_thiscss)) {
                                    $thiscss = $tmp_thiscss;
                                    $import_ok = true;
                                    unset($tmp_thiscss);
                                }
                                unset($code);
                            }
                        }
                    }

                    if (!$import_ok) {
                        // external imports and general fall-back
                        $external_imports .= $import;
                        $thiscss = str_replace($import,'',$thiscss);
                        $fiximports = true;
                    }
                }
                $thiscss = preg_replace('#/\*FILESTART\*/#','',$thiscss);
                $thiscss = preg_replace('#/\*FILESTART2\*/#','/*FILESTART*/',$thiscss);
                
                // and update $thiscss_nocomments before going into next iteration in while loop
                $thiscss_nocomments=preg_replace('#/\*.*\*/#Um','',$thiscss);
            }
            unset($thiscss_nocomments);
            
            // add external imports to top of aggregated CSS
            if($fiximports) {
                $thiscss=$external_imports.$thiscss;
            }
        }
        unset($thiscss);
        
        // $this->csscode has all the uncompressed code now. 
        $mhtmlcount = 0;
        foreach($this->csscode as &$code) {
            // Check for already-minified code
            $hash = md5($code);
            $ccheck = new autoptimizeCache($hash,'css');
            if($ccheck->check()) {
                $code = $ccheck->retrieve();
                $this->hashmap[md5($code)] = $hash;
                continue;
            }
            unset($ccheck);            

            // Do the imaging!
            $imgreplace = array();
            preg_match_all('#(background[^;{}]*url\((?!\s?"?\'?\s?data)(.*)\)[^;}]*)(?:;|$|})#Usm',$code,$matches);
            
            if(($this->datauris == true) && (function_exists('base64_encode')) && (is_array($matches)))    {
                foreach($matches[2] as $count => $quotedurl) {
                    $iurl = trim($quotedurl," \t\n\r\0\x0B\"'");

                    // if querystring, remove it from url
                    if (strpos($iurl,'?') !== false) { $iurl = strtok($iurl,'?'); }
                    
                    $ipath = $this->getpath($iurl);

                    $datauri_max_size = 4096;
                    $datauri_max_size = (int) apply_filters( 'autoptimize_filter_css_datauri_maxsize', $datauri_max_size );
                    $datauri_exclude = apply_filters( 'autoptimize_filter_css_datauri_exclude', "");
                    if (!empty($datauri_exclude)) {
                        $no_datauris=array_filter(array_map('trim',explode(",",$datauri_exclude)));
                        foreach ($no_datauris as $no_datauri) {
                            if (strpos($iurl,$no_datauri)!==false) {
                                $ipath=false;
                                break;
                            }
                        }
                    }

                    if($ipath != false && preg_match('#\.(jpe?g|png|gif|bmp)$#i',$ipath) && file_exists($ipath) && is_readable($ipath) && filesize($ipath) <= $datauri_max_size) {
                        $ihash=md5($ipath);
                        $icheck = new autoptimizeCache($ihash,'img');
                        if($icheck->check()) {
                            // we have the base64 image in cache
                            $headAndData=$icheck->retrieve();
                            $_base64data=explode(";base64,",$headAndData);
                            $base64data=$_base64data[1];
                        } else {
                            // It's an image and we don't have it in cache, get the type
                            $explA=explode('.',$ipath);
                            $type=end($explA);

                            switch($type) {
                                case 'jpeg':
                                    $dataurihead = 'data:image/jpeg;base64,';
                                    break;
                                case 'jpg':
                                    $dataurihead = 'data:image/jpeg;base64,';
                                    break;
                                case 'gif':
                                    $dataurihead = 'data:image/gif;base64,';
                                    break;
                                case 'png':
                                    $dataurihead = 'data:image/png;base64,';
                                    break;
                                case 'bmp':
                                    $dataurihead = 'data:image/bmp;base64,';
                                    break;
                                default:
                                    $dataurihead = 'data:application/octet-stream;base64,';
                            }
                        
                            // Encode the data
                            $base64data = base64_encode(file_get_contents($ipath));
                            $headAndData=$dataurihead.$base64data;

                            // Save in cache
                            $icheck->cache($headAndData,"text/plain");
                        }
                        unset($icheck);

                        // Add it to the list for replacement
                        $imgreplace[$matches[1][$count]] = str_replace($quotedurl,$headAndData,$matches[1][$count]).";\n*".str_replace($quotedurl,'mhtml:%%MHTML%%!'.$mhtmlcount,$matches[1][$count]).";\n_".$matches[1][$count].';';
                        
                        // Store image on the mhtml document
                        $this->mhtml .= "--_\r\nContent-Location:{$mhtmlcount}\r\nContent-Transfer-Encoding:base64\r\n\r\n{$base64data}\r\n";
                        $mhtmlcount++;
                    } else {
                        // just cdn the URL if applicable
                        if (!empty($this->cdn_url)) {
                            $url = trim($quotedurl," \t\n\r\0\x0B\"'");
                            $cdn_url=$this->url_replace_cdn($url);
                            $imgreplace[$matches[1][$count]] = str_replace($quotedurl,$cdn_url,$matches[1][$count]);
                        }
                    }
                }
            } else if ((is_array($matches)) && (!empty($this->cdn_url))) {
                // change background image urls to cdn-url
                foreach($matches[2] as $count => $quotedurl) {
                    $url = trim($quotedurl," \t\n\r\0\x0B\"'");
                    $cdn_url=$this->url_replace_cdn($url);
                    $imgreplace[$matches[1][$count]] = str_replace($quotedurl,$cdn_url,$matches[1][$count]);
                }
            }
            
            if(!empty($imgreplace)) {
                $code = str_replace(array_keys($imgreplace),array_values($imgreplace),$code);
            }
            
            // CDN the fonts!
            if ( (!empty($this->cdn_url)) && (apply_filters('autoptimize_filter_css_fonts_cdn',false)) && (version_compare(PHP_VERSION, '5.3.0') >= 0) ) {
                $fontreplace = array();
                include_once(AUTOPTIMIZE_PLUGIN_DIR.'classlesses/autoptimizeFontRegex.php');
                
                preg_match_all($fonturl_regex,$code,$matches);
                if (is_array($matches)) {
                    foreach($matches[8] as $count => $quotedurl) {
                        $url = trim($quotedurl," \t\n\r\0\x0B\"'");
                        $cdn_url=$this->url_replace_cdn($url);
                        $fontreplace[$matches[8][$count]] = str_replace($quotedurl,$cdn_url,$matches[8][$count]);
                    }
                    if(!empty($fontreplace)) {
                        $code = str_replace(array_keys($fontreplace),array_values($fontreplace),$code);
                    }
                }
            }
            
            // Minify
            if (($this->alreadyminified!==true) && (apply_filters( "autoptimize_css_do_minify", true))) {
                if (class_exists('Minify_CSS_Compressor')) {
                    $tmp_code = trim(Minify_CSS_Compressor::process($code));
                } else if(class_exists('CSSmin')) {
                    $cssmin = new CSSmin();
                    if (method_exists($cssmin,"run")) {
                        $tmp_code = trim($cssmin->run($code));
                    } elseif (@is_callable(array($cssmin,"minify"))) {
                        $tmp_code = trim(CssMin::minify($code));
                    }
                }
                if (!empty($tmp_code)) {
                    $code = $tmp_code;
                    unset($tmp_code);
                }
            }
            
            $code = $this->inject_minified($code);
            
            $tmp_code = apply_filters( 'autoptimize_css_after_minify',$code );
            if (!empty($tmp_code)) {
                $code = $tmp_code;
                unset($tmp_code);
            }
            
            $this->hashmap[md5($code)] = $hash;
        }
        unset($code);
        return true;
    }
    
    //Caches the CSS in uncompressed, deflated and gzipped form.
    public function cache() {
        if($this->datauris) {
            // MHTML Preparation
            $this->mhtml = "/*\r\nContent-Type: multipart/related; boundary=\"_\"\r\n\r\n".$this->mhtml."*/\r\n";
            $md5 = md5($this->mhtml);
            $cache = new autoptimizeCache($md5,'txt');
            if(!$cache->check()) {
                // Cache our images for IE
                $cache->cache($this->mhtml,'text/plain');
            }
            $mhtml = AUTOPTIMIZE_CACHE_URL.$cache->getname();
        }
        
        // CSS cache
        foreach($this->csscode as $media => $code) {
            $md5 = $this->hashmap[md5($code)];

            if($this->datauris)    {
                // Images for ie! Get the right url
                $code = str_replace('%%MHTML%%',$mhtml,$code);
            }
                
            $cache = new autoptimizeCache($md5,'css');
            if(!$cache->check()) {
                // Cache our code
                $cache->cache($code,'text/css');
            }
            $this->url[$media] = AUTOPTIMIZE_CACHE_URL.$cache->getname();
        }
    }
    
    //Returns the content
    public function getcontent() {
        // restore IE hacks
        $this->content = $this->restore_iehacks($this->content);

        // restore comments
        $this->content = $this->restore_comments($this->content);
        
        // restore (no)script
        if ( strpos( $this->content, '%%SCRIPT%%' ) !== false ) { 
            $this->content = preg_replace_callback(
                '#%%SCRIPT%%(.*?)%%SCRIPT%%#is',
                create_function(
                    '$matches',
                    'return base64_decode($matches[1]);'
                ),
                $this->content
            );
        }

        // restore noptimize
        $this->content = $this->restore_noptimize($this->content);
        
        //Restore the full content
        if(!empty($this->restofcontent)) {
            $this->content .= $this->restofcontent;
            $this->restofcontent = '';
        }
        
        // Inject the new stylesheets
        $replaceTag = array("<title","before");
        $replaceTag = apply_filters( 'autoptimize_filter_css_replacetag', $replaceTag );

        if ($this->inline == true) {
            foreach($this->csscode as $media => $code) {
                $this->inject_in_html('<style type="text/css" media="'.$media.'">'.$code.'</style>',$replaceTag);
            }
        } else {
            if ($this->defer == true) {
                $deferredCssBlock = "<script data-cfasync='false'>function lCss(url,media) {var d=document;var l=d.createElement('link');l.rel='stylesheet';l.type='text/css';l.href=url;l.media=media;aoin=d.getElementById('aonoscrcss');aoin.parentNode.insertBefore(l,aoin.nextSibling);}function deferredCSS() {";
                $noScriptCssBlock = "<noscript id=\"aonoscrcss\">";
                $defer_inline_code=$this->defer_inline;
                if(!empty($defer_inline_code)){
                    if ( apply_filters( 'autoptimize_filter_css_critcss_minify',true ) ) {
                        $iCssHash=md5($defer_inline_code);
                        $iCssCache = new autoptimizeCache($iCssHash,'css');
                        if($iCssCache->check()) { 
                            // we have the optimized inline CSS in cache
                            $defer_inline_code=$iCssCache->retrieve();
                        } else {
                            if (class_exists('Minify_CSS_Compressor')) {
                                $tmp_code = trim(Minify_CSS_Compressor::process($defer_inline_code));
                            } else if(class_exists('CSSmin')) {
                                $cssmin = new CSSmin();
                                $tmp_code = trim($cssmin->run($defer_inline_code));
                            }
                            if (!empty($tmp_code)) {
                                $defer_inline_code = $tmp_code;
                                $iCssCache->cache($defer_inline_code,"text/css");
                                unset($tmp_code);
                            }
                        }
                    }
                    $code_out='<style type="text/css" id="aoatfcss" media="all">'.$defer_inline_code.'</style>';
                    $this->inject_in_html($code_out,$replaceTag);
                }
            }

            foreach($this->url as $media => $url) {
                $url = $this->url_replace_cdn($url);
                
                //Add the stylesheet either deferred (import at bottom) or normal links in head
                if($this->defer == true) {
                    $deferredCssBlock .= "lCss('".$url."','".$media."');";
                    $noScriptCssBlock .= '<link type="text/css" media="'.$media.'" href="'.$url.'" rel="stylesheet" />';
                } else {
                    if (strlen($this->csscode[$media]) > $this->cssinlinesize) {
                        $this->inject_in_html('<link type="text/css" media="'.$media.'" href="'.$url.'" rel="stylesheet" />',$replaceTag);
                    } else if (strlen($this->csscode[$media])>0) {
                        $this->inject_in_html('<style type="text/css" media="'.$media.'">'.$this->csscode[$media].'</style>',$replaceTag);
                    }
                }
            }
            
            if($this->defer == true) {
                $deferredCssBlock .= "}if(window.addEventListener){window.addEventListener('DOMContentLoaded',deferredCSS,false);}else{window.onload = deferredCSS;}</script>";
                $noScriptCssBlock .= "</noscript>";
                $this->inject_in_html($noScriptCssBlock,$replaceTag);
                $this->inject_in_html($deferredCssBlock,array('</body>','before'));
            }
        }

        //Return the modified stylesheet
        return $this->content;
    }
    
    static function fixurls($file,$code) {
        $file = str_replace(WP_ROOT_DIR,'/',$file);
        $dir = dirname($file); //Like /wp-content

        // quick fix for import-troubles in e.g. arras theme
        $code=preg_replace('#@import ("|\')(.+?)\.css("|\')#','@import url("${2}.css")',$code);

        if(preg_match_all('#url\((?!data)(?!\#)(?!"\#)(.*)\)#Usi',$code,$matches)) {
            $replace = array();
            foreach($matches[1] as $k => $url) {
                // Remove quotes
                $url = trim($url," \t\n\r\0\x0B\"'");
                $noQurl = trim($url,"\"'");
                if ($url!==$noQurl) {
                    $removedQuotes=true;
                } else {
                    $removedQuotes=false;
                }
                $url=$noQurl;
                if(substr($url,0,1)=='/' || preg_match('#^(https?://|ftp://|data:)#i',$url)) {
                    //URL is absolute
                    continue;
                } else {
                    // relative URL
                    $newurl = preg_replace('/https?:/','',str_replace(" ","%20",AUTOPTIMIZE_WP_ROOT_URL.str_replace('//','/',$dir.'/'.$url)));

                    $hash = md5($url);
                    $code = str_replace($matches[0][$k],$hash,$code);

                    if (!empty($removedQuotes)) {
                        $replace[$hash] = 'url(\''.$newurl.'\')';
                    } else {
                        $replace[$hash] = 'url('.$newurl.')';
                    }
                }
            }    
            //Do the replacing here to avoid breaking URLs
            $code = str_replace(array_keys($replace),array_values($replace),$code);
        }    
        return $code;
    }
    
    private function ismovable($tag) {
        if (!empty($this->whitelist)) {
            foreach ($this->whitelist as $match) {
                if(strpos($tag,$match)!==false) {
                    return true;
                }
            }
            // no match with whitelist
            return false;
        } else {
            if (is_array($this->dontmove)) {
                foreach($this->dontmove as $match) {
                    if(strpos($tag,$match)!==false) {
                        //Matched something
                        return false;
                    }
                }
            }
            
            //If we're here it's safe to move
            return true;
        }
    }
    
    private function can_inject_late($cssPath,$css) {
        if ((strpos($cssPath,"min.css")===false) || ($this->inject_min_late!==true)) {
            // late-inject turned off or file not minified based on filename
            return false;
        } else if (strpos($css,"@import")!==false) {
            // can't late-inject files with imports as those need to be aggregated 
            return false;
        } else if ( (strpos($css,"@font-face")!==false ) && ( apply_filters("autoptimize_filter_css_fonts_cdn",false)===true) && (!empty($this->cdn_url)) ) {
            // don't late-inject CSS with font-src's if fonts are set to be CDN'ed
            return false;
        } else if ( (($this->datauris == true) || (!empty($this->cdn_url))) && preg_match("#background[^;}]*url\(#Ui",$css) ) {
            // don't late-inject CSS with images if CDN is set OR is image inlining is on
            return false;
        } else {
            // phew, all is safe, we can late-inject
            return true;
        }
    }
}
