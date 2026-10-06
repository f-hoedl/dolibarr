/* Copyright (C) 2026 ANX HR contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * Library javascript of module ANX HR. Loaded on all pages (module_parts js).
 * Keep it small: refresh of the clock badge in the top right menu.
 */

(function () {
	'use strict';

	var REFRESH_MS = 5 * 60 * 1000;
	var STATES = ['in', 'break', 'out'];

	function getToken() {
		var meta = document.querySelector('meta[name="anti-csrf-newtoken"]');
		return meta ? meta.getAttribute('content') : '';
	}

	function applyState(badge, data) {
		if (!data || STATES.indexOf(data.state) < 0) {
			return;
		}
		STATES.forEach(function (s) {
			badge.classList.remove('anxhr-clock-' + s);
		});
		badge.classList.add('anxhr-clock-' + data.state);
		if (data.label) {
			badge.setAttribute('title', data.label);
			var label = badge.querySelector('.anxhr-clock-label');
			if (label) {
				label.textContent = data.label;
			}
		}
	}

	function refresh(badge) {
		var url = badge.getAttribute('data-ajaxurl');
		if (!url || typeof window.jQuery === 'undefined') {
			return;
		}
		window.jQuery.ajax({
			url: url,
			data: { token: getToken() },
			dataType: 'json',
			cache: false,
			timeout: 10000
		}).done(function (data) {
			applyState(badge, data);
		});
	}

	function init() {
		var badge = document.getElementById('anxhr-clock-badge');
		if (!badge) {
			return;
		}
		window.setInterval(function () {
			if (!document.hidden) {
				refresh(badge);
			}
		}, REFRESH_MS);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
