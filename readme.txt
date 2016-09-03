=== Autoptimize ===
Contributors: futtta, turl, optimizingmatters
Tags: css, html, javascript, js, optimize, speed, cache, aggregate, minimize, minification, performance, pagespeed
Donate link: http://blog.futtta.be/2013/10/21/do-not-donate-to-me/
Requires at least: 4.0
Tested up to: 4.6
Stable tag: 2.1.0

Autoptimize speeds up your website and helps you save bandwidth by aggregating and minimizing JS, CSS and HTML.

== Description ==

Autoptimize makes optimizing your site really easy. It concatenates all scripts and styles, minifies and compresses them, adds expires headers, caches them, and moves styles to the page head and can move scripts to the footer. It also minifies the HTML code itself, making your page really lightweight. There are advanced options and an extensive API available to enable you to tailor Autoptimize to each and every site's specific needs.

If you consider performance important, you really should use one of the many caching plugins to do page caching. Some good candidates to complement Autoptimize that way are e.g. [WP Super Cache](http://wordpress.org/extend/plugins/wp-super-cache/), [HyperCache](http://wordpress.org/extend/plugins/hyper-cache/), [Comet Cache](https://wordpress.org/plugins/comet-cache/) or [KeyCDN's Cache Enabler](https://wordpress.org/plugins/cache-enabler).

(Speed-surfing image under creative commons [by LL Twistiti](https://www.flickr.com/photos/twistiti/818552808/))

== Installation ==

Just install from your WordPress "Plugins > Add New" screen and all will be well. Manual installation is very straightforward as well:

1. Upload the zip file and unzip it in the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to `Settings > Autoptimize` and enable the options you want. Generally this means "Optimize HTML/ CSS/ JavaScript".

== Frequently Asked Questions ==

= What does the plugin do to help speed up my site? =

It concatenates all scripts and styles, minifies and compresses them, adds expires headers, caches them, and moves styles to the page head, and scripts (optionally) to the footer. It also minifies the HTML code itself, making your page really lightweight.

= Will this work with my blog? =

Although Autoptimize comes without any warranties, it will in general work flawlessly if you configure it correctly. See "Troubleshooting" below for info on how to configure in case of problems.

= What is the use of "inline and defer CSS"? =

CSS in general should go in the head of the document. Recently a.o. Google started promoting deferring non-essential CSS, while inlining those styles needed to build the page above the fold. This is especially important to render pages as quickly as possible on mobile devices. As from Autoptimize 1.9.0 this is easy; select "inline and defer CSS", paste the block of "above the fold CSS" in the input field (text area) and you're good to go!

= But how can one find out what the "above the fold CSS" is? =

There's no easy solution for that as "above the fold" depends on where the fold is, which in turn depends on screensize. There are some tools available however, which try to identify just what is "above the fold". [This list of tools](https://github.com/addyosmani/above-the-fold-css-tools) is a great starting point. [http://jonassebastianohlsson.com/criticalpathcssgenerator/](http://jonassebastianohlsson.com/criticalpathcssgenerator/) is a nice basic solution and [http://criticalcss.com/](http://misc.optimizingmatters.com/partners/?from=faq&amp;partner=critcss) is a premium solution by the same developer. Alternatively [this bookmarklet](https://gist.github.com/PaulKinlan/6284142) (Chrome-only) can be helpful as well.

= Or should you inline all CSS? =

The short answer: probably not (but I do).

Back in the days CSS optimization was easy; put all CSS in your head, aggregating everything in one CSS-file per media-type and you were good to go. But ever since Google included mobile in PageSpeed Insights and started complaining about render blocking CSS, things got messy (see "deferring CSS" elsewhere in this FAQ). One of the solutions is inlining all your CSS, which as of Autoptimize 1.8.0 is supported.

Inlining all CSS has one clear advantage (better PageSpeed score) and one big disadvantage; your base HTML-page gets significantly bigger and if the amount of CSS is big, Pagespeed Insights will complain of "roundtrip times". Also when looking at a test that includes multiple requests (let's say 5 pages), performance will be worse, as the CSS-payload is sent over again and again whereas normally the separate CSS-files would not need to be sent any more as they would be in cache.

So the choice should be based on your answer to some site-specific questions; how much CSS do you have? How many pages per visit do your visitors request? If you have a lot of CSS o a high number of pages/ visit, it's probably not a good idea to inline all CSS. But I do (as I have a low amount of average requests/ visitor and only a small amount of CSS as I use a pretty simple theme).

You can find more information on this topic [in this blog post](http://blog.futtta.be/2014/02/13/should-you-inline-or-defer-blocking-css/).

= My cache is getting huge, doesn't Autoptimize purge the cache? =

Autoptimize does not have its proper cache purging mechanism, as this could remove optimized CSS/JS which is still referred to in other caches, which would break your site. As from version 2.0.0 Autoptimize will display a notice on the administration pages if the cache size surpasses the half a Gigabyte mark.

You can keep the cache size at an acceptable level by either:

* disactivating the "aggregate inline JS" and/ or "aggregate inline CSS" options
* excluding JS-variables (or sometimes CSS-selectors) that change on a per page (or per pageload) basis. You can read how you can do that [in this blogpost](http://blog.futtta.be/2014/03/19/how-to-keep-autoptimizes-cache-size-under-control-and-improve-visitor-experience/).

= Where is the "look only in head" option? =

While "look only in head" still works, it is now (since Autoptimize 2.0.0) no longer visible on the settings-page if it is not active. As long as the option is active (for JS or CSS), it will however remain visible until you deactivate it. If you're comfortable with PHP, there still are filters available to keep on using "look only in head".

= So should I aggregate inline CSS/ JS? =

Before Autoptimize 2.0.0, inline code was always optimized with all CSS pushed in the head-section and all JS at the end with a defer-flag. This often caused 2 problems; the priority of inline CSS got lost and inline JS could contain page- or request-specific code which broke Autoptimize's caching mechanism leading to too many cached files and the minification running over and over. This is why as from 2.0.0 by default inline code is not optimized (except for those upgrading from previous versions). Additionally, to avoid inline JS breaking because the optimized JS is not available, JS is forced in head by default. If you want to squeeze out as much performance as possible, you should indeed tick the "aggregate inline"-options and disable "force JS into head", while off course keeping an eye out for the disadvantages listed above.

= What can I do with the API? =

A whole lot; there are filters you can use to conditionally disable Autoptimize per request, to change the CSS- and JS-excludes, to change the limit for CSS background-images to be inlined in the CSS, to define what JS-files are moved behind the aggregated one, to change the defer-attribute on the aggregated JS script-tag, ... There are examples for many filters in autoptimize_helper.php_example and in this FAQ.

= How can I use the code in autoptimize_helper.php_example? =

Although you could add the code to your theme's functions.php, it would get overwritten with your next theme update. Therefor it is better to either create a helper plugin of your own or to simply use [the Code Snippets plugin](https://wordpress.org/plugins/code-snippets/) to manage any custom code.

= Why is jquery.js not optimized =

Starting from AO 2.1 WordPress core's jquery.js is not optimized for the simple reason a lot of popular plugins inject inline JS that is not aggregated either (due to possible cache size issues with unique code in inline JS) which relies on jquery being available, so excluding jquery.js ensures that most sites will work out of the box. If you want optimize jquery as well, you can remove it from the JS optimization exclusion-list (you might have to enable "also aggregate inline JS" as well or switch to "force JS in head").

= How does CDN work? =

Starting from version 1.7.0, CDN is activated upon entering the CDN blog root directory (e.g. http://cdn.example.net/wordpress/). If that URL is present, it will used for all Autoptimize-generated files (i.e. aggregated CSS and JS), including background-images in the CSS (when not using data-uri's).

If you want your uploaded images to be on the CDN as well, you can change the upload_url_path in your WordPress configuration (/wp-admin/options.php) to the target CDN upload directory (e.g. http://cdn.example.net/wordpress/wp-content/uploads/). Do take into consideration this only works for images uploaded from that point onwards, not for images that already were uploaded. Thanks to [BeautyPirate for the tip](http://wordpress.org/support/topic/please-don%c2%b4t-remove-cdn?replies=15#post-4720048)!

= Why aren't my fonts put on the CDN as well? =

Autoptimize supports this, but it is not enabled by default because [non-local fonts might require some extra configuration](http://davidwalsh.name/cdn-fonts). But if you have your cross-origin request policy in order, you can tell Autoptimize to put your fonts on the CDN by hooking into the API, setting `autoptimize_filter_css_fonts_cdn` to `true` this way;

`add_filter('autoptimize_filter_css_fonts_cdn',__return_true);`

= How can I force the aggregated files to be static CSS or JS instead of PHP? =

If your webserver is properly configured to handle compression (gzip or deflate) and cache expiry (expires and cache-control with sufficient cacheability), you don't need Autoptimize to handle that for you. In that case you can check the "Save aggregated script/css as static files?"-option, which will force Autoptimize to save the aggregated files as .css and .js-files (meaning no PHP is needed to serve these files). This setting is default as of Autoptimize 1.8.

= How does "exclude from optimizing" work? =

Both CSS and JS optimization can skip code from being aggregated and minimized by adding "identifiers" to the comma-separated exclusion list. The exact identifier string to use can be determined this way:

* if you want to exclude a specific file, e.g. wp-content/plugins/funkyplugin/css/style.css, you could simply exclude "funkyplugin/css/style.css"
* if you want to exclude all files of a specific plugin, e.g. wp-content/plugins/funkyplugin/js/*, you can exclude for example "funkyplugin/js/" or "plugins/funkyplugin"
* if you want to exclude inline code, you'll have to find a specific, unique string in that block of code and add that to the exclusion list. Example: to exclude `<script>funky_data='Won\'t you take me to, Funky Town'</script>`, the identifier is "funky_data".

= Configuring & Troubleshooting Autoptimize =

After having installed and activated the plugin, you'll have access to an admin page where you can to enable HTML, CSS and JavaScript optimization. According to your liking, you can start of just enabling all of them, or if you're more cautious one at a time. 

If your blog doesn't function normally after having turned on Autoptimize, here are some pointers to identify & solve such issues using "advanced settings":

* If all works but you notice your blog is slower, ensure you have a page caching plugin installed (WP Super Cache or similar) and check the info on cache size (the solution for that problem also impacts performance for uncached pages) in this FAQ as well.
* In case your blog looks weird, i.e. when the layout gets messed up, there is problem with CSS optimization. In this case you can turn on the option "Look for styles on just head?" and see if that solves the problem. You can also force CSS not to be aggregated by wrapping it in noptimize-tags in your theme or widget or by adding filename (for external stylesheets) or string (for inline styles) to the exclude-list.
* In case some functionality on your site stops working (a carroussel, a menu, the search input, ...) you're likely hitting JavaScript optimization trouble. Change the "Aggregate inline JS" and/ or "Force JavaScript in head?" settings and try again. Excluding 'js/jquery/jquery.js' from optimization (see below) and optionally activating "[Add try/catch wrapping](http://blog.futtta.be/2014/08/18/when-should-you-trycatch-javascript/)") can also help. Alternatively -for the technically savvy- you can exclude specific scripts from being treated (moved and/ or aggregated) by Autoptimize by adding a string that will match the offending Javascript or excluding it from within your template files or widgets by wrapping the code between noptimize-tags. Identifying the offending JavaScript and choosing the correct exclusion-string can be trial and error, but in the majority of cases JavaScript optimization issues can be solved this way. When debugging JavaScript issues, your browsers error console is the most important tool to help you understand what is going on.
* If your theme or plugin require jQuery, you can try either forcing all in head and/ or excluding jquery.js (and jQuery-plugins if needed).
* If you can't get either CSS or JS optimization working, you can off course always continue using the other two optimization-techniques.
* If you tried the troubleshooting tips above and you still can't get CSS and JS working at all, you can ask for support on the [WordPress Autoptimize support forum](http://wordpress.org/support/plugin/autoptimize). See below for a description of what information you should provide in your "trouble ticket"

= Help, I have a blank page or an internal server error after enabling Autoptimize!! =

First of all make sure you're not running other HTML, CSS or JS minification plugins (BWP minify, WP minify, ...) simultaneously with Autoptimize or disable that functionality your page caching plugin (W3 Total Cache, WP Fastest Cache, ...).

In some rare cases the [CSS minification component](https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port/) currently used by Autoptimize crashes due to a lack of resources (see [detailed technical explanation here](http://blog.futtta.be/2014/01/14/irregular-expressions-have-your-stack-for-lunch/)). You can in that case either disable CSS optimization, try to exclude specific CSS from being aggregated or activate the legacy minifiers which don't have that problem. The latter can be accomplished by adding this to your wp-config.php:

`define("AUTOPTIMIZE_LEGACY_MINIFIERS","true");`

The "legacy minifiers" will remain in Autoptimize "for ever" and changes to wp-config.php are not affected by core-, theme- or plugin-upgrades so you should be good to go.

= But I still have blank autoptimized CSS or JS-files! =

If you are running Apache, the htaccess file written by Autoptimize can in some cases conflict with the AllowOverrides settings of your Apache configuration (as is the case with the default configuration of some Ubuntu installations), which results in "internal server errors" on the autoptimize CSS- and JS-files. This can be solved by [setting AllowOverrides to All](http://httpd.apache.org/docs/2.4/mod/core.html#allowoverride).

= I get no error, but my pages are not optimized at all? =

Autoptimize does a number of checks before actually optimizing. When one of the following is true, your pages won't be optimized:
* when in the customizer
* if there is no opening `<html` tag
* if there is `<xsl:stylesheet` in the response (indicating the output is not HTML but XML)
* if there is `<html amp` in the response (as AMP-pages are optimized already)
* if the output is an RSS-feed (is_feed() function)
* if the output is a WordPress administration page (is_admin() function)
* if the page is requested with ?ao_noptimize=1 appended to the URL
* if code hooks into Autoptimize to disable optimization (see topic on Visual Composer)
* if other plugins use the output buffer in an incompatible manner (disable other plugins selectively to identify the culprit)

= Visual Composer, Beaver Builder and similar page builder solutions are broken!! =

These page builder plugins run on the frontend for logged in users and are very JavaScript intensive and should not be optimized. You can tell Autoptimize not to act on these page-builder powered pages for logged in users with this code:

`
add_filter('autoptimize_filter_noptimize','pagebuilder_noptimize',10,0);
function pagebuilder_noptimize() {
  if (is_user_logged_in()) {
	return true;
  } else {
	return false;
  }
}
`

= Revolution Slider is broken! =

You can fix this by adding `js/jquery/jquery.js` to the comma-separated list of JS optimization exclusion.

= I'm getting "jQuery is not defined" errors =

In that case you have un-aggregated JavaScript that requires jQuery to be loaded, so you'll have to either aggregate that JavaScript (ticking the "also aggregate inline JS"-option) or add `js/jquery/jquery.js` to the comma-separated list of JS optimization exclusions.

= My Autoptimized CSS/ JS is broken after upgrading from 1.9.4 to 2.0! =

One of the bigger changes in Autoptimize 2.0 is that files that have "min.js" or "min.css" in their name are considered already minified and are only injected into the aggregated code after the actual minification, because this has an important performance-benefit. Although this has been tested rather thoroughly, it is possible that this approach does not always work. You can turn this behavior off by hooking into Autoptimize's API, like this;

`
add_filter('autoptimize_filter_js_inject_min_late',__return_false);
add_filter('autoptimize_filter_css_inject_min_late',__return_false);
`

Obviously you can choose to do this for only CSS, JS or both (as in example).

= I use NextGen Galleries and a lot of JS is not aggregated/ minified? =

NextGen Galleries does some nifty stuff to add JavaScript. In order for Autoptimize to be able to aggregate that, you'll need to tell it to initialize earlier, by adding this to your wp-config.php:
`define("AUTOPTIMIZE_INIT_EARLIER","true");`

= What is noptimize? =

Starting with version 1.6.6 Autoptimize excludes everything inside noptimize tags, e.g.:
`<!--noptimize--><script>alert('this will not get autoptimized');</script><!--/noptimize-->`

You can do this in your page/ post content, in widgets and in your theme files (consider creating [a child theme](http://codex.wordpress.org/Child_Themes) to avoid your work being overwritten by theme updates).

= Can I change the directory & filename of cached autoptimize files? =

Yes, if you want to serve files from e.g. /wp-content/resources/aggregated_12345.css instead of the default /wp-content/cache/autoptimize/autoptimize_12345.css, then add this to wp-config.php: 
`
define('AUTOPTIMIZE_CACHE_CHILD_DIR','/resources/');
define('AUTOPTIMIZE_CACHEFILE_PREFIX','aggregated_');
`

= Can the generated JS/ CSS be pre-gzipped? =

Yes, but this is off by default. You can enable this by passing ´true´ to ´autoptimize_filter_cache_create_static_gzip´. You'll obviously still have to configure your webserver to use these files instead of the non-gzipped ones to avoid the overhead of on-the-fly compression.

= Where can I report an error? =

You can report problems on the [wordpress.org support forum](http://wordpress.org/support/plugin/autoptimize). If you are 100% sure this your problem cannot be solved using Autoptimize configuration and that you in fact discovered a bug in the code, you can [create an issue on GitHub](https://github.com/futtta/autoptimize/issues).

= What information should I include when requesting support =

* A description of the problem, including screenshots and information from your browser's Error/ debug console
* URL of your blog (you can turn Autoptimize off, but should be willing to turn it briefly on to have the error visible)
* your Autoptimize settings (including a description of changes you made to the configuration to try to troubleshoot yourself)
* the Theme used (including the Theme's download link)
* optionally plugins used (if you suspect one or more plugins are raising havoc)

= I want out, how should I remove Autoptimize? =

* Disable the plugin (this will remove options and cache)
* Remove the plugin
* Clear any cache that might still have pages which reference Autoptimized CSS/JS (e.g. of a page caching plugin such as WP Super Cache)

= How can I help/ contribute? =

Just [fork Autoptimize on Github](https://github.com/futtta/autoptimize) and code away!

== Changelog ==

= 2.1.0 =
* new: Autoptimize now appears in admin-toolbar with an easy view on cache size and the possibility to purge the cache (pass `false` to `autoptimize_filter_toolbar_show` filter to disable), a big thanks to [Pablo Custo](https://github.com/pablocusto) for his hard work on this nice feature!
* new: An extra "More Optimization"-tab is shown (can be hidden with ´autoptimize_filter_show_partner_tabs´-filter) with information about related optimization tools- and services.
* new: If cache size becomes too big, a mail will be sent to the site admin (pass `false` to `autoptimize_filter_cachecheck_sendmail` filter to disable or pass alternative email to the `autoptimize_filter_cachecheck_mailto` filter to change email-address)
* new: power-users can enable Autoptimize to pre-gzip the autoptimized files by passing `true` to `autoptimize_filter_cache_create_static_gzip`, kudo's to (Draikin)[https://github.com/Draikin] for this!
* improvement: admin GUI updated (again; thanks Pablo!) with some responsiveness added in the mix (not showing the right hand column on smaller screen-sizes)
* improvement: settings-screen now accepts protocol-relative URL for CDN base URL
* improvement: new (smarter) defaults for JS (don't force in head + exclude jquery.js) and CSS optimization (include inline CSS)
* Misc. bugfixes & small improvements (see [commit-log on GitHub](https://github.com/futtta/autoptimize/commits/master))
* Minimal version updated from 2.7 (!) to 4.0
* Tested and confirmed working on WordPress 4.6

= 2.0.2 =
* bugfix: disallow moving non-aggregated JS by default (can be re-enabled by passing false to the `autoptimize_filter_js_unmovable`)
* bugfix: hook autoptimize_action_cachepurged into init to avoid ugly error-message for ZenCache (Comet Cache) users
* bugfix to allow for Autoptimize to work with PHP 5.2 (although [you really should upgrade](http://blog.futtta.be/2016/03/15/why-would-you-still-be-on-php-5-2/))

= 2.0.1 =
* Improvement: Autoptimize now also tries to purge WP Engine cache when AO's cache is cleared
* Improvement: for AMP pages (which are pretty optimized anyway) Autoptimize will not optimize to avoid issues with e.g. "inline & defer" and with AO adding attributes to link-tags that are not allowed in the subset of HTML that AMP is
* Improvement: refactored the page cache purging mechanism (removing duplicate code, now nicely hooking into AO's own `autoptimize_action_cachepurged` action)
* Improvement: Re-enable functionality to move non-aggregated JS if "also aggregate inline JS" is active (can be disabled with `autoptimize_filter_js_unmovable` filter)
* Improvement: script tags with `data-noptimize` attribute will be excluded from optimization
* Bugfix: Better support for renamed wp-content directories
* Bugfix: Multiple fixes for late-injected CSS/ JS (changes in those files were not always picked up, fonts or background images were not being CDN'ed, ...)
* Misc. other fixes & improvements, go read [the commit-log on GitHub](https://github.com/futtta/autoptimize/commits/master) if you're that curious
* Tested & confirmed working with WordPress 4.5 (beta 3)

= 2.0.0 =
* On average 30% faster minification (more info [in this blogpost](http://blog.futtta.be/2015/12/22/making-autoptimize-faster/))!
* New: Option to (de-)activate aggregation of inline JS and CSS.
* New: Option to remove Google Fonts.
* New: Cache-size will be checked daily and a notice will be shown on wp-admin if cache size goes over 512 MB (can be changed by filter).
* New: Small autoptimized CSS (less then 256 characters, can be changed by filter) will be inlined instead of linked.
* New in API: filters to declare a JS and CSS whitelist, where only files in that whitelist are autoptimized and all others are left untouched.
* New in API: filters to declare removable CSS and JS, upon which Autoptimize will simply delete that code (emoji CSS/JS for example, if you prefer not to dequeue them).
* New in API: filter to move fonts to CDN as well.
* lots of small and bigger bugfixes, I won't bother you with a full list but have a look at [the commmit log on GitHub](https://github.com/futtta/autoptimize/commits/master).
* tested and confirmed working with PHP7

= 1.9.4 =
* bugfix: make sure non-AO CSSmin doesn't get fed 2 parameters (as some only expect one, which resulted in an internal server error), based on [feedback from zerooverture and zamba](https://wordpress.org/support/topic/error-code-500internal-server-error?replies=7)
* bugfix: make default add_action hook back into "template_redirect" instead of "init" to fix multiple problems as reported by [schecteracademicservices, bond138, rickenbacker](https://wordpress.org/support/topic/192-concatenated-js-but-193-does-not-for-me?replies=11), [Rick Sportel](https://wordpress.org/support/topic/version-193-made-plugin-wp-cdn-rewrite-crash?replies=3#post-6833159) and [wizray](https://wordpress.org/support/topic/the-page-loads-both-the-auto-combined-css-file-and-origin-raw-file?replies=11#post-6833146). If you do need Autoptimize to initialize earlier (e.g. when using Nextgen Galleries), then add this to your wp-config.php:
`define("AUTOPTIMIZE_INIT_EARLIER","true");`

= 1.9.3 =
* improvement: more intelligent CDN-replacement logic, thanks [Squazz for reporting and testing](https://wordpress.org/support/topic/enable-cdn-for-images-referenced-in-the-css?replies=9)
* improvement: allow strings (comments) to be excluded from HTML-optimization (comment removal)
* improvement: changed priority with which AO gets triggered by WordPress, solving JS not being aggregated when NextGen Galleries is active, with great [help from msebald](https://wordpress.org/support/topic/js-options-dont-work-if-html-disabled/)
* improvement: extra JS exclude-strings: gist.github.com, text/html, text/template, wp-slimstat.min.js, _stq, nonce, post_id (the latter two were removed from the "manual" exclude list on the settings-page)
* new in API: autoptimize_filter_html_exclude, autoptimize_filter_css_defer, autoptimize_filter_css_inline, autoptimize_filter_base_replace_cdn, autopitmize_filter_js_noptimize, autopitmize_filter_css_noptimize, autopitmize_filter_html_noptimize
* bugfix: remove some PHP notices, as [reported by dimitrov.adrian](https://wordpress.org/support/topic/php-errors-39)
* bugfix: make sure HTML-optimalization does not gobble a space before a cite [as proposed by ecdltf](https://wordpress.org/support/topic/%E2%80%9Coptimize-html%E2%80%9D-is-gobbling-whitespace-before-cite-tag)
* bugfix: cleaning the cache did not work on non-default directories as [encountered by NoahJ Champion](https://wordpress.org/support/topic/changing-the-wp-content-path-to-top-level?replies=10#post-6573657)
* upgraded to [yui compressor php port 2.4.8-4](https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port)
* added arabic translation, thanks to the [ekleel team](http://www.ekleel.net)
* tested with WordPress 4.2 beta 3

= 1.9.2 =
First of all; Happy holidays, all the best for 2015!!

* New: support for alternative cache-directory and file-prefix as requested by a.o. [Jassi Bacha](https://wordpress.org/support/topic/requesthelp-add-ability-to-specify-cache-folder?replies=1#post-6300128), [Cluster666](https://wordpress.org/support/topic/rewrite-js-path?replies=6#post-6363535) and Baris Unver.
* Improvement: hard-exclude all linked-data json objects (script type=application/ld+json)
* Improvement: several filters added to the API, e.g. to alter optimized HTML, CSS or JS
* Bugfix: set Autoptimize priority back from 11 to 2 (as previously) to avoid some pages not being optimized (thanks to [CaveatLector for investigating & reporting](https://wordpress.org/support/topic/wp-property-plugin-add_action-priority-incompatibility?replies=1))
* Bugfix (in YUI-CSS-compressor-PHP-port): don't convert bools to percentages in rotate3D-transforms (cfr. [bugreport on Github](https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port/issues/17))
* Bugfix: background images with a space in the path didn't load, [reported by johnh10](https://wordpress.org/support/topic/optimize-css-code-error-with-background-image-elements?replies=6#post-6201582).
* Bugfix: SVG image with fill:url broken after CSS optimization as [reported by Tkama](https://wordpress.org/support/topic/one-more-broblem-with-plugin?replies=2)
* Updated translation for Swedish, new translation for Ukrainian by [Zanatoly of SebWeo.com](http://SebWeo.com)
* Updated readme.txt
* Confirmed working with WordPress 4.1

= 1.9.1 =
* hard-exclude [the sidelink-search-box introduced in WP SEO v1.6](http://wordpress.org/plugins/wordpress-seo/changelog/) from JS optimization (this [broke some JS-optimization badly](http://wordpress.org/support/topic/190-breaks-js?replies=4))
* bugfix: first add semi-colon to inline script, only then add try-catch if required instead of the other way around.

= 1.9.0 =
* "Inline and defer CSS" allows one to specify which "above the fold CSS" should be inlined, while the normal optimized CSS is deferred.
* Inlined Base64-encoded background Images will now be cached as well and the threshold for inlining these images has been bumped up to 4096 bytes (from 2560).
* Separate cache-directories for CSS and JS in /wp-content/cache/autoptimize, which should result in faster cache pruning (and in some cases possibly faster serving of individual aggregated files).
* Autoptimized CSS is now injected before the <title>-tag, JS before </body> (and after </title> when forced in head). This can be overridden in the API.
* Some usability improvements of the administration-page
* Multiple hooks added to the API a.o. filters to not aggregate inline CSS or JS and filters to aggregate but not minify CSS or JS.
* Updated translations for Dutch, French, German, Persian and Polish and new translations for Brazilian Portuguese (thanks to [Leonardo Antonioli](http://tobeguarany.com/)) and Turkish (kudo's [Baris Unver](http://beyn.org/))
* Multiple bugfixes & improvements
* Tested with WordPress 4.0 rc3

= 1.8.5 =
* Updated to lastest version of [CSS minification component](https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port/)
* Improvement: for multi-sites the cache is now written to separate directories, avoiding one site to clean out the cache for the entire installation. Code [contributed by Joern Lund](http://wordpress.org/support/topic/multisite-blog-admin-can-delete-entire-network-cache), kudo's Joern!!
* Improvement: add WordPress plugin header to autoptimize_helper.php_example to make it easier to enable it as a module
* Improvement: nonce and post_id are added to default configuration for JS exclusion
* Improvement: explicitely exclude wp-admin from being Autoptimized
* Bugfix: plupload.min.js, syntaxhighlighter and "adsbygoogle" are excluded from JS aggregation.
* Bugfix: avoid double closing body-tags when Autoptimize adds JS to HTML as [reported by Can](http://wordpress.org/support/topic/works-like-a-charm-but-i-have-two-problems)
* Bugfix: make .htaccess compatible with both Apache 2.2 and 2.4 (http://wordpress.org/support/topic/feature-request-support-generating-htaccess-files-for-apache-24?replies=3)

= 1.8.4 =
* Bugfix: code in inline JS (or CSS) can be wrapped inside HTML-comments, but these got removed since 1.8.2 as part of a bugfix.

= 1.8.3 =
* Bugfix: avoid useless warnings on is_callable to flood php error log as [reported by Praveen Kumar](http://wordpress.org/support/topic/182-breaks-css-and-js?replies=14#post-5377604)

= 1.8.2 =
* Improvement: more graceful failure when minifier classes exist but method does not, based on [bug-report by Franck160](http://wordpress.org/support/topic/confict-with-dynamic-to-top)
* Improvement: deferred CSS is also outputted in noscript-tags
* Improvement: differentiate between Apache version in .htaccess file as suggested by [iMadalin](http://www.imadalin.ro/)
* Improvement: also aggregate protocol-less CSS/JS URI's (as [suggested by Ross](http://wordpress.org/support/topic/protocol-less-url-support))
* Improvement: disable autoptimization based on parameter in querystring (for debugging)
* Bugfix: some CSS-imports were not being aggregated/ minified
* Bugfix: add CSS before <title instead of <title> to avoid breakage when title includes other attributes (e.g. itemscope)
* Bugfix: make sure javascript or css between comments is not aggregated as reported by [Milap Gajjar](http://wordpress.org/support/topic/the-optimized-css-contains-duplicate-classes)
* Tested with WordPress 3.9 (beta 1)
* Updates in FAQ

= 1.8.1 =
* bugfix: CSS in conditional comments was not excluded from aggregation as reported by [Rolf](http://www.finkbeiner-holz.de/) and [bottapress](http://www.wordpress-hebergement.fr/)

= 1.8.0 =
* New: Option to inline all CSS [as suggested by Hamed](http://wordpress.org/support/topic/make-style-sheet-inline)
* New: set of filters to provide a simple API to change Autoptimize behavior (e.g. replace "defer" with "async", disabling Autoptimization on certain pages, specificy non-aggregatable script to be moved after aggregated one (cfr. http://wordpress.org/support/topic/feature-request-some-extra-options?replies=14), size of image to be data-urized). More info in the included autoptimize_helper.php_example.
* Improvement: exclude (css in) noscript-tags as [proposed by belg4mit](http://wordpress.org/support/topic/feature-suggestion-noscript-for-css)
* Improvement: switch default delivery of optimized CSS/JS-files from PHP to static files
* Updated [upstream CSS minifier](https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port/commit/fb33d2ffd0963692747101330b175a80173ce21b)
* Improvement (force gzip of static files) and Bugfix (force expiry for dynamic files, thanks to [Willem Razenberg](http://www.column-razenberg.nl/) in .htaccess
* Improvement: fail gracefully when things go wrong (e.g. CSS import resulting in empty aggregated CSS-files [reported by Danka](http://wordpress.org/support/topic/very-good-332) or when the theme is broken [as seen by Prateek Gupta](http://wordpress.org/support/topic/js-optimization-break-site-white-page-issue?replies=14#post-5038941))
* Updated translations and Polish added (thanks to [Jakub Sierpinski](http://www.sierpinski.pl/)).
* Bugfix: stop import-statements in CSS comments to be taken into acccount [hat tip to Josef from blog-it-solutions.de](http://www.blog-it-solutions.de/)
* Bugfix: fix for blur in CSS breakeage as [reported by Chris of clickpanic.com](http://blog.clickpanic.com/)

= 1.7.3 =
* improvement: remove cache + options on uninstall as [requested by Gingerbreadmen](http://wordpress.org/support/topic/wp_options-entries)
* improvement: set .htaccess to allow PHP execution in wp-content/cache/autoptimize when saving optimized files as PHP, as suggested by (David Mottershead of bermuda4u.com)[http://www.bermuda4u.com/] but forbid PHP execution when saving aggregated script/css as static files (except for multisite).
* bugfix: avoid Yoast SEO sitemaps going blank (due optimization of Yoast's dynamically built XML/XSL) as reported by [Vance Hallman](http://www.icefishing.co) and [Armand Hadife](http://solar-flag-pole-lights.com/). More info on this issue [can be found on my blog](http://blog.futtta.be/2013/12/09/blank-yoast-seo-sitemaps-no-more/).
* smaller changes to readme.txt

= 1.7.2 =
* improvement: extra checks in CSS @import-handling + move import rules to top of CSS if not imported successfully, based a.o. on bug reports [by ozum](http://wordpress.org/support/topic/zero-lenght-file-with-css-optimization) and by [Peter Stolwijk](http://wordpress.org/support/topic/cant-activate-plugin-22?replies=13#post-4891377)
* improvement: check if JS and CSS minifier classes exist and only load if they don't to avoid possible conflicts with other themes or plugins that already loaded minifiers
* tested and approved for WordPress 3.8 (beta1)

= 1.7.1 =
* New: support for mapped domains as suggested by [Michael for tiremoni.com](http://tiremoni.com/)
* Added an .htaccess to wp-content/cache/autoptimize to overwrite other caching directives (fixing a problem with WP Super Cache's .htaccess really, [as reported](http://wordpress.org/support/topic/expiresmax-age-compatibility-with-supercache) by [Hugh of www.unitedworldschools.org](http://www.unitedworldschools.org/))
* bugfix: Autoptimize broke data:uri's in CSS in some cases as reported by [Josef from blog-it-solutions.de](http://www.blog-it-solutions.de/)
* bugfix: avoid PHP notice if CSS exclusion list is empty
* moved "do not donate"-image into plugin

= 1.7.0 =
* New: exclude CSS
* New: defer CSS
* Updated minimizing components (JSMin & YUI PHP CSSMin)
* Updated admin-page, hiding advanced configuration options
* Updated CDN-support for added simplicity (code & UI-wise), including changing background image url in CSS
* Updated/ new translations provided for [French: wordpress-hebergement.fr](http://www.wordpress-hebergement.fr/), [Persian: Hamed Irani](http://basics.ir/), [Swedish: Jonathan Sulo](http://sulo.se/), [German: blog-it-solutions.de](http://www.blog-it-solutions.de/) and Dutch
* Removed support for YUI
* Flush HTML caching plugin's cache when flushing Autoptimize's one
* fix for BOM marker in CSS-files [as seen in Frontier theme](http://wordpress.org/support/topic/sidebar-problem-42), kudo's to [Download Converter](http://convertertoolz.com/) for reporting!
* fix for [protocol-less 3rd party scripts disappearing](http://wordpress.org/support/topic/javascript-optimize-breaks-twentythirteen-mobile-menu), thanks for reporting p33t3r!
* fix for stylesheets without type="text/css" not being autoptimized as reported by [renzo](http://cocobeanproductions.com/)
* tested with WordPress 3.7 beta2 (admin-bar.min.js added to automatically excluded scripts)

= 1.6.6 =
* New: disable autoptimizatoin by putting part of your HTML, JS or CSS in between noptimize-tags, e.g.;
`<!--noptimize--><script>alert('this will not get autoptimized');</script><!--/noptimize-->`
* Added extra check to prevent plugin-files being called outside of WordPress as suggested in [this good article on security](http://mikejolley.com/2013/08/keeping-your-shit-secure-whilst-developing-for-wordpress/).
* Added small notice to be displayed after installation/ activation to ask user to configure the plugin as well.
* Added Persian translation, thanks to [Hamed T.](http://basics.ir/)

= 1.6.5 =
* new javascript-debug option to force the aggregated javascript file in the head-section of the HTML instead of at the bottom
* YUI compression & CDN are now deprecated functionality that will be removed in 1.7.0

= 1.6.4 =
* fix for PHP notice about mfunc_functions
* fix for strpos warnings due to empty values from the "Exclude scripts from autoptimize" configuration as [reported by CandleFOREX](http://wordpress.org/support/topic/empty-needle-warning)
* fix for broken feeds as [reported by Dinata and talgalili](http://wordpress.org/support/topic/feed-issue-5)

= 1.6.3 =
* fix for IE-hacks with javascript inside, causing javascript breakage (as seen in Sampression theme) as reported by [Takahiro of hiskip.com](http://www.hiskip.com/wp/)
* fix for escaping problem of imported css causing css breakage (as seen in Sampression theme) as reported by Takahiro as well
* fix to parse imports with syntax @import 'custom.css' not being parsed (as seen in Arras theme), again as reported by Takahiro
* fix for complex media types in media-attribute [as reported by jvwisssen](http://wordpress.org/support/topic/autoptimize-and-media-queries)
* fix for disappearing background-images that were already datauri's [as reported by will.blaschko](http://wordpress.org/support/topic/data-uris)
* fix not to strip out comments in HTML needed by WP Super Cache or W3 Total Cache (e.g. mfunc)
* added check to clean cache on upgrade
* updated FAQ in readme with information on troubleshooting and support
* tested with WordPress 3.6 beta

= 1.6.2 =
* Yet another emergency bugfix I'm afraid: apache_request_headers (again in config/delayed.php) is only available on ... Apache (duh), breaking non-Apache systems such as ngnix, Lighttpd and MS IIS badly. Reported by multiple users, thanks all!

= 1.6.1 =
* fixed stupid typo in config/delayed.php which broke things badly (april fools-wise); strpos instead of str_pos as reported by Takahiro.

= 1.6.0 =
* You can now specify scripts that should not be Autoptimized in the admin page. Just add the names (or part of the path) of the scripts in a comma-separated list and that JavaScript-file will remain untouched by Autoptimize.
* Added support for ETag and LastModified (essentially for a better pagespeed score, as the files are explicitely cacheable for 1 year)
* Autoptimizing for logged in users is enabled again
* Autoptimize now creates an index.html in wp-content/cache/autoptimize to prevent snooping (as [proposed by Chris](http://blog.futtta.be/2013/01/07/adopting-an-oss-orphan-autoptimize/#li-comment-36292))
* bugfix: removed all deprecated functions ([reported by Hypolythe](http://wordpress.org/support/topic/many-deprecated-errors) and diff by Heiko Adams, thanks guys!)
* bugfix for HTTPS-problem as [reported by dbs121](http://wordpress.org/support/topic/woocommerce-autoptimizer-https-issue)
* bugfix for breakage with unusual WordPress directory layout as reported by [Josef from blog-it-solutions.de](http://www.blog-it-solutions.de/).

= 1.5.1 =
* bugfix: add CSS before opening title-tag instead of after closing title, to avoid CSS being loaded in wrong order, as reported by [fotofashion](http://fotoandfashion.de/) and [blogitsolutions](http://www.blog-it-solutions.de) (thanks guys)

= 1.5 =
* first bugfix release by [futtta](http://blog.futtta.be/2013/01/07/adopting-an-oss-orphan-autoptimize/), thanks for a great plugin Turl!
* misc bug fixes, a.o. support for Twenty Twelve theme, admin bar problem in WP3.5, data-uri breaking CSS file naming

= 1.4 =
* Add support for inline style tags with CSS media
* Fix Wordpress top bar

= 1.3 =
* Add workaround for TinyMCEComments
* Add workaround for asynchronous Google Analytics

= 1.2 =
* Add workaround for Chitika ads.
* Add workaround for LinkWithin widget.
* Belorussian translation

= 1.1 =
* Add workarounds for amazon and fastclick
* Add workaround for Comment Form Quicktags
* Fix issue with Vipers Video Quicktags
* Fix a bug in where some scripts that shouldn't be moved were moved
* Fix a bug in where the config page wouldn't appear
* Fix @import handling
* Implement an option to disable js/css gzipping
* Implement CDN functionality
* Implement data: URI generation for images
* Support YUI CSS/JS Compressor
* Performance increases
* Handle WP Super Cache's cache files better
* Update translations

= 1.0 =
* Add workaround for whos.among.us
* Support preserving HTML Comments. 
* Implement "delayed cache compression"
* French translation
* Update Spanish translation

= 0.9 =
* Add workaround for networkedblogs.
* Add workarounds for histats and statscounter
* Add workaround for smowtion and infolinks. 
* Add workaround for Featured Content Gallery
* Simplified Chinese translation
* Update Spanish Translation
* Modify the cache system so it uses wp-content/cache/
* Add a clear cache button

= 0.8 =
* Add workaround for Vipers Video Quicktags
* Support <link> tags without media.
* Take even more precautions so we don't break urls in CSS
* Support adding try-catch wrappings to JavaScript code
* Add workaround for Wordpress.com Stats
* Fix a bug in where the tags wouldn't move
* Update translation template
* Update Spanish translation

= 0.7 =
* Add fix for DISQUS Comment System.

= 0.6 =
* Add workaround for mybloglog, blogcatalog, tweetmeme and Google CSE 

= 0.5 =
* Support localization
* Fix the move and don't move system (again)
* Improve url detection in CSS
* Support looking for scripts and styles on just the header
* Fix an issue with data: uris getting modified
* Spanish translation

= 0.4 =
* Write plugin description in English
* Set default config to everything off
* Add link from plugins page to options page
* Fix problems with scripts that shouldn't be moved and were moved all the same

= 0.3 =
* Disable CSS media on @imports - caused an infinite loop

= 0.2 =
* Support CSS media
* Fix an issue in the IE Hacks preservation mechanism
* Fix an issue with some urls getting broken in CSS

= 0.1 =
* First released version.
