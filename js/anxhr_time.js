/* Copyright (C) 2026  ANX HR contributors
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/custom/anxhr/js/anxhr_time.js
 * \ingroup anxhr
 * \brief   Live counters of the time clock page (display only, server stays the source of truth).
 */
(function () {
	'use strict';

	function pad(n) {
		return (n < 10 ? '0' : '') + n;
	}

	function formatMinutes(min) {
		var sign = min < 0 ? '-' : '';
		var abs = Math.abs(min);
		return sign + pad(Math.floor(abs / 60)) + ':' + pad(abs % 60);
	}

	function start() {
		var worked = document.getElementById('anxhr-worked');
		var now = document.getElementById('anxhr-now');
		if (!worked && !now) {
			return;
		}
		var startedAt = Date.now();
		var baseMinutes = worked ? parseInt(worked.getAttribute('data-minutes') || '0', 10) : 0;
		var running = worked && worked.getAttribute('data-running') === '1';
		var baseClock = now ? now.textContent.trim() : '';
		var m = /^(\d{1,2}):(\d{2})/.exec(baseClock);
		var baseClockMinutes = m ? parseInt(m[1], 10) * 60 + parseInt(m[2], 10) : null;

		window.setInterval(function () {
			var elapsed = Math.floor((Date.now() - startedAt) / 60000);
			if (running) {
				worked.textContent = formatMinutes(baseMinutes + elapsed);
			}
			if (baseClockMinutes !== null) {
				var c = (baseClockMinutes + elapsed) % 1440;
				now.textContent = pad(Math.floor(c / 60)) + ':' + pad(c % 60);
			}
		}, 20000);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
