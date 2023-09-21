/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/******/ 	// The require scope
/******/ 	var __webpack_require__ = {};
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	!function() {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = function(exports, definition) {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/global */
/******/ 	!function() {
/******/ 		__webpack_require__.g = (function() {
/******/ 			if (typeof globalThis === 'object') return globalThis;
/******/ 			try {
/******/ 				return this || new Function('return this')();
/******/ 			} catch (e) {
/******/ 				if (typeof window === 'object') return window;
/******/ 			}
/******/ 		})();
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	!function() {
/******/ 		__webpack_require__.o = function(obj, prop) { return Object.prototype.hasOwnProperty.call(obj, prop); }
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	!function() {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = function(exports) {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	}();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};

// NAMESPACE OBJECT: ./node_modules/quicklink/dist/quicklink.mjs
var quicklink_namespaceObject = {};
__webpack_require__.r(quicklink_namespaceObject);
__webpack_require__.d(quicklink_namespaceObject, {
  "listen": function() { return u; },
  "prefetch": function() { return s; },
  "prerender": function() { return f; }
});

;// CONCATENATED MODULE: ./node_modules/quicklink/dist/quicklink.mjs
function e(e){return new Promise(function(n,r,t){(t=new XMLHttpRequest).open("GET",e,t.withCredentials=!0),t.onload=function(){200===t.status?n():r()},t.send()})}var n,r=(n=document.createElement("link")).relList&&n.relList.supports&&n.relList.supports("prefetch")?function(e){return new Promise(function(n,r,t){(t=document.createElement("link")).rel="prefetch",t.href=e,t.onload=n,t.onerror=r,document.head.appendChild(t)})}:e,t=window.requestIdleCallback||function(e){var n=Date.now();return setTimeout(function(){e({didTimeout:!1,timeRemaining:function(){return Math.max(0,50-(Date.now()-n))}})},1)},o=new Set,i=new Set,c=!1;function a(e){if(e){if(e.saveData)return new Error("Save-Data is enabled");if(/2g/.test(e.effectiveType))return new Error("network conditions are poor")}return!0}function u(e){if(e||(e={}),window.IntersectionObserver){var n=function(e){e=e||1;var n=[],r=0;function t(){r<e&&n.length>0&&(n.shift()(),r++)}return[function(e){n.push(e)>1||t()},function(){r--,t()}]}(e.throttle||1/0),r=n[0],a=n[1],u=e.limit||1/0,l=e.origins||[location.hostname],d=e.ignores||[],h=e.delay||0,p=[],m=e.timeoutFn||t,w="function"==typeof e.hrefFn&&e.hrefFn,g=e.prerender||!1;c=e.prerenderAndPrefetch||!1;var v=new IntersectionObserver(function(n){n.forEach(function(n){if(n.isIntersecting)p.push((n=n.target).href),function(e,n){n?setTimeout(e,n):e()}(function(){-1!==p.indexOf(n.href)&&(v.unobserve(n),(c||g)&&i.size<1?f(w?w(n):n.href).catch(function(n){if(!e.onError)throw n;e.onError(n)}):o.size<u&&!g&&r(function(){s(w?w(n):n.href,e.priority).then(a).catch(function(n){a(),e.onError&&e.onError(n)})}))},h);else{var t=p.indexOf((n=n.target).href);t>-1&&p.splice(t)}})},{threshold:e.threshold||0});return m(function(){(e.el||document).querySelectorAll("a").forEach(function(e){l.length&&!l.includes(e.hostname)||function e(n,r){return Array.isArray(r)?r.some(function(r){return e(n,r)}):(r.test||r).call(r,n.href,n)}(e,d)||v.observe(e)})},{timeout:e.timeout||2e3}),function(){o.clear(),v.disconnect()}}}function s(n,t,u){var s=a(navigator.connection);return s instanceof Error?Promise.reject(new Error("Cannot prefetch, "+s.message)):(i.size>0&&!c&&console.warn("[Warning] You are using both prefetching and prerendering on the same document"),Promise.all([].concat(n).map(function(n){if(!o.has(n))return o.add(n),(t?function(n){return window.fetch?fetch(n,{credentials:"include"}):e(n)}:r)(new URL(n,location.href).toString())})))}function f(e,n){var r=a(navigator.connection);if(r instanceof Error)return Promise.reject(new Error("Cannot prerender, "+r.message));if(!HTMLScriptElement.supports("speculationrules"))return s(e),Promise.reject(new Error("This browser does not support the speculation rules API. Falling back to prefetch."));if(document.querySelector('script[type="speculationrules"]'))return Promise.reject(new Error("Speculation Rules is already defined and cannot be altered."));for(var t=0,u=[].concat(e);t<u.length;t+=1){var f=u[t];if(window.location.origin!==new URL(f,window.location.href).origin)return Promise.reject(new Error("Only same origin URLs are allowed: "+f));i.add(f)}o.size>0&&!c&&console.warn("[Warning] You are using both prefetching and prerendering on the same document");var l=function(e){var n=document.createElement("script");n.type="speculationrules",n.text='{"prerender":[{"source": "list","urls": ["'+Array.from(e).join('","')+'"]}]}';try{document.head.appendChild(n)}catch(e){return e}return!0}(i);return!0===l?Promise.resolve():Promise.reject(l)}

;// CONCATENATED MODULE: ./build/js/quicklink.js


// Move quicklink to the global scope
window.quicklink = quicklink_namespaceObject;
__webpack_require__.g.addEventListener('load', () => {
  const exportedOptions = window.quicklinkOptions || {};
  const listenerOptions = {};

  // el: Convert selector into element reference.
  if ('string' === typeof exportedOptions.el && exportedOptions.el) {
    listenerOptions.el = document.querySelector(exportedOptions.el);
  }

  // timeout: Verify we actually get an int for milliseconds.
  if ('number' === typeof exportedOptions.timeout) {
    listenerOptions.timeout = exportedOptions.timeout;
  }

  // limit: Verify we actually get an int.
  if ('number' === typeof exportedOptions.limit && exportedOptions.limit > 0) {
    listenerOptions.limit = exportedOptions.limit;
  }

  // throttle: Verify we actually get an int.
  if ('number' === typeof exportedOptions.throttle && exportedOptions.throttle > 0) {
    listenerOptions.throttle = exportedOptions.throttle;
  }

  // timeoutFn: Obtain function reference as opposed to function string, if it is not the default.
  if ('string' === typeof exportedOptions.timeoutFn && 'requestIdleCallback' !== exportedOptions.timeoutFn && typeof 'function' === window[exportedOptions.timeoutFn]) {
    const timeoutFn = window[exportedOptions.timeoutFn];
    listenerOptions.timeoutFn = function () {
      return timeoutFn.apply(window, arguments);
    };
  }

  // onError: Obtain function reference as opposed to function string, if it is not the default.
  if ('string' === typeof exportedOptions.onError && typeof 'function' === window[exportedOptions.onError]) {
    const onError = window[exportedOptions.onError];
    listenerOptions.onError = function () {
      return onError.apply(window, arguments);
    };
  }

  // priority: Obtain priority.
  if ('boolean' === typeof exportedOptions.priority) {
    listenerOptions.priority = exportedOptions.priority;
  }

  // origins: Verify we don't get an empty array, as that would turn off quicklink.
  if (Array.isArray(exportedOptions.origins) && 0 < exportedOptions.origins.length) {
    listenerOptions.origins = exportedOptions.origins;
  }

  // ignores: Convert strings to regular expressions.
  if (Array.isArray(exportedOptions.ignores) && 0 < exportedOptions.ignores.length) {
    listenerOptions.ignores = exportedOptions.ignores.map(ignore => {
      return new RegExp(ignore);
    });
  }
  u(listenerOptions);

  /**
   * The option to prefetch urls from the options is deprecated as of version 0.8.0.
   */
  if (Array.isArray(exportedOptions.urls) && 0 < exportedOptions.urls.length) {
    s(exportedOptions.urls);
  }
});
/******/ })()
;