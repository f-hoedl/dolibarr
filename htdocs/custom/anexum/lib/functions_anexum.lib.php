<?php

/* Copyright (C) 2026  Florian Hödl  <florian@hoedl.co>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/anexum/lib/functions_anexum.lib.php
 * \ingroup anexum
 * \brief   Substitution-array extensions loaded by Dolibarr core via
 *          $conf->modules_parts['substitutions']. Core calls
 *          anexum_completesubstitutionarray() from
 *          htdocs/core/lib/functions.lib.php:10240
 *          (function complete_substitutions_array).
 *
 * ClickUp 869ab3xx9 — adds the tokens the Fertigstellungsmeldung
 * template relies on:
 *   __EXTRAFIELD_<NAME>_LABEL__   for every sellist / select extrafield
 *   __ORDER_REF__                 first linked commande on a contract
 *   __DATE__                      today's date, formatted per user langs
 */

/**
 * Populate anexum-specific substitution tokens on the given object.
 *
 * Registered via `$this->module_parts['substitutions'] = array('/anexum/lib/')`
 * in modAnexum.class.php. Dolibarr picks us up by file-name pattern
 * `functions_*.lib.php` and calls this function name derived from the
 * suffix (`anexum_completesubstitutionarray`).
 *
 * @param  array       $substitutionarray Reference; we add keys in place.
 * @param  Translate   $outputlangs       Current langs (for date format).
 * @param  ?CommonObject $object          Object being mailed (contract, order, ...).
 * @param  ?array      $parameters        Free-form parameters from caller.
 * @return int                            0 (ignored by caller).
 */
function anexum_completesubstitutionarray(&$substitutionarray, $outputlangs, $object = null, $parameters = null)
{
    global $db;

    // Always expose __DATE__ — useful for every template regardless of object.
    if (!isset($substitutionarray['__DATE__'])) {
        $substitutionarray['__DATE__'] = dol_print_date(dol_now(), 'day', 'tzuser', $outputlangs);
    }

    if (!is_object($object) || empty($object->table_element) || empty($object->id)) {
        return 0;
    }

    // Sellist / select label resolution.
    require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
    $extrafields = new ExtraFields($db);
    $extrafields->fetch_name_optionals_label($object->table_element, true);

    $attrs = $extrafields->attributes[$object->table_element] ?? null;
    if (is_array($attrs) && !empty($attrs['label']) && is_array($attrs['label'])) {
        if (empty($object->array_options) && method_exists($object, 'fetch_optionals')) {
            $object->fetch_optionals();
        }
        foreach ($attrs['label'] as $key => $label) {
            $type  = $attrs['type'][$key] ?? '';
            if (!in_array($type, array('sellist', 'select'), true)) {
                continue;
            }
            $param = $attrs['param'][$key] ?? array();
            $raw   = $object->array_options['options_' . $key] ?? null;
            $token = '__EXTRAFIELD_' . strtoupper($key) . '_LABEL__';
            if ($raw === null || $raw === '') {
                $substitutionarray[$token] = '';
                continue;
            }
            $substitutionarray[$token] = anexum_resolve_extrafield_label($type, $param, $raw);
        }
    }

    // Contract → first linked order ref as __ORDER_REF__.
    if ($object->element === 'contrat' && method_exists($object, 'fetchObjectLinked')) {
        $object->fetchObjectLinked('', 'commande');
        if (!empty($object->linkedObjects['commande']) && is_array($object->linkedObjects['commande'])) {
            $firstOrder = reset($object->linkedObjects['commande']);
            if (is_object($firstOrder) && !empty($firstOrder->ref)) {
                $substitutionarray['__ORDER_REF__'] = (string) $firstOrder->ref;
            }
        }
    }

    return 0;
}

/**
 * Resolve a sellist / select raw stored value to its human-readable label.
 *
 * @param  string $type  'sellist' or 'select'
 * @param  array  $param Extrafield param array (options key holds config)
 * @param  mixed  $raw   Stored value (rowid for sellist, key for select)
 * @return string        Resolved label, or the raw value if no resolution possible.
 */
function anexum_resolve_extrafield_label($type, $param, $raw)
{
    global $db;

    if ($type === 'select') {
        if (isset($param['options']) && is_array($param['options']) && isset($param['options'][$raw])) {
            return (string) $param['options'][$raw];
        }
        return (string) $raw;
    }

    // sellist: options is a single-entry array whose key is "table:labelcol:rowidcol[:filter]"
    if (!isset($param['options']) || !is_array($param['options'])) {
        return (string) $raw;
    }
    $optkey = array_key_first($param['options']);
    if (empty($optkey)) {
        return (string) $raw;
    }
    $pieces   = explode(':', $optkey);
    $table    = $pieces[0] ?? '';
    $labelcol = $pieces[1] ?? 'label';
    $idcol    = $pieces[2] ?? 'rowid';
    if (empty($table)) {
        return (string) $raw;
    }

    $sql = "SELECT " . $db->sanitize($labelcol) . " AS l"
        . " FROM " . MAIN_DB_PREFIX . $db->sanitize($table)
        . " WHERE " . $db->sanitize($idcol) . " = '" . $db->escape((string) $raw) . "'"
        . " LIMIT 1";
    $resql = $db->query($sql);
    if ($resql) {
        if ($obj = $db->fetch_object($resql)) {
            $db->free($resql);
            return (string) $obj->l;
        }
        $db->free($resql);
    }
    return (string) $raw;
}
