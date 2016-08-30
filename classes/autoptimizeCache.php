<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class autoptimizeCache {
    private $filename;
    private $mime;
    private $cachedir;
    private $delayed;
    
    public function __construct($md5,$ext='php') {
        $this->cachedir = AUTOPTIMIZE_CACHE_DIR;
        $this->delayed = AUTOPTIMIZE_CACHE_DELAY;
        $this->nogzip = AUTOPTIMIZE_CACHE_NOGZIP;
        if($this->nogzip == false) {
            $this->filename = AUTOPTIMIZE_CACHEFILE_PREFIX.$md5.'.php';
        } else {
            if (in_array($ext, array("js","css")))     {
                $this->filename = $ext.'/'.AUTOPTIMIZE_CACHEFILE_PREFIX.$md5.'.'.$ext;
            } else {
                $this->filename = AUTOPTIMIZE_CACHEFILE_PREFIX.$md5.'.'.$ext;
            }
        }
    }
    
    public function check() {
        if(!file_exists($this->cachedir.$this->filename)) {
            // No cached file, sorry
            return false;
        }
        // Cache exists!
        return true;
    }
    
    public function retrieve() {
        if($this->check()) {
            if($this->nogzip == false) {
                return file_get_contents($this->cachedir.$this->filename.'.none');
            } else {
                return file_get_contents($this->cachedir.$this->filename);
            }
        }
        return false;
    }
    
    public function cache($code,$mime) {
        if($this->nogzip == false) {
            $file = ($this->delayed ? 'delayed.php' : 'default.php');
            $phpcode = file_get_contents(AUTOPTIMIZE_PLUGIN_DIR.'/config/'.$file);
            $phpcode = str_replace(array('%%CONTENT%%','exit;'),array($mime,''),$phpcode);
            file_put_contents($this->cachedir.$this->filename,$phpcode, LOCK_EX);
            file_put_contents($this->cachedir.$this->filename.'.none',$code, LOCK_EX);
            if(!$this->delayed) {
                // Compress now!
                file_put_contents($this->cachedir.$this->filename.'.deflate',gzencode($code,9,FORCE_DEFLATE), LOCK_EX);
                file_put_contents($this->cachedir.$this->filename.'.gzip',gzencode($code,9,FORCE_GZIP), LOCK_EX);
            }
        } else {
            // Write code to cache without doing anything else
            file_put_contents($this->cachedir.$this->filename,$code, LOCK_EX);
            if (apply_filters('autoptimize_filter_cache_create_static_gzip', false)) {
                // Create an additional cached gzip file
                file_put_contents($this->cachedir.$this->filename.'.gzip',gzencode($code,9,FORCE_GZIP), LOCK_EX);
            }
        }
    }
    
    public function getname() {
        apply_filters('autoptimize_filter_cache_getname',AUTOPTIMIZE_CACHE_URL.$this->filename);
        return $this->filename;
    }
    
    static function clearall() {
        if(!autoptimizeCache::cacheavail()) {
            return false;
        }
    
        // scan the cachedirs        
        foreach (array("","js","css") as $scandirName) {
            $scan[$scandirName] = scandir(AUTOPTIMIZE_CACHE_DIR.$scandirName);
        }
        
        // clear the cachedirs
        foreach ($scan as $scandirName=>$scanneddir) {
            $thisAoCacheDir=rtrim(AUTOPTIMIZE_CACHE_DIR.$scandirName,"/")."/";
            foreach($scanneddir as $file) {
                if(!in_array($file,array('.','..')) && strpos($file,AUTOPTIMIZE_CACHEFILE_PREFIX) !== false && is_file($thisAoCacheDir.$file)) {
                    @unlink($thisAoCacheDir.$file);
                }
            }
        }

        @unlink(AUTOPTIMIZE_CACHE_DIR."/.htaccess");
        delete_transient("autoptimize_stats");

        // add cachepurged action 
        if (!function_exists('autoptimize_do_cachepurged_action')) {
            function autoptimize_do_cachepurged_action() {
                do_action("autoptimize_action_cachepurged");
            }
        }
        add_action("shutdown","autoptimize_do_cachepurged_action",11);
        
        // try to purge caching plugins cache-files?
        include_once(AUTOPTIMIZE_PLUGIN_DIR.'classlesses/autoptimizePageCacheFlush.php');
        add_action("autoptimize_action_cachepurged","autoptimize_flush_pagecache",10,0);

        return true;
    }

    static function stats()    {
        $AOstats=get_transient("autoptimize_stats");

        if (empty($AOstats)) {
            // Cache not available :(
            if(!autoptimizeCache::cacheavail()) {
                return 0;
            }
            
            // Count cached info
            $count = 0;
            $size = 0;
            
            // scan the cachedirs        
            foreach (array("","js","css") as $scandirName) {
                $scan[$scandirName] = scandir(AUTOPTIMIZE_CACHE_DIR.$scandirName);
            }
            
            foreach ($scan as $scandirName=>$scanneddir) {
                $thisAoCacheDir=rtrim(AUTOPTIMIZE_CACHE_DIR.$scandirName,"/")."/";
                foreach($scanneddir as $file) {
                    if(!in_array($file,array('.','..')) && strpos($file,AUTOPTIMIZE_CACHEFILE_PREFIX) !== false) {
                        if(is_file($thisAoCacheDir.$file)) {
                            if(AUTOPTIMIZE_CACHE_NOGZIP && (strpos($file,'.js') !== false || strpos($file,'.css') !== false || strpos($file,'.img') !== false || strpos($file,'.txt') !== false )) {
                                $count++;
                            } elseif(!AUTOPTIMIZE_CACHE_NOGZIP && strpos($file,'.none') !== false) {
                                $count++;
                            }
                            $size+=filesize($thisAoCacheDir.$file);
                        }
                    }
                }
            }
            $AOstats=array($count,$size,time());
            if ($count>100) {
                set_transient("autoptimize_stats",$AOstats,HOUR_IN_SECONDS);
            }
        }
        // print the number of instances
        return $AOstats;
    }    

    static function cacheavail() {
        if(!defined('AUTOPTIMIZE_CACHE_DIR')) {
            // We didn't set a cache
            return false;
        }
        
        foreach (array("","js","css") as $checkDir) {
            if(!autoptimizeCache::checkCacheDir(AUTOPTIMIZE_CACHE_DIR.$checkDir)) {
                return false;
            }
        }
        
        /** write index.html here to avoid prying eyes */
        $indexFile=AUTOPTIMIZE_CACHE_DIR.'/index.html';
        if(!is_file($indexFile)) {
            @file_put_contents($indexFile,'<html><head><meta name="robots" content="noindex, nofollow"></head><body>Generated by <a href="http://wordpress.org/extend/plugins/autoptimize/" rel="nofollow">Autoptimize</a></body></html>');
        }

        /** write .htaccess here to overrule wp_super_cache */
        $htAccess=AUTOPTIMIZE_CACHE_DIR.'/.htaccess';
        if(!is_file($htAccess)) {
            /** 
             * create wp-content/AO_htaccess_tmpl with 
             * whatever htaccess rules you might need
             * if you want to override default AO htaccess
             */
            $htaccess_tmpl=WP_CONTENT_DIR."/AO_htaccess_tmpl";
            if (is_file($htaccess_tmpl)) { 
                $htAccessContent=file_get_contents($htaccess_tmpl);
            } else if (is_multisite() || AUTOPTIMIZE_CACHE_NOGZIP == false) {
                $htAccessContent='<IfModule mod_headers.c>
        Header set Vary "Accept-Encoding"
        Header set Cache-Control "max-age=10672000, must-revalidate"
</IfModule>
<IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType text/css A30672000
        ExpiresByType text/javascript A30672000
        ExpiresByType application/javascript A30672000
</IfModule>
<IfModule mod_deflate.c>
        <FilesMatch "\.(js|css)$">
        SetOutputFilter DEFLATE
    </FilesMatch>
</IfModule>
<IfModule mod_authz_core.c>
    <Files *.php>
        Require all granted
    </Files>
</IfModule>
<IfModule !mod_authz_core.c>
    <Files *.php>
        Order allow,deny
        Allow from all
    </Files>
</IfModule>';
            } else {
                $htAccessContent='<IfModule mod_headers.c>
        Header set Vary "Accept-Encoding"
        Header set Cache-Control "max-age=10672000, must-revalidate"
</IfModule>
<IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType text/css A30672000
        ExpiresByType text/javascript A30672000
        ExpiresByType application/javascript A30672000
</IfModule>
<IfModule mod_deflate.c>
    <FilesMatch "\.(js|css)$">
        SetOutputFilter DEFLATE
    </FilesMatch>
</IfModule>
<IfModule mod_authz_core.c>
    <Files *.php>
        Require all denied
    </Files>
</IfModule>
<IfModule !mod_authz_core.c>
    <Files *.php>
        Order deny,allow
        Deny from all
    </Files>
</IfModule>';
            }
            @file_put_contents($htAccess,$htAccessContent);
        }

        // All OK
        return true;
    }

    static function checkCacheDir($dir) {
        // Check and create if not exists
        if(!file_exists($dir))    {
            @mkdir($dir,0775,true);
            if(!file_exists($dir))    {
                return false;
            }
        }

        // check if we can now write
        if(!is_writable($dir))    {
            return false;
        }

        // and write index.html here to avoid prying eyes
        $indexFile=$dir.'/index.html';
        if(!is_file($indexFile)) {
            @file_put_contents($indexFile,'<html><head><meta name="robots" content="noindex, nofollow"></head><body>Generated by <a href="http://wordpress.org/extend/plugins/autoptimize/" rel="nofollow">Autoptimize</a></body></html>');
        }
        
        return true;
    }
}
