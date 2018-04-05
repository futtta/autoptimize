<?php

class AOTest extends WP_UnitTestcase
{
    /**
     * @var autoptimizeMain
     */
    protected $ao;

    /**
     * Normalizes EOLs into "\n" otherwise some tests fail due to simple newline
     * differences in the markup (depending on how/where it was entered/generated).
     * This can occasionally get even more complicated by git changing newlines
     * on checkout (if so configured).
     *
     * @param $str
     *
     * @return mixed
     */
    private function normalize_newlines($str)
    {
        return str_replace("\r\n", "\n", $str);
    }

    protected function getAoStylesDefaultOptions()
    {
        $conf = autoptimizeConfig::instance();

        return [
            'aggregate'      => $conf->get('autoptimize_css_aggregate'),
            'justhead'       => $conf->get('autoptimize_css_justhead'),
            'datauris'       => $conf->get('autoptimize_css_datauris'),
            'defer'          => $conf->get('autoptimize_css_defer'),
            'defer_inline'   => $conf->get('autoptimize_css_defer_inline'),
            'inline'         => $conf->get('autoptimize_css_inline'),
            'css_exclude'    => $conf->get('autoptimize_css_exclude'),
            'cdn_url'        => $conf->get('autoptimize_cdn_url'),
            'include_inline' => $conf->get('autoptimize_css_include_inline'),
            'nogooglefont'   => $conf->get('autoptimize_css_nogooglefont')
        ];
    }

    protected function getAoScriptsDefaultOptions()
    {
        $conf = autoptimizeConfig::instance();

        return [
            'aggregate'      => $conf->get( 'autoptimize_js_aggregate' ),
            'justhead'       => $conf->get( 'autoptimize_js_justhead' ),
            'forcehead'      => $conf->get( 'autoptimize_js_forcehead' ),
            'trycatch'       => $conf->get( 'autoptimize_js_trycatch' ),
            'js_exclude'     => $conf->get( 'autoptimize_js_exclude' ),
            'cdn_url'        => $conf->get( 'autoptimize_cdn_url' ),
            'include_inline' => $conf->get( 'autoptimize_js_include_inline' ),
        ];
    }

    public function setUp()
    {
        $this->ao = new autoptimizeMain( AUTOPTIMIZE_PLUGIN_VERSION, AUTOPTIMIZE_PLUGIN_FILE );

        parent::setUp();
    }

    // Runs after each test method
    public function tearDown()
    {
        // Making sure certain filters are removed after each test to ensure isolation
        $filter_tags = array(
            'autoptimize_filter_noptimize',
            'autoptimize_filter_base_cdnurl',
            'autoptimize_filter_css_is_datauri_candidate',
            'autoptimize_filter_css_datauri_image',
            'autoptimize_filter_css_inlinesize',
            'autoptimize_filter_css_fonts_cdn'
        );
        foreach ( $filter_tags as $filter_tag ) {
            remove_all_filters( $filter_tag );
        }

        parent::tearDown();
    }

    const TEST_MARKUP = <<<MARKUP
<!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8 lt-ie7"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 8]> <html class="no-svg no-js lt-ie9"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-svg no-js"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <!--<![endif]-->
<head>
<meta charset="utf-8">
<title>Mliječna juha od brokule &#9832; Kuhaj.hr</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style type="text/css">
/* cdn rewrite tests */

.bg { background:url('img/something.svg'); }
.bg-no-quote { background: url(img/something.svg); }
.bg-double-quotes { background: url("img/something.svg"); }

.whitespaces { background : url   (  "../../somewhere-else/svg.svg" ) ; }

.host-relative { background: url("/img/something.svg"); }
.protocol-relative { background: url("//something/somewhere/example.png"); }

/* roboto-100 - latin-ext_latin */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('../fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('../fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('../fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
/* roboto-300 - latin-ext_latin */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 300;
  src: url('../fonts/roboto-v15-latin-ext_latin-300.eot'); /* IE9 Compat Modes */
  src: local('Roboto Light'), local('Roboto-Light'),
       url('../fonts/roboto-v15-latin-ext_latin-300.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('../fonts/roboto-v15-latin-ext_latin-300.woff2') format('woff2'), /* Super Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-300.woff') format('woff'), /* Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-300.ttf') format('truetype'), /* Safari, Android, iOS */
       url('../fonts/roboto-v15-latin-ext_latin-300.svg#Roboto') format('svg'); /* Legacy iOS */
}
/* roboto-regular - latin-ext_latin */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  src: url('../fonts/roboto-v15-latin-ext_latin-regular.eot'); /* IE9 Compat Modes */
  src: local('Roboto'), local('Roboto-Regular'),
       url('../fonts/roboto-v15-latin-ext_latin-regular.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('../fonts/roboto-v15-latin-ext_latin-regular.woff2') format('woff2'), /* Super Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-regular.woff') format('woff'), /* Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-regular.ttf') format('truetype'), /* Safari, Android, iOS */
       url('../fonts/roboto-v15-latin-ext_latin-regular.svg#Roboto') format('svg'); /* Legacy iOS */
}
/* roboto-500 - latin-ext_latin */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 500;
  src: url('../fonts/roboto-v15-latin-ext_latin-500.eot'); /* IE9 Compat Modes */
  src: local('Roboto Medium'), local('Roboto-Medium'),
       url('../fonts/roboto-v15-latin-ext_latin-500.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('../fonts/roboto-v15-latin-ext_latin-500.woff2') format('woff2'), /* Super Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-500.woff') format('woff'), /* Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-500.ttf') format('truetype'), /* Safari, Android, iOS */
       url('../fonts/roboto-v15-latin-ext_latin-500.svg#Roboto') format('svg'); /* Legacy iOS */
}
</style>
    <!--[if lt IE 9]>
    <script src="http://example.org/wp-content/themes/my-theme/js/vendor/html5shiv-printshiv.min.js" type="text/javascript"></script>
    <![endif]-->
    <!--[if (gte IE 6)&(lte IE 8)]>
        <script type="text/javascript" src="http://example.org/wp-content/themes/my-theme/js/vendor/respond.min.js"></script>
    <![endif]-->
</head>

<body class="single single-post">

    <div id="fb-root"></div>
    <script>(function(d, s, id) {
      var js, fjs = d.getElementsByTagName(s)[0];
      if (d.getElementById(id)) return;
      js = d.createElement(s); js.id = id;
      js.src = "//connect.facebook.net/hr_HR/sdk.js#version=v2.0&xfbml=1&appId=";
      fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));</script>
    </script>

<script type='text/javascript' src='http://example.org/wp-content/plugins/ajax-load-more/core/js/ajax-load-more.min.js?ver=1.1'></script>
<script type='text/javascript' src='http://example.org/wp-content/plugins/wp-ga-social-tracking-js/ga-social-tracking.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/alm-seo.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/jquery.placeholder-2.1.1.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/typeahead.bundle.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/bootstrap-tagsinput.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/m-mobilemenu.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/main.js'></script>
<script type='text/javascript' src='http://example.org/wp-includes/js/comment-reply.min.js?ver=4.1.1'></script>
</body>
</html>
MARKUP;

    const TEST_MARKUP_OUTPUT = <<<MARKUP
<!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8 lt-ie7"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 8]> <html class="no-svg no-js lt-ie9"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-svg no-js"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <!--<![endif]-->
<head>
<meta charset="utf-8">
<link type="text/css" media="all" href="http://cdn.example.org/wp-content/cache/autoptimize/css/autoptimize_863f587e89f100b0223ddccc0dabc57a.css" rel="stylesheet" /><title>Mliječna juha od brokule &#9832; Kuhaj.hr</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

    <!--[if lt IE 9]>
    <script src="http://example.org/wp-content/themes/my-theme/js/vendor/html5shiv-printshiv.min.js" type="text/javascript"></script>
    <![endif]-->
    <!--[if (gte IE 6)&(lte IE 8)]>
        <script type="text/javascript" src="http://example.org/wp-content/themes/my-theme/js/vendor/respond.min.js"></script>
    <![endif]-->
</head>

<body class="single single-post">

    <div id="fb-root"></div>
    <script>(function(d, s, id) {
      var js, fjs = d.getElementsByTagName(s)[0];
      if (d.getElementById(id)) return;
      js = d.createElement(s); js.id = id;
      js.src = "//connect.facebook.net/hr_HR/sdk.js#version=v2.0&xfbml=1&appId=";
      fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));</script>
    </script>

<script type='text/javascript' src='http://example.org/wp-content/plugins/ajax-load-more/core/js/ajax-load-more.min.js?ver=1.1'></script>
<script type='text/javascript' src='http://example.org/wp-content/plugins/wp-ga-social-tracking-js/ga-social-tracking.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/alm-seo.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/jquery.placeholder-2.1.1.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/typeahead.bundle.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/bootstrap-tagsinput.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/m-mobilemenu.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/main.js'></script>

<script type="text/javascript" defer src="http://cdn.example.org/wp-content/cache/autoptimize/js/autoptimize_730dfe55780a3a6fc98224e18fa27340.js"></script></body>
</html>
MARKUP;

    // When `is_multisite()` returns true, default path to files is different
    const TEST_MARKUP_OUTPUT_MS = <<<MARKUP
<!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8 lt-ie7"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 8]> <html class="no-svg no-js lt-ie9"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-svg no-js"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <!--<![endif]-->
<head>
<meta charset="utf-8">
<link type="text/css" media="all" href="http://cdn.example.org/wp-content/cache/autoptimize/1/css/autoptimize_863f587e89f100b0223ddccc0dabc57a.css" rel="stylesheet" /><title>Mliječna juha od brokule &#9832; Kuhaj.hr</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

    <!--[if lt IE 9]>
    <script src="http://example.org/wp-content/themes/my-theme/js/vendor/html5shiv-printshiv.min.js" type="text/javascript"></script>
    <![endif]-->
    <!--[if (gte IE 6)&(lte IE 8)]>
        <script type="text/javascript" src="http://example.org/wp-content/themes/my-theme/js/vendor/respond.min.js"></script>
    <![endif]-->
</head>

<body class="single single-post">

    <div id="fb-root"></div>
    <script>(function(d, s, id) {
      var js, fjs = d.getElementsByTagName(s)[0];
      if (d.getElementById(id)) return;
      js = d.createElement(s); js.id = id;
      js.src = "//connect.facebook.net/hr_HR/sdk.js#version=v2.0&xfbml=1&appId=";
      fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));</script>
    </script>

<script type='text/javascript' src='http://example.org/wp-content/plugins/ajax-load-more/core/js/ajax-load-more.min.js?ver=1.1'></script>
<script type='text/javascript' src='http://example.org/wp-content/plugins/wp-ga-social-tracking-js/ga-social-tracking.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/alm-seo.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/jquery.placeholder-2.1.1.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/typeahead.bundle.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/bootstrap-tagsinput.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/m-mobilemenu.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/main.js'></script>

<script type="text/javascript" defer src="http://cdn.example.org/wp-content/cache/autoptimize/1/js/autoptimize_730dfe55780a3a6fc98224e18fa27340.js"></script></body>
</html>
MARKUP;

    const TEST_MARKUP_OUTPUT_INLINE_DEFER = <<<MARKUP
<!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8 lt-ie7"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 8]> <html class="no-svg no-js lt-ie9"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-svg no-js"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <!--<![endif]-->
<head>
<meta charset="utf-8">
<style type="text/css" id="aoatfcss" media="all">1</style><link rel="preload" as="style" media="all" href="http://cdn.example.org/wp-content/cache/autoptimize/css/autoptimize_863f587e89f100b0223ddccc0dabc57a.css" onload="this.onload=null;this.rel='stylesheet'" /><noscript id="aonoscrcss"><link type="text/css" media="all" href="http://cdn.example.org/wp-content/cache/autoptimize/css/autoptimize_863f587e89f100b0223ddccc0dabc57a.css" rel="stylesheet" /></noscript><title>Mliječna juha od brokule &#9832; Kuhaj.hr</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

    <!--[if lt IE 9]>
    <script src="http://example.org/wp-content/themes/my-theme/js/vendor/html5shiv-printshiv.min.js" type="text/javascript"></script>
    <![endif]-->
    <!--[if (gte IE 6)&(lte IE 8)]>
        <script type="text/javascript" src="http://example.org/wp-content/themes/my-theme/js/vendor/respond.min.js"></script>
    <![endif]-->
</head>

<body class="single single-post">

    <div id="fb-root"></div>
    <script>(function(d, s, id) {
      var js, fjs = d.getElementsByTagName(s)[0];
      if (d.getElementById(id)) return;
      js = d.createElement(s); js.id = id;
      js.src = "//connect.facebook.net/hr_HR/sdk.js#version=v2.0&xfbml=1&appId=";
      fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));</script>
    </script>

<script type='text/javascript' src='http://example.org/wp-content/plugins/ajax-load-more/core/js/ajax-load-more.min.js?ver=1.1'></script>
<script type='text/javascript' src='http://example.org/wp-content/plugins/wp-ga-social-tracking-js/ga-social-tracking.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/alm-seo.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/jquery.placeholder-2.1.1.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/typeahead.bundle.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/bootstrap-tagsinput.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/m-mobilemenu.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/main.js'></script>

<script type="text/javascript" defer src="http://cdn.example.org/wp-content/cache/autoptimize/js/autoptimize_730dfe55780a3a6fc98224e18fa27340.js"></script><script data-cfasync='false'>!function(t){"use strict";t.loadCSS||(t.loadCSS=function(){});var e=loadCSS.relpreload={};if(e.support=function(){var e;try{e=t.document.createElement("link").relList.supports("preload")}catch(t){e=!1}return function(){return e}}(),e.bindMediaToggle=function(t){function e(){t.media=a}var a=t.media||"all";t.addEventListener?t.addEventListener("load",e):t.attachEvent&&t.attachEvent("onload",e),setTimeout(function(){t.rel="stylesheet",t.media="only x"}),setTimeout(e,3e3)},e.poly=function(){if(!e.support())for(var a=t.document.getElementsByTagName("link"),n=0;n<a.length;n++){var o=a[n];"preload"!==o.rel||"style"!==o.getAttribute("as")||o.getAttribute("data-loadcss")||(o.setAttribute("data-loadcss",!0),e.bindMediaToggle(o))}},!e.support()){e.poly();var a=t.setInterval(e.poly,500);t.addEventListener?t.addEventListener("load",function(){e.poly(),t.clearInterval(a)}):t.attachEvent&&t.attachEvent("onload",function(){e.poly(),t.clearInterval(a)})}"undefined"!=typeof exports?exports.loadCSS=loadCSS:t.loadCSS=loadCSS}("undefined"!=typeof global?global:this);</script></body>
</html>
MARKUP;

    const TEST_MARKUP_OUTPUT_INLINE_DEFER_MS = <<<MARKUP
<!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8 lt-ie7"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 8]> <html class="no-svg no-js lt-ie9"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-svg no-js"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <!--<![endif]-->
<head>
<meta charset="utf-8">
<style type="text/css" id="aoatfcss" media="all">1</style><link rel="preload" as="style" media="all" href="http://cdn.example.org/wp-content/cache/autoptimize/1/css/autoptimize_863f587e89f100b0223ddccc0dabc57a.css" onload="this.onload=null;this.rel='stylesheet'" /><noscript id="aonoscrcss"><link type="text/css" media="all" href="http://cdn.example.org/wp-content/cache/autoptimize/1/css/autoptimize_863f587e89f100b0223ddccc0dabc57a.css" rel="stylesheet" /></noscript><title>Mliječna juha od brokule &#9832; Kuhaj.hr</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

    <!--[if lt IE 9]>
    <script src="http://example.org/wp-content/themes/my-theme/js/vendor/html5shiv-printshiv.min.js" type="text/javascript"></script>
    <![endif]-->
    <!--[if (gte IE 6)&(lte IE 8)]>
        <script type="text/javascript" src="http://example.org/wp-content/themes/my-theme/js/vendor/respond.min.js"></script>
    <![endif]-->
</head>

<body class="single single-post">

    <div id="fb-root"></div>
    <script>(function(d, s, id) {
      var js, fjs = d.getElementsByTagName(s)[0];
      if (d.getElementById(id)) return;
      js = d.createElement(s); js.id = id;
      js.src = "//connect.facebook.net/hr_HR/sdk.js#version=v2.0&xfbml=1&appId=";
      fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));</script>
    </script>

<script type='text/javascript' src='http://example.org/wp-content/plugins/ajax-load-more/core/js/ajax-load-more.min.js?ver=1.1'></script>
<script type='text/javascript' src='http://example.org/wp-content/plugins/wp-ga-social-tracking-js/ga-social-tracking.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/alm-seo.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/jquery.placeholder-2.1.1.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/typeahead.bundle.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/bootstrap-tagsinput.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/m-mobilemenu.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/main.js'></script>

<script type="text/javascript" defer src="http://cdn.example.org/wp-content/cache/autoptimize/1/js/autoptimize_730dfe55780a3a6fc98224e18fa27340.js"></script><script data-cfasync='false'>!function(t){"use strict";t.loadCSS||(t.loadCSS=function(){});var e=loadCSS.relpreload={};if(e.support=function(){var e;try{e=t.document.createElement("link").relList.supports("preload")}catch(t){e=!1}return function(){return e}}(),e.bindMediaToggle=function(t){function e(){t.media=a}var a=t.media||"all";t.addEventListener?t.addEventListener("load",e):t.attachEvent&&t.attachEvent("onload",e),setTimeout(function(){t.rel="stylesheet",t.media="only x"}),setTimeout(e,3e3)},e.poly=function(){if(!e.support())for(var a=t.document.getElementsByTagName("link"),n=0;n<a.length;n++){var o=a[n];"preload"!==o.rel||"style"!==o.getAttribute("as")||o.getAttribute("data-loadcss")||(o.setAttribute("data-loadcss",!0),e.bindMediaToggle(o))}},!e.support()){e.poly();var a=t.setInterval(e.poly,500);t.addEventListener?t.addEventListener("load",function(){e.poly(),t.clearInterval(a)}):t.attachEvent&&t.attachEvent("onload",function(){e.poly(),t.clearInterval(a)})}"undefined"!=typeof exports?exports.loadCSS=loadCSS:t.loadCSS=loadCSS}("undefined"!=typeof global?global:this);</script></body>
</html>
MARKUP;

    /**
     * @dataProvider provider_test_rewrite_markup_with_cdn
     */
    function test_rewrite_markup_with_cdn($input, $expected)
    {
        $actual = $this->ao->end_buffering( $input );

        // $this->markTestIncomplete('Full-blown rewrite test currently doesn\'t work on Windows (or with any custom WP-tests setup/location really).');
        $this->assertEquals($expected, $actual);
    }

    public function provider_test_rewrite_markup_with_cdn()
    {
        return array(

            array(
                // input
                self::TEST_MARKUP,
                // expected output
                // TODO/FIXME: this seemed like the fastest way to get MS crude test to pass
                ( is_multisite() ? self::TEST_MARKUP_OUTPUT_MS : self::TEST_MARKUP_OUTPUT )
            ),

        );
    }

    public function test_rewrite_css_assets()
    {
        $css_in = <<<CSS
.bg { background:url('img/something.svg'); }
.bg-no-quote { background: url(img/something.svg); }
.bg-double-quotes { background: url("img/something.svg"); }

.whitespaces { background : url   (  "../../somewhere-else/svg.svg" ) ; }

.host-relative { background: url("/img/something.svg"); }
.protocol-relative { background: url("//something/somewhere/example.png"); }

@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('../fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('../fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('../fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
CSS;
        $css_expected = <<<CSS
.bg { background:url(img/something.svg); }
.bg-no-quote { background: url(img/something.svg); }
.bg-double-quotes { background: url(img/something.svg); }

.whitespaces { background : url   (  ../../somewhere-else/svg.svg) ; }

.host-relative { background: url(http://cdn.example.org/img/something.svg); }
.protocol-relative { background: url(//something/somewhere/example.png); }

@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('../fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('../fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('../fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
CSS;

        //add_filter( 'autoptimize_filter_css_fonts_cdn', '__return_true' );

        $instance = new autoptimizeStyles($css_in);
        $instance->setOption('cdn_url', 'http://cdn.example.org');

        $css_actual = $instance->rewrite_assets($css_in);

        $this->assertEquals($css_expected, $css_actual);
    }

    public function test_default_cssmin_minifier()
    {
        $css = <<<CSS
.bg { background:url('img/something.svg'); }
.bg-no-quote { background: url(img/something.svg); }
.bg-double-quotes { background: url("img/something.svg"); }

.whitespaces { background : url   (  "../../somewhere-else/svg.svg" ) ; }

.host-relative { background: url("/img/something.svg"); }
.protocol-relative { background: url("//something/somewhere/example.png"); }

/* roboto-100 - latin-ext_latin */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url(../fonts/roboto-v15-latin-ext_latin-100.eot); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url(../fonts/roboto-v15-latin-ext_latin-100.eot?#iefix) format('embedded-opentype'), /* IE6-IE8 */
       url(../fonts/roboto-v15-latin-ext_latin-100.woff2) format('woff2'), /* Super Modern Browsers */
       url(../fonts/roboto-v15-latin-ext_latin-100.woff) format('woff'), /* Modern Browsers */
       url(../fonts/roboto-v15-latin-ext_latin-100.ttf) format('truetype'), /* Safari, Android, iOS */
       url(../fonts/roboto-v15-latin-ext_latin-100.svg#Roboto) format('svg'); /* Legacy iOS */
}
CSS;

$expected = <<<CSS
.bg{background:url('img/something.svg')}.bg-no-quote{background:url(img/something.svg)}.bg-double-quotes{background:url("img/something.svg")}.whitespaces{background:url ("../../somewhere-else/svg.svg")}.host-relative{background:url("/img/something.svg")}.protocol-relative{background:url("//something/somewhere/example.png")}@font-face{font-family:'Roboto';font-style:normal;font-weight:100;src:url(../fonts/roboto-v15-latin-ext_latin-100.eot);src:local('Roboto Thin'),local('Roboto-Thin'),url(../fonts/roboto-v15-latin-ext_latin-100.eot?#iefix) format('embedded-opentype'),url(../fonts/roboto-v15-latin-ext_latin-100.woff2) format('woff2'),url(../fonts/roboto-v15-latin-ext_latin-100.woff) format('woff'),url(../fonts/roboto-v15-latin-ext_latin-100.ttf) format('truetype'),url(../fonts/roboto-v15-latin-ext_latin-100.svg#Roboto) format('svg')}
CSS;

        $instance = new autoptimizeStyles($css);
        $minified = $instance->run_minifier_on($css);

        $this->assertEquals($expected, $minified);
    }

    /**
     * @dataProvider provider_test_should_aggregate_script_types
     * @covers autoptimizeScripts::should_aggregate
     */
    public function test_should_aggregate_script_types($input, $expected)
    {
        $instance = new autoptimizeScripts('');
        $actual = $instance->should_aggregate($input);

        $this->assertEquals($expected, $actual);
    }

    public function provider_test_should_aggregate_script_types()
    {
        return array(
            // no type attribute at all
            array(
                // input
                '<script>var something=true</script>',
                // expected output
                true
            ),
            // case-insensitive
            array(
                '<script type="text/ecmaScript">var something=true</script>',
                true
            ),
            // allowed/aggregated now (wasn't previously)
            array(
                '<script type="application/javascript">var something=true</script>',
                true
            ),
            // quotes shouldn't matter, nor should case-sensitivity
            array(
                '<script type=\'text/JaVascriPt">var something=true</script>',
                true
            ),
            // liberal to whitespace around attribute names/values
            array(
                '<script tYpe = text/javascript>var something=true</script>',
                true
            ),
            // something custom, should be ignored/skipped
            array(
                '<script type=template/javascript>var something=true</script>',
                false
            ),
            // type attribute checking should be constrained to actual script tag's type attribute
            // only, regardless of any `type=` string present in the actual inline script contents
            array(
                // since there's no type attribute, it should be aggregate by default
                '<script>var type=something;</script>',
                true
            ),
            // application/ld+json should not be aggregated by default regardless of spacing around attr/values
            array(
                '<script type = "application/ld+json" >{   "@context": "" }',
                false
            ),
            array(
                '<script type="application/ld+json">{   "@context": "" }',
                false
            ),
        );
    }

    /**
     * @dataProvider provider_is_valid_buffer
     * @covers autoptimizeMain::is_valid_buffer
     */
    public function test_valid_buffers($input, $expected)
    {
        $actual = $this->ao->is_valid_buffer($input);

        $this->assertEquals($expected, $actual);
    }

    public function provider_is_valid_buffer()
    {
        return array(
            array(
                '<!doctype html>
<html ⚡>',
                false,
            ),
            array(
                '<!doctype html>
<html amp>',
                false
            ),
            array(
                '<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">',
                false
            ),
            array(
                '<!doctype html>
<html>',
                true
            ),
            array(
                '<html dir="ltr" amp>',
                false
            ),
            array(
                '<html dir="ltr" ⚡>',
                false
            ),
            array(
                '<html amp dir="ltr">',
                false
            ),
            array(
                '<html ⚡ dir="ltr">',
                false
            ),
            array(
                '<HTML ⚡ DIR="LTR">',
                false
            ),
            array(
                '<HTML AMP DIR="LTR">',
                false
            ),
            // https://github.com/futtta/autoptimize/commit/54385939db06f725fcafe68598cce6ed148ef6c1
            array(
                '<!doctype html>',
                true
            ),
        );
    }

    /**
     * @dataProvider provider_is_amp_markup
     * @covers autoptimizeMain::is_amp_markup
     */
    public function test_autoptimize_is_amp_markup($input, $expected)
    {
        $actual = autoptimizeMain::is_amp_markup($input);

        $this->assertEquals($expected, $actual);
    }

    public function provider_is_amp_markup()
    {
        return array(
            array(
                '<!doctype html>
<html ⚡>',
                true,
            ),
            array(
                '<!doctype html>
<html amp>',
                true
            ),
            array(
                '<!doctype html>
<head>
<meta charset=utf-8>',
                false
            )
        );
    }

    /**
     * Test various conditions that can/should prevent autoptimize from buffering content.
     */

    public function test_skips_buffering_when_ao_noptimize_filter_is_true()
    {
        // true => disable autoptimize
        add_filter( 'autoptimize_filter_noptimize', '__return_true' );

        // buffering should not run due to the above filter
        $expected = false;
        $actual   = $this->ao->should_buffer( $doing_tests = true );

        $this->assertEquals($expected, $actual);
    }

    public function test_does_buffering_when_ao_noptimize_filter_is_false()
    {
        // false => disable noptimize, aka, run normally (weird, yes...)
        add_filter( 'autoptimize_filter_noptimize', '__return_false' );

        // buffering should run because of above
        $expected = true;
        $actual   = $this->ao->should_buffer( $doing_tests = true );

        $this->assertEquals($expected, $actual);
    }

    public function test_ignores_ao_noptimize_qs_when_instructed()
    {
        // Should skip checking for the qs completely due to filter.
        add_filter( 'autoptimize_filter_honor_qs_noptimize', '__return_false' );

        // Which should then result in the "current" value being `false` when passed to 'autoptimize_filter_noptimize'
        // unless the DONOTMINIFY constant is defined, which changes the result... Which
        // basically means this test changes its' expected result depending on the order of tests
        // execution and/or the environment, which is AAAARGGGGGGHHH...

        $that = $this; // Makes it work on php 5.3!
        add_filter( 'autoptimize_filter_noptimize', function ($current_value) use ($that) {
            $expected = false;
            if ( defined( 'DONOTMINIFY' ) && DONOTMINIFY ) {
                $expected = true;
            }

            $that->assertEquals($expected, $current_value);
        });

        $this->ao->should_buffer( $doing_tests = true );
    }

    public function test_wpengine_cache_flush()
    {
        // Creating a mock so that we can get past class_exists() and method_exists() checks present
        // in `autoptimizeCache::flushPageCache()`...
        $stub = $this->getMockBuilder('WpeCommon')->disableAutoload()
                ->disableOriginalConstructor()->setMethods(array(
                    'purge_varnish_cache'))
                ->getMock();

        $that = $this;
        add_filter( 'autoptimize_flush_wpengine_methods', function($methods) use ($that) {
            $expected_methods = array('purge_varnish_cache');
            $that->assertEquals($methods, $expected_methods);

            return $methods;
        });

        autoptimizeCache::flushPageCache();
    }

    // Test with the `autoptimize_flush_wpengine_aggressive` filter
    public function test_wpengine_cache_flush_agressive()
    {
        // Creating a mock so that we can get past class_exists() and method_exists() checks `autoptimize_flush_pagecache()`...
        $stub = $this->getMockBuilder('WpeCommon')->disableAutoload()
                ->disableOriginalConstructor()->setMethods(array(
                    'purge_varnish_cache',
                    'purge_memcached',
                    'clear_maxcdn_cache'))
                ->getMock();

        add_filter( 'autoptimize_flush_wpengine_aggressive', function(){
            return true;
        });

        $that = $this;
        add_filter( 'autoptimize_flush_wpengine_methods', function($methods) use ($that) {
            $expected_methods = array(
                'purge_varnish_cache',
                'purge_memcached',
                'clear_maxcdn_cache'
            );

            $that->assertEquals($methods, $expected_methods);

            return $methods;
        });

        autoptimizeCache::flushPageCache();
    }

    /**
     * @dataProvider provider_test_url_replace_cdn
     * @covers autoptimizeBase::url_replace_cdn
     */
    public function test_url_replace_cdn($cdn_url, $input, $expected)
    {
        $mock = $this->getMockBuilder('autoptimizeBase')->disableOriginalConstructor()->getMockForAbstractClass();
        $mock->cdn_url = $cdn_url;

        $actual = $mock->url_replace_cdn($input);
        $this->assertEquals($expected, $actual);
    }

    public function provider_test_url_replace_cdn()
    {
        return array(
            // host-relative links get properly transformed
            array(
                // cdn base url, url, expected result
                'http://cdn-test.example.org',
                '/a.jpg',
                'http://cdn-test.example.org/a.jpg',
            ),
            // full link with a matching AUTOPTIMIZE_WP_SITE_URL gets properly replaced
            array(
                'http://cdn-test.example.org',
                'http://example.org/wp-content/themes/something/example.svg',
                'http://cdn-test.example.org/wp-content/themes/something/example.svg'
            ),
            // protocol-relative url with a "local" hostname that doesn't match example.org (AUTOPTIMIZE_WP_SITE_URL)
            array(
                'http://cdn-test.example.org',
                '//something/somewhere.jpg',
                '//something/somewhere.jpg'
            ),
            // www.example.org does not match example.org (AUTOPTIMIZE_WP_SITE_URL) so it's left alone
            array(
                'http://cdn-test.example.org',
                'http://www.example.org/wp-content/themes/something/example.svg',
                'http://www.example.org/wp-content/themes/something/example.svg'
            ),
            // ssl cdn url + host-relative link
            array(
                'https://cdn.example.org',
                '/a.jpg',
                'https://cdn.example.org/a.jpg'
            ),
            // ssl cdn url + http site url that matches AUTOPTIMIZE_WP_SITE_URL is properly replaced
            array(
                'https://cdn.example.org',
                'http://example.org/wp-content/themes/something/example.svg',
                'https://cdn.example.org/wp-content/themes/something/example.svg'
            ),
            // protocol-relative cdn url given with protocol relative link that matches AUTOPTIMIZE_WP_SITE_URL host
            array(
                '//cdn.example.org',
                '//example.org/something.jpg',
                '//cdn.example.org/something.jpg'
            ),
            // protocol-relative cdn url given a http link that matches AUTOPTIMIZE_WP_SITE_URL host
            array(
                '//cdn.example.org',
                'http://example.org/something.png',
                '//cdn.example.org/something.png',
            ),
            // protocol-relative cdn url with a host-relative link
            array(
                '//cdn.example.org',
                '/a.jpg',
                '//cdn.example.org/a.jpg',
            ),
            // Testing cdn urls with an explicit port number
            array(
                'http://cdn.com:8080',
                '/a.jpg',
                'http://cdn.com:8080/a.jpg'
            ),
            array(
                '//cdn.com:4433',
                '/a.jpg',
                '//cdn.com:4433/a.jpg'
            ),
            array(
                '//cdn.com:4433',
                'http://example.org/something.jpg',
                '//cdn.com:4433/something.jpg'
            ),
            array(
                '//cdn.com:1234',
                '//example.org/something.jpg',
                '//cdn.com:1234/something.jpg'
            ),
            // relative links should not be touched by url_replace_cdn() method
            array(
                // base cdn url
                'http://cdn-test.example.org',
                // url
                'a.jpg',
                // expected result
                'a.jpg',
            ),
            array(
                'http://cdn-test.example.org',
                './a.jpg',
                './a.jpg',
            ),
            array(
                'http://cdn-test.example.org',
                '../something/somewhere.svg',
                '../something/somewhere.svg',
            )
        );
    }

    // test `autoptimize_filter_base_cdnurl` filtering as described here: https://wordpress.org/support/topic/disable-cdn-of-ssl-pages
    public function test_autoptimize_filter_base_cdnurl()
    {
        $test_link = '/a.jpg';
        $cdn_url = '//cdn.example.org';

        $with_ssl = function($cdn) {
            return '';
        };
        $expected_with_ssl = '/a.jpg';

        $without_ssl = function($cdn) {
            return $cdn;
        };
        $expected_without_ssl = '//cdn.example.org/a.jpg';

        // with a filter that returns something considered "empty", cdn replacement shouldn't occur
        add_filter( 'autoptimize_filter_base_cdnurl', $with_ssl );
        $mock = $this->getMockBuilder('autoptimizeBase')->disableOriginalConstructor()->getMockForAbstractClass();
        $mock->cdn_url = $cdn_url;
        $actual_with_ssl = $mock->url_replace_cdn($test_link);
        $this->assertEquals($expected_with_ssl, $actual_with_ssl);
        remove_filter( 'autoptimize_filter_base_cdnurl', $with_ssl );

        // with a filter that returns an actual cdn url, cdn replacement should occur
        add_filter( 'autoptimize_filter_base_cdnurl', $without_ssl );
        $actual_without_ssl = $mock->url_replace_cdn($test_link);
        $this->assertEquals($expected_without_ssl, $actual_without_ssl);
    }

    public function provider_cssmin_issues()
    {
        return array(
            // https://wordpress.org/support/topic/css-minify-breaks-calc-subtract-operation-in-css/?replies=2#post-6610027
            array(
                // input
                'input { width: calc(33.33333% - ((0.75em*2)/3)); }',
                // expected output (ancient version of CSSmin returns 0.75, newer versions drop the 0)
                'input{width:calc(33.33333% - ((.75em*2)/3))}'
            ),
            // Actual examples from above, but original wasn't really valid css input fully,
            // but these tests used to work and we'd like to know if output changes with various
            // CSSmin versions, for backcompat reasons if nothing else
            array(
                // input
                'width: calc(33.33333% - ((0.75em*2)/3));',
                // expected output
                'width:calc(33.33333% - ((0.75em*2)/3));'
            ),
            // https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port/issues/22#issuecomment-251401341
            array(
                'input { width: calc(100% - (1em*1.5) - 2em); }',
                'input{width:calc(100% - (1em*1.5) - 2em)}'
            ),
            // https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port/issues/26
            array(
                '.px { flex: 1 1 0px; }, .percent {flex: 1 1 0%}',
                '.px{flex:1 1 0px},.percent{flex:1 1 0%}'
            )
        );
    }

    /**
     * @dataProvider provider_cssmin_issues
     * @covers CSSmin::replace_calc
     */
    public function test_cssmin_issues($input, $expected)
    {
        $minifier = new autoptimizeCSSmin(false); // no need to raise limits for now

        $actual = $minifier->run($input);
        $this->assertEquals($expected, $actual);
    }

    public function provider_getpath()
    {
        return array(
            // These all don't really exist, and getpath() returns
            // false for non-existing files since upstream's 1386e4fe1d commit
            array(
                'img/something.svg',
                false
            ),
            array(
                '../../somewhere-else/svg.svg',
                false
            ),
            array(
                '//something/somewhere/example.png',
                false
            ),
            // This file comes with core, so should exist...
            array(
                '/wp-includes/js/jquery/jquery.js',
                WP_ROOT_DIR . '/wp-includes/js/jquery/jquery.js'
            ),
            // Empty $url should return false
            array(
                '',
                false
            ),
            array(
                false,
                false
            ),
            array(
                null,
                false
            ),
            array(
                0,
                false
            )
        );
    }

    /**
     * @dataProvider provider_getpath
     * @covers autoptimizeBase::getpath
     */
    public function test_getpath($input, $expected)
    {
        $mock = $this->getMockBuilder('autoptimizeBase')->disableOriginalConstructor()->getMockForAbstractClass();

        $actual = $mock->getpath($input);
        $this->assertEquals($expected, $actual);
    }

    // https://github.com/futtta/autoptimize/pull/81#issuecomment-278935307
    public function test_fixurls_with_hash_only_urls()
    {
        $css_orig = <<<CSS
header{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='90px' height='110px' viewBox='0 0 90 110'%3E%3Cstyle%3E.a%7Bstop-color:%23FFF;%7D.b%7Bstop-color:%23B2D235;%7D.c%7Bstop-color:%23BEE7FA;%7D.d%7Bfill:%23590C15;%7D%3C/style%3E%3ClinearGradient id='c' y2='135.4' gradientUnits='userSpaceOnUse' x2='209.1' gradientTransform='rotate(-1.467 -4082.888 7786.794)' y1='205.8' x1='262'%3E%3Cstop class='b' offset='0'/%3E%3Cstop class='b' offset='.48'/%3E%3Cstop stop-color='%23829D25' offset='1'/%3E%3C/linearGradient%3E%3Cpath stroke-width='.3' d='M77.3 45.4c-3-3.5-7.1-6.5-11.6-7.8-5.1-1.5-10-.1-14.9 1.5C52 35.4 54.3 29 60 24l-4.8-5.5c-3.4 3-5.8 6.3-7.5 9.4-1.7-4.3-4.1-8.4-7.5-12C33.4 8.6 24.3 4.7 15.1 4.2c-.2 9.3 3.1 18.6 9.9 25.9 5.2 5.6 11.8 9.2 18.7 10.8-2.5.2-4.9-.1-7.7-.9-5.2-1.4-10.5-2.8-15.8-1C10.6 42.3 4.5 51.9 4 61.7c-.5 11.6 3.8 23.8 9.9 33.5 3.9 6.3 9.6 13.7 17.7 13.4 3.8-.1 7-2.1 10.7-2.7 5.2-.8 9.1 1.2 14.1 1.8 16.4 2 24.4-23.6 26.4-35.9 1.2-9.1.8-19.1-5.5-26.4z' stroke='%233E6D1F' fill='url(%23c)'/%3E%3C/svg%3E")}
section.clipped.clippedTop {clip-path:url("#clipPolygonTop")}
section.clipped.clippedBottom {clip-path:url("#clipPolygonBottom")}
.myimg {background-image: url("images/under-left-leaf.png"), url("images/over-blue-bird.png"), url("images/under-top.png"), url("images/bg-top-grunge.png");}
CSS;
        $css_expected = <<<CSS
header{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='90px' height='110px' viewBox='0 0 90 110'%3E%3Cstyle%3E.a%7Bstop-color:%23FFF;%7D.b%7Bstop-color:%23B2D235;%7D.c%7Bstop-color:%23BEE7FA;%7D.d%7Bfill:%23590C15;%7D%3C/style%3E%3ClinearGradient id='c' y2='135.4' gradientUnits='userSpaceOnUse' x2='209.1' gradientTransform='rotate(-1.467 -4082.888 7786.794)' y1='205.8' x1='262'%3E%3Cstop class='b' offset='0'/%3E%3Cstop class='b' offset='.48'/%3E%3Cstop stop-color='%23829D25' offset='1'/%3E%3C/linearGradient%3E%3Cpath stroke-width='.3' d='M77.3 45.4c-3-3.5-7.1-6.5-11.6-7.8-5.1-1.5-10-.1-14.9 1.5C52 35.4 54.3 29 60 24l-4.8-5.5c-3.4 3-5.8 6.3-7.5 9.4-1.7-4.3-4.1-8.4-7.5-12C33.4 8.6 24.3 4.7 15.1 4.2c-.2 9.3 3.1 18.6 9.9 25.9 5.2 5.6 11.8 9.2 18.7 10.8-2.5.2-4.9-.1-7.7-.9-5.2-1.4-10.5-2.8-15.8-1C10.6 42.3 4.5 51.9 4 61.7c-.5 11.6 3.8 23.8 9.9 33.5 3.9 6.3 9.6 13.7 17.7 13.4 3.8-.1 7-2.1 10.7-2.7 5.2-.8 9.1 1.2 14.1 1.8 16.4 2 24.4-23.6 26.4-35.9 1.2-9.1.8-19.1-5.5-26.4z' stroke='%233E6D1F' fill='url(%23c)'/%3E%3C/svg%3E")}
section.clipped.clippedTop {clip-path:url("#clipPolygonTop")}
section.clipped.clippedBottom {clip-path:url("#clipPolygonBottom")}
.myimg {background-image: url(//example.org/wp-content/themes/my-theme/images/under-left-leaf.png), url(//example.org/wp-content/themes/my-theme/images/over-blue-bird.png), url(//example.org/wp-content/themes/my-theme/images/under-top.png), url(//example.org/wp-content/themes/my-theme/images/bg-top-grunge.png);}
CSS;

        $fixurls_result = autoptimizeStyles::fixurls(ABSPATH . 'wp-content/themes/my-theme/style.css', $css_orig);
        $this->assertEquals($css_expected, $fixurls_result);
    }

    public function test_background_datauri_sprites_with_fixurls()
    {
        $css_orig = <<<CSS
.shadow { background:url(img/1x1.png) top center; }
.shadow1 { background-image:url(img/1x1.png) 0 -767px repeat-x; }
.shadow2 {background:url(img/1x1.png) top center}

.test { background:url(img/1x1.png) top center; }
.test1 { background-image:url('img/1x1.png') 0 -767px repeat-x; }
.test2 {background:url("img/1x1.png") top center}

header{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='90px' height='110px' viewBox='0 0 90 110'%3E%3Cstyle%3E.a%7Bstop-color:%23FFF;%7D.b%7Bstop-color:%23B2D235;%7D.c%7Bstop-color:%23BEE7FA;%7D.d%7Bfill:%23590C15;%7D%3C/style%3E%3ClinearGradient id='c' y2='135.4' gradientUnits='userSpaceOnUse' x2='209.1' gradientTransform='rotate(-1.467 -4082.888 7786.794)' y1='205.8' x1='262'%3E%3Cstop class='b' offset='0'/%3E%3Cstop class='b' offset='.48'/%3E%3Cstop stop-color='%23829D25' offset='1'/%3E%3C/linearGradient%3E%3Cpath stroke-width='.3' d='M77.3 45.4c-3-3.5-7.1-6.5-11.6-7.8-5.1-1.5-10-.1-14.9 1.5C52 35.4 54.3 29 60 24l-4.8-5.5c-3.4 3-5.8 6.3-7.5 9.4-1.7-4.3-4.1-8.4-7.5-12C33.4 8.6 24.3 4.7 15.1 4.2c-.2 9.3 3.1 18.6 9.9 25.9 5.2 5.6 11.8 9.2 18.7 10.8-2.5.2-4.9-.1-7.7-.9-5.2-1.4-10.5-2.8-15.8-1C10.6 42.3 4.5 51.9 4 61.7c-.5 11.6 3.8 23.8 9.9 33.5 3.9 6.3 9.6 13.7 17.7 13.4 3.8-.1 7-2.1 10.7-2.7 5.2-.8 9.1 1.2 14.1 1.8 16.4 2 24.4-23.6 26.4-35.9 1.2-9.1.8-19.1-5.5-26.4z' stroke='%233E6D1F' fill='url(%23c)'/%3E%3C/svg%3E")}

/*
section.clipped.clippedTop {clip-path:url("#clipPolygonTop")}
section.clipped.clippedBottom {clip-path:url("#clipPolygonBottom")}
.myimg {background-image: url("images/under-left-leaf.png"), url("images/over-blue-bird.png"), url("images/under-top.png"), url("images/bg-top-grunge.png");}
.something {
    background:url(http://example.org/wp-content/themes/my-theme/images/nothing.png);
}
.something-else {background:url(wp-content/themes/my-theme/images/shadow.png) -100px 0 repeat-y;}
.another-thing { background:url(/wp-content/themes/my-theme/images/shadow.png) 0 -767px repeat-x; }
#whatevz {background:url(wp-content/themes/my-theme/images/shadow.png) center top no-repeat;}

.widget ul li { background:url(img/shadow.png) top center; }
*/
CSS;
        $css_expected = <<<CSS
.shadow { background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) top center; }
.shadow1 { background-image:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) 0 -767px repeat-x; }
.shadow2 {background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) top center}

.test { background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) top center; }
.test1 { background-image:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) 0 -767px repeat-x; }
.test2 {background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) top center}

header{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='90px' height='110px' viewBox='0 0 90 110'%3E%3Cstyle%3E.a%7Bstop-color:%23FFF;%7D.b%7Bstop-color:%23B2D235;%7D.c%7Bstop-color:%23BEE7FA;%7D.d%7Bfill:%23590C15;%7D%3C/style%3E%3ClinearGradient id='c' y2='135.4' gradientUnits='userSpaceOnUse' x2='209.1' gradientTransform='rotate(-1.467 -4082.888 7786.794)' y1='205.8' x1='262'%3E%3Cstop class='b' offset='0'/%3E%3Cstop class='b' offset='.48'/%3E%3Cstop stop-color='%23829D25' offset='1'/%3E%3C/linearGradient%3E%3Cpath stroke-width='.3' d='M77.3 45.4c-3-3.5-7.1-6.5-11.6-7.8-5.1-1.5-10-.1-14.9 1.5C52 35.4 54.3 29 60 24l-4.8-5.5c-3.4 3-5.8 6.3-7.5 9.4-1.7-4.3-4.1-8.4-7.5-12C33.4 8.6 24.3 4.7 15.1 4.2c-.2 9.3 3.1 18.6 9.9 25.9 5.2 5.6 11.8 9.2 18.7 10.8-2.5.2-4.9-.1-7.7-.9-5.2-1.4-10.5-2.8-15.8-1C10.6 42.3 4.5 51.9 4 61.7c-.5 11.6 3.8 23.8 9.9 33.5 3.9 6.3 9.6 13.7 17.7 13.4 3.8-.1 7-2.1 10.7-2.7 5.2-.8 9.1 1.2 14.1 1.8 16.4 2 24.4-23.6 26.4-35.9 1.2-9.1.8-19.1-5.5-26.4z' stroke='%233E6D1F' fill='url(%23c)'/%3E%3C/svg%3E")}

/*
section.clipped.clippedTop {clip-path:url("#clipPolygonTop")}
section.clipped.clippedBottom {clip-path:url("#clipPolygonBottom")}
.myimg {background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=), url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=), url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=), url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=);}
.something {
    background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=);
}
.something-else {background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) -100px 0 repeat-y;}
.another-thing { background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) 0 -767px repeat-x; }
#whatevz {background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) center top no-repeat;}

.widget ul li { background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) top center; }
*/
CSS;

        // For test purposes, ALL images in the css are being inline with a 1x1 trans png string/datauri
        add_filter( 'autoptimize_filter_css_is_datauri_candidate', function($is_candidate, $path) {
            return true;
        }, 10, 2);

        // For test purposes, ALL images in the css are being inline with a 1x1 trans png string/datauri
        add_filter( 'autoptimize_filter_css_datauri_image', function($base64array, $path) {
            $head = 'data:image/png;base64,';
            $data = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

            $result['full'] = $head . $data;
            $result['base64data'] = $data;
            return $result;
        }, 10, 2);

        $instance = new autoptimizeStyles($css_orig);
        $instance->setOption('datauris', true);

        $fixurls_result = autoptimizeStyles::fixurls(ABSPATH . 'wp-content/themes/my-theme/style.css', $css_orig);
        $css_actual = $instance->rewrite_assets($fixurls_result);

        $this->assertEquals($css_expected, $css_actual);
    }

    /**
     * Doing rewrite_assets() without calling fixurls() beforehand could cause wrong results if/when there's a
     * (same) image referenced via root-relative and relative urls, i.e., `/wp-content/themes/my-theme/images/shadow.png` and
     * `wp-content/themes/my-theme/images/shadow.png` in test code below.
     * That's because urls are not really "normalized" in rewrite_assets() at all, and replacements are done
     * using simple string keys (based on url), so whenever the shorter url ends up being spotted first, the replacement
     * was done in a way that leaves the first `/` character in place. Which could mean trouble, especially when
     * doing inlining of smaller images.
     * After sorting the replacements array in rewrite_assets() by string length in descending order, the problem
     * goes away.
     */
    public function test_background_datauri_sprites_without_fixurls()
    {
        $css_orig = <<<CSS
.shadow { background:url(img/1x1.png) top center; }
.shadow1 { background-image:url(img/1x1.png) 0 -767px repeat-x; }
.shadow2 {background:url(img/1x1.png) top center}

.test { background:url(img/1x1.png) top center; }
.test1 { background-image:url('img/1x1.png') 0 -767px repeat-x; }
.test2 {background:url("img/1x1.png") top center}

section.clipped.clippedTop {clip-path:url("#clipPolygonTop")}
section.clipped.clippedBottom {clip-path:url("#clipPolygonBottom")}
.myimg {background-image: url("images/under-left-leaf.png"), url("images/over-blue-bird.png"), url("images/under-top.png"), url("images/bg-top-grunge.png");}
.something {
    background:url(http://example.org/wp-content/themes/my-theme/images/nothing.png);
}
.something-else {background:url(wp-content/themes/my-theme/images/shadow.png) -100px 0 repeat-y;}
.another-thing { background:url(/wp-content/themes/my-theme/images/shadow.png) 0 -767px repeat-x; }
#whatevz {background:url(wp-content/themes/my-theme/images/shadow.png) center top no-repeat;}

.widget ul li { background:url(img/shadow.png) top center; }
CSS;
        $css_expected = <<<CSS
.shadow { background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) top center; }
.shadow1 { background-image:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) 0 -767px repeat-x; }
.shadow2 {background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) top center}

.test { background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) top center; }
.test1 { background-image:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) 0 -767px repeat-x; }
.test2 {background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) top center}

section.clipped.clippedTop {clip-path:url("#clipPolygonTop")}
section.clipped.clippedBottom {clip-path:url("#clipPolygonBottom")}
.myimg {background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=), url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=), url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=), url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=);}
.something {
    background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=);
}
.something-else {background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) -100px 0 repeat-y;}
.another-thing { background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) 0 -767px repeat-x; }
#whatevz {background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) center top no-repeat;}

.widget ul li { background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=) top center; }
CSS;

        // For test purposes, ALL images in the css are being inline with a 1x1 trans png string/datauri
        add_filter( 'autoptimize_filter_css_is_datauri_candidate', function($is_candidate, $path) {
            return true;
        }, 10, 2);

        // For test purposes, ALL images in the css are being inline with a 1x1 trans png string/datauri
        add_filter( 'autoptimize_filter_css_datauri_image', function($base64array, $path) {
            $head = 'data:image/png;base64,';
            $data = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

            $result['full'] = $head . $data;
            $result['base64data'] = $data;
            return $result;
        }, 10, 2);

        $instance = new autoptimizeStyles($css_orig);
        $instance->setOption('datauris', true);
        $css_actual = $instance->rewrite_assets($css_orig);
        $this->assertEquals($css_expected, $css_actual);
    }

    // Test css with fonts pointed to the CDN + cdn_url option is set
    public function test_css_fonts_on_cdn_with_filter()
    {
        $css_in = <<<CSS
/* these should not be touched except for quotes removal */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('../fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('../fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('../fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('//fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('//fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('//fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('//fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('//fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('//fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
/* these will be replaced and quotes gone */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('/fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('/fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('/fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('/fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('/fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('/fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
CSS;
        $css_expected_fonts_cdn = <<<CSS
/* these should not be touched except for quotes removal */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url(../fonts/roboto-v15-latin-ext_latin-100.eot); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url(../fonts/roboto-v15-latin-ext_latin-100.eot?#iefix) format('embedded-opentype'), /* IE6-IE8 */
       url(../fonts/roboto-v15-latin-ext_latin-100.woff2) format('woff2'), /* Super Modern Browsers */
       url(../fonts/roboto-v15-latin-ext_latin-100.woff) format('woff'), /* Modern Browsers */
       url(../fonts/roboto-v15-latin-ext_latin-100.ttf) format('truetype'), /* Safari, Android, iOS */
       url(../fonts/roboto-v15-latin-ext_latin-100.svg#Roboto) format('svg'); /* Legacy iOS */
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url(//fonts/roboto-v15-latin-ext_latin-100.eot); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url(//fonts/roboto-v15-latin-ext_latin-100.eot?#iefix) format('embedded-opentype'), /* IE6-IE8 */
       url(//fonts/roboto-v15-latin-ext_latin-100.woff2) format('woff2'), /* Super Modern Browsers */
       url(//fonts/roboto-v15-latin-ext_latin-100.woff) format('woff'), /* Modern Browsers */
       url(//fonts/roboto-v15-latin-ext_latin-100.ttf) format('truetype'), /* Safari, Android, iOS */
       url(//fonts/roboto-v15-latin-ext_latin-100.svg#Roboto) format('svg'); /* Legacy iOS */
}
/* these will be replaced and quotes gone */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url(http://cdn.example.org/fonts/roboto-v15-latin-ext_latin-100.eot); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url(http://cdn.example.org/fonts/roboto-v15-latin-ext_latin-100.eot?#iefix) format('embedded-opentype'), /* IE6-IE8 */
       url(http://cdn.example.org/fonts/roboto-v15-latin-ext_latin-100.woff2) format('woff2'), /* Super Modern Browsers */
       url(http://cdn.example.org/fonts/roboto-v15-latin-ext_latin-100.woff) format('woff'), /* Modern Browsers */
       url(http://cdn.example.org/fonts/roboto-v15-latin-ext_latin-100.ttf) format('truetype'), /* Safari, Android, iOS */
       url(http://cdn.example.org/fonts/roboto-v15-latin-ext_latin-100.svg#Roboto) format('svg'); /* Legacy iOS */
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url(http://cdn.example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.eot); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url(http://cdn.example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.eot?#iefix) format('embedded-opentype'), /* IE6-IE8 */
       url(http://cdn.example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.woff2) format('woff2'), /* Super Modern Browsers */
       url(http://cdn.example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.woff) format('woff'), /* Modern Browsers */
       url(http://cdn.example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.ttf) format('truetype'), /* Safari, Android, iOS */
       url(http://cdn.example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.svg#Roboto) format('svg'); /* Legacy iOS */
}
CSS;

        // Test with fonts pointed to the CDN + cdn option is set
        add_filter( 'autoptimize_filter_css_fonts_cdn', '__return_true' );
        $instance = new autoptimizeStyles($css_in);
        $instance->setOption('cdn_url', 'http://cdn.example.org');
        $css_actual_fonts_cdn = $instance->rewrite_assets($css_in);

        $this->assertEquals($css_expected_fonts_cdn, $css_actual_fonts_cdn);
    }

    // Test css fonts not moved to cdn by default even if cdn_url option is set
    public function test_css_fonts_skipped_by_default_when_cdn_is_set()
    {
        $css_in = <<<CSS
/* these should not be changed, not even quotes */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('../fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('../fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('../fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('//fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('//fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('//fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('//fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('//fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('//fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('/fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('/fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('/fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('/fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('/fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('/fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
CSS;
        // Expected without cdning fonts but cdn option is set
        $css_expected = <<<CSS
/* these should not be changed, not even quotes */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('../fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('../fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('../fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('//fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('//fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('//fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('//fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('//fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('//fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('/fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('/fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('/fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('/fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('/fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('/fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('http://example.org/wp-content/themes/mytheme/fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
CSS;
        // Test without moving fonts to CDN, but cdn option is set
        $instance = new autoptimizeStyles($css_in);
        $instance->setOption('cdn_url', 'http://cdn.example.org');
        $css_actual = $instance->rewrite_assets($css_in);
        $this->assertEquals($css_expected, $css_actual);
    }

    public function test_assets_regex_replaces_multi_bg_images()
    {
        $in = <<<CSS
body:after {
  content: url(/img/close.png) url(/img/loading.gif) url(/img/prev.png) url(/img/next.png);
}
CSS;
        $expected = <<<CSS
body:after {
  content: url(http://cdn.example.org/img/close.png) url(http://cdn.example.org/img/loading.gif) url(http://cdn.example.org/img/prev.png) url(http://cdn.example.org/img/next.png);
}
CSS;

        $instance = new autoptimizeStyles($in);
        $instance->setOption('cdn_url', 'http://cdn.example.org');
        $actual = $instance->rewrite_assets($in);

        $this->assertEquals($expected, $actual);
    }

    public function test_at_supports_spacing_issue_110()
    {
        $in = <<<CSS
@supports (-webkit-filter: blur(3px)) or (filter: blur(3px)) {
    .blur {
        filter:blur(3px);
    }
}
@supports((position:-webkit-sticky) or (position:sticky)) {
    .sticky { position:sticky; }
}
CSS;
        $expected = <<<CSS
@supports (-webkit-filter:blur(3px)) or (filter:blur(3px)){.blur{filter:blur(3px)}}@supports((position:-webkit-sticky) or (position:sticky)){.sticky{position:sticky}}
CSS;

        $instance = new autoptimizeStyles($in);
        $actual = $instance->run_minifier_on($in);

        $this->assertEquals($expected, $actual);
    }

    public function test_css_import_semicolon_url_issue_122()
    {
        $in = <<<HTML
<style type="text/css">
@import url("foo.css?a&#038;b");
@import url("bar.css");
</style>
HTML;

        $expected = '<style type="text/css" media="all">@import url(http://cdn.example.org/foo.css?a&#038;b);@import url(http://cdn.example.org/bar.css);</style><!--noptimize--><!-- Autoptimize found a problem with the HTML in your Theme, tag `title` missing --><!--/noptimize-->';

        $options = [
            'autoptimizeStyles' => $this->getAoStylesDefaultOptions()
        ];

        $instance = new autoptimizeStyles($in);
        $instance->read($options['autoptimizeStyles']);
        $instance->minify();
        $instance->cache();
        $actual = $instance->getcontent();
        $this->assertEquals($expected, $actual);
    }

    public function test_fixurls_with_at_imports_and_media_queries()
    {
        $in  = '@import "foo.css"; @import "bar.css" (orientation:landscape);';
        $exp = '@import url(//example.org/wp-content/themes/my-theme/foo.css); @import url(//example.org/wp-content/themes/my-theme/bar.css) (orientation:landscape);';

        $actual = autoptimizeStyles::fixurls(ABSPATH . 'wp-content/themes/my-theme/style.css', $in);
        $this->assertEquals($exp, $actual);
    }

    public function test_aostyles_at_imports_with_media_queries()
    {
        $in = <<<HTML
<style type="text/css">
@import "foo.css"; @import "bar.css" (orientation:landscape);
</style>
HTML;

        $expected = '<style type="text/css" media="all">@import url(http://cdn.example.org/foo.css);@import url(http://cdn.example.org/bar.css) (orientation:landscape);</style><!--noptimize--><!-- Autoptimize found a problem with the HTML in your Theme, tag `title` missing --><!--/noptimize-->';

        $options = [
            'autoptimizeStyles' => $this->getAoStylesDefaultOptions()
        ];

        $instance = new autoptimizeStyles($in);
        $instance->read($options['autoptimizeStyles']);
        $instance->minify();
        $instance->cache();

        $actual = $instance->getcontent();
        $this->assertEquals($expected, $actual);
    }

    public function test_cache_size_checker_hooked_by_default()
    {
        $this->assertTrue(true, autoptimizeCacheChecker::SCHEDULE_HOOK);

        // No schedule, because it's only added when is_admin() is true
        $this->assertEquals(false, wp_get_schedule(autoptimizeCacheChecker::SCHEDULE_HOOK));

        // Prove that setup() sets the schedule as needed
        $checker = new autoptimizeCacheChecker();
        $checker->setup();
        $this->assertEquals('daily', wp_get_schedule(autoptimizeCacheChecker::SCHEDULE_HOOK));
    }

    public function test_cache_size_checker_disabled_with_filter()
    {
        add_filter( 'autoptimize_filter_cachecheck_do', '__return_false' );

        $checker = new autoptimizeCacheChecker();
        $checker->setup();
        $this->assertEquals(false, wp_get_schedule(autoptimizeCacheChecker::SCHEDULE_HOOK));

        remove_all_filters( 'autoptimize_filter_cachecheck_do' );
    }

    public function test_is_start_buffering_hooked_properly()
    {
        $instance = autoptimize();

        // TODO/FIXME: ideally, we'd test all possible setups, but once we set
        // a constant, there's no going back, unless we use runkit or somesuch:
        // https://www.theaveragedev.com/mocking-constants-in-tests/

        if (defined('AUTOPTIMIZE_INIT_EARLIER')) {
            $this->assertEquals(
                autoptimizeMain::INIT_EARLIER_PRIORITY,
                has_action('init', array($instance, 'start_buffering'))
            );
            $this->assertTrue(!defined('AUTOPTIMIZE_HOOK_INTO'));
        }
/*
        // AUTOPTIMIZE_HOOK_INTO only exists if AUTOPTIMIZE_INIT_EARLIER doesnt
        $this->assertEquals(
            autoptimizeMain::DEFAULT_HOOK_PRIORITY,
            has_action(constant('AUTOPTIMIZE_HOOK_INTO'), array($instance, 'start_buffering'))
        );
        $this->assertFalse(
            has_action('init', array($instance, 'start_buffering'))
        );
*/
    }

    public function test_inline_and_defer_markup()
    {
        add_filter( 'autoptimize_filter_css_defer', '__return_true' );
        add_filter( 'autoptimize_filter_css_defer_inline', '__return_true' );

        $actual = $this->ao->end_buffering( self::TEST_MARKUP );
        if ( is_multisite() ) {
            $this->assertEquals( self::TEST_MARKUP_OUTPUT_INLINE_DEFER_MS, $actual );
        } else {
            $this->assertEquals( self::TEST_MARKUP_OUTPUT_INLINE_DEFER, $actual );
        }

        remove_all_filters( 'autoptimize_filter_css_defer' );
        remove_all_filters( 'autoptimize_filter_css_defer_inline' );
    }

    public function test_js_aggregation_decision_and_dontaggregate_filter()
    {
        $opts = $this->getAoScriptsDefaultOptions();

        // Aggregating: true by default
        $scripts = new autoptimizeScripts('');
        $scripts->read($opts);
        $this->assertTrue($scripts->aggregating());

        // Aggregating: option=true (dontaggregate=false by default).
        $opts['aggregate'] = true;
        $scripts = new autoptimizeScripts('');
        $scripts->read($opts);
        $this->assertTrue($scripts->aggregating());

        // Aggregating: option=true, dontaggregate=false explicit.
        add_filter( 'autoptimize_filter_js_dontaggregate', '__return_false' );
        $opts['aggregate'] = true;
        $scripts = new autoptimizeScripts('');
        $scripts->read($opts);
        $this->assertTrue($scripts->aggregating());
        remove_all_filters( 'autoptimize_filter_js_dontaggregate' );

        // Not aggregating: option=true, dontaggregate=true.
        add_filter( 'autoptimize_filter_js_dontaggregate', '__return_true' );
        $opts['aggregate'] = true;
        $scripts = new autoptimizeScripts('');
        $scripts->read($opts);
        $this->assertFalse($scripts->aggregating());
        remove_all_filters( 'autoptimize_filter_js_dontaggregate' );

        // Not aggregating: option=false, dontaggregate=false.
        add_filter( 'autoptimize_filter_js_dontaggregate', '__return_false' );
        $opts['aggregate'] = false;
        $scripts = new autoptimizeScripts('');
        $scripts->read($opts);
        $this->assertFalse($scripts->aggregating());
        remove_all_filters( 'autoptimize_filter_js_dontaggregate' );

        // Not aggregating: option=false, dontaggregate=true.
        add_filter( 'autoptimize_filter_js_dontaggregate', '__return_true' );
        $opts['aggregate'] = false;
        $scripts = new autoptimizeScripts('');
        $scripts->read($opts);
        $this->assertFalse($scripts->aggregating());
        remove_all_filters( 'autoptimize_filter_js_dontaggregate' );
    }

    public function test_css_aggregation_decision_and_dontaggregate_filter()
    {
        $opts = $this->getAoStylesDefaultOptions();

        // Aggregating: true by default
        $styles = new autoptimizeStyles('');
        $this->assertTrue($styles->aggregating());

        // Aggregating: option=true (dontaggregate=false by default).
        $opts['aggregate'] = true;
        $styles = new autoptimizeStyles('');
        $styles->read($opts);
        $this->assertTrue($styles->aggregating());

        // Aggregating: option=true, dontaggregate=false explicit.
        add_filter( 'autoptimize_filter_css_dontaggregate', '__return_false' );
        $opts['aggregate'] = true;
        $styles = new autoptimizeStyles('');
        $styles->read($opts);
        $this->assertTrue($styles->aggregating());
        remove_all_filters( 'autoptimize_filter_css_dontaggregate' );

        // Not aggregating: option=true, dontaggregate=true.
        add_filter( 'autoptimize_filter_css_dontaggregate', '__return_true' );
        $opts['aggregate'] = true;
        $styles = new autoptimizeStyles('');
        $styles->read($opts);
        $this->assertFalse($styles->aggregating());
        remove_all_filters( 'autoptimize_filter_css_dontaggregate' );

        // Not aggregating: option=false, dontaggregate=false.
        add_filter( 'autoptimize_filter_css_dontaggregate', '__return_false' );
        $opts['aggregate'] = false;
        $styles = new autoptimizeStyles('');
        $styles->read($opts);
        $this->assertFalse($styles->aggregating());
        remove_all_filters( 'autoptimize_filter_css_dontaggregate' );

        // Not aggregating: option=false, dontaggregate=true.
        add_filter( 'autoptimize_filter_css_dontaggregate', '__return_true' );
        $opts['aggregate'] = false;
        $styles = new autoptimizeStyles('');
        $styles->read($opts);
        $this->assertFalse($styles->aggregating());
        remove_all_filters( 'autoptimize_filter_css_dontaggregate' );
    }

    public function test_css_minify_single_with_cdning()
    {
        $pathname = dirname(__FILE__) . '/fixtures/minify-single.css';

        $styles = new autoptimizeStyles( '' );
        $opts = $this->getAoStylesDefaultOptions();
        $styles->read( $opts );
        $url = $styles->minify_single( $pathname, $cache_miss = true );

        // Minified url filename + its pointed to cdn
        $this->assertContains( 'cache/autoptimize/', $url );
        $this->assertContains( '/autoptimize_single_', $url );
        $this->assertContains( $styles->cdn_url, $url );

        // Actual minified css contents are minified and cdn-ed.
        $path = $styles->getpath( $url );
        $contents = file_get_contents( $path );
        $this->assertContains( $styles->cdn_url, $contents );
        $this->assertContains( '.bg{background:url(' . $styles->cdn_url, $contents );
    }

    public function test_ao_partners_instantiation_without_explicit_include()
    {
        $partners = new autoptimizePartners();
        $this->assertTrue($partners instanceof autoptimizePartners);
    }

    public function  test_html_minify_keep_html_comments_inside_script_blocks()
    {
        $markup = <<<MARKUP
<script>
<!-- End Support AJAX add to cart -->
var a = "b";
</script>
MARKUP;
        $expected = <<<MARKUP
<script><!-- End Support AJAX add to cart -->
var a = "b";</script>
MARKUP;

        $markup2 = <<<MARKUP
<script>
var a = "b";
<!-- End Support AJAX add to cart -->
</script>
MARKUP;

        $expected2 = <<<MARKUP
<script>var a = "b";
<!-- End Support AJAX add to cart --></script>
MARKUP;

        // When keepcomments is true
        $options = [
            'autoptimizeHTML' => [
                'keepcomments' => true
            ],
        ];

        $instance = new autoptimizeHTML( $markup );
        $instance->read( $options['autoptimizeHTML'] );
        $instance->minify();
        $actual = $instance->getcontent();
        $this->assertEquals( $expected, $actual );

        $instance = new autoptimizeHTML( $markup2 );
        $instance->read( $options['autoptimizeHTML'] );
        $instance->minify();
        $actual2 = $instance->getcontent();
        $this->assertEquals( $expected2, $actual2 );
    }

    public function test_html_minify_remove_html_comments_inside_script_blocks()
    {
        // Default case, where html comments are removed (keepcomments = false)
        $markup1 = <<<MARKUP
<script>
var a = "b";
<!-- End Support AJAX add to cart -->
</script>
MARKUP;
        $expected1 = <<<MARKUP
<script>var a = "b";
<!-- End Support AJAX add to cart</script>
MARKUP;

        $markup2 = <<<MARKUP
<script>
<!-- End Support AJAX add to cart -->
var a = "b";
</script>
MARKUP;
        $expected2 = <<<MARKUP
<script>End Support AJAX add to cart -->
var a = "b";</script>
MARKUP;

        $options = [
            'autoptimizeHTML' => [
                'keepcomments' => false,
            ],
        ];

        $instance = new autoptimizeHTML( $markup1 );
        $instance->read( $options['autoptimizeHTML'] );
        $instance->minify();
        $actual = $instance->getcontent();
        $this->assertEquals( $expected1, $actual );

        $instance = new autoptimizeHTML( $markup2 );
        $instance->read( $options['autoptimizeHTML'] );
        $instance->minify();
        $actual2 = $instance->getcontent();
        $this->assertEquals( $expected2, $actual2 );
    }

    public function test_html_minify_html_comments_inside_script_blocks_old_school_pattern()
    {
        $markup = <<<MARKUP
<script>
<!-- // invisible for old browsers
var a = "z";
// -->
</script>
MARKUP;

        $expected = <<<MARKUP
<script>// invisible for old browsers
var a = "z";</script>
MARKUP;

        $options = [
            'autoptimizeHTML' => [
                'keepcomments' => false,
            ],
        ];

        $instance = new autoptimizeHTML( $markup );
        $instance->read( $options['autoptimizeHTML'] );
        $instance->minify();
        $actual = $instance->getcontent();
        $this->assertEquals( $expected, $actual );
    }

    public function test_html_minify_html_comments_inside_script_blocks_old_school_pattern_untouched()
    {
        $markup = <<<MARKUP
<script>
<!-- // invisible for old browsers
var a = "z";
// -->
</script>
MARKUP;

        $expected = <<<MARKUP
<script><!-- // invisible for old browsers
var a = "z";
// --></script>
MARKUP;

        $options = [
            'autoptimizeHTML' => [
                'keepcomments' => true,
            ],
        ];

        $instance = new autoptimizeHTML( $markup );
        $instance->read( $options['autoptimizeHTML'] );
        $instance->minify();
        $actual = $instance->getcontent();
        $this->assertEquals( $expected, $actual );
    }
}
