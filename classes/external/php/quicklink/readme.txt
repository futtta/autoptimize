=== Quicklink for WordPress ===

Contributors: wpmunich, google, luehrsen, westonruter
Tags: performance, speed, fast, prefetch, seo, http2, preconnect, optimization
Requires at least: 4.9
Tested up to: 6.2
Requires PHP: 5.6
Stable tag: 0.10.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

⚡️ Faster subsequent page-loads by prefetching in-viewport links during idle time.

== Description ==

Quicklink for WordPress attempts to make navigation to subsequent pages load faster. Embedded with the plugin is a javascript library, which detects links in the viewport, waits until the browser is idle and prefetches the URLs for these links. The library also tries to detect, if the user is on a slow connection or on a data plan.

This plugin builds heavily on the amazing work done by [Google Chrome Labs](https://github.com/GoogleChromeLabs/quicklink).

More information about [Quicklink on the official Website](https://getquick.link).

= How it works =

* **Detects links within the viewport** (using [Intersection Observer](https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API))
* **Waits until the browser is idle** (using [requestIdleCallback](https://developer.mozilla.org/en-US/docs/Web/API/Window/requestIdleCallback))
* **Checks if the user isn't on a slow connection** (using `navigator.connection.effectiveType`) or has data-saver enabled (using `navigator.connection.saveData`)
* **Prefetches URLs to the links** (using [`<link rel=prefetch>`](https://www.w3.org/TR/resource-hints/#prefetch) or XHR). Provides some control over the request priority (can switch to `fetch()` if supported).

If you are a developer, we encourage you to follow along or [contribute](https://github.com/luehrsenheinrich/wp-quicklink) to the development of this plugin [on GitHub](https://github.com/luehrsenheinrich/wp-quicklink).

== Installation ==

= From within WordPress =

1. Visit 'Plugins > Add New'
1. Search for 'Quicklink for WordPress'
1. Activate 'Quicklink for WordPress' from your Plugins page.

= Manually =

1. Upload the `quicklink` folder to the `/wp-content/plugins/` directory
1. Activate the Quicklink for WordPress plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Will this make my website faster? =
Yes and no. This plugin has no impact on the actual performance of your website. But navigating the website will feel faster, because potential navigation targets of the user have been prefetched in the users browser.

= Will this make my website slower? =
Slowing down the site is highly unlikely, but possible. If this plugin is used with a caching plugin, the additional hits on the server should not impact performance. But if resource intensive, uncached targets are being prefetched, a performance loss is to be expected.

= What can I do if my website is getting slower? =
You should fist check, that a good caching plugin like "WP Super Cache", "W3 Total Cache" or "WP Rocket" is enabled. If this is not enough you can always add exception rules to the Quicklink configuration by modifying the 'quicklink_options' filter.

== Changelog ==

= 0.10.0 =
* General maintenance for the repository
* Updated Quicklink dependency to version 2.3
* Tested for WordPress 6.2

= 0.9.0 =
* General maintenance for the repository
* Updated Quicklink dependency to version 2.2
* Tested for WordPress 5.8

= 0.8.0 =
* Updated Quicklink dependency to version 2.0

= 0.7.3 =
* Made a function have a less generic name

= 0.7.1 =
* Fix some more deprecations with WooCommerce

= 0.7.0 =
* Changed the defaults to ignore links with get requests to improve compatibility
* Removed some deprecated functions for WooCommerce

= 0.6.0 =
* Updated Quicklink to version 1.0.1

= 0.5.0 =
* Added rules and compatibility for WooCommerce

= 0.4.0 =
* Updated the script loading to load asynchronously
* Updated the plugin assets

= 0.3.0 =
* Added compatibility with AMP
* Added amazing contributors
* Added the new WordPress filter 'quicklink_options' to modify quicklink settings

= 0.2.0 =
* Release for the plugin repository
* Tuned quicklink ignores for WordPress

= 0.1.0 =
* Initial release
