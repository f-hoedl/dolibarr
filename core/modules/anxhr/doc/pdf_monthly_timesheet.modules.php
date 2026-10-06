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
 * \file        htdocs/custom/anxhr/core/modules/anxhr/doc/pdf_monthly_timesheet.modules.php
 * \ingroup     anxhr
 * \brief       PDF model "Monatsblatt": working time record of one employee and month (AZG paragraph 26)
 */

require_once __DIR__.'/../modules_anxhrtimesheet.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once __DIR__.'/../../../../class/timeentry.class.php';
require_once __DIR__.'/../../../../class/timeday.class.php';
require_once __DIR__.'/../../../../class/timeperiod.class.php';
require_once __DIR__.'/../../../../lib/anxhr_time.lib.php';


/**
 * Monthly time sheet (one row per calendar day, totals, balance, legend, signatures)
 */
class pdf_monthly_timesheet extends ModelePDFAnxhrTimesheet
{
	/**
	 * Short codes printed in the day table, by violation code (explained in the legend)
	 * @var array<string,string>
	 */
	const VIOLATION_SHORT = array(
		'BREAK_MISSING' => 'BR',
		'BREAK_AUTO_ADDED' => 'BR*',
		'MAX_DAILY_10H' => '10h',
		'MAX_DAILY_12H' => '12h',
		'REST_11H' => 'R11',
		'HOLIDAY_WORK' => 'HW',
		'SUNDAY_WORK' => 'SO',
		'OPEN_ENTRY' => 'OE',
		'ORDER_INVALID' => 'IO',
		'MAX_WEEKLY_60H' => 'W60',
	);

	/**
	 * Lang keys of the legend, by violation code
	 * @var array<string,string>
	 */
	const VIOLATION_KEYS = array(
		'BREAK_MISSING' => 'AnxhrViolationBreakMissing',
		'BREAK_AUTO_ADDED' => 'AnxhrViolationBreakAutoAdded',
		'MAX_DAILY_10H' => 'AnxhrViolationMaxDaily10h',
		'MAX_DAILY_12H' => 'AnxhrViolationMaxDaily12h',
		'REST_11H' => 'AnxhrViolationRest11h',
		'HOLIDAY_WORK' => 'AnxhrViolationHolidayWork',
		'SUNDAY_WORK' => 'AnxhrViolationSundayWork',
		'OPEN_ENTRY' => 'AnxhrViolationOpenEntry',
		'ORDER_INVALID' => 'AnxhrViolationOrderInvalid',
		'MAX_WEEKLY_60H' => 'AnxhrViolationMaxWeekly60h',
	);

	/** @var string */
	public $version = 'dolibarr';

	/** @var float Height reserved for the page footer (from bottom border) */
	protected $footerHeight = 14;

	/** @var array<int,array{key:string,w:float,align:string}> Table columns */
	protected $tableCols = array();

	/** @var string Font name */
	protected $font = 'helvetica';

	/** @var array<string,string> Lines of the compact header of follow-up pages */
	protected $headInfo = array();

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$langs->loadLangs(array('main', 'anxhr@anxhr', 'anxhr_time@anxhr'));

		$this->db = $db;
		$this->name = 'monthly_timesheet';
		$this->description = $langs->trans('AnxhrSheetModelDesc');
		$this->update_main_doc_field = 1;
		$this->type = 'pdf';

		// A4 portrait (legal record, always A4 independently of MAIN_PDF_FORMAT)
		$this->page_largeur = 210;
		$this->page_hauteur = 297;
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$this->option_logo = 1;
		$this->option_multilang = 1;

		$this->emetteur = $mysoc;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Build the PDF of a monthly period onto disk
	 *
	 * @param	TimePeriod	$object				Monthly period
	 * @param	Translate	$outputlangs		Output language
	 * @param	string		$srctemplatepath	Unused
	 * @param	int<0,1>	$hidedetails		1 = do not print the clock times column content
	 * @param	int<0,1>	$hidedesc			Unused
	 * @param	int<0,1>	$hideref			Unused
	 * @return	int<0,1>						1=OK, 0=KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $conf, $langs, $user, $hookmanager;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		if (!($object instanceof TimePeriod) || (int) $object->fk_user <= 0) {
			$this->error = 'ErrorBadParameters';
			return 0;
		}
		$outputlangs->loadLangs(array('main', 'other', 'dict', 'companies', 'anxhr@anxhr', 'anxhr_time@anxhr'));

		$employee = new User($this->db);
		if ($employee->fetch((int) $object->fk_user) <= 0) {
			$this->error = 'ErrorRecordNotFound';
			return 0;
		}

		$file = self::getTargetFile($object, $employee);
		if ($file === '') {
			$this->error = $outputlangs->transnoentities('ErrorConstantNotDefined', 'anxhr->multidir_output');
			return 0;
		}
		$dir = dirname($file);
		if (!is_dir($dir) && dol_mkdir($dir) < 0) {
			$this->error = $outputlangs->transnoentities('ErrorCanNotCreateDir', $dir);
			return 0;
		}

		if (!is_object($hookmanager)) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		global $action;
		$hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);

		$data = $this->loadData($object, $employee, $outputlangs);
		if (!is_array($data)) {
			return 0;
		}

		$pdf = pdf_getInstance($this->format);
		$this->font = pdf_getPDFFont($outputlangs);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetAutoPageBreak(false, 0);
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetFont($this->font, '', 9);
		$pdf->setCellPaddings(0.8, 0.4, 0.8, 0.4);
		$pdf->Open();
		$pdf->SetDrawColor(160, 160, 160);
		$title = $outputlangs->transnoentitiesnoconv('AnxhrSheetTitle').' '.$data['monthlabel'];
		$pdf->SetTitle($outputlangs->convToOutputCharset($title.' - '.$data['employee_name']));
		$pdf->SetSubject($outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('AnxhrSheetLegalNote')));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
		$pdf->SetKeyWords($outputlangs->convToOutputCharset('AZG '.$data['monthlabel']));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}

		$this->defineColumns($outputlangs, $hidedetails);
		$this->headInfo = array('title' => $title, 'employee' => $data['employee_name'], 'month' => $data['monthlabel']);

		$pdf->AddPage();
		$posy = $this->pageHead($pdf, $outputlangs, true);
		$posy = $this->employeeBlock($pdf, $object, $data, $outputlangs, $posy);
		$posy = $this->dayTable($pdf, $data, $outputlangs, $posy + 3, $hidedetails);
		$posy = $this->balanceBlock($pdf, $object, $data, $outputlangs, $posy + 4);
		$posy = $this->legendBlock($pdf, $data, $outputlangs, $posy + 4);
		$this->signatureBlock($pdf, $outputlangs, $posy + 4);
		$this->pageFoots($pdf, $outputlangs);

		$pdf->Close();
		// Keep only one sheet per employee and month (the last name may have changed)
		foreach (dol_dir_list($dir, 'files', 0, '^Monatsblatt_.*\.pdf$') as $old) {
			if ($old['fullname'] !== $file) {
				dol_delete_file($old['fullname'], 0, 1);
			}
		}
		$pdf->Output($file, 'F');

		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);
		if ($reshook < 0) {
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
		}
		dolChmod($file);

		$this->result = array('fullpath' => $file);
		return 1;
	}

	/**
	 * Return the absolute path of the sheet of a period (directory is not created).
	 * <multidir_output>/timesheet/<userid>/<YYYY-MM>/Monatsblatt_<YYYY-MM>_<lastname>.pdf
	 *
	 * @param	TimePeriod	$period		Period
	 * @param	User		$employee	Employee
	 * @return	string					Path or '' if the module output dir is unknown
	 */
	public static function getTargetFile($period, $employee)
	{
		$dir = TimePeriod::getSheetDir($period);
		if ($dir === '') {
			return '';
		}
		$ym = sprintf('%04d-%02d', (int) $period->year, (int) $period->month);
		$name = dol_string_unaccent((string) ($employee->lastname !== '' ? $employee->lastname : $employee->login));
		$name = preg_replace('/[^A-Za-z0-9\-]+/', '', $name);
		return $dir.'/'.dol_sanitizeFileName('Monatsblatt_'.$ym.'_'.($name !== '' ? $name : (int) $employee->id).'.pdf');
	}

	/**
	 * Load all data of the sheet (one query per source, no query per day).
	 *
	 * @param	TimePeriod	$object			Period
	 * @param	User		$employee		Employee
	 * @param	Translate	$outputlangs	Output language
	 * @return	array<string,mixed>|int		Data or 0 if KO
	 */
	protected function loadData($object, $employee, $outputlangs)
	{
		list($first, $last) = $object->getMonthBounds();
		$tday = new TimeDay($this->db);
		$days = $tday->fetchDays((int) $object->fk_user, $first, $last);
		if (!is_array($days)) {
			$this->error = $tday->error;
			return 0;
		}
		// One day before and after the month, so night shifts crossing midnight are complete
		$gmFirst = (int) anxhrDayToGmt($first);
		$gmLast = (int) anxhrDayToGmt($last);
		$entryObj = new TimeEntry($this->db);
		$grouped = $entryObj->fetchRangeGroupedByDay((int) $object->fk_user, gmdate('Y-m-d', $gmFirst - 86400), gmdate('Y-m-d', $gmLast + 86400));
		if (!is_array($grouped)) {
			$this->error = $entryObj->error;
			return 0;
		}

		$data = array(
			'first' => $first,
			'last' => $last,
			'days' => $days,
			'shifts' => self::groupByShift($grouped),
			'monthlabel' => $outputlangs->transnoentitiesnoconv('Month'.sprintf('%02d', (int) $object->month)).' '.((int) $object->year),
			'employee_name' => $employee->getFullName($outputlangs),
			'personnel_no' => (!empty($employee->ref_employee) ? $employee->ref_employee : $employee->login),
		);
		$data = array_merge($data, $this->loadContractInfo($object, $employee, $last));
		$data['weekly'] = $object->getWeeklyViolations();

		$data['approver'] = '';
		if ((int) $object->fk_user_approve > 0) {
			$approver = new User($this->db);
			if ($approver->fetch((int) $object->fk_user_approve) > 0) {
				$data['approver'] = $approver->getFullName($outputlangs);
			}
		}
		return $data;
	}

	/**
	 * Weekly hours, time model and collective agreement of the employee for the month
	 * (active HR contract at the end, else at the start of the month; else user card).
	 *
	 * @param	TimePeriod	$object		Period
	 * @param	User		$employee	Employee
	 * @param	string		$last		Last day of the month
	 * @return	array{weekly_hours:float,model_label:string,kv_code:string}
	 */
	protected function loadContractInfo($object, $employee, $last)
	{
		$res = array('weekly_hours' => (float) $employee->weeklyhours, 'model_label' => '', 'kv_code' => '');
		$modelid = 0;
		$contractFile = __DIR__.'/../../../../class/hrcontract.class.php';
		if (file_exists($contractFile)) {
			include_once $contractFile;
		}
		if (class_exists('HrContract')) {
			$contract = new HrContract($this->db);
			$found = (method_exists($contract, 'fetchActiveForUser') && $contract->fetchActiveForUser((int) $object->fk_user, anxhrDayToServerTs($last)) > 0);
			if (!$found && method_exists($contract, 'fetchActiveForUser')) {
				$found = ($contract->fetchActiveForUser((int) $object->fk_user, anxhrDayToServerTs(sprintf('%04d-%02d-01', (int) $object->year, (int) $object->month))) > 0);
			}
			if ($found) {
				if ((float) $contract->weekly_hours > 0) {
					$res['weekly_hours'] = (float) $contract->weekly_hours;
				}
				$modelid = (int) $contract->fk_timemodel;
				$res['kv_code'] = (string) $contract->kv_code;
			}
		}
		if ($modelid <= 0) {
			$modelid = getDolGlobalInt('ANXHR_TIME_DEFAULT_MODEL');
		}
		if ($modelid > 0 && file_exists(__DIR__.'/../../../../class/timemodel.class.php')) {
			include_once __DIR__.'/../../../../class/timemodel.class.php';
			$model = new TimeModel($this->db);
			if ($model->fetch($modelid) > 0) {
				$res['model_label'] = (string) $model->label;
				if ($res['kv_code'] === '' && !empty($model->kv_code)) {
					$res['kv_code'] = (string) $model->kv_code;
				}
			}
		}
		if ($res['kv_code'] === '') {
			$res['kv_code'] = getDolGlobalString('ANXHR_DEFAULT_KV', 'SWOE');
		}
		return $res;
	}

	/**
	 * Attach entries to the day of their shift: a shift belongs to the day of its clock in, so a night
	 * shift 22:00-06:00 is printed on one row (same rule as TimeEntry::fetchDayContext()).
	 *
	 * @param	array<string,array<int,array<string,mixed>>>	$grouped	Entries grouped by calendar day
	 * @return	array<string,array<int,array<string,mixed>>>				Entries grouped by shift day
	 */
	public static function groupByShift(array $grouped)
	{
		ksort($grouped);
		$res = array();
		$shiftDay = null;
		$open = false;
		foreach ($grouped as $day => $entries) {
			foreach ($entries as $e) {
				// A shift never spans more than to the next calendar day
				if ($open && $shiftDay !== null && $day !== $shiftDay && $day !== gmdate('Y-m-d', (int) anxhrDayToGmt($shiftDay) + 86400)) {
					$open = false;
				}
				if ($e['type'] === 'in') {
					$shiftDay = $day;
					$open = true;
				}
				$target = ($open && $shiftDay !== null) ? $shiftDay : $day;
				$res[$target][] = $e;
				if ($e['type'] === 'out') {
					$open = false;
				}
			}
		}
		return $res;
	}

	/**
	 * Define the columns of the day table (total width = page width minus margins).
	 *
	 * @param	Translate	$outputlangs	Output language
	 * @param	int			$hidedetails	1 = no clock times column
	 * @return	void
	 */
	protected function defineColumns($outputlangs, $hidedetails)
	{
		$cols = array(
			array('key' => 'date', 'w' => 17, 'align' => 'L', 'label' => 'AnxhrSheetColDate'),
			array('key' => 'entries', 'w' => 47, 'align' => 'L', 'label' => 'AnxhrSheetColEntries'),
			array('key' => 'absence', 'w' => 19, 'align' => 'L', 'label' => 'AnxhrSheetColAbsence'),
			array('key' => 'target_min', 'w' => 11, 'align' => 'R', 'label' => 'AnxhrSheetColTarget'),
			array('key' => 'worked_min', 'w' => 11, 'align' => 'R', 'label' => 'AnxhrSheetColWorked'),
			array('key' => 'break_min', 'w' => 10, 'align' => 'R', 'label' => 'AnxhrSheetColBreak'),
			array('key' => 'diff_min', 'w' => 12, 'align' => 'R', 'label' => 'AnxhrSheetColDiff'),
			array('key' => 'overtime50_min', 'w' => 11, 'align' => 'R', 'label' => 'AnxhrSheetColOt50'),
			array('key' => 'overtime100_min', 'w' => 11, 'align' => 'R', 'label' => 'AnxhrSheetColOt100'),
			array('key' => 'night_min', 'w' => 10, 'align' => 'R', 'label' => 'AnxhrSheetColNight'),
			array('key' => 'ho', 'w' => 7, 'align' => 'C', 'label' => 'AnxhrSheetColHo'),
			array('key' => 'violations', 'w' => 0, 'align' => 'L', 'label' => 'AnxhrSheetColViolations'),
		);
		if ($hidedetails) {
			$cols[1]['w'] = 0;
		}
		$avail = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$used = 0;
		foreach ($cols as $c) {
			$used += $c['w'];
		}
		$cols[11]['w'] = max(14, $avail - $used);
		$x = $this->marge_gauche;
		foreach ($cols as $k => $c) {
			$cols[$k]['x'] = $x;
			$cols[$k]['title'] = $outputlangs->transnoentitiesnoconv($c['label']);
			$x += $c['w'];
		}
		$this->tableCols = array_values(array_filter($cols, function ($c) {
			return $c['w'] > 0;
		}));
	}

	/**
	 * Print the page head: company (logo, name, address) and title; compact version on follow-up pages.
	 *
	 * @param	TCPDF		$pdf			PDF
	 * @param	Translate	$outputlangs	Output language
	 * @param	bool		$full			Full head (first page)
	 * @return	float						Y position below the head
	 */
	protected function pageHead($pdf, $outputlangs, $full)
	{
		global $conf;

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);
		$w = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$posy = $this->marge_haute;
		$pdf->SetTextColor(0, 0, 60);
		if (!$full) {
			$pdf->SetFont($this->font, 'B', 9);
			$pdf->SetXY($this->marge_gauche, $posy);
			$pdf->Cell($w / 2, 5, $outputlangs->convToOutputCharset($this->headInfo['title']), 0, 0, 'L');
			$pdf->SetFont($this->font, '', 9);
			$pdf->Cell($w / 2, 5, $outputlangs->convToOutputCharset($this->headInfo['employee']), 0, 1, 'R');
			$pdf->SetDrawColor(160, 160, 160);
			$pdf->Line($this->marge_gauche, $posy + 6, $this->page_largeur - $this->marge_droite, $posy + 6);
			return $posy + 8;
		}

		// Company: name and address on the left, logo on the right
		$pdf->SetXY($this->marge_gauche, $posy);
		$address = '';
		if (is_object($this->emetteur) && trim((string) $this->emetteur->name) !== '') {
			$pdf->SetFont($this->font, 'B', 11);
			$pdf->MultiCell(110, 5, $outputlangs->convToOutputCharset((string) $this->emetteur->name), 0, 'L');
			$address = dol_format_address($this->emetteur, 1, ", ", $outputlangs);
		}
		$pdf->SetFont($this->font, '', 8);
		$pdf->SetTextColor(60, 60, 60);
		if ($address !== '') {
			$pdf->SetX($this->marge_gauche);
			$pdf->MultiCell(110, 3.5, $outputlangs->convToOutputCharset($address), 0, 'L');
		}
		$ycompany = $pdf->GetY();
		$ylogo = $posy;
		if (!empty($this->emetteur->logo) && !getDolGlobalString('MAIN_PDF_DISABLE_LOGO')) {
			$logodir = !empty($conf->mycompany->multidir_output[$conf->entity]) ? $conf->mycompany->multidir_output[$conf->entity] : $conf->mycompany->dir_output;
			$logo = $logodir.'/logos/'.$this->emetteur->logo;
			if (is_readable($logo)) {
				$height = min(18, pdf_getHeightForLogo($logo));
				$pdf->Image($logo, $this->page_largeur - $this->marge_droite - 60, $posy, 60, $height, '', '', '', false, 300, '', false, false, 0, 'RT');
				$ylogo = $posy + $height;
			}
		}
		$posy = max($ycompany, $ylogo) + 3;

		// Title
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont($this->font, 'B', 14);
		$pdf->SetXY($this->marge_gauche, $posy);
		$pdf->Cell($w, 7, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('AnxhrSheetTitle')), 0, 1, 'L');
		$pdf->SetFont($this->font, '', 11);
		$pdf->SetX($this->marge_gauche);
		$pdf->Cell($w, 5, $outputlangs->convToOutputCharset($this->headInfo['month'].' - '.$this->headInfo['employee']), 0, 1, 'L');
		$pdf->SetDrawColor(160, 160, 160);
		$pdf->Line($this->marge_gauche, $pdf->GetY() + 1, $this->page_largeur - $this->marge_droite, $pdf->GetY() + 1);
		$pdf->SetTextColor(0, 0, 0);
		return $pdf->GetY() + 3;
	}

	/**
	 * Employee and period status block (two columns of label/value pairs)
	 *
	 * @param	TCPDF				$pdf			PDF
	 * @param	TimePeriod			$object			Period
	 * @param	array<string,mixed>	$data			Data
	 * @param	Translate			$outputlangs	Output language
	 * @param	float				$posy			Y position
	 * @return	float								Y position below the block
	 */
	protected function employeeBlock($pdf, $object, $data, $outputlangs, $posy)
	{
		$statusKeys = array(
			TimePeriod::STATUS_OPEN => 'AnxhrPeriodOpen',
			TimePeriod::STATUS_CONFIRMED => 'AnxhrPeriodConfirmed',
			TimePeriod::STATUS_APPROVED => 'AnxhrPeriodApproved',
			TimePeriod::STATUS_EXPORTED => 'AnxhrPeriodExported',
			TimePeriod::STATUS_REOPENED => 'AnxhrPeriodReopened',
		);
		$status = isset($statusKeys[(int) $object->status]) ? $outputlangs->transnoentitiesnoconv($statusKeys[(int) $object->status]) : '';
		$approval = '';
		if ($data['approver'] !== '' && !empty($object->date_approve)) {
			$approval = $outputlangs->transnoentitiesnoconv('AnxhrSheetApprovedByOn', $data['approver'], self::printDateTz($object->date_approve, 'dayhour', $outputlangs));
		}
		$confirm = '';
		if (!empty($object->date_confirm)) {
			$confirm = self::printDateTz($object->date_confirm, 'dayhour', $outputlangs);
		}
		$weekly = $data['weekly_hours'] > 0 ? price2num($data['weekly_hours'], 2).' h' : '-';
		$left = array(
			array('AnxhrSheetEmployee', $data['employee_name']),
			array('AnxhrSheetPersonnelNo', (string) $data['personnel_no']),
			array('AnxhrSheetWeeklyHours', str_replace('.', $outputlangs->transnoentitiesnoconv('SeparatorDecimal'), $weekly)),
		);
		$right = array(
			array('AnxhrSheetTimeModel', $data['model_label'] !== '' ? $data['model_label'] : '-'),
			array('AnxhrSheetKv', $data['kv_code'] !== '' ? $data['kv_code'] : '-'),
			array('AnxhrSheetStatus', $status),
		);
		if ($confirm !== '') {
			$left[] = array('AnxhrSheetConfirmedOn', $confirm);
		}
		if ($approval !== '') {
			$right[] = array('AnxhrSheetApproval', $approval);
		}
		$colw = ($this->page_largeur - $this->marge_gauche - $this->marge_droite) / 2;
		$yl = $this->labelValueList($pdf, $left, $outputlangs, $this->marge_gauche, $posy, $colw);
		$yr = $this->labelValueList($pdf, $right, $outputlangs, $this->marge_gauche + $colw, $posy, $colw);
		return max($yl, $yr);
	}

	/**
	 * Print a list of label: value lines
	 *
	 * @param	TCPDF							$pdf			PDF
	 * @param	array<int,array{0:string,1:string}>	$lines		Lines (lang key, value)
	 * @param	Translate						$outputlangs	Output language
	 * @param	float							$x				X
	 * @param	float							$y				Y
	 * @param	float							$w				Width
	 * @return	float											Y below
	 */
	protected function labelValueList($pdf, $lines, $outputlangs, $x, $y, $w)
	{
		$lw = 34;
		foreach ($lines as $l) {
			$pdf->SetFont($this->font, '', 8.5);
			$pdf->SetTextColor(90, 90, 90);
			$pdf->SetXY($x, $y);
			$pdf->Cell($lw, 4.5, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv($l[0])), 0, 0, 'L');
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont($this->font, 'B', 8.5);
			$pdf->MultiCell($w - $lw - 2, 4.5, $outputlangs->convToOutputCharset($l[1]), 0, 'L', false, 1, $x + $lw, $y);
			$y = max($y + 4.5, $pdf->GetY());
		}
		return $y;
	}

	/**
	 * Print the table header row
	 *
	 * @param	TCPDF		$pdf			PDF
	 * @param	float		$posy			Y
	 * @return	float						Y below
	 */
	protected function tableHeader($pdf, $posy)
	{
		$pdf->SetFont($this->font, 'B', 7);
		$pdf->SetFillColor(220, 224, 234);
		$pdf->SetTextColor(0, 0, 60);
		$h = 6;
		foreach ($this->tableCols as $c) {
			$pdf->MultiCell($c['w'], $h, $c['title'], 1, ($c['align'] === 'R' ? 'R' : ($c['align'] === 'C' ? 'C' : 'L')), true, 0, $c['x'], $posy, true, 0, false, true, $h, 'M', true);
		}
		$pdf->SetTextColor(0, 0, 0);
		return $posy + $h;
	}

	/**
	 * Return the bottom limit for content on a page
	 *
	 * @return float
	 */
	protected function contentBottom()
	{
		return $this->page_hauteur - $this->marge_basse - $this->footerHeight;
	}

	/**
	 * Start a new page with compact head when the block of height $h does not fit.
	 *
	 * @param	TCPDF		$pdf			PDF
	 * @param	Translate	$outputlangs	Output language
	 * @param	float		$posy			Current Y
	 * @param	float		$h				Height needed
	 * @return	float						Y to use
	 */
	protected function ensureSpace($pdf, $outputlangs, $posy, $h)
	{
		if ($posy + $h <= $this->contentBottom()) {
			return $posy;
		}
		$pdf->AddPage();
		return $this->pageHead($pdf, $outputlangs, false);
	}

	/**
	 * Print the day table (one row per calendar day) and the totals row
	 *
	 * @param	TCPDF				$pdf			PDF
	 * @param	array<string,mixed>	$data			Data
	 * @param	Translate			$outputlangs	Output language
	 * @param	float				$posy			Y
	 * @param	int					$hidedetails	1 = no clock times
	 * @return	float								Y below the table
	 */
	protected function dayTable($pdf, $data, $outputlangs, $posy, $hidedetails)
	{
		$posy = $this->ensureSpace($pdf, $outputlangs, $posy, 20);
		$posy = $this->tableHeader($pdf, $posy);
		$totals = array('target_min' => 0, 'worked_min' => 0, 'break_min' => 0, 'diff_min' => 0, 'overtime50_min' => 0, 'overtime100_min' => 0, 'night_min' => 0);
		$y = (int) substr($data['first'], 0, 4);
		$m = (int) substr($data['first'], 5, 2);
		$nbdays = (int) substr($data['last'], 8, 2);
		for ($d = 1; $d <= $nbdays; $d++) {
			$day = sprintf('%04d-%02d-%02d', $y, $m, $d);
			$gm = gmmktime(0, 0, 0, $m, $d, $y);
			$row = isset($data['days'][$day]) ? $data['days'][$day] : null;
			$entries = isset($data['shifts'][$day]) ? $data['shifts'][$day] : array();
			$cells = $this->dayCells($day, $gm, $row, $entries, $outputlangs, $hidedetails);
			if ($row) {
				foreach (array_keys($totals) as $k) {
					$totals[$k] += (int) $row[$k];
				}
			}
			$grey = ((int) gmdate('N', $gm) >= 6 || ($row && !empty($row['holiday_flag'])));
			$posy = $this->tableRow($pdf, $outputlangs, $cells, $posy, $grey, $cells['_warn']);
		}

		// Totals row
		$cells = array('date' => $outputlangs->transnoentitiesnoconv('AnxhrSheetTotal'), 'entries' => '', 'absence' => '', 'ho' => '', 'violations' => '', '_warn' => 0);
		foreach ($totals as $k => $v) {
			$cells[$k] = anxhrTimeFormatMinutes($v, $k === 'diff_min');
		}
		$posy = $this->tableRow($pdf, $outputlangs, $cells, $posy, false, 0, true);
		return $posy;
	}

	/**
	 * Build the cell texts of one day
	 *
	 * @param	string							$day			Day Y-m-d
	 * @param	int								$gm				GMT midnight timestamp of the day
	 * @param	array<string,mixed>|null		$row			Computed day or null
	 * @param	array<int,array<string,mixed>>	$entries		Entries of the day (shift based)
	 * @param	Translate						$outputlangs	Output language
	 * @param	int								$hidedetails	1 = no clock times
	 * @return	array<string,mixed>
	 */
	protected function dayCells($day, $gm, $row, $entries, $outputlangs, $hidedetails)
	{
		// Two letter abbreviation when the language has one (de: Mo, Di), else the 3 letter one (en: Mon, Tue)
		$wd = $outputlangs->transnoentitiesnoconv('Short'.gmdate('l', $gm));
		if (dol_strlen($wd) < 2) {
			$wd = dol_print_date($gm, '%a', 'gmt', $outputlangs);
		}
		$cells = array(
			'date' => $wd.' '.dol_print_date($gm, '%d.%m.', 'gmt', $outputlangs),
			'entries' => ($hidedetails || empty($entries)) ? '' : TimeEntry::formatCompact($entries),
			'absence' => '',
			'ho' => '',
			'violations' => '',
			'_warn' => 0,
		);
		foreach ($entries as $e) {
			if (!empty($e['homeoffice'])) {
				$cells['ho'] = 'HO';
				break;
			}
		}
		foreach (array('target_min', 'worked_min', 'break_min', 'diff_min', 'overtime50_min', 'overtime100_min', 'night_min') as $k) {
			$cells[$k] = '';
		}
		if (!$row) {
			return $cells;
		}
		$cells['absence'] = self::absenceLabel($row['absence_code'], $outputlangs);
		if ($cells['absence'] === '' && !empty($row['holiday_flag'])) {
			$cells['absence'] = $outputlangs->transnoentitiesnoconv('AnxhrPublicHoliday');
		}
		// Free days without work stay empty, so the relevant rows stand out
		$idle = (!(int) $row['target_min'] && !(int) $row['worked_min'] && !(int) $row['diff_min'] && empty($entries));
		$cells['target_min'] = $idle ? '' : anxhrTimeFormatMinutes($row['target_min']);
		$cells['worked_min'] = ((int) $row['worked_min'] || !empty($entries)) ? anxhrTimeFormatMinutes($row['worked_min']) : '';
		$cells['break_min'] = (int) $row['break_min'] ? anxhrTimeFormatMinutes($row['break_min']) : '';
		$cells['diff_min'] = $idle ? '' : anxhrTimeFormatMinutes($row['diff_min'], true);
		foreach (array('overtime50_min', 'overtime100_min', 'night_min') as $k) {
			$cells[$k] = (int) $row[$k] ? anxhrTimeFormatMinutes($row[$k]) : '';
		}
		$codes = array();
		foreach ($row['violations'] as $v) {
			if (!is_array($v) || empty($v['code'])) {
				continue;
			}
			$codes[] = isset(self::VIOLATION_SHORT[$v['code']]) ? self::VIOLATION_SHORT[$v['code']] : (string) $v['code'];
			if (!isset($v['level']) || $v['level'] !== 'info') {
				$cells['_warn'] = 1;
			}
		}
		$cells['violations'] = implode(', ', array_unique($codes));
		return $cells;
	}

	/**
	 * Print one table row (height adapted to wrapped cells), with page break and repeated header.
	 *
	 * @param	TCPDF				$pdf			PDF
	 * @param	Translate			$outputlangs	Output language
	 * @param	array<string,mixed>	$cells			Cell texts by column key
	 * @param	float				$posy			Y
	 * @param	bool				$grey			Grey fill (weekend, public holiday)
	 * @param	int					$warn			1 = print violation codes in warning color
	 * @param	bool				$total			Totals row (bold)
	 * @return	float								Y below the row
	 */
	protected function tableRow($pdf, $outputlangs, $cells, $posy, $grey, $warn, $total = false)
	{
		$fontsize = 7;
		$minh = 4.4;
		$h = $minh;
		$pdf->SetFont($this->font, $total ? 'B' : '', $fontsize);
		foreach ($this->tableCols as $c) {
			if (in_array($c['key'], array('entries', 'violations', 'absence'), true) && $cells[$c['key']] !== '') {
				$pdf->SetFont($this->font, $total ? 'B' : '', $c['key'] === 'entries' ? 6.5 : $fontsize);
				$h = max($h, $pdf->getStringHeight($c['w'], $outputlangs->convToOutputCharset((string) $cells[$c['key']])));
			}
		}
		if ($posy + $h > $this->contentBottom()) {
			$pdf->AddPage();
			$posy = $this->pageHead($pdf, $outputlangs, false);
			$posy = $this->tableHeader($pdf, $posy);
		}
		if ($total) {
			$pdf->SetFillColor(220, 224, 234);
		} else {
			$pdf->SetFillColor(236, 236, 236);
		}
		$fill = ($grey || $total);
		foreach ($this->tableCols as $c) {
			$txt = isset($cells[$c['key']]) ? (string) $cells[$c['key']] : '';
			$pdf->SetFont($this->font, $total ? 'B' : '', $c['key'] === 'entries' ? 6.5 : $fontsize);
			if ($c['key'] === 'violations' && $warn) {
				$pdf->SetTextColor(190, 60, 0);
				$pdf->SetFont($this->font, 'B', $fontsize);
			} elseif ($c['key'] === 'violations') {
				$pdf->SetTextColor(110, 110, 110);
			} else {
				$pdf->SetTextColor(0, 0, 0);
			}
			$pdf->MultiCell($c['w'], $h, $outputlangs->convToOutputCharset($txt), 1, $c['align'], $fill, 0, $c['x'], $posy, true, 0, false, true, $h, 'M');
		}
		$pdf->SetTextColor(0, 0, 0);
		return $posy + $h;
	}

	/**
	 * Print the balance block
	 *
	 * @param	TCPDF				$pdf			PDF
	 * @param	TimePeriod			$object			Period
	 * @param	array<string,mixed>	$data			Data
	 * @param	Translate			$outputlangs	Output language
	 * @param	float				$posy			Y
	 * @return	float								Y below
	 */
	protected function balanceBlock($pdf, $object, $data, $outputlangs, $posy)
	{
		$sumdiff = 0;
		foreach ($data['days'] as $row) {
			$sumdiff += (int) $row['diff_min'];
		}
		$dec = $outputlangs->transnoentitiesnoconv('SeparatorDecimal');
		$lines = array(
			array('AnxhrSheetBalanceStart', anxhrTimeFormatMinutes($object->balance_start_min, true)),
			array('AnxhrSheetSumDiff', anxhrTimeFormatMinutes($sumdiff, true)),
			array('AnxhrSheetOvertimePaid', anxhrTimeFormatMinutes(-1 * (int) $object->overtime_paid_min, true)),
			array('AnxhrSheetBalanceEnd', anxhrTimeFormatMinutes($object->balance_end_min, true)),
		);
		$lines2 = array(
			array('AnxhrSheetVacationDays', str_replace('.', $dec, (string) price2num((float) $object->vacation_days, 2))),
			array('AnxhrSheetSickDays', str_replace('.', $dec, (string) price2num((float) $object->sick_days, 2))),
			array('AnxhrSheetZaTaken', anxhrTimeFormatMinutes($object->za_taken_min)),
			array('AnxhrSheetExtraParttime', anxhrTimeFormatMinutes($object->extra_parttime_min)),
		);
		$h = 7 + 4.6 * 4;
		$posy = $this->ensureSpace($pdf, $outputlangs, $posy, $h);
		$w = ($this->page_largeur - $this->marge_gauche - $this->marge_droite);
		$pdf->SetFont($this->font, 'B', 9);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetXY($this->marge_gauche, $posy);
		$pdf->Cell($w, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('AnxhrSheetBalance')), 0, 1, 'L');
		$pdf->SetTextColor(0, 0, 0);
		$top = $posy + 6.5;
		$colw = $w / 2 - 3;
		foreach (array(array($lines, $this->marge_gauche), array($lines2, $this->marge_gauche + $w / 2 + 3)) as $blk) {
			$y = $top;
			foreach ($blk[0] as $i => $l) {
				$bold = ($blk[0] === $lines && $i === 3);
				$pdf->SetFont($this->font, $bold ? 'B' : '', 8.5);
				$pdf->SetXY($blk[1], $y);
				$pdf->Cell($colw - 25, 4.6, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv($l[0])), 'B', 0, 'L');
				$pdf->Cell(25, 4.6, $l[1], 'B', 0, 'R');
				$y += 4.6;
			}
		}
		return $top + 4.6 * 4;
	}

	/**
	 * Print the legend (codes, colors) and the list of notes / violations of the month
	 *
	 * @param	TCPDF				$pdf			PDF
	 * @param	array<string,mixed>	$data			Data
	 * @param	Translate			$outputlangs	Output language
	 * @param	float				$posy			Y
	 * @return	float								Y below
	 */
	protected function legendBlock($pdf, $data, $outputlangs, $posy)
	{
		$w = $this->page_largeur - $this->marge_gauche - $this->marge_droite;

		// Notes and violations of the month (with value), daily and weekly
		$details = array();
		foreach ($data['days'] as $day => $row) {
			foreach ($row['violations'] as $v) {
				if (is_array($v) && !empty($v['code'])) {
					$short = isset(self::VIOLATION_SHORT[$v['code']]) ? self::VIOLATION_SHORT[$v['code']] : $v['code'];
					$details[] = dol_print_date(anxhrDayToGmt($day), '%d.%m.', 'gmt', $outputlangs).' '.$short.': '.anxhrViolationMessage($v, $outputlangs);
				}
			}
		}
		foreach ($this->weeklyViolations($data) as $v) {
			$details[] = $outputlangs->transnoentitiesnoconv('AnxhrSheetWeek', isset($v['week']) ? substr((string) $v['week'], -2) : '').' '.self::VIOLATION_SHORT['MAX_WEEKLY_60H'].': '.anxhrViolationMessage($v, $outputlangs);
		}

		$posy = $this->ensureSpace($pdf, $outputlangs, $posy, 14);
		$pdf->SetFont($this->font, 'B', 9);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetXY($this->marge_gauche, $posy);
		$pdf->Cell($w, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('AnxhrSheetViolationDetails')), 0, 1, 'L');
		$pdf->SetTextColor(0, 0, 0);
		$posy += 6;
		$pdf->SetFont($this->font, '', 7.5);
		if (empty($details)) {
			$details[] = $outputlangs->transnoentitiesnoconv('AnxhrSheetNoViolations');
		}
		foreach ($details as $line) {
			$txt = $outputlangs->convToOutputCharset($line);
			$h = max(3.6, $pdf->getStringHeight($w, $txt));
			$posy = $this->ensureSpace($pdf, $outputlangs, $posy, $h);
			$pdf->MultiCell($w, $h, $txt, 0, 'L', false, 1, $this->marge_gauche, $posy, true, 0, false, true, 0, 'T');
			$posy += $h;
		}

		// Legend of codes (two columns)
		$items = array();
		foreach (self::VIOLATION_SHORT as $code => $short) {
			$items[] = $short.' = '.$outputlangs->transnoentitiesnoconv(self::VIOLATION_KEYS[$code]);
		}
		$items[] = 'HO = '.$outputlangs->transnoentitiesnoconv('AnxhrSheetLegendHo');
		$items[] = $outputlangs->transnoentitiesnoconv('AnxhrSheetLegendGrey');
		$lineh = 3.4;
		$rows = (int) ceil(count($items) / 2);
		$posy = $this->ensureSpace($pdf, $outputlangs, $posy + 2, 6 + $rows * $lineh);
		$pdf->SetFont($this->font, 'B', 8);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetXY($this->marge_gauche, $posy);
		$pdf->Cell($w, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('AnxhrSheetLegend')), 0, 1, 'L');
		$pdf->SetTextColor(70, 70, 70);
		$pdf->SetFont($this->font, '', 6.5);
		$posy += 5;
		foreach ($items as $i => $txt) {
			$col = (int) ($i >= $rows);
			$row = $i - $col * $rows;
			$pdf->SetXY($this->marge_gauche + $col * $w / 2, $posy + $row * $lineh);
			$pdf->Cell($w / 2, $lineh, $outputlangs->convToOutputCharset($txt), 0, 0, 'L', false, '', 1);
		}
		$pdf->SetTextColor(0, 0, 0);
		return $posy + $rows * $lineh;
	}

	/**
	 * Return the weekly violations stored on the period (60 hours per week)
	 *
	 * @param	array<string,mixed>	$data	Data
	 * @return	array<int,array<string,mixed>>
	 */
	protected function weeklyViolations($data)
	{
		return isset($data['weekly']) && is_array($data['weekly']) ? $data['weekly'] : array();
	}

	/**
	 * Print the confirmation text and the two signature lines
	 *
	 * @param	TCPDF		$pdf			PDF
	 * @param	Translate	$outputlangs	Output language
	 * @param	float		$posy			Y
	 * @return	float						Y below
	 */
	protected function signatureBlock($pdf, $outputlangs, $posy)
	{
		$w = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$posy = $this->ensureSpace($pdf, $outputlangs, $posy, 30);
		$pdf->SetFont($this->font, '', 8);
		$pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('AnxhrSheetConfirmText')), 0, 'L', false, 1, $this->marge_gauche, $posy);
		$posy = $pdf->GetY() + 14;
		$colw = $w / 2 - 6;
		foreach (array(array('AnxhrSheetSignEmployee', $this->marge_gauche), array('AnxhrSheetSignEmployer', $this->marge_gauche + $w / 2 + 6)) as $s) {
			$pdf->SetDrawColor(0, 0, 0);
			$pdf->Line($s[1], $posy, $s[1] + $colw, $posy);
			$pdf->SetFont($this->font, '', 7.5);
			$pdf->SetXY($s[1], $posy + 0.5);
			$pdf->Cell($colw, 4, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv($s[0])), 0, 0, 'L');
		}
		$pdf->SetDrawColor(160, 160, 160);
		return $posy + 5;
	}

	/**
	 * Print the footer on every page: generation date, legal note, page x / y
	 *
	 * @param	TCPDF		$pdf			PDF
	 * @param	Translate	$outputlangs	Output language
	 * @return	void
	 */
	protected function pageFoots($pdf, $outputlangs)
	{
		$nb = $pdf->getNumPages();
		$w = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$generated = $outputlangs->transnoentitiesnoconv('AnxhrSheetGeneratedOn', self::printDateTz(dol_now(), 'dayhour', $outputlangs));
		$legal = $outputlangs->transnoentitiesnoconv('AnxhrSheetLegalNote');
		$company = is_object($this->emetteur) ? trim((string) $this->emetteur->name) : '';
		$line2 = ($company !== '' ? $company.' - ' : '').$this->headInfo['employee'].' - '.$this->headInfo['title'];
		for ($i = 1; $i <= $nb; $i++) {
			$pdf->setPage($i);
			$y = $this->page_hauteur - $this->marge_basse - $this->footerHeight + 4;
			$pdf->SetDrawColor(160, 160, 160);
			$pdf->Line($this->marge_gauche, $y, $this->page_largeur - $this->marge_droite, $y);
			$pdf->SetFont($this->font, '', 7);
			$pdf->SetTextColor(90, 90, 90);
			$pdf->SetXY($this->marge_gauche, $y + 1);
			$pdf->Cell($w / 3, 4, $outputlangs->convToOutputCharset($generated), 0, 0, 'L');
			$pdf->Cell($w / 3, 4, $outputlangs->convToOutputCharset($legal), 0, 0, 'C');
			$pdf->Cell($w / 3, 4, $outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv('AnxhrSheetPage', $i, $nb)), 0, 0, 'R');
			$pdf->SetXY($this->marge_gauche, $y + 5);
			$pdf->Cell($w, 4, $outputlangs->convToOutputCharset($line2), 0, 0, 'C', false, '', 1);
			$pdf->SetTextColor(0, 0, 0);
		}
		$pdf->lastPage();
	}

	/**
	 * Print a timestamp in the company time zone (ANXHR_TIMEZONE) with the output language formats
	 *
	 * @param	int|string	$ts				Timestamp
	 * @param	string		$format			dol_print_date() format
	 * @param	Translate	$outputlangs	Output language
	 * @return	string
	 */
	public static function printDateTz($ts, $format, $outputlangs)
	{
		if ($ts === '' || $ts === null) {
			return '';
		}
		$dt = new DateTime('@'.((int) $ts));
		$dt->setTimezone(anxhrTimeZone());
		return dol_print_date((int) $ts + $dt->getOffset(), $format, 'gmt', $outputlangs);
	}

	/**
	 * Return the translated absence label (plain text, for PDF)
	 *
	 * @param	string|null	$code			Absence code
	 * @param	Translate	$outputlangs	Output language
	 * @return	string
	 */
	public static function absenceLabel($code, $outputlangs)
	{
		if (empty($code)) {
			return '';
		}
		$map = array('vacation' => 'AnxhrAbsenceVacation', 'sick' => 'AnxhrAbsenceSick', 'za' => 'AnxhrAbsenceZa', 'holiday' => 'AnxhrAbsenceHoliday', 'special' => 'AnxhrAbsenceSpecial', 'other' => 'AnxhrAbsenceOther');
		return isset($map[$code]) ? $outputlangs->transnoentitiesnoconv($map[$code]) : (string) $code;
	}
}
