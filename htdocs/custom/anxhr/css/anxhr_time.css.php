<?php
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
 * \file        htdocs/custom/anxhr/css/anxhr_time.css.php
 * \ingroup     anxhr
 * \brief       Styles of the time tracking pages (static, no Dolibarr environment needed)
 */

header('Content-type: text/css; charset=UTF-8');
header('Cache-Control: max-age=10800, public, must-revalidate');
?>
/* Clock panel */
.anxhr-clock-panel { display: flex; flex-wrap: wrap; gap: 16px; align-items: stretch; margin: 10px 0 20px 0; }
.anxhr-clock-state { flex: 1 1 260px; padding: 16px; border: 1px solid var(--colortopbordertitle1, #ddd); border-radius: 8px; }
.anxhr-clock-state .anxhr-now { font-size: 2.4em; font-weight: bold; letter-spacing: 1px; }
.anxhr-clock-actions { flex: 2 1 360px; display: grid; grid-template-columns: repeat(2, minmax(140px, 1fr)); gap: 12px; }
.anxhr-clock-actions form { margin: 0; }
button.anxhr-bigbutton {
	width: 100%; min-height: 72px; padding: 12px; font-size: 1.25em; font-weight: bold;
	border-radius: 10px; border: 0; cursor: pointer; color: #fff; display: flex; align-items: center; justify-content: center; gap: 10px;
}
button.anxhr-bigbutton:focus-visible { outline: 3px solid #1a5fb4; outline-offset: 2px; }
button.anxhr-bigbutton[disabled] { opacity: 0.35; cursor: not-allowed; }
button.anxhr-btn-in { background: #2e7d32; }
button.anxhr-btn-out { background: #c62828; }
button.anxhr-btn-break_start { background: #ef6c00; }
button.anxhr-btn-break_end { background: #1565c0; }
.anxhr-homeoffice { grid-column: 1 / -1; font-size: 1.1em; padding: 6px 0; }
.anxhr-homeoffice input { transform: scale(1.4); margin-right: 8px; }

/* Counters */
.anxhr-counters { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; }
.anxhr-counter { flex: 1 1 120px; padding: 10px; border-radius: 6px; background: var(--colorbacklinepair2, #f6f6f6); text-align: center; }
.anxhr-counter .anxhr-counter-value { font-size: 1.6em; font-weight: bold; display: block; }
.anxhr-counter .anxhr-counter-label { font-size: 0.9em; opacity: 0.8; }

/* Month navigation */
.anxhr-monthnav { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 10px; }

/* Balances */
.anxhr-pos { color: #2e7d32; font-weight: bold; }
.anxhr-neg { color: #c62828; font-weight: bold; }
.anxhr-viol-badge { cursor: help; min-width: 1.6em; }
tr.anxhr-weekend td { background: var(--colorbacklinepairhover, #f3f3f3); }
tr.anxhr-today td { font-weight: bold; }
tr.anxhr-holiday td.anxhr-daycell { color: #c62828; }
.anxhr-entries-compact { font-family: monospace; white-space: nowrap; }
.anxhr-correction-row input[type=time] { min-width: 110px; }

@media only screen and (max-width: 767px) {
	.anxhr-clock-actions { grid-template-columns: 1fr 1fr; }
	button.anxhr-bigbutton { min-height: 88px; font-size: 1.15em; }
	.anxhr-clock-state .anxhr-now { font-size: 2em; }
}
