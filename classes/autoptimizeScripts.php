<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class autoptimizeScripts extends autoptimizeBase {
	private $scripts = array();
	private $dontmove = array('document.write','html5.js','show_ads.js','google_ad','blogcatalog.com/w','tweetmeme.com/i','mybloglog.com/','histats.com/js','ads.smowtion.com/ad.js','statcounter.com/counter/counter.js','widgets.amung.us','ws.amazon.com/widgets','media.fastclick.net','/ads/','comment-form-quicktags/quicktags.php','edToolbar','intensedebate.com','scripts.chitika.net/','_gaq.push','jotform.com/','admin-bar.min.js','GoogleAnalyticsObject','plupload.full.min.js','syntaxhighlighter','adsbygoogle','application/ld+json');
	private $domove = array('gaJsHost','load_cmc','jd.gallery.transitions.js','swfobject.embedSWF(','tiny_mce.js','tinyMCEPreInit.go');
	private $domovelast = array('addthis.com','/afsonline/show_afs_search.js','disqus.js','networkedblogs.com/getnetworkwidget','infolinks.com/js/','jd.gallery.js.php','jd.gallery.transitions.js','swfobject.embedSWF(','linkwithin.com/widget.js','tiny_mce.js','tinyMCEPreInit.go');
	private $trycatch = false;
	private $alreadyminified = false;
	private $forcehead = false;
	private $jscode = '';
	private $url = '';
	private $move = array('first' => array(), 'last' => array());
	private $restofcontent = '';
	private $md5hash = '';
	
	//Reads the page and collects script tags
	public function read($options) {
		//Remove everything that's not the header
		if($options['justhead'] == true) {
			$content = explode('</head>',$this->content,2);
			$this->content = $content[0].'</head>';
			$this->restofcontent = $content[1];
		}

		$excludeJS = $options['js_exclude'];
		$excludeJS = apply_filters( 'autoptimize_filter_js_exclude', $excludeJS );
		
		if ($excludeJS!=="") {
			$exclJSArr = array_filter(array_map('trim',explode(",",$excludeJS)));
			$this->dontmove = array_merge($exclJSArr,$this->dontmove);
		}
		
		$this->domovelast = apply_filters( 'autoptimize_filter_js_movelast', $this->domovelast );
		
		//Should we add try-catch?
		if($options['trycatch'] == true)
			$this->trycatch = true;

		// force js in head?	
		if($options['forcehead'] == true)
			$this->forcehead = true;

		// get cdn url
		$this->cdn_url = $options['cdn_url'];
			
		// noptimize me
		$this->content = $this->hide_noptimize($this->content);

		// Save IE hacks
		$this->content = $this->hide_iehacks($this->content);

		// comments
		$this->content = $this->hide_comments($this->content);

		//Get script files
		if(preg_match_all('#<script.*</script>#Usmi',$this->content,$matches)) {
			foreach($matches[0] as $tag) {
				if(preg_match('#src=("|\')(.*)("|\')#Usmi',$tag,$source)) {
					//External script
					$url = current(explode('?',$source[2],2));
					$path = $this->getpath($url);
					if($path !== false && preg_match('#\.js$#',$path)) {
						//Inline
						if($this->ismergeable($tag)) {
							//We can merge it
							$this->scripts[] = $path;
						} else {
							//No merge, but maybe we can move it
							if($this->ismovable($tag)) {
								//Yeah, move it
								if($this->movetolast($tag)) {
									$this->move['last'][] = $tag;
								} else {
									$this->move['first'][] = $tag;
								}
							} else {
								//We shouldn't touch this
								$tag = '';
							}
						}
					} else {
						//External script (example: google analytics)
						//OR Script is dynamic (.php etc)
						if($this->ismovable($tag)) {
							if($this->movetolast($tag))	{
								$this->move['last'][] = $tag;
							} else {
								$this->move['first'][] = $tag;
							}
						} else {
							//We shouldn't touch this
							$tag = '';
						}
					}
				} else {
					// Inline script
					// unhide comments, as javascript may be wrapped in comment-tags for old times' sake
					$tag = $this->restore_comments($tag);
					if($this->ismergeable($tag) && ( apply_filters('autoptimize_js_include_inline',true) )) {
						preg_match('#<script.*>(.*)</script>#Usmi',$tag,$code);
						$code = preg_replace('#.*<!\[CDATA\[(?:\s*\*/)?(.*)(?://|/\*)\s*?\]\]>.*#sm','$1',$code[1]);
						$code = preg_replace('/(?:^\\s*<!--\\s*|\\s*(?:\\/\\/)?\\s*-->\\s*$)/','',$code);
						$this->scripts[] = 'INLINE;'.$code;
					} else {
						//Can we move this?
						if($this->ismovable($tag)) {
							if($this->movetolast($tag))	{
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
				$tmpscript = apply_filters( 'autoptimize_js_individual_script', $script, "" );
				if ($tmpscript!==$script && !empty($tmpscript)) {
					$script=$tmpscript;
					$this->alreadyminified=true;
				}
				$this->jscode .= "\n" . $script;
			} else {
				//External script
				if($script !== false && file_exists($script) && is_readable($script)) {
					$scriptsrc = file_get_contents($script);
					$scriptsrc = preg_replace('/\x{EF}\x{BB}\x{BF}/','',$scriptsrc);
					$scriptsrc = rtrim($scriptsrc,";\n\t\r").';';
					//Add try-catch?
					if($this->trycatch) {
						$scriptsrc = 'try{'.$scriptsrc.'}catch(e){}';
					}
					$tmpscriptsrc = apply_filters( 'autoptimize_js_individual_script', $scriptsrc, $script );
					if ($tmpscriptsrc!==$scriptsrc && !empty($tmpscriptsrc)) {
						$scriptsrc=$tmpscriptsrc;
						$this->alreadyminified=true;
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
			if (@is_callable(array(new JSMin,"minify"))) {
				$tmp_jscode = trim(JSMin::minify($this->jscode));
				$tmp_jscode = apply_filters( 'autoptimize_js_after_minify', $tmp_jscode );
				if (!empty($tmp_jscode)) {
					$this->jscode = $tmp_jscode;
					unset($tmp_jscode);
				}
				return true;
			} else {
				return false;
			}
		  } else {
			return false;
		  }
		}
		return true;
	}
	
	//Caches the JS in uncompressed, deflated and gzipped form.
	public function cache()
	{
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
			$replaceTag=array("</title>","after");
			$defer="";
		} else {
			$replaceTag=array("</body>","before");
			$defer="defer ";
		}
		
		$defer = apply_filters( 'autoptimize_filter_js_defer', $defer );
		$replaceTag = apply_filters( 'autoptimize_filter_js_replacetag', $replaceTag );

		$bodyreplacement = implode('',$this->move['first']);
		$bodyreplacement .= '<script type="text/javascript" '.$defer.'src="'.$this->url.'"></script>';
		$bodyreplacement .= implode('',$this->move['last']);

		$this->inject_in_html($bodyreplacement,$replaceTag);

		// restore comments
		$this->content = $this->restore_comments($this->content);

		// Restore IE hacks
		$this->content = $this->restore_iehacks($this->content);
		
		// Restore noptimize
		$this->content = $this->restore_noptimize($this->content);

		// Return the modified HTML
		return $this->content;
	}
	
	//Checks agains the whitelist
	private function ismergeable($tag) {
		foreach($this->domove as $match) {
			if(strpos($tag,$match)!==false)	{
				//Matched something
				return false;
			}
		}
		
		if ($this->movetolast($tag)) {
			return false;
			}
		
		foreach($this->dontmove as $match) {
			if(strpos($tag,$match)!==false)	{
				//Matched something
				return false;
			}
		}
		
		//If we're here it's safe to merge
		return true;
	}
	
	//Checks agains the blacklist
	private function ismovable($tag) {
		foreach($this->domove as $match) {
			if(strpos($tag,$match)!==false)	{
				//Matched something
				return true;
			}
		}
		
		if ($this->movetolast($tag)) {
			return true;
		}
		
		foreach($this->dontmove as $match) {
			if(strpos($tag,$match)!==false) {
				//Matched something
				return false;
			}
		}
		
		//If we're here it's safe to move
		return true;
	}
	
	private function movetolast($tag) {
		foreach($this->domovelast as $match) {
			if(strpos($tag,$match)!==false)	{
				//Matched, return true
				return true;
			}
		}
		
		//Should be in 'first'
		return false;
	}
}
