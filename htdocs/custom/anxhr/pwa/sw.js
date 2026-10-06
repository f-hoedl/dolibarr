/* ANX HR PWA service worker: cache-first for the app shell, network only for the API. */
'use strict';
var CACHE = 'anxhr-pwa-v1';
var SHELL = ['./', './index.html', './app.js', './app.css', './manifest.webmanifest', './icon.svg'];

self.addEventListener('install', function (e) {
	e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll(SHELL); }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (e) {
	e.waitUntil(caches.keys().then(function (keys) {
		return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
	}).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (e) {
	var url = new URL(e.request.url);
	if (e.request.method !== 'GET' || url.pathname.indexOf('/api/') !== -1 || url.origin !== self.location.origin) {
		return; // network (offline queue for clock actions is handled in app.js)
	}
	e.respondWith(caches.match(e.request, { ignoreSearch: true }).then(function (hit) {
		return hit || fetch(e.request);
	}));
});
