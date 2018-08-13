=== Autoptimize Beta ===
Contributors: futtta, optimizingmatters, zytzagoo, turl
Tags: optimize, minify, performance, pagespeed, google fonts, images
Donate link: http://blog.futtta.be/2013/10/21/do-not-donate-to-me/
Requires at least: 4.0
Tested up to: 4.9
Requires PHP: 5.3
Stable tag: 2.4.0-beta-4

Autoptimize (Beta) speeds up your website by optimizing JS, CSS, HTML, Google Fonts and images, async-ing JS, removing emoji cruft and more.

== Description ==

Autoptimize makes optimizing your site really easy. It can aggregate, minify and cache scripts and styles, injects CSS in the page head by default (but can also defer), moves and defers scripts to the footer and minifies HTML. The "Extra" options allow you to optimize Google Fonts and images, async non-aggregated JavaScript, remove WordPress core emoji cruft and more. As such it can improve your site's performance even when already on HTTP/2! There is extensive API available to enable you to tailor Autoptimize to each and every site's specific needs.

If you consider performance important, you really should use one of the many caching plugins to do page caching. Some good candidates to complement Autoptimize that way are e.g. [WP Super Cache](http://wordpress.org/plugins/wp-super-cache/), [HyperCache](http://wordpress.org/plugins/hyper-cache/), [Comet Cache](https://wordpress.org/plugins/comet-cache/) or [KeyCDN's Cache Enabler](https://wordpress.org/plugins/cache-enabler).

> <strong>Premium Support</strong><br>
> We provide great [Autoptimize Pro Support and Web Performance Optimization services](https://autoptimize.com/), check out our offering on [https://autoptimize.com/](https://autoptimize.com/)!

(Speed-surfing image under creative commons [by LL Twistiti](https://www.flickr.com/photos/twistiti/818552808/))

== Installation ==

Just install from your WordPress "Plugins > Add New" screen and all will be well. Manual installation is very straightforward as well:

1. Upload the zip file and unzip it in the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to `Settings > Autoptimize` and enable the options you want. Generally this means "Optimize HTML/ CSS/ JavaScript".

== Frequently Asked Questions ==

= What does the plugin do to help speed up my site? =

It concatenates all scripts and styles, minifies and compresses them, adds expires headers, caches them, and moves styles to the page head, and scripts (optionally) to the footer. It also minifies the HTML code itself, making your page really lightweight.

= But I'm on HTTP/2, so I don't need Autoptimize? =

HTTP/2 is a great step forward for sure, reducing the impact of multiple requests from the same server significantly by using the same connection to perform several concurrent requests. That being said, [concatenation of CSS/ JS can still make a lot of sense](http://engineering.khanacademy.org/posts/js-packaging-http2.htm), as described in [this css-tricks.com article](https://css-tricks.com/http2-real-world-performance-test-analysis/) and this [blogpost from one of the Ebay engineers](http://calendar.perfplanet.com/2015/packaging-for-performance/). The conclusion; configure, test, reconfigure, retest, tweak and look what works best in your context. Maybe it's just HTTP/2, maybe it's HTTP/2 + aggregation and minification, maybe it's HTTP/2 + minification (which AO can do as well, simply untick the "aggregate JS-files" and/ or "aggregate CSS-files" options).

= Will this work with my blog? =

Although Autoptimize comes without any warranties, it will in general work flawlessly if you configure it correctly. See "Troubleshooting" below for info on how to configure in case of problems.

= Why is jquery.js not optimized =

Starting from AO 2.1 WordPress core's jquery.js is not optimized for the simple reason a lot of popular plugins inject inline JS that is not aggregated either (due to possible cache size issues with unique code in inline JS) which relies on jquery being available, so excluding jquery.js ensures that most sites will work out of the box. If you want optimize jquery as well, you can remove it from the JS optimization exclusion-list (you might have to enable "also aggregate inline JS" as well or switch to "force JS in head").

= Why is Autoptimized JS render blocking? =

If not "forced in head", Autoptimized JS is not render blocking as it has the "defer" flag added. It is however possible another plugin removes the "defer"-flag. Speed Booster Pack was reported doing this, but [the behavior has not been confirmed yet](https://wordpress.org/support/topic/speed-booster-pack-autoptimized-js-defer-flag/).

= Why is the autoptimized CSS still called out as render blocking? =

With the default Autoptimize configuration the CSS is linked in the head, which is a safe default but has Google PageSpeed Insights complaining. You can look into "inline all CSS" (easy) or "inline and defer CSS" (better) which are explained in this FAQ as well.

= What is the use of "inline and defer CSS"? =

CSS in general should go in the head of the document. Recently a.o. Google started promoting deferring non-essential CSS, while inlining those styles needed to build the page above the fold. This is especially important to render pages as quickly as possible on mobile devices. As from Autoptimize 1.9.0 this is easy; select "inline and defer CSS", paste the block of "above the fold CSS" in the input field (text area) and you're good to go!

= But how can one find out what the "above the fold CSS" is? =

There's no easy solution for that as "above the fold" depends on where the fold is, which in turn depends on screensize. There are some tools available however, which try to identify just what is "above the fold". [This list of tools](https://github.com/addyosmani/above-the-fold-css-tools) is a great starting point. The [Sitelocity critical CSS generator](https://www.sitelocity.com/critical-path-css-generator) and [Jonas Ohlsson's criticalpathcssgenerator](http://jonassebastianohlsson.com/criticalpathcssgenerator/) are nice basic solutions and [http://criticalcss.com/](http://misc.optimizingmatters.com/partners/?from=faq&amp;partner=critcss) is a premium solution by the same Jonas Ohlsson. Alternatively [this bookmarklet](https://gist.github.com/PaulKinlan/6284142) (Chrome-only) can be helpful as well.

= Or should you inline all CSS? =

The short answer: probably not.

Back in the days CSS optimization was easy; put all CSS in your head, aggregating everything in one CSS-file per media-type and you were good to go. But ever since Google included mobile in PageSpeed Insights and started complaining about render blocking CSS, things got messy (see "deferring CSS" elsewhere in this FAQ). One of the solutions is inlining all your CSS, which as of Autoptimize 1.8.0 is supported.

Inlining all CSS has one clear advantage (better PageSpeed score) and one big disadvantage; your base HTML-page gets significantly bigger and if the amount of CSS is big, Pagespeed Insights will complain of "roundtrip times". Also when looking at a test that includes multiple requests (let's say 5 pages), performance will be worse, as the CSS-payload is sent over again and again whereas normally the separate CSS-files would not need to be sent any more as they would be in cache.

So the choice should be based on your answer to some site-specific questions; how much CSS do you have? How many pages per visit do your visitors request? If you have a lot of CSS or a high number of pages/ visit, it's probably not a good idea to inline all CSS.

You can find more information on this topic [in this blog post](http://blog.futtta.be/2014/02/13/should-you-inline-or-defer-blocking-css/).

= My cache is getting huge, doesn't Autoptimize purge the cache? =

Autoptimize does not have its proper cache purging mechanism, as this could remove optimized CSS/JS which is still referred to in other caches, which would break your site. Moreover a fast growing cache is an indication of [other problems you should avoid](http://blog.futtta.be/2016/09/15/autoptimize-cache-size-the-canary-in-the-coal-mine/).

Instead you can keep the cache size at an acceptable level by either:

* disactivating the "aggregate inline JS" and/ or "aggregate inline CSS" options
* excluding JS-variables (or sometimes CSS-selectors) that change on a per page (or per pageload) basis. You can read how you can do that [in this blogpost](http://blog.futtta.be/2014/03/19/how-to-keep-autoptimizes-cache-size-under-control-and-improve-visitor-experience/).

Despite above objections, there are 3rd party solutions to automatically purge the AO cache, e.g. using [this code](https://wordpress.org/support/topic/contribution-autoptimize-cache-size-under-control-by-schedule-auto-cache-purge/) or [this plugin](https://wordpress.org/plugins/bi-clean-cache/), but for reasons above these are to be used only if you really know what you're doing.

= "Clear cache" doesn't seem to work? =

When clicking the "Delete Cache" link in the Autoptimize dropdown in the admin toolbar, you might to get a "Your cache might not have been purged successfully". In that case go to Autoptimizes setting page and click the "Save changes & clear cache"-button.

Moreover don't worry if your cache never is down to 0 files/ 0KB, as Autoptimize (as from version 2.2) will automatically preload the cache immediately after it has been cleared to speed further minification significantly up.

= Can I still use Cloudflare's Rocket Loader? =

Cloudflare Rocket Loader is a pretty advanced but invasive way to make JavaScript non-render-blocking, which [Cloudflare still considers Beta](https://wordpress.org/support/topic/rocket-loader-breaking-onload-js-on-linked-css/#post-9263738). Sometimes Autoptimize & Rocket Loader work together, sometimes they don't. The best approach is to disable Rocket Loader, configure Autoptimize and re-enable Rocket Loader (if you think it can help) after that and test if everything still works.

At the moment (June 2017) it seems RocketLoader might break AO's "inline & defer CSS", which is based on [Filamentgroup’s loadCSS](https://github.com/filamentgroup/loadCSS), resulting in the deferred CSS not loading.

= I tried Autoptimize but my Google Pagespeed Scored barely improved =

Autoptimize is not a simple "fix my Pagespeed-problems" plugin; it "only" aggregates & minifies (local) JS & CSS and allows for some nice extra's as removing Google Fonts and deferring the loading of the CSS. As such Autoptimize will allow you to improve your performance (load time measured in seconds) and will probably also help you tackle some specific Pagespeed warnings. If you want to improve further, you will probably also have to look into e.g. page caching, image optimization and your webserver configuration, which will improve real performance (again, load time as measured by e.g. https://webpagetest.org) and your "performance best practise" pagespeed ratings.

= What can I do with the API? =

A whole lot; there are filters you can use to conditionally disable Autoptimize per request, to change the CSS- and JS-excludes, to change the limit for CSS background-images to be inlined in the CSS, to define what JS-files are moved behind the aggregated one, to change the defer-attribute on the aggregated JS script-tag, ... There are examples for some filters in autoptimize_helper.php_example and in this FAQ.

= How does CDN work? =

Starting from version 1.7.0, CDN is activated upon entering the CDN blog root directory (e.g. http://cdn.example.net/wordpress/). If that URL is present, it will used for all Autoptimize-generated files (i.e. aggregated CSS and JS), including background-images in the CSS (when not using data-uri's).

If you want your uploaded images to be on the CDN as well, you can change the upload_url_path in your WordPress configuration (/wp-admin/options.php) to the target CDN upload directory (e.g. http://cdn.example.net/wordpress/wp-content/uploads/). Do take into consideration this only works for images uploaded from that point onwards, not for images that already were uploaded. Thanks to [BeautyPirate for the tip](http://wordpress.org/support/topic/please-don%c2%b4t-remove-cdn?replies=15#post-4720048)!

= Why aren't my fonts put on the CDN as well? =

Autoptimize supports this, but it is not enabled by default because [non-local fonts might require some extra configuration](http://davidwalsh.name/cdn-fonts). But if you have your cross-origin request policy in order, you can tell Autoptimize to put your fonts on the CDN by hooking into the API, setting `autoptimize_filter_css_fonts_cdn` to `true` this way;

`add_filter('autoptimize_filter_css_fonts_cdn',__return_true);`

= I'm using Cloudflare, what should I enter as CDN root directory =

Nothing, when on Cloudflare your autoptimized CSS/ JS is on the Cloudflare's CDN automatically.

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

Make sure you're not running other HTML, CSS or JS minification plugins (BWP minify, WP minify, ...) simultaneously with Autoptimize or disable that functionality your page caching plugin (W3 Total Cache, WP Fastest Cache, ...). Try enabling only CSS or only JS optimization to see which one causes the server error and follow the generic troubleshooting steps to find a workaround.

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

Disable the option to have Autoptimize active for logged on users and go crazy dragging and dropping ;-)

= Help, my shop checkout/ payment don't work!! =

Disable the option to optimize cart/ checkout pages (works for WooCommerce, Easy Digital Downloads and WP eCommerce).

= Revolution Slider is broken! =

Make sure `js/jquery/jquery.js` is in the comma-separated list of JS optimization exclusions (this is excluded in the default configuration).

= I'm getting "jQuery is not defined" errors =

In that case you have un-aggregated JavaScript that requires jQuery to be loaded, so you'll have to add `js/jquery/jquery.js` to the comma-separated list of JS optimization exclusions.

= I use NextGen Galleries and a lot of JS is not aggregated/ minified? =

NextGen Galleries does some nifty stuff to add JavaScript. In order for Autoptimize to be able to aggregate that, you can either disable Nextgen Gallery's resourced manage with this code snippet `add_filter( 'run_ngg_resource_manager', '__return_false' );` or you can tell Autoptimize to initialize earlier, by adding this to your wp-config.php: `define("AUTOPTIMIZE_INIT_EARLIER","true");`

= What is noptimize? =

Starting with version 1.6.6 Autoptimize excludes everything inside noptimize tags, e.g.:
`&lt;!--noptimize-->&lt;script>alert('this will not get autoptimized');&lt;/script>&lt;!--/noptimize-->`

You can do this in your page/ post content, in widgets and in your theme files (consider creating [a child theme](http://codex.wordpress.org/Child_Themes) to avoid your work being overwritten by theme updates).

= Can I change the directory & filename of cached autoptimize files? =

Yes, if you want to serve files from e.g. /wp-content/resources/aggregated_12345.css instead of the default /wp-content/cache/autoptimize/autoptimize_12345.css, then add this to wp-config.php:
`
define('AUTOPTIMIZE_CACHE_CHILD_DIR','/resources/');
define('AUTOPTIMIZE_CACHEFILE_PREFIX','aggregated_');
`

= Can the generated JS/ CSS be pre-gzipped? =

Yes, but this is off by default. You can enable this by passing ´true´ to ´autoptimize_filter_cache_create_static_gzip´. You'll obviously still have to configure your webserver to use these files instead of the non-gzipped ones to avoid the overhead of on-the-fly compression.

= What does "remove emojis" do? =

This new option in Autoptimize 2.3 removes the inline CSS, inline JS and linked JS-file added by WordPress core. As such is can have a small positive impact on your site's performance.

= Is "remove query strings" useful? =

Although some online performance assessement tools will single out "query strings for static files" as an issue for performance, in general the impact of these is almost non-existant. As such Autoptimize, since version 2.3, allows you to have the query string (or more precisely the "ver"-parameter) removed, but ticking "remove query strings from static resources" will have little or no impact of on your site's performance as measured in (milli-)seconds.

= (How) should I optimize Google Fonts? =

Google Fonts are typically loaded by a "render blocking" linked CSS-file. If you have a theme and plugins that use Google Fonts, you might end up with multiple such CSS-files. Autoptimize (since version 2.3) now let's you lessen the impact of Google Fonts by either removing them alltogether or by optimizing the way they are loaded. There are two optimization-flavors; the first one is "combine and link", which replaces all requests for Google Fonts into one request, which will still be render-blocking but will allow the fonts to be loaded immediately (meaning you won't see fonts change while the page is loading). The alternative is "combine and load async" which uses JavaScript to load the fonts in a non-render blocking manner but which might cause a "flash of unstyled text".

= Should I use "preconnect" =

Preconnect is a somewhat advanced feature to instruct browsers ([if they support it](https://caniuse.com/#feat=link-rel-preconnect)) to make a connection to specific domains even if the connection is not immediately needed. This can be used e.g. to lessen the impact of 3rd party resources on HTTPS (as DNS-request, TCP-connection and SSL/TLS negotiation are executed early). Use with care, as preconnecting to too many domains can be counter-productive.

= When can('t) I async JS? =

JavaScript files that are not autoptimized (because they were excluded or because they are hosted elsewhere) are typically render-blocking. By adding them in the comma-separated "async JS" field, Autoptimize will add the async flag causing the browser to load those files asynchronously (i.e. non-render blocking). This can however break your site (page), e.g. if you async "js/jquery/jquery.js" you will very likely get "jQuery is not defined"-errors. Use with care.

= Where can I get help? =

You can get help on the [wordpress.org support forum](http://wordpress.org/support/plugin/autoptimize). If you are 100% sure this your problem cannot be solved using Autoptimize configuration and that you in fact discovered a bug in the code, you can [create an issue on GitHub](https://github.com/futtta/autoptimize/issues). If you're looking for premium support, check out our [Autoptimize Pro Support and Web Performance Optimization services](http://autoptimize.com/).

= I want out, how should I remove Autoptimize? =

* Disable the plugin (this will remove options and cache)
* Remove the plugin
* Clear any cache that might still have pages which reference Autoptimized CSS/JS (e.g. of a page caching plugin such as WP Super Cache)

= How can I help/ contribute? =

Just [fork Autoptimize on Github](https://github.com/futtta/autoptimize) and code away!

== Changelog ==

= 2.4.0 beta 4 =

* edging closer to release (but we'll have at least one more beta)
* misc smaller improvements & fixes (see [GitHub changelog](https://github.com/futtta/autoptimize/commits/beta))

= 2.4.0 beta 3 =

* new: image optimization (see "Extra"-tab) using Shortpixel's smart image optimization proxy
* misc. under the hood improvements (e.g. more robust cache clearing, better support for multibyte character sets, improved CDN rewrite logic, avoid PHP warnings when writing files to cache, ...)

= 2.4.0 beta 2 =

* new: Autoptimize "listens" to page caches being cleared, upon which it purges it's own cache as well. Support depends on known action hooks firing by the page cache, supported by Hyper Cache, WP Rocket, W3 Total Cache, KeyCDN Cache Enabler, support confirmed coming in WP Fastest Cache and Comet Cache.
* new: Google Fonts can now be "aggregated & preloaded", this uses CSS which is loaded non render-blocking
* improvement: "remove all Google Fonts" is now more careful (avoiding removing entire CSS blocks)

= 2.4.0 beta 1 =

* first release as "Autoptimize Beta"
* refactored significantly (no more "classlesses", all is OO), classes are autoloaded, tests added (travis-ci) by zytzagoo
* updated minifiers (with very significant improvements for YUI CSS compressor PHP port)
* you can now disable JS/ CSS-files being aggregated, having them minified individually instead
* local JS/ CSS-files that are excluded from optimization are minified by default (can be overridden by filter)
