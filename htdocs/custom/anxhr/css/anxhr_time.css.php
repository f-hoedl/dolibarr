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
/* Clock panel: state card and action buttons */
.anxhr-clock-panel { display: flex; flex-wrap: wrap; gap: 16px; align-items: stretch; margin: 10px 0 16px 0; }
.anxhr-clock-state {
	flex: 1 1 260px; display: flex; flex-direction: column; justify-content: center; gap: 8px;
	padding: 16px; border: 1px solid var(--colortopbordertitle1, #ddd); border-left-width: 6px; border-radius: 8px;
}
.anxhr-clock-state-in { border-left-color: #25a580; }
.anxhr-clock-state-break { border-left-color: #bc9526; }
.anxhr-clock-state-out { border-left-color: #999; }
.anxhr-clock-statusline { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.anxhr-clock-statelabel { font-weight: bold; }
.anxhr-clock-statebadge .badge { font-size: 1.1em; padding: 6px 12px; max-width: none; overflow: visible; white-space: normal; }
.anxhr-clock-timeline { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 12px; }
.anxhr-clock-state .anxhr-now { font-size: 2.4em; font-weight: bold; letter-spacing: 1px; line-height: 1.1em; }
.anxhr-clock-actions { flex: 2 1 360px; display: grid; grid-template-columns: repeat(2, minmax(120px, 1fr)); gap: 12px; margin: 0; }
button.anxhr-bigbutton {
	width: 100%; min-height: 64px; padding: 10px 12px; font-size: 1.25em; font-weight: bold;
	border-radius: 10px; border: 2px solid transparent; cursor: pointer; color: #fff;
	display: flex; align-items: center; justify-content: center; gap: 6px; text-align: center;
}
button.anxhr-bigbutton:hover:not([disabled]) { filter: brightness(1.08); box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2); }
button.anxhr-bigbutton:focus-visible { outline: 3px solid #1a5fb4; outline-offset: 2px; }
button.anxhr-btn-in { background: #2e7d32; }
button.anxhr-btn-out { background: #c62828; }
button.anxhr-btn-break_start { background: #b35c00; }
button.anxhr-btn-break_end { background: #1565c0; }
/* Disabled: neutral grey, dashed border, no color so it never looks like an available action */
button.anxhr-bigbutton[disabled], button.anxhr-bigbutton.anxhr-bigbutton-disabled {
	background: var(--colorbacklinepair2, #f0f0f0); color: #8a8a8a; border: 2px dashed #c4c4c4;
	cursor: not-allowed; font-weight: normal; box-shadow: none; filter: none;
}
button.anxhr-bigbutton[disabled] .pictofixedwidth, button.anxhr-bigbutton[disabled] span { color: inherit; opacity: 0.8; }
button.anxhr-bigbutton .pictofixedwidth { color: inherit; }
.anxhr-homeoffice { grid-column: 1 / -1; display: flex; align-items: center; gap: 8px; min-height: 48px; font-size: 1.1em; }
.anxhr-homeoffice input[type=checkbox] { width: 22px; height: 22px; margin: 0; }
.anxhr-homeoffice label { cursor: pointer; padding: 8px 0; }

/* KPI tiles of the clock page (worked today, month balance) */
.anxhr-clock-kpis { display: flex; flex-wrap: wrap; gap: 16px; margin: 0 0 20px 0; }
.anxhr-clock-kpi {
	flex: 1 1 240px; display: flex; flex-direction: column; gap: 4px; padding: 14px 16px;
	border: 1px solid var(--colortopbordertitle1, #ddd); border-radius: 8px; color: inherit; text-decoration: none;
}
a.anxhr-clock-kpi .anxhr-clock-kpi-label, a.anxhr-clock-kpi .anxhr-clock-kpi-sub { color: var(--colortext, #333); }
a.anxhr-clock-kpi:hover { box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12); text-decoration: none; }
.anxhr-clock-kpi-label { opacity: 0.8; }
.anxhr-clock-kpi-value { font-size: 2em; font-weight: bold; line-height: 1.2em; }
.anxhr-clock-kpi-sub { font-size: 0.9em; opacity: 0.85; }

/* Counters (other pages) */
.anxhr-counters { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; }
.anxhr-counter { flex: 1 1 120px; padding: 10px; border-radius: 6px; background: var(--colorbacklinepair2, #f6f6f6); text-align: center; }
.anxhr-counter .anxhr-counter-value { font-size: 1.6em; font-weight: bold; display: block; }
.anxhr-counter .anxhr-counter-label { font-size: 0.9em; opacity: 0.8; }

/* Monthly closing: action buttons may wrap instead of widening the table */
td.anxhr-period-actions { min-width: 120px; }
td.anxhr-period-actions form { display: inline-block; margin: 2px 0 2px 4px; }

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
	.anxhr-clock-panel { gap: 12px; }
	.anxhr-clock-state, .anxhr-clock-actions, .anxhr-clock-kpi { flex-basis: 100%; min-width: 0; }
	.anxhr-clock-actions { grid-template-columns: 1fr 1fr; gap: 10px; }
	button.anxhr-bigbutton { min-height: 72px; font-size: 1.1em; flex-direction: column; gap: 2px; padding: 8px; }
	.anxhr-clock-state { padding: 12px; }
	.anxhr-clock-state .anxhr-now { font-size: 2em; }
	.anxhr-clock-kpis { gap: 10px; }
	.anxhr-clock-kpi { flex: 1 1 40%; padding: 10px 12px; }
	.anxhr-clock-kpi-value { font-size: 1.6em; }
}
