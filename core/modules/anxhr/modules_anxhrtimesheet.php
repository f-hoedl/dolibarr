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
 * \file        htdocs/custom/anxhr/core/modules/anxhr/modules_anxhrtimesheet.php
 * \ingroup     anxhr
 * \brief       Parent class of the monthly time sheet document models (AZG paragraph 26 record)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';


/**
 * Parent class of the monthly time sheet models
 */
abstract class ModelePDFAnxhrTimesheet extends CommonDocGenerator
{
	/**
	 * @var string Document model type stored in llx_document_model
	 */
	const DOC_TYPE = 'anxhr_timesheet';

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Return list of active generation models
	 *
	 * @param	DoliDB		$db					Database handler
	 * @param	int<0,max>	$maxfilenamelength	Max length of value to show
	 * @return	string[]|int<-1,0>				List of templates
	 */
	public static function liste_modeles($db, $maxfilenamelength = 0)
	{
		// phpcs:enable
		include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
		return getListOfModels($db, self::DOC_TYPE, $maxfilenamelength);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Build the document onto disk
	 *
	 * @param	TimePeriod	$object				Monthly period to print
	 * @param	Translate	$outputlangs		Output language
	 * @param	string		$srctemplatepath	Full path of source template (unused for PDF)
	 * @param	int<0,1>	$hidedetails		Do not show the entries column
	 * @param	int<0,1>	$hidedesc			Unused
	 * @param	int<0,1>	$hideref			Unused
	 * @return	int<0,1>						1=OK, 0=KO
	 */
	abstract public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0);
}
