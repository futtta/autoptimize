<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class autoptimizeHTML extends autoptimizeBase {
    private $keepcomments = false;
    private $exclude = array('<!-- ngg_resource_manager_marker -->');
    
    public function read($options) {
        // Remove the HTML comments?
        $this->keepcomments = (bool) $options['keepcomments'];
        
        // filter to force xhtml
        $this->forcexhtml = (bool) apply_filters( 'autoptimize_filter_html_forcexhtml', false );
        
        // filter to add strings to be excluded from HTML minification
        $excludeHTML = apply_filters( 'autoptimize_filter_html_exclude','' );
        if ($excludeHTML!=="") {
            $exclHTMLArr = array_filter(array_map('trim',explode(",",$excludeHTML)));
            $this->exclude = array_merge($exclHTMLArr,$this->exclude);
        }
        
        // Nothing else for HTML
        return true;
    }
    
    //Joins and optimizes CSS
    public function minify() {
        $noptimizeHTML = apply_filters( 'autoptimize_filter_html_noptimize', false, $this->content );
        if ($noptimizeHTML)
            return false;
        
        if(class_exists('Minify_HTML')) {
            // wrap the to-be-excluded strings in noptimize tags
            foreach ($this->exclude as $exclString) {
                if (strpos($this->content,$exclString)!==false) {
                    $replString="<!--noptimize-->".$exclString."<!--/noptimize-->";
                    $this->content=str_replace($exclString,$replString,$this->content);
                }
            }

            // noptimize me
            $this->content = $this->hide_noptimize($this->content);

            // Minify html
            $options = array('keepComments' => $this->keepcomments);
            if ($this->forcexhtml) {
                $options['xhtml'] = true;
            }

            if (@is_callable(array("Minify_HTML","minify"))) {
                $tmp_content = Minify_HTML::minify($this->content,$options);
                if (!empty($tmp_content)) {
                    $this->content = $tmp_content;
                    unset($tmp_content);
                }
            }

            // restore noptimize
            $this->content = $this->restore_noptimize($this->content);
            
            // remove the noptimize-wrapper from around the excluded strings
            foreach ($this->exclude as $exclString) {
                $replString="<!--noptimize-->".$exclString."<!--/noptimize-->";
                if (strpos($this->content,$replString)!==false) {
                    $this->content=str_replace($replString,$exclString,$this->content);
                }
            }

            return true;
        }
        
        // Didn't minify :(
        return false;
    }
    
    // Does nothing
    public function cache() {
        //No cache for HTML
        return true;
    }
    
    //Returns the content
    public function getcontent() {
        return $this->content;
    }
}
