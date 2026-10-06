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
 * \file        htdocs/custom/anxhr/lib/anxhr_time.lib.php
 * \ingroup     anxhr
 * \brief       Helper functions of the time tracking part (formatting, badges, visibility, tabs)
 */

if (file_exists(__DIR__.'/anxhr.lib.php')) {
	require_once __DIR__.'/anxhr.lib.php';
}

/**
 * Format minutes as HH:MM, optionally always signed ("+07:24").
 * Note: anxhr.lib.php (SCAFFOLD) provides anxhrFormatMinutes($minutes) without sign option.
 *
 * @param	int|string|null	$min		Minutes
 * @param	bool			$signed		Always output sign
 * @return	string
 */
function anxhrTimeFormatMinutes($min, $signed = false)
{
	$min = (int) $min;
	$sign = $min < 0 ? '-' : ($signed ? '+' : '');
	$abs = abs($min);
	return $sign.sprintf('%02d:%02d', intdiv($abs, 60), $abs % 60);
}

/**
 * Parse a duration typed by a user into minutes: "07:24", "7:24", "7,4" / "7.4" (hours) or "444m".
 *
 * @param	string	$str	Input
 * @return	int|null		Minutes or null if empty / invalid
 */
function anxhrTimeParseDuration($str)
{
	$str = trim((string) $str);
	if ($str === '') {
		return null;
	}
	if (preg_match('/^(-?)(\d{1,3}):([0-5]\d)$/', $str, $m)) {
		return ($m[1] === '-' ? -1 : 1) * ((int) $m[2] * 60 + (int) $m[3]);
	}
	if (preg_match('/^(-?\d+)m$/i', $str, $m)) {
		return (int) $m[1];
	}
	if (preg_match('/^-?\d+([.,]\d+)?$/', $str)) {
		return (int) round((float) str_replace(',', '.', $str) * 60);
	}
	return null;
}

/**
 * Parse a decimal number typed with comma or dot as decimal separator ("1,5", "1.5", "35,30").
 * Thousand separators are not supported (rates and hours never need them).
 *
 * @param	string	$str	Input
 * @return	float|null		Number or null if empty / invalid
 */
function anxhrTimeParseNumber($str)
{
	$str = str_replace(array(' ', "\xc2\xa0"), '', trim((string) $str));
	if ($str === '') {
		return null;
	}
	$str = str_replace(',', '.', $str);
	if (!preg_match('/^-?\d*\.?\d+$/', $str)) {
		return null;
	}
	return (float) $str;
}

/**
 * Check a clock time "HH:MM".
 *
 * @param	string	$str	Input
 * @return	string			Normalized "HH:MM" or '' if invalid / empty
 */
function anxhrTimeCleanHhmm($str)
{
	if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', trim((string) $str), $m)) {
		return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
	}
	return '';
}

/**
 * Return HTML for a signed balance with color (green positive, red negative).
 *
 * @param	int		$min	Minutes
 * @return	string
 */
function anxhrTimeBalanceHtml($min)
{
	$min = (int) $min;
	$css = $min > 0 ? 'anxhr-pos' : ($min < 0 ? 'anxhr-neg' : 'opacitymedium');
	return '<span class="'.$css.' nowraponall">'.anxhrTimeFormatMinutes($min, true).'</span>';
}

/**
 * Return translated message of a violation.
 *
 * @param	array<string,mixed>	$v		Violation (code, message_key, value, level)
 * @param	Translate			$langs	Language
 * @return	string
 */
function anxhrViolationMessage(array $v, $langs)
{
	$key = !empty($v['message_key']) ? $v['message_key'] : 'AnxhrViolation'.(isset($v['code']) ? $v['code'] : '');
	$code = isset($v['code']) ? $v['code'] : '';
	$value = isset($v['value']) ? (int) $v['value'] : 0;
	$display = in_array($code, array('ORDER_INVALID', 'OPEN_ENTRY'), true) ? (string) $value : anxhrTimeFormatMinutes($value);
	// Detailed message with value (own lang file), fallback to the generic key (module lang file).
	$detail = $langs->transnoentitiesnoconv($key.'Detail', $display);
	if ($detail !== $key.'Detail') {
		return $detail;
	}
	return $langs->transnoentitiesnoconv($key, $display);
}

/**
 * Render violations as one compact badge (count) with a tooltip listing the translated messages.
 * Color by worst level: error=danger, warning=warning, info=info.
 *
 * @param	array<int,array<string,mixed>>	$violations		Violations
 * @param	Translate						$langs			Language
 * @param	int								$showinfo		1=also show badge when there are only info items
 * @return	string									HTML
 */
function anxhrViolationBadges(array $violations, $langs, $showinfo = 1)
{
	if (empty($violations)) {
		return '';
	}
	$rank = array('info' => 1, 'warning' => 2, 'error' => 3);
	$worst = 0;
	$lines = array();
	$nb = 0;
	foreach ($violations as $v) {
		if (!is_array($v)) {
			continue;
		}
		$level = (isset($v['level']) && isset($rank[$v['level']])) ? $v['level'] : 'warning';
		$worst = max($worst, $rank[$level]);
		if ($level !== 'info') {
			$nb++;
		}
		$lines[] = '<span class="anxhr-viol-'.$level.'">'.dol_escape_htmltag(anxhrViolationMessage($v, $langs)).'</span>';
	}
	if ($worst <= 1 && !$showinfo) {
		return '';
	}
	$type = ($worst >= 3 ? 'danger' : ($worst == 2 ? 'warning' : 'info'));
	$label = ($nb > 0 ? $nb : 'i');
	$tooltip = '<b>'.dol_escape_htmltag($langs->trans('AnxhrViolations')).'</b><br>'.implode('<br>', $lines);
	return '<span class="classfortooltip badge badge-'.$type.' anxhr-viol-badge" title="'.dolPrintHTMLForAttribute($tooltip).'">'.$label.'</span>';
}

/**
 * Return ids of users supervised (directly or indirectly) by a user, viewer excluded.
 *
 * @param	User	$viewer		Supervisor
 * @return	int[]
 */
function anxhrGetSupervisedUserIds(User $viewer)
{
	if (function_exists('anxhrGetSubordinateIds')) {
		return array_values(array_diff(anxhrGetSubordinateIds($viewer), array((int) $viewer->id)));
	}
	$ids = $viewer->getAllChildIds(0);
	return array_values(array_diff(array_map('intval', is_array($ids) ? $ids : array()), array((int) $viewer->id)));
}

/**
 * Check if a viewer may see the time data of an employee.
 * Own data with time own right, time admin sees all, time approver sees subordinates.
 *
 * @param	User	$viewer		Viewer
 * @param	int		$userid		Employee
 * @return	bool
 */
function anxhrTimeCanSeeUser(User $viewer, $userid)
{
	$userid = (int) $userid;
	if ($userid <= 0) {
		return false;
	}
	if ($viewer->hasRight('anxhr', 'time', 'admin')) {
		return true;
	}
	if ($userid == (int) $viewer->id) {
		return (bool) $viewer->hasRight('anxhr', 'time', 'own');
	}
	if ($viewer->hasRight('anxhr', 'time', 'approve')) {
		return in_array($userid, anxhrGetSupervisedUserIds($viewer), true);
	}
	return false;
}

/**
 * Return the list of user ids a viewer may select in time pages (null = all users).
 *
 * @param	User	$viewer		Viewer
 * @return	int[]|null
 */
function anxhrTimeVisibleUserIds(User $viewer)
{
	if ($viewer->hasRight('anxhr', 'time', 'admin')) {
		return null;
	}
	$ids = array((int) $viewer->id);
	if ($viewer->hasRight('anxhr', 'time', 'approve')) {
		$ids = array_merge($ids, anxhrGetSupervisedUserIds($viewer));
	}
	return array_values(array_unique($ids));
}

/**
 * Return translated label of an entry type.
 *
 * @param	string		$type	in|out|break_start|break_end
 * @param	Translate	$langs	Language
 * @return	string
 */
function anxhrTimeEntryTypeLabel($type, $langs)
{
	$map = array('in' => 'AnxhrEntryIn', 'out' => 'AnxhrEntryOut', 'break_start' => 'AnxhrEntryBreakStart', 'break_end' => 'AnxhrEntryBreakEnd');
	return isset($map[$type]) ? $langs->trans($map[$type]) : dol_escape_htmltag($type);
}

/**
 * Return a badge for a clock state.
 *
 * @param	string		$state	out|in|break
 * @param	Translate	$langs	Language
 * @return	string				HTML
 */
function anxhrTimeStateBadge($state, $langs)
{
	$map = array('in' => array('AnxhrStateIn', 'status4'), 'break' => array('AnxhrStateBreak', 'status1'), 'out' => array('AnxhrStateOut', 'status0'));
	$s = isset($map[$state]) ? $map[$state] : $map['out'];
	$label = $langs->trans($s[0]);
	return dolGetStatus($label, $label, '', $s[1], 5);
}

/**
 * Return translated label of an absence code.
 *
 * @param	string|null	$code	Absence code
 * @param	Translate	$langs	Language
 * @return	string
 */
function anxhrTimeAbsenceLabel($code, $langs)
{
	if (empty($code)) {
		return '';
	}
	$map = array('vacation' => 'AnxhrAbsenceVacation', 'sick' => 'AnxhrAbsenceSick', 'za' => 'AnxhrAbsenceZa', 'holiday' => 'AnxhrAbsenceHoliday', 'special' => 'AnxhrAbsenceSpecial');
	return isset($map[$code]) ? $langs->trans($map[$code]) : dol_escape_htmltag($code);
}

/**
 * Prepare tabs of the time pages.
 *
 * @param	int		$userid		Employee shown
 * @return	array<int,array{0:string,1:string,2:string}>
 */
function anxhrTimePrepareHead($userid = 0)
{
	global $langs, $user;

	$param = ($userid > 0 && $userid != $user->id) ? '?id='.((int) $userid) : '';
	$h = 0;
	$head = array();
	$head[$h][0] = dol_buildpath('/anxhr/time_clock.php', 1);
	$head[$h][1] = $langs->trans('AnxhrMyTime');
	$head[$h][2] = 'clock';
	$h++;
	$head[$h][0] = dol_buildpath('/anxhr/time_day.php', 1).$param;
	$head[$h][1] = $langs->trans('AnxhrMonthView');
	$head[$h][2] = 'month';
	$h++;
	if ($user->hasRight('anxhr', 'time', 'correct_own') || $user->hasRight('anxhr', 'time', 'approve') || $user->hasRight('anxhr', 'time', 'admin')) {
		$head[$h][0] = dol_buildpath('/anxhr/time_corrections.php', 1);
		$head[$h][1] = $langs->trans('AnxhrCorrections');
		$head[$h][2] = 'corrections';
		$h++;
	}
	$head[$h][0] = dol_buildpath('/anxhr/time_periods.php', 1);
	$head[$h][1] = $langs->trans('AnxhrPeriods');
	$head[$h][2] = 'periods';
	$h++;

	return $head;
}

/**
 * Return the client IP (Dolibarr helper when available).
 *
 * @return string
 */
function anxhrTimeClientIp()
{
	if (function_exists('getUserRemoteIP')) {
		return (string) getUserRemoteIP();
	}
	return '';
}
