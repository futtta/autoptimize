Autoptimize
===========

The [official Autoptimize repo on Github can be found here](https://github.com/futtta/autoptimize/).

## Example use of Autoptimize's API

### Filter: `autoptimize_filter_css_datauri_maxsize`

```php
add_filter('autoptimize_filter_css_datauri_maxsize','my_ao_override_dataursize',10,1);
/**
 * Change the threshold at which background images are turned into data URI-s.
 *
 * @param $urisize: default size
 * @return: your own preferred size
 */
function my_ao_override_dataursize($urisizeIn) {
	return 100000;
}
```

### Filter: `autoptimize_filter_css_datauri_exclude`

```php
add_filter('autoptimize_filter_css_datauri_exclude','my_ao_exclude_image',10,1);
/**
 * Exclude background images from being turned into data URI-s.
 *
 * @param $imageexcl: default images excluded (empty)
 * @return: comma-seperated list of images to exclude
 */
function my_ao_exclude_image($imageexcl) {
	return "adfreebutton.jpg, otherimage.png";
}
```

### Filter: `autoptimize_filter_js_defer`

```php
add_filter('autoptimize_filter_js_defer','my_ao_override_defer',10,1);
/**
 * Change flag added to Javascript.
 *
 * @param $defer: default value, "" when forced in head, "defer " when not forced in head
 * @return: new value
 */
function my_ao_override_defer($defer) {
	return $defer."async ";
}
```

### Filter: autoptimize_filter_noptimize

```php
add_filter('autoptimize_filter_noptimize','my_ao_noptimize',10,0);
/**
 * Stop autoptimize from optimizing, e.g. based on URL as in example.
 *
 * @return: boolean, true or false
 */
function my_ao_noptimize() {
	if (strpos($_SERVER['REQUEST_URI'],'no-autoptimize-now')!==false) {
		return true;
	} else {
		return false;
	}
}
```

### Filter: `autoptimize_filter_js_exclude`

```php
add_filter('autoptimize_filter_js_exclude','my_ao_override_jsexclude',10,1);
/**
 * JS optimization exclude strings, as configured in admin page.
 *
 * @param $exclude: comma-seperated list of exclude strings
 * @return: comma-seperated list of exclude strings
 */
function my_ao_override_jsexclude($exclude) {
	return $exclude.", customize-support";
}
```

### Filter: `autoptimize_filter_css_exclude`

```php
add_filter('autoptimize_filter_css_exclude','my_ao_override_cssexclude',10,1);
/**
 * CSS optimization exclude strings, as configured in admin page.
 *
 * @param $exclude: comma-seperated list of exclude strings
 * @return: comma-seperated list of exclude strings
 */
function my_ao_override_cssexclude($exclude) {
	return $exclude.", recentcomments";
}
```

## Filter: `autoptimize_filter_js_movelast`

```php
add_filter('autoptimize_filter_js_movelast','my_ao_override_movelast',10,1);
/**
 * Internal array of what script can be moved to the bottom of the HTML.
 *
 * @param array $movelast
 * @return: updated array
 */
function my_ao_override_movelast($movelast) {
	$movelast[]="console.log";
	return $movelast;
}
```

### Filter: `autoptimize_filter_css_replacetag`

```php
add_filter('autoptimize_filter_css_replacetag','my_ao_override_css_replacetag',10,1);
/**
 * Where in the HTML is optimized CSS injected.
 *
 * @param array $replacetag, containing the html-tag and the method (inject "before", "after" or "replace")
 * @return array with updated values
 */
function my_ao_override_css_replacetag($replacetag) {
	return array("<head>","after");
}
```

### Filter: `autoptimize_filter_js_replacetag`

```php
add_filter('autoptimize_filter_js_replacetag','my_ao_override_js_replacetag',10,1);
/**
 * Where in the HTML optimized JS is injected.
 *
 * @param array $replacetag, containing the html-tag and the method (inject "before", "after" or "replace")
 * @return array with updated values
 */
function my_ao_override_js_replacetag($replacetag) {
    return array("<injectjs />","replace");
}
```

### Filter: `autoptimize_js_do_minify`

```php
add_filter('autoptimize_js_do_minify','my_ao_js_minify',10,1);
/**
 * Do we want to minify?
 * If set to false autoptimize effectively only aggregates, but does not minify.
 *
 * @return: boolean true or false
 */
function my_ao_js_minify() {
	return false;
}
```

### Filter: `autoptimize_css_do_minify`

```php
add_filter('autoptimize_css_do_minify','my_ao_css_minify',10,1);
/**
 * Do we want to minify?
 * If set to false autoptimize effectively only aggregates, but does not minify.
 *
 * @return: boolean true or false
 */
function my_ao_css_minify() {
   return false;
}
```

### Filter: `autoptimize_js_include_inline`

```php
add_filter('autoptimize_js_include_inline','my_ao_js_include_inline',10,1);
/**
 * Do we want AO to also aggregate inline JS?
 *
 * @return: boolean true or false
 */
function my_ao_js_include_inline() {
	return false;
}
```

### Filter: `autoptimize_css_include_inline`

```php
add_filter('autoptimize_css_include_inline','my_ao_css_include_inline',10,1);
/**
 * Do we want AO to also aggregate inline CSS?
 *
 * @return: boolean true or false
 */
function my_ao_css_include_inline() {
    return false;
}
```

### Filter: `autoptimize_filter_css_defer_inline`

```php
add_filter('autoptimize_filter_css_defer_inline','my_ao_css_defer_inline',10,1);
/**
 * What CSS to inline when "defer and inline" is activated.
 *
 * @param $inlined: string with above the fold CSS as configured in admin
 * @return: updated string with above the fold CSS
 */
function my_ao_css_defer_inline($inlined) {
	return $inlined."h2,h1{color:red !important;}";
}
```

### Filter: `autoptimize_separate_blog_caches`

```php
add_filter('autoptimize_separate_blog_caches','__return_false',10,1);
/**
 * Do not separate cache folders in multisite setup.
 */
```
