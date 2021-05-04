=== Autoptimize ===
Contributors: futtta, optimizingmatters, zytzagoo, turl
Tags: optimize, minify, performance, images, core web vitals, lazy-load, pagespeed, google fonts
Donate link: http://blog.futtta.be/2013/10/21/do-not-donate-to-me/
Requires at least: 4.9
Tested up to: 5.7
Requires PHP: 5.6
Stable tag: 2.8.4

Autoptimize speeds up your website by optimizing JS, CSS, images (incl. lazy-load), HTML and Google Fonts, asyncing JS, removing emoji cruft and more.

== Description ==

Autoptimize makes optimizing your site really easy. It can aggregate, minify and cache scripts and styles, injects CSS in the page head by default but can also inline critical CSS and defer the aggregated full CSS, moves and defers scripts to the footer and minifies HTML. You can optimize and lazy-load images (with support for WebP and AVIF formats), optimize Google Fonts, async non-aggregated JavaScript, remove WordPress core emoji cruft and more. As such it can improve your site's performance even when already on HTTP/2! There is extensive API available to enable you to tailor Autoptimize to each and every site's specific needs.
If you consider performance important, you really should use one of the many caching plugins to do page caching. Some good candidates to complement Autoptimize that way are e.g. [Speed Booster pack](https://wordpress.org/plugins/speed-booster-pack/), [KeyCDN's Cache Enabler](https://wordpress.org/plugins/cache-enabler), [WP Super Cache](http://wordpress.org/plugins/wp-super-cache/) or if you use Cloudflare [WP Cloudflare Super Page Cache](https://wordpress.org/plugins/wp-cloudflare-page-cache/).

> <strong>Premium Support</strong><br>
> We provide great [Autoptimize Pro Support and Web Performance Optimization services](https://autoptimize.com/), check out our offering on [https://autoptimize.com/](https://autoptimize.com/)!

(Speed-surfing image under creative commons [by LL Twistiti](https://www.flickr.com/photos/twistiti/818552808/))

== Installation ==

Just install from your WordPress "Plugins > Add New" screen and all will be well. Manual installation is very straightforward as well:

1. Upload the zip file and unzip it in the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to `Settings > Autoptimize` and enable the options you want. Generally this means "Optimize HTML/ CSS/ JavaScript".

== Frequently Asked Questions ==

= Do you offer or recommend a course on how to speed up WordPress/ use Autoptimize? =

There are many great resources online, both free and premium, but [the "Autoptimize Masterclass" by Load Labz](https://misc.optimizingmatters.com/partners/?from=faq&partner=loadlabz) stands out for the systematic and detailed approach in the video-based course. Have a look at the free sample class(es) and when interested make sure to use the `EarlyBird` coupon to get a discount!

= What does the plugin do to help speed up my site? =

It concatenates all scripts and styles, minifies and compresses them, adds expires headers, caches them, and moves styles to the page head, and scripts (optionally) to the footer. It also minifies the HTML code itself, making your page really lightweight.

= But I'm on HTTP/2, so I don't need Autoptimize? =

HTTP/2 is a great step forward for sure, reducing the impact of multiple requests from the same server significantly by using the same connection to perform several concurrent requests. That being said, [concatenation of CSS/ JS can still make a lot of sense](http://engineering.khanacademy.org/posts/js-packaging-http2.htm), as described in [this css-tricks.com article](https://css-tricks.com/http2-real-world-performance-test-analysis/) and this [blogpost from one of the Ebay engineers](http://calendar.perfplanet.com/2015/packaging-for-performance/). The conclusion; configure, test, reconfigure, retest, tweak and look what works best in your context. Maybe it's just HTTP/2, maybe it's HTTP/2 + aggregation and minification, maybe it's HTTP/2 + minification (which AO can do as well, simply untick the "aggregate JS-files" and/ or "aggregate CSS-files" options). And Autoptimize can do a lot more then "just" optimizing your JS & CSS off course ;-)

= Will this work with my blog? =

Although Autoptimize comes without any warranties, it will in general work flawlessly if you configure it correctly. See "Troubleshooting" below for info on how to configure in case of problems.

= Why is jquery.min.js not optimized =

Starting from AO 2.1 WordPress core's jquery.min.js is not optimized for the simple reason a lot of popular plugins inject inline JS that is not aggregated either (due to possible cache size issues with unique code in inline JS) which relies on jquery being available, so excluding jquery.min.js ensures that most sites will work out of the box. If you want optimize jquery as well, you can remove it from the JS optimization exclusion-list (you might have to enable "also aggregate inline JS" as well or switch to "force JS in head").

= Why is Autoptimized JS render blocking? =

If not "forced in head", Autoptimized JS is not render blocking as it has the "defer" flag added. It is however possible another plugin removes the "defer"-flag. Speed Booster Pack was reported doing this, but [the behavior has not been confirmed yet](https://wordpress.org/support/topic/speed-booster-pack-autoptimized-js-defer-flag/).

= Why is the autoptimized CSS still called out as render blocking? =

With the default Autoptimize configuration the CSS is linked in the head, which is a safe default but has Google PageSpeed Insights complaining. You can look into "inline all CSS" (easy) or "inline and defer CSS" (better) which are explained in this FAQ as well.

= What is the use of "inline and defer CSS"? =

CSS in general should go in the head of the document. Recently a.o. Google started promoting deferring non-essential CSS, while inlining those styles needed to build the page above the fold. This is especially important to render pages as quickly as possible on mobile devices. As from Autoptimize 1.9.0 this is easy; select "inline and defer CSS", paste the block of "above the fold CSS" in the input field (text area) and you're good to go!

= But how can one find out what the "above the fold CSS" is? =

There's no easy solution for that as "above the fold" depends on where the fold is, which in turn depends on screensize. There are some tools available however, which try to identify just what is "above the fold". [This list of tools](https://github.com/addyosmani/above-the-fold-css-tools) is a great starting point. The [Sitelocity critical CSS generator](https://www.sitelocity.com/critical-path-css-generator) and [Jonas Ohlsson's criticalpathcssgenerator](http://jonassebastianohlsson.com/criticalpathcssgenerator/) are nice basic solutions and [http://criticalcss.com/](http://misc.optimizingmatters.com/partners/?from=faq&amp;partner=critcss) is a premium solution by the same Jonas Ohlsson. Alternatively [this bookmarklet](https://gist.github.com/PaulKinlan/6284142) (Chrome-only) can be helpful as well.

= Or should you inline all CSS? =

The short answer: probably not. Although inlining all CSS will make the CSS non-render blocking, it will result in your base HTML-page getting significantly bigger thus requiring more "roundtrip times". Moreover when considering multiple pages being requested in a browsing session the inline CSS is sent over each time, whereas when not inlined it would be served from cache. Finally the inlined CSS will push the meta-tags in the HTML down to a position where Facebook or Whatsapp might not look for it any more, breaking e.g. thumbnails when sharing on these platforms.

= My cache is getting huge, doesn't Autoptimize purge the cache? =

Autoptimize does not have its proper cache purging mechanism, as this could remove optimized CSS/JS which is still referred to in other caches, which would break your site. Moreover a fast growing cache is an indication of [other problems you should avoid](http://blog.futtta.be/2016/09/15/autoptimize-cache-size-the-canary-in-the-coal-mine/).

Instead you can keep the cache size at an acceptable level by either:

* disactivating the "aggregate inline JS" and/ or "aggregate inline CSS" options
* excluding JS-variables (or sometimes CSS-selectors) that change on a per page (or per pageload) basis. You can read how you can do that [in this blogpost](http://blog.futtta.be/2014/03/19/how-to-keep-autoptimizes-cache-size-under-control-and-improve-visitor-experience/).

Despite above objections, there are 3rd party solutions to automatically purge the AO cache, e.g. using [this code](https://wordpress.org/support/topic/contribution-autoptimize-cache-size-under-control-by-schedule-auto-cache-purge/) or [this plugin](https://wordpress.org/plugins/bi-clean-cache/), but for reasons above these are to be used only if you really know what you're doing.

= "Clear cache" doesn't seem to work? =

When clicking the "Delete Cache" link in the Autoptimize dropdown in the admin toolbar, you might to get a "Your cache might not have been purged successfully". In that case go to Autoptimizes setting page and click the "Save changes & clear cache"-button.

Moreover don't worry if your cache never is down to 0 files/ 0KB, as Autoptimize (as from version 2.2) will automatically preload the cache immediately after it has been cleared to speed further minification significantly up.

= My site looks broken when I purge Autoptimize's cache! =

When clearing AO's cache, no page cache should contain pages (HTML) that refers to the removed optimized CSS/ JS. Although for that purpose there is integration between Autoptimize and some page caches, this integration does not cover 100% of setups so you might need to purge your page cache manually.

= Can I still use Cloudflare's Rocket Loader? =

Cloudflare Rocket Loader is a pretty advanced but invasive way to make JavaScript non-render-blocking, which [Cloudflare still considers Beta](https://wordpress.org/support/topic/rocket-loader-breaking-onload-js-on-linked-css/#post-9263738). Sometimes Autoptimize & Rocket Loader work together, sometimes they don't. The best approach is to disable Rocket Loader, configure Autoptimize and re-enable Rocket Loader (if you think it can help) after that and test if everything still works.

At the moment (June 2017) it seems RocketLoader might break AO's "inline & defer CSS", which is based on [Filamentgroup’s loadCSS](https://github.com/filamentgroup/loadCSS), resulting in the deferred CSS not loading.

= I tried Autoptimize but my Google Pagespeed Scored barely improved =

Autoptimize is not a simple "fix my Pagespeed-problems" plugin; it "only" aggregates & minifies (local) JS & CSS and images and allows for some nice extra's as removing Google Fonts and deferring the loading of the CSS. As such Autoptimize will allow you to improve your performance (load time measured in seconds) and will probably also help you tackle some specific Pagespeed warnings. If you want to improve further, you will probably also have to look into e.g. page caching and your webserver configuration, which will improve real performance (again, load time as measured by e.g. https://webpagetest.org) and your "performance best practice" pagespeed ratings.

= What can I do with the API? =

A whole lot; there are filters you can use to conditionally disable Autoptimize per request, to change the CSS- and JS-excludes, to change the limit for CSS background-images to be inlined in the CSS, to define what JS-files are moved behind the aggregated one, to change the defer-attribute on the aggregated JS script-tag, ... There are examples for some filters in autoptimize_helper.php_example and in this FAQ.

= How does CDN work? =

Starting from version 1.7.0, CDN is activated upon entering the CDN blog root directory (e.g. http://cdn.example.net/wordpress/). If that URL is present, it will used for all Autoptimize-generated files (i.e. aggregated CSS and JS), including background-images in the CSS (when not using data-uri's).

If you want your uploaded images to be on the CDN as well, you can change the upload_url_path in your WordPress configuration (/wp-admin/options.php) to the target CDN upload directory (e.g. http://cdn.example.net/wordpress/wp-content/uploads/). Do take into consideration this only works for images uploaded from that point onwards, not for images that already were uploaded. Thanks to [BeautyPirate for the tip](http://wordpress.org/support/topic/please-don%c2%b4t-remove-cdn?replies=15#post-4720048)!

= Why aren't my fonts put on the CDN as well? =

Autoptimize supports this, but it is not enabled by default because [non-local fonts might require some extra configuration](http://davidwalsh.name/cdn-fonts). But if you have your cross-origin request policy in order, you can tell Autoptimize to put your fonts on the CDN by hooking into the API, setting `autoptimize_filter_css_fonts_cdn` to `true` this way;

`add_filter( 'autoptimize_filter_css_fonts_cdn', '__return_true' );`

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

* If all works but you notice your blog is slower, ensure you have a page caching plugin installed (WP Super Cache or similar) and check the info on cache size (the soution for that problem also impacts performance for uncached pages) in this FAQ as well.
* In case your blog looks weird, i.e. when the layout gets messed up, there is problem with CSS optimization. Try excluding one or more CSS-files from being optimized. You can also force CSS not to be aggregated by wrapping it in noptimize-tags in your theme or widget or by adding filename (for external stylesheets) or string (for inline styles) to the exclude-list.
* In case some functionality on your site stops working (a carroussel, a menu, the search input, ...) you're likely hitting JavaScript optimization trouble. Change the "Aggregate inline JS" and/ or "Force JavaScript in head?" settings and try again. Excluding 'js/jquery/jquery.min.js' from optimization (see below) and optionally activating "[Add try/catch wrapping](http://blog.futtta.be/2014/08/18/when-should-you-trycatch-javascript/)") can also help. Alternatively -for the technically savvy- you can exclude specific scripts from being treated (moved and/ or aggregated) by Autoptimize by adding a string that will match the offending Javascript or excluding it from within your template files or widgets by wrapping the code between noptimize-tags. Identifying the offending JavaScript and choosing the correct exclusion-string can be trial and error, but in the majority of cases JavaScript optimization issues can be solved this way. When debugging JavaScript issues, your browsers error console is the most important tool to help you understand what is going on.
* If your theme or plugin require jQuery, you can try either forcing all in head and/ or excluding jquery.min.js (and jQuery-plugins if needed).
* If you can't get either CSS or JS optimization working, you can off course always continue using the other two optimization-techniques.
* If you tried the troubleshooting tips above and you still can't get CSS and JS working at all, you can ask for support on the [WordPress Autoptimize support forum](http://wordpress.org/support/plugin/autoptimize). See below for a description of what information you should provide in your "trouble ticket"

= I excluded files but they are still being autoptimized? =

AO minifies excluded JS/ CSS if the filename indicates the file is not minified yet. As of AO 2.5 you can disable this on the "JS, CSS & HTML"-tab under misc. options by unticking "minify excluded files".

= Help, I have a blank page or an internal server error after enabling Autoptimize!! =

Make sure you're not running other HTML, CSS or JS minification plugins (BWP minify, WP minify, ...) simultaneously with Autoptimize or disable that functionality your page caching plugin (W3 Total Cache, WP Fastest Cache, ...). Try enabling only CSS or only JS optimization to see which one causes the server error and follow the generic troubleshooting steps to find a workaround.

= But I still have blank autoptimized CSS or JS-files! =

If you are running Apache, the .htaccess file written by Autoptimize can in some cases conflict with the AllowOverrides settings of your Apache configuration (as is the case with the default configuration of some Ubuntu installations), which results in "internal server errors" on the autoptimize CSS- and JS-files. This can be solved by [setting AllowOverrides to All](http://httpd.apache.org/docs/2.4/mod/core.html#allowoverride).

= Can't log in on domain mapped multisites =

Domain mapped multisites require Autoptimize to be initialized at a different WordPress action, add this line of code to your wp-config.php to make it so to hook into `setup_theme` for example:

`define( 'AUTOPTIMIZE_SETUP_INITHOOK', 'setup_theme' );`

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

Make sure `js/jquery/jquery.min.js` is in the comma-separated list of JS optimization exclusions (this is excluded in the default configuration).

= I'm getting "jQuery is not defined" errors =

In that case you have un-aggregated JavaScript that requires jQuery to be loaded, so you'll have to add `js/jquery/jquery.min.js` to the comma-separated list of JS optimization exclusions.

= I use NextGen Galleries and a lot of JS is not aggregated/ minified? =

NextGen Galleries does some nifty stuff to add JavaScript. In order for Autoptimize to be able to aggregate that, you can either disable Nextgen Gallery's resourced manage with this code snippet `add_filter( 'run_ngg_resource_manager', '__return_false' );` or you can tell Autoptimize to initialize earlier, by adding this to your wp-config.php: `define("AUTOPTIMIZE_INIT_EARLIER","true");`

= What is noptimize? =

Starting with version 1.6.6 Autoptimize excludes everything inside noptimize tags, e.g.:
`&lt;!&#45;&#45;noptimize&#45;&#45;>&lt;script>alert('this will not get autoptimized');&lt;/script>&lt;!&#45;&#45;/noptimize&#45;&#45;>`

You can do this in your page/ post content, in widgets and in your theme files (consider creating [a child theme](http://codex.wordpress.org/Child_Themes) to avoid your work being overwritten by theme updates).

= Can I change the directory & filename of cached autoptimize files? =

Yes, if you want to serve files from e.g. /wp-content/resources/aggregated_12345.css instead of the default /wp-content/cache/autoptimize/autoptimize_12345.css, then add this to wp-config.php:
`
define('AUTOPTIMIZE_CACHE_CHILD_DIR','/resources/');
define('AUTOPTIMIZE_CACHEFILE_PREFIX','aggregated_');
`

= Does this work with non-default WP_CONTENT_URL ? =

No, Autoptimize does not support a non-default WP_CONTENT_URL out-of-the-box, but this can be accomplished with a couple of lines of code hooking into Autoptimize's API.

= Can the generated JS/ CSS be pre-gzipped? =

Yes, but this is off by default. You can enable this by passing ´true´ to ´autoptimize_filter_cache_create_static_gzip´. You'll obviously still have to configure your webserver to use these files instead of the non-gzipped ones to avoid the overhead of on-the-fly compression.

= What does "remove emojis" do? =

This new option in Autoptimize 2.3 removes the inline CSS, inline JS and linked JS-file added by WordPress core. As such is can have a small positive impact on your site's performance.

= Is "remove query strings" useful? =

Although some online performance assessment tools will single out "query strings for static files" as an issue for performance, in general the impact of these is almost non-existant. As such Autoptimize, since version 2.3, allows you to have the query string (or more precisely the "ver"-parameter) removed, but ticking "remove query strings from static resources" will have little or no impact of on your site's performance as measured in (milli-)seconds.

= (How) should I optimize Google Fonts? =

Google Fonts are typically loaded by a "render blocking" linked CSS-file. If you have a theme and plugins that use Google Fonts, you might end up with multiple such CSS-files. Autoptimize (since version 2.3) now let's you lessen the impact of Google Fonts by either removing them alltogether or by optimizing the way they are loaded. There are two optimization-flavors; the first one is "combine and link", which replaces all requests for Google Fonts into one request, which will still be render-blocking but will allow the fonts to be loaded immediately (meaning you won't see fonts change while the page is loading). The alternative is "combine and load async" which uses JavaScript to load the fonts in a non-render blocking manner but which might cause a "flash of unstyled text".

= Should I use "preconnect" =

Preconnect is a somewhat advanced feature to instruct browsers ([if they support it](https://caniuse.com/#feat=link-rel-preconnect)) to make a connection to specific domains even if the connection is not immediately needed. This can be used e.g. to lessen the impact of 3rd party resources on HTTPS (as DNS-request, TCP-connection and SSL/TLS negotiation are executed early). Use with care, as preconnecting to too many domains can be counter-productive.

= When can('t) I async JS? =

JavaScript files that are not autoptimized (because they were excluded or because they are hosted elsewhere) are typically render-blocking. By adding them in the comma-separated "async JS" field, Autoptimize will add the async flag causing the browser to load those files asynchronously (i.e. non-render blocking). This can however break your site (page), e.g. if you async "js/jquery/jquery.min.js" you will very likely get "jQuery is not defined"-errors. Use with care.

= How does image optimization work? =

When image optimization is on, Autoptimize will look for png, gif, jpeg (.jpg) files in image tags and in your CSS files that are loaded from your own domain and change the src (source) to the ShortPixel CDN for those. Important: this can only work for publicly available images, otherwise the image optimization proxy will not be able to get the image to optimize it, so firewalls or proxies or password protection or even hotlinking-prevention might break image optimization.

= Can I use image optimization for my intranet/ protected site? =

No; Image optimization depends on the ability of the external image optimization service to fetch the original image from your site, optimize it and save it on the CDN. If you images cannot be downloaded by anonymous visitors (due to firewall/ proxy/ password protection/ hotlinking-protection), image optimization will not work.

= Where can I get more info on image optimization? =

Have a look at [Shortpixel's FAQ](https://shortpixel.helpscoutdocs.com/category/60-shortpixel-ai-cdn).

= Can I disable AO listening to page cache purges? =

As from AO 2.4 AO "listens" to page cache purges to clear its own cache. You can disable this behavior with this filter;

`
add_filter('autoptimize_filter_main_hookpagecachepurge','__return_false');`

= Some of the non-ASCII characters get lost after optimization =

By default AO uses non multibyte-safe string methods, but if your PHP has the mbstring extension you can enable multibyte-safe string functions with this filter;

`
add_filter('autoptimize_filter_main_use_mbstring', '__return_true');`

= I can't get Critical CSS working =

Check [the FAQ on the (legacy) "power-up" here](https://wordpress.org/plugins/autoptimize-criticalcss/#faq), this info will be integrated in this FAQ at a later date.

= Do I still need the Critical CSS power-up when I have Autoptimize 2.7? =

When both Autoptimize 2.7 and the separate Critical CSS power-up are installed and active, the power-up will handle the critical CSS part. When you disable the power-up, the integrated critical CSS code in Autoptimize 2.7 will take over.

= What does "enable 404 fallbacks" do? Why would I need this? =

Autoptimize caches aggregated & optimized CSS/ JS and links to those cached files are stored in the HTML, which will be stored in a page cache (which can be a plugin, can be at host level, can be at 3rd party, in the Google cache, in a browser). If there is HTML in a page cache that links to Autoptimized CSS/ JS that has been removed in the mean time (when the cache was cleared) then the page from cache will not look/ work as expected as the CSS or JS were not found (a 404 error).

This setting aims to prevent things from breaking by serving "fallback" CSS or JS. The fallback-files are copies of the first Autoptimized CSS & JS files created after the cache was emptied and as such will based on the homepage. This means that the CSS/ JS migth not apply 100% on other pages, but at least the impact of missing CSS/ JS will be lessened (often significantly).

When the option is enabled, Autoptimize adds an `ErrorDocument 404` to the .htaccess (as used by Apache) and will also hook into WordPress core `template_redirect` to capture 404's handled by Wordpress. When using NGINX something like below should work (I'm not an NGINX specialist, but it does work for me);

`
location ~* /wp-content/cache/autoptimize/.*\.(js|css)$ {
    try_files $uri $uri/ /wp-content/autoptimize_404_handler.php;
}`

= What open source software/ projects are used in Autoptimize? =

The following great open source projects are used in Autoptimize in some form or another:

* [Mr Clay's Minify](https://github.com/mrclay/minify/) for JS & HTML minification
* [YUI CSS compressor PHP Port](https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port) for CSS minification
* [Lazysizes](https://github.com/aFarkas/lazysizes) for lazyload
* [Persist Admin Notices Dismissal](https://github.com/w3guy/persist-admin-notices-dismissal) for notices in the administration screens
* [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker/) for automated updates from Github for the beta version
* [LoadCSS](https://github.com/filamentgroup/loadCSS) for deferring full CSS
* [jQuery cookie](https://github.com/carhartl/jquery-cookie) to store the "futtta about" category selection in a cookie
* [jQuery tablesorter](https://github.com/christianbach/tablesorter) for the critical CSS rules/ jobs display
* [jQuery unslider](https://github.com/idiot/unslider/) for the mini-slider in the top right corner on the main settings page (repo gone)
* [JavaScript-md5](https://github.com/blueimp/JavaScript-MD5) for critical CSS rules editing
* [Speed Booster Pack](https://wordpress.org/plugins/speed-booster-pack/) for advanced JS deferring

= Where can I get help? =

You can get help on the [wordpress.org support forum](http://wordpress.org/support/plugin/autoptimize). If you are 100% sure this your problem cannot be solved using Autoptimize configuration and that you in fact discovered a bug in the code, you can [create an issue on GitHub](https://github.com/futtta/autoptimize/issues). If you're looking for premium support, check out our [Autoptimize Pro Support and Web Performance Optimization services](http://autoptimize.com/).

= I want out, how should I remove Autoptimize? =

* Disable the plugin (this will remove options and cache)
* Remove the plugin
* Clear any cache that might still have pages which reference Autoptimized CSS/JS (e.g. of a page caching plugin such as WP Super Cache)

= How can I help/ contribute? =

Just [fork Autoptimize on Github](https://github.com/futtta/autoptimize) and code away!

== Changelog ==

= 2.8.4 =
* fix for an authenticated XSS vulnerability

= 2.8.3 =
* fix for missing ao-minify-html.php file

= 2.8.2 =
* Images: only show "did you know shortpixel" notice on Autoptimize settings pages (no more littering all over the backend)
* Images: update lazysizes from upstream
* Images: misc. improvements such as fix for PHP "undefined index" notice, updated copy, ...
* HTML: rename HTML minify class from minify_HTML to AO_minify_HTML to avoid conflicts with e.g. W3TC
* Critical CSS: misc. improvements such as detect is_front_page before any other conditional, fix for conditional rules without an actual condition, improved debug logging, ...
* JS/ CSS: fix for AO not optimizing multisite child sites when CDN set

= 2.8.1 =
* Images: new option not to lazyload first X images
* fix for "array to string" conversion errors in image optimization logic of .ico files
* switch jQuery shorthand .click (in toolbar JS & PaND dismiss notice JS) to please jQuery Migrate helper (and because it's better that way)

= 2.8.0 =
* JavaScript: new option "defer but don't aggregate"
* JavaScript: ensure Autoptimize also acts on jQuery in WordPress 5.6 which is renamed to jquery.min.js from jquery.js before.
* Images: add field to exclude images from being optimized.
* Images: new filter (`autoptimize_filter_imgopt_lazyload_from_nth`) to tell AO not to lazyload the first X images (to improve LCP/ CLS).
* Critical CSS: major improvements of the job processing mechanism, reducing time spent from up to 1 minute to just a couple of seconds.
* Critical CSS: under "advanced options" replace "request limit" with "queue processing time limit" (default 30s).
* Extra | Google Fonts: better parsing of version 2 Google Font URL's (/css2/).
* Misc. other minor fixes, see the [GitHub commit log](https://github.com/futtta/autoptimize/commits/beta).

= 2.7.8 =
* Image optimization: add support for AVIF image format for browsers that support it (enabled with the existing WebP-option, also requires lazy-load to be active)
* Critical CSS: further security improvements of critical CSS import settings upload, based on the input of [Marcin Weglowski of afine.com](https://afine.com)
* Misc. other minor fixes, see the [GitHub commit log](https://github.com/futtta/autoptimize/commits/beta).

= 2.7.7 =
* critical CSS: make sure pages get a path-based rule even if a CPT or template matches (when "path based rules for pages" option is on)
* critical CSS: make sure the "unload CCSS javascript" is only added once
* settings screens: switch jQuery .attr() to .prop() as suggested by jQuery Migrate to prepare for [the great oncoming big jQuery updates](https://wptavern.com/major-jquery-changes-on-the-way-for-wordpress-5-5-and-beyond)
* HTML minify: reverse placeholder array to make sure last replaced placeholder is changed back first to fix rare issues
* security fix: kudos to [Erin Germ](https://eringerm.com/) for finding & reporting an authenticated XSS vulnerability
* security fix: props to an anonymous pentester for finding & reporting an authenticated malicous file upload vulnerability

= 2.7.6 =
* fix for top frontend admin-bar being invisible when "inline & defer" is active.
* fix for 3rd party CSS-files not being deferred when "inline & defer" is active.
* small copy changes on Extra settings screen.

= 2.7.5 =
* urgent fix for Google Fonts aggregate & preload that broke badly in 2.7.4.

= 2.7.4 =
* Image optimization: also optimize icon links
* Image optimization: fix webp-detection for Safari (contributed by @pinkasey)
* Image lazyload: remove CSS that hides the placeholder image/ sets transistion between placeholder and final image
* Critical CSS: new advanced option to unload CCSS on onLoad
* Critical CSS improvement: cache templates in a transient to avoid overhead of having to search filesystem time and time again (contributed by @pratham2003)
* Critical CSS improvement: better but still experimental jQuery deferring logic
* Critical CSS fix: prevent MANUAL template-based rules being overwritten
* CSS Inline & defer: move away from old loadCSS-based approach to [Filamentgroup's new, simpler method](https://www.filamentgroup.com/lab/load-css-simpler/)
* 404 fallback enabled by default for new installations
* changed all occurences of blacklist/ whitelist to blocklist/ allowlist. The filters `autoptimize_filter_js_whitelist` and `autoptimize_filter_css_whitelist` still work in 2.7.4 but usage is deprecated and should be replaced with `autoptimize_filter_js_allowlist` and `autoptimize_filter_css_allowlist`.
* updated readme to explicitly confirm this is GPL + praise open source projects used in Autoptimize as praise was long overdue!
* tested and confirmed working on WordPress 5.5 beta 2

= 2.7.3 =
* Critical CSS: cache settings in the PHP process instead of re-fetching them
* Critical CSS: shorter intervals between calls to criticalcss.com (shortening the asynchronous job queue processing time)
* inline & defer CSS: fix for some excluded files not being preloaded
* 404 fallback: only create fallback files for CSS/ JS, not for (background-)images
* copy changes as suggested by Cyrille (@css31), un grand merci!
* misc. other minor fixes, see the [GitHub commit log](https://github.com/futtta/autoptimize/commits/beta).

= 2.7.2 =
* Critical CSS: fix settings page issues with certain translation strings
* Critical CSS: fix "inline & defer" not being "seen" on multisite network settings
* Critical CSS: add links on path-based rules
* Critical CSS: fix for non-asci URL's not matching rules
* Improvement: auto-disable autoptimize on misc. page builder URL's
* Improvement: don't change non-aggregated CSS if it already has an onload attribute
* Image lazyload improvement: remove `&quot;` from around background images

= 2.7.1 =
* A couple of small bugfixes, see the [GitHub commit log](https://github.com/futtta/autoptimize/commits/beta).

= 2.7.0 =
* Integration of critical CSS power-up.
* New option to ensure missing autoptimized files are served with fallback JS/ CSS.
* Batch of misc. smaller improvements & fixes, more info in the [GitHub commit log](https://github.com/futtta/autoptimize/commits/beta).

= older =
* see [https://plugins.svn.wordpress.org/autoptimize/tags/2.7.2/readme.txt](https://plugins.svn.wordpress.org/autoptimize/tags/2.7.2/readme.txt)
