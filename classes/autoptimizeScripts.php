<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class autoptimizeScripts extends autoptimizeBase {
    private $scripts = array();
    private $dontmove = array('document.write','html5.js','show_ads.js','google_ad','histats.com/js','statcounter.com/counter/counter.js','ws.amazon.com/widgets','media.fastclick.net','/ads/','comment-form-quicktags/quicktags.php','edToolbar','intensedebate.com','scripts.chitika.net/','_gaq.push','jotform.com/','admin-bar.min.js','GoogleAnalyticsObject','plupload.full.min.js','syntaxhighlighter','adsbygoogle','gist.github.com','_stq','nonce','post_id','data-noptimize','wordfence_logHuman');
    private $domove = array('gaJsHost','load_cmc','jd.gallery.transitions.js','swfobject.embedSWF(','tiny_mce.js','tinyMCEPreInit.go');
    private $domovelast = array('addthis.com','/afsonline/show_afs_search.js','disqus.js','networkedblogs.com/getnetworkwidget','infolinks.com/js/','jd.gallery.js.php','jd.gallery.transitions.js','swfobject.embedSWF(','linkwithin.com/widget.js','tiny_mce.js','tinyMCEPreInit.go');
    private $trycatch = false;
    private $alreadyminified = false;
    private $forcehead = true;
    private $include_inline = false;
    private $jscode = '';
    private $url = '';
    private $move = array('first' => array(), 'last' => array());
    private $restofcontent = '';
    private $md5hash = '';
    private $whitelist = '';
    private $jsremovables = array();
    private $inject_min_late = '';
    
    //Reads the page and collects script tags
    public function read($options) {
        $noptimizeJS = apply_filters( 'autoptimize_filter_js_noptimize', false, $this->content );
        if ($noptimizeJS) return false;

        // only optimize known good JS?
        $whitelistJS = apply_filters( 'autoptimize_filter_js_whitelist', '', $this->content );
        if (!empty($whitelistJS)) {
            $this->whitelist = array_filter(array_map('trim',explode(",",$whitelistJS)));
        }

        // is there JS we should simply remove
        $removableJS = apply_filters( 'autoptimize_filter_js_removables', '', $this->content );
        if (!empty($removableJS)) {
            $this->jsremovables = array_filter(array_map('trim',explode(",",$removableJS)));
        }

        // only header?
        if( apply_filters('autoptimize_filter_js_justhead', $options['justhead']) == true ) {
            $content = explode('</head>',$this->content,2);
            $this->content = $content[0].'</head>';
            $this->restofcontent = $content[1];
        }
        
        // include inline?
        if( apply_filters('autoptimize_js_include_inline', $options['include_inline']) == true ) {
            $this->include_inline = true;
        }

        // filter to "late inject minified JS", default to true for now (it is faster)
        $this->inject_min_late = apply_filters('autoptimize_filter_js_inject_min_late',true);

        // filters to override hardcoded do(nt)move(last) array contents (array in, array out!)
        $this->dontmove = apply_filters( 'autoptimize_filter_js_dontmove', $this->dontmove );        
        $this->domovelast = apply_filters( 'autoptimize_filter_js_movelast', $this->domovelast );
        $this->domove = apply_filters( 'autoptimize_filter_js_domove', $this->domove );

        // get extra exclusions settings or filter
        $excludeJS = $options['js_exclude'];
        $excludeJS = apply_filters( 'autoptimize_filter_js_exclude', $excludeJS, $this->content );
        if ($excludeJS!=="") {
            if (is_array($excludeJS)) {
                if(($removeKeys = array_keys($excludeJS,"remove")) !== false) {
                    foreach ($removeKeys as $removeKey) {
                        unset($excludeJS[$removeKey]);
                        $this->jsremovables[]=$removeKey;
                    }
                }
                $exclJSArr = array_keys($excludeJS);
            } else {
                $exclJSArr = array_filter(array_map('trim',explode(",",$excludeJS)));
            }
            $this->dontmove = array_merge($exclJSArr,$this->dontmove);
        }

        //Should we add try-catch?
        if($options['trycatch'] == true)
            $this->trycatch = true;

        // force js in head?    
        if($options['forcehead'] == true) {
            $this->forcehead = true;
        } else {
            $this->forcehead = false;
        }
        $this->forcehead = apply_filters( 'autoptimize_filter_js_forcehead', $this->forcehead );

        // get cdn url
        $this->cdn_url = $options['cdn_url'];
            
        // noptimize me
        $this->content = $this->hide_noptimize($this->content);

        // Save IE hacks
        $this->content = $this->hide_iehacks($this->content);

        // comments
        $this->content = $this->hide_comments($this->content);

        // Get script files
        if (preg_match_all('#<script.*</script>#Usmi',$this->content,$matches)) {
            foreach($matches[0] as $tag) {
                // only consider script aggregation for types whitelisted in should_aggregate-function
                if( !$this->should_aggregate($tag) ) {
                    $tag='';
                    continue;
                }

                if (preg_match('#<script[^>]*src=("|\')([^>]*)("|\')#Usmi',$tag,$source)) {
                    // non-inline script
                    if ($this->isremovable($tag,$this->jsremovables)) {
                        $this->content = str_replace($tag,'',$this->content);
                        continue;
                    }
                    $explUrl = explode('?',$source[2],2);
                    $url = $explUrl[0];
                    $path = $this->getpath($url);
                    if($path !== false && preg_match('#\.js$#',$path) && $this->ismergeable($tag)) {
                        // ok to optimize, add to array
                        $this->scripts[] = $path;
                    } else {
                        $origTag = $tag;
                        $newTag = $tag;
                        
                        // non-mergeable script (excluded or dynamic or external)
                        if (is_array($excludeJS)) {
                            // should we add flags?
                            foreach ($excludeJS as $exclTag => $exclFlags) {
                                if ( strpos($origTag,$exclTag)!==false && in_array($exclFlags,array("async","defer")) ) {
                                   $newTag = str_replace('<script ','<script '.$exclFlags.' ',$newTag);
                                }
                            }
                        }
                        
   						// should we minify the non-aggregated script?
						if ($path && apply_filters('autoptimize_filter_js_minify_excluded',false)) {
							$_CachedMinifiedUrl = $this->minify_single($path);

							// replace orig URL with minified URL from cache if so
							if (!empty($_CachedMinifiedUrl)) {
								$newTag = str_replace($url, $_CachedMinifiedUrl, $newTag);
							}
							
							// remove querystring from URL in newTag
							if ( !empty($explUrl[1]) ) {
								$newTag = str_replace("?".$explUrl[1],"",$newTag);
							}
						}

						// should we move the non-aggregated script?
                        if( $this->ismovable($newTag) ) {
                            // can be moved, flags and all
                            if( $this->movetolast($newTag) )    {
                                $this->move['last'][] = $newTag;
                            } else {
                                $this->move['first'][] = $newTag;
                            }
                        } else {
                            // cannot be moved, so if flag was added re-inject altered tag immediately
                            if ( $origTag !== $newTag ) {
                                $this->content = str_replace($origTag,$newTag,$this->content);
                                $origTag = '';
                            }
                            // and forget about the $tag (not to be touched any more)
                            $tag = '';
                        }
                    }
                } else {
                    // Inline script
                    if ($this->isremovable($tag,$this->jsremovables)) {
                        $this->content = str_replace($tag,'',$this->content);
                        continue;
                    }
                    
                    // unhide comments, as javascript may be wrapped in comment-tags for old times' sake
                    $tag = $this->restore_comments($tag);
                    if($this->ismergeable($tag) && ( $this->include_inline )) {
                        preg_match('#<script.*>(.*)</script>#Usmi',$tag,$code);
                        $code = preg_replace('#.*<!\[CDATA\[(?:\s*\*/)?(.*)(?://|/\*)\s*?\]\]>.*#sm','$1',$code[1]);
                        $code = preg_replace('/(?:^\\s*<!--\\s*|\\s*(?:\\/\\/)?\\s*-->\\s*$)/','',$code);
                        $this->scripts[] = 'INLINE;'.$code;
                    } else {
                        // Can we move this?
                        $autoptimize_js_moveable = apply_filters( 'autoptimize_js_moveable', '', $tag );
                        if( $this->ismovable($tag) || $autoptimize_js_moveable !== '' ) {
                            if( $this->movetolast($tag) || $autoptimize_js_moveable === 'last' ) {
                                $this->move['last'][] = $tag;
                            } else {
                                $this->move['first'][] = $tag;
                            }
                        } else {
                            //We shouldn't touch this
                            $tag = '';
                        }
                    }
                    // re-hide comments to be able to do the removal based on tag from $this->content
                    $tag = $this->hide_comments($tag);
                }
                
                //Remove the original script tag
                $this->content = str_replace($tag,'',$this->content);
            }
            
            return true;
        }
    
        // No script files, great ;-)
        return false;
    }
    
    //Joins and optimizes JS
    public function minify() {
        foreach($this->scripts as $script) {
            if(preg_match('#^INLINE;#',$script)) {
                //Inline script
                $script = preg_replace('#^INLINE;#','',$script);
                $script = rtrim( $script, ";\n\t\r" ) . ';';
                //Add try-catch?
                if($this->trycatch) {
                    $script = 'try{'.$script.'}catch(e){}';
                }
                $tmpscript = apply_filters( 'autoptimize_js_individual_script', $script, '' );
                if ( has_filter('autoptimize_js_individual_script') && !empty($tmpscript) ) {
                    $script=$tmpscript;
                    $this->alreadyminified=true;
                }
                $this->jscode .= "\n" . $script;
            } else {
                //External script
                if($script !== false && file_exists($script) && is_readable($script)) {
                    $scriptsrc = file_get_contents($script);
                    $scripthash = md5($scriptsrc);
                    $scriptsrc = preg_replace('/\x{EF}\x{BB}\x{BF}/','',$scriptsrc);
                    $scriptsrc = rtrim($scriptsrc,";\n\t\r").';';

                    //Add try-catch?
                    if($this->trycatch) {
                        $scriptsrc = 'try{'.$scriptsrc.'}catch(e){}';
                    }
                    $tmpscriptsrc = apply_filters( 'autoptimize_js_individual_script', $scriptsrc, $script );
                    if ( has_filter('autoptimize_js_individual_script') && !empty($tmpscriptsrc) ) {
                        $scriptsrc=$tmpscriptsrc;
                        $this->alreadyminified=true;
                    } else if ($this->can_inject_late($script)) {
                        $scriptsrc="/*!%%INJECTLATER".AUTOPTIMIZE_HASH."%%".base64_encode($script)."|".$scripthash."%%INJECTLATER%%*/";
                    }
                    $this->jscode .= "\n".$scriptsrc;
                }/*else{
                    //Couldn't read JS. Maybe getpath isn't working?
                }*/
            }
        }

        //Check for already-minified code
        $this->md5hash = md5($this->jscode);
        $ccheck = new autoptimizeCache($this->md5hash,'js');
        if($ccheck->check()) {
            $this->jscode = $ccheck->retrieve();
            return true;
        }
        unset($ccheck);
        
        //$this->jscode has all the uncompressed code now.
        if ($this->alreadyminified!==true) {
          if (class_exists('JSMin') && apply_filters( 'autoptimize_js_do_minify' , true)) {
            if (@is_callable(array("JSMin","minify"))) {
                $tmp_jscode = trim(JSMin::minify($this->jscode));
                if (!empty($tmp_jscode)) {
                    $this->jscode = $tmp_jscode;
                    unset($tmp_jscode);
                }
                $this->jscode = $this->inject_minified($this->jscode);
                $this->jscode = apply_filters( 'autoptimize_js_after_minify', $this->jscode );
                return true;
            } else {
                $this->jscode = $this->inject_minified($this->jscode);
                return false;
            }
          } else {
              $this->jscode = $this->inject_minified($this->jscode);
              return false;
          }
        }
        $this->jscode = apply_filters( 'autoptimize_js_after_minify', $this->jscode );
        return true;
    }
    
    //Caches the JS in uncompressed, deflated and gzipped form.
    public function cache()    {
        $cache = new autoptimizeCache($this->md5hash,'js');
        if(!$cache->check()) {
            //Cache our code
            $cache->cache($this->jscode,'text/javascript');
        }
        $this->url = AUTOPTIMIZE_CACHE_URL.$cache->getname();
        $this->url = $this->url_replace_cdn($this->url);
    }
    
    // Returns the content
    public function getcontent() {
        // Restore the full content
        if(!empty($this->restofcontent)) {
            $this->content .= $this->restofcontent;
            $this->restofcontent = '';
        }
        
        // Add the scripts taking forcehead/ deferred (default) into account
        if($this->forcehead == true) {
            $replaceTag=array("</head>","before");
            $defer="";
        } else {
            $replaceTag=array("</body>","before");
            $defer="defer ";
        }
        
        $defer = apply_filters( 'autoptimize_filter_js_defer', $defer );
        $bodyreplacementpayload = '<script type="text/javascript" '.$defer.'src="'.$this->url.'"></script>';
        $bodyreplacementpayload = apply_filters('autoptimize_filter_js_bodyreplacementpayload',$bodyreplacementpayload);

        $bodyreplacement = implode('',$this->move['first']);
        $bodyreplacement .= $bodyreplacementpayload;
        $bodyreplacement .= implode('',$this->move['last']);

        $replaceTag = apply_filters( 'autoptimize_filter_js_replacetag', $replaceTag );

        if (strlen($this->jscode)>0) {
            $this->inject_in_html($bodyreplacement,$replaceTag);
        }
        
        // restore comments
        $this->content = $this->restore_comments($this->content);

        // Restore IE hacks
        $this->content = $this->restore_iehacks($this->content);
        
        // Restore noptimize
        $this->content = $this->restore_noptimize($this->content);

        // Return the modified HTML
        return $this->content;
    }
    
    // Checks against the white- and blacklists
    private function ismergeable($tag) {
		if (apply_filters('autoptimize_filter_js_dontaggregate',false)) {
			return false;
        } else if (!empty($this->whitelist)) {
            foreach ($this->whitelist as $match) {
                if(strpos($tag,$match)!==false) {
                    return true;
                }
            }
            // no match with whitelist
            return false;
        } else {
            foreach($this->domove as $match) {
                if(strpos($tag,$match)!==false)    {
                    // Matched something
                    return false;
                }
            }
            
            if ($this->movetolast($tag)) {
                return false;
            }
            
            foreach($this->dontmove as $match) {
                if(strpos($tag,$match)!==false)    {
                    //Matched something
                    return false;
                }
            }
            
            // If we're here it's safe to merge
            return true;
        }
    }
    
    // Checks againstt the blacklist
    private function ismovable($tag) {
        if ($this->include_inline !== true || apply_filters('autoptimize_filter_js_unmovable',true)) {
            return false;
        }
        
        foreach($this->domove as $match) {
            if(strpos($tag,$match)!==false)    {
                // Matched something
                return true;
            }
        }
        
        if ($this->movetolast($tag)) {
            return true;
        }
        
        foreach($this->dontmove as $match) {
            if(strpos($tag,$match)!==false) {
                // Matched something
                return false;
            }
        }
        
        // If we're here it's safe to move
        return true;
    }
    
    private function movetolast($tag) {
        foreach($this->domovelast as $match) {
            if(strpos($tag,$match)!==false)    {
                // Matched, return true
                return true;
            }
        }
        
        // Should be in 'first'
        return false;
    }
    
    /**
     * Determines wheter a <script> $tag should be aggregated or not.
     *
     * We consider these as "aggregation-safe" currently:
     * - script tags without a `type` attribute
     * - script tags with an explicit `type` of `text/javascript`, 'text/ecmascript', 
     *   'application/javascript' or 'application/ecmascript'
     *
     * Everything else should return false.
     *
     * @param string $tag
     * @return bool
     * 
     * original function by https://github.com/zytzagoo/ on his AO fork, thanks Tomas!
     */
    public function should_aggregate($tag) {
        preg_match('#<(script[^>]*)>#i',$tag,$scripttag);
        if ( strpos($scripttag[1], 'type')===false ) {
            return true;
        } else if ( preg_match('/type\s*=\s*["\']?(?:text|application)\/(?:javascript|ecmascript)["\']?/i', $scripttag[1]) ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Determines wheter a <script> $tag can be excluded from minification (as already minified) based on:
     * - inject_min_late being active
     * - filename ending in `min.js`
     * - filename matching `js/jquery/jquery.js` (wordpress core jquery, is minified)
     * - filename matching one passed in the consider minified filter
     * 
     * @param string $jsPath
     * @return bool
	 */
	private function can_inject_late($jsPath) {
		$consider_minified_array = apply_filters('autoptimize_filter_js_consider_minified',false);
        if ( $this->inject_min_late !== true ) {
            // late-inject turned off
            return false;
        } else if ( (strpos($jsPath,"min.js") === false) && ( strpos($jsPath,"wp-includes/js/jquery/jquery.js") === false ) && ( str_replace($consider_minified_array, '', $jsPath) === $jsPath ) ) {
			// file not minified based on filename & filter
			return false;
        } else {
            // phew, all is safe, we can late-inject
            return true;
        }
    }
}
