<?php

/* Copyright (C) 2023 SuperAdmin
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
 */

/**
 * \file    anexum/class/actions_anexum.class.php
 * \ingroup anexum
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class ActionsAnexum
 */
class ActionsAnexum
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;

    /**
     * @var string Error code (or message)
     */
    public $error = '';

    /**
     * @var array Errors
     */
    public $errors = array();


    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = array();

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;

    /**
     * @var int		Priority of hook (50 is used if value is not defined)
     */
    public $priority;


    /**
     * Constructor
     *
     *  @param		DoliDB		$db      Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }


    /**
     * Execute action
     *
     * @param	array			$parameters		Array of parameters
     * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param	string			$action      	'add', 'update', 'view'
     * @return	int         					<0 if KO,
     *                           				=0 if OK but we want to process standard actions too,
     *                            				>0 if OK and we want to replace standard actions.
     */
    public function getNomUrl($parameters, &$object, &$action)
    {
        global $user;

        // Only modify URLs for external users viewing ActionComm objects
        if (empty($user->socid) || !is_object($object) || $object->element !== 'action') {
            return 0;
        }

        // Inject socid into the card.php URL so the early security check passes
        if (isset($parameters['getnomurl'])) {
            $modified = str_replace(
                DOL_URL_ROOT . '/comm/action/card.php?id=',
                DOL_URL_ROOT . '/comm/action/card.php?socid=' . ((int) $user->socid) . '&id=',
                $parameters['getnomurl']
            );

            if ($modified === $parameters['getnomurl']) {
                dol_syslog('ActionsAnexum::getNomUrl - card.php URL pattern not found, hook skipped', LOG_DEBUG);
                return 0;
            }

            $this->resprints = $modified;
            return 1; // Replace result with $this->resprints
        }

        return 0;
    }

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param   array           $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $db, $conf, $user, $langs;
        global $delayedhtmlcontent;
        global $extrafields;

        $this->replaceExtUserFilter($parameters, $action);
        $this->hideDraftsForExternalUsersCard($parameters, $object, $action);
        $this->prefillTicketCcMonitoring($parameters, $action);
        $this->injectTicketHumanOnlyToggle($parameters);


        if ($parameters["currentcontext"] == "publicnewticketcard" || ($parameters["currentcontext"] == "ticketcard" && ($action == 'create') || ($action == 'edit'))) {
            $error = 0; // Error counter

            require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

            //
            // Umschreiben: Alles soll am Anfang ausgeblendet werden und nur das eingeblendet was auch angezeigt werden soll (COM alles wo COM vorkommt)
            //

            $ef = new ExtraFields($db);
            $ef->fetch_name_optionals_label("ticket");
            // $ef = $extrafields;

            $sql = "SELECT code FROM llx_c_ticket_type WHERE active = 1";
            $result = $db->query($sql);

            $codes = array();

            if (!empty($result)) {
                while ($obj = $db->fetch_object($result)) {
                    $codes[] = $obj->code;
                }
            }

            $keys = array_keys($ef->attributes['ticket']['param']);

            $delayedhtmlcontent = '<script>';

            $hide = array();
            foreach ($keys as $key) {
                if (substr($key, 0, 7) == "iftype_") {
                    // Everything gets hidden at the start
                    $delayedhtmlcontent .= "$('.ticket_extras_" . $key . "').hide();";

                    foreach ($codes as $code) {
                        $tempcode = "_" . strtolower($code) . "_";
                        if (strpos($key, $tempcode) && (substr($key, 0, 7) == "iftype_")) {
                            $hide[$code][] = $key;
                        }
                    }
                }
            }

            $delayedhtmlcontent .= "$('#selecttype_code').on('change', function() {
				console.log($('#selecttype_code').val());";

            // Everything gets hidden on change
            foreach ($keys as $key) {
                if (substr($key, 0, 7) == "iftype_") {
                    $delayedhtmlcontent .= "$('.ticket_extras_" . $key . "').hide();";
                }
            }

            foreach ($hide as $code => $exe) {
                $delayedhtmlcontent .= "


				if($('#selecttype_code').val() == '" . $code . "')
				{";
                foreach ($exe as $line) {
                    $delayedhtmlcontent .= "$('.ticket_extras_" . $line . "').show();
					console.log('" . $line . "');
					";
                }
                $delayedhtmlcontent .= "}";
            }

            $delayedhtmlcontent .= "});";

            $delayedhtmlcontent .= "setTimeout(function () {
				console.log($('#selecttype_code').val());";

            foreach ($hide as $code => $exe) {
                $delayedhtmlcontent .= "
				console.log ('XXX');
				console.log($('#selecttype_code').val());
				if($('#selecttype_code').val() == '" . $code . "')
				{";
                foreach ($exe as $line) {
                    $delayedhtmlcontent .= "$('.ticket_extras_" . $line . "').show();
					console.log('" . $line . "');
					";
                }
                $delayedhtmlcontent .= "}";
            }

            $delayedhtmlcontent .= "console.log ('Super');
			  	}, 100)";


            // Remove Types which should not be on public page
            //$("#selecttype_code option[value='COM']").remove();


            // Remove private messages fdrom ticket events
            // $(this).parent().prev('li').
            // $('.timeline-code-ticket_msg_private').remove();

            $delayedhtmlcontent .= '</script>';


            /* print_r($parameters); print_r($object); echo "action: " . $action; */
            if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {	    // do something only for the context 'somecontext1' or 'somecontext2'
                // Do what you want here...
                // You can for example call global vars like $fieldstosearchall to overwrite them, or update database depending on $action and $_POST values.
            }

            if (!$error) {
                $this->results = array('myreturn' => 999);
                $this->resprints = 'A text to show';
                return 0; // or return 1 to replace standard code
            } else {
                $this->errors[] = 'Error message';
                return -1;
            }
        } else {
            return 0;
        }
    }


    /**
     * Overloading the doMassActions function : replacing the parent's function with the one below
     *
     * @param   array           $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doMassActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0; // Error counter

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
            foreach ($parameters['toselect'] as $objectid) {
                // Do action on each object id
            }
        }

        if (!$error) {
            $this->results = array('myreturn' => 999);
            $this->resprints = 'A text to show';
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }


    /**
     * Overloading the addMoreMassActions function : replacing the parent's function with the one below
     *
     * @param   array           $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0; // Error counter
        $disabled = 1;

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
            $this->resprints = '<option value="0"' . ($disabled ? ' disabled="disabled"' : '') . '>' . $langs->trans("AnexumMassAction") . '</option>';
        }

        if (!$error) {
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }



    /**
     * Execute action
     *
     * @param	array	$parameters     Array of parameters
     * @param   Object	$object		   	Object output on PDF
     * @param   string	$action     	'add', 'update', 'view'
     * @return  int 		        	<0 if KO,
     *                          		=0 if OK but we want to process standard actions too,
     *  	                            >0 if OK and we want to replace standard actions.
     */
    public function beforePDFCreation($parameters, &$object, &$action)
    {
        global $conf, $user, $langs;
        global $hookmanager;

        $outputlangs = $langs;

        $ret = 0;
        $deltemp = array();
        dol_syslog(get_class($this) . '::executeHooks action=' . $action);

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
        }

        return $ret;
    }

    /**
     * Execute action
     *
     * @param	array	$parameters     Array of parameters
     * @param   Object	$pdfhandler     PDF builder handler
     * @param   string	$action         'add', 'update', 'view'
     * @return  int 		            <0 if KO,
     *                                  =0 if OK but we want to process standard actions too,
     *                                  >0 if OK and we want to replace standard actions.
     */
    public function afterPDFCreation($parameters, &$pdfhandler, &$action)
    {
        global $conf, $user, $langs;
        global $hookmanager;

        $outputlangs = $langs;

        $ret = 0;
        $deltemp = array();
        dol_syslog(get_class($this) . '::executeHooks action=' . $action);

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {
            // do something only for the context 'somecontext1' or 'somecontext2'
        }

        return $ret;
    }



    /**
     * Overloading the loadDataForCustomReports function : returns data to complete the customreport tool
     *
     * @param   array           $parameters     Hook metadatas (context, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function loadDataForCustomReports($parameters, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $langs->load("anexum@anexum");

        $this->results = array();

        $head = array();
        $h = 0;

        if ($parameters['tabfamily'] == 'anexum') {
            $head[$h][0] = dol_buildpath('/module/index.php', 1);
            $head[$h][1] = $langs->trans("Home");
            $head[$h][2] = 'home';
            $h++;

            $this->results['title'] = $langs->trans("Anexum");
            $this->results['picto'] = 'anexum@anexum';
        }

        $head[$h][0] = 'customreports.php?objecttype=' . $parameters['objecttype'] . (empty($parameters['tabfamily']) ? '' : '&tabfamily=' . $parameters['tabfamily']);
        $head[$h][1] = $langs->trans("CustomReports");
        $head[$h][2] = 'customreports';

        $this->results['head'] = $head;

        return 1;
    }



    /**
     * Overloading the restrictedArea function : check permission on an object
     *
     * @param   array           $parameters     Hook metadatas (context, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int 		      			  	<0 if KO,
     *                          				=0 if OK but we want to process standard actions too,
     *  	                            		>0 if OK and we want to replace standard actions.
     */
    public function restrictedArea($parameters, &$action, $hookmanager)
    {
        global $user;

        if ($parameters['features'] == 'myobject') {
            if ($user->rights->anexum->myobject->read) {
                $this->results['result'] = 1;
                return 1;
            } else {
                $this->results['result'] = 0;
                return 1;
            }
        }

        return 0;
    }


    public function printFieldListWhere($parameters, &$object, &$action, $hookmanager)
    {
        // Add filter conditions based on the context
        $filter_condition = $this->hideDraftsForExternalUsersList($parameters, $object, $action);
        $this->resprints = $filter_condition;

        // Check if the list context
        if (in_array($parameters['currentcontext'], array('contactlist'))) {
            // Add filter conditions based on the context
            $filter_condition = $this->filterContactsForExternalUsers($parameters, $object, $action);
            $this->resprints = $filter_condition;
        }

        // Return 0 to allow other hooks (like companygroup) to append their conditions
        return 0;
    }

    /**
     * Returns SQL condition to restrict contacts to only those from companies
     * assigned to the current user as sales representative (for external users).
     *
     * @return string SQL condition or empty string
     */
    private function filterContactsForExternalUsers($parameters, &$object, &$action)
    {
        global $db, $user;

        $targetCategoryId = 8; // External users category

        // Load user categories
        require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
        $category = new Categorie($db);
        $userCategories = $category->containing($user->id, 'user');

        foreach ($userCategories as $cat) {
            if ($cat->id == $targetCategoryId) {
                // SQL assumes alias 'c' is for llx_socpeople
                // return " AND EXISTS (
                // 	SELECT 1 FROM llx_societe_commerciaux sc
                // 	WHERE sc.fk_soc = c.fk_soc AND sc.fk_user = " . ((int) $user->id) . "
                // )";
                return " AND EXISTS (
					SELECT 1 FROM llx_societe_commerciaux sc
					WHERE sc.fk_soc = s.rowid AND sc.fk_user = " . ((int) $user->id) . "
				)";
            }
        }

        return '';
    }


    /**
     * Execute action completeTabsHead
     *
     * @param   array           $parameters     Array of parameters
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         'add', 'update', 'view'
     * @param   Hookmanager     $hookmanager    hookmanager
     * @return  int                             <0 if KO,
     *                                          =0 if OK but we want to process standard actions too,
     *                                          >0 if OK and we want to replace standard actions.
     */
    public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $conf, $user;

        if (!isset($parameters['object']->element)) {
            return 0;
        }

        // Inject socid into action tab URLs for external users
        if (!empty($user->socid) && is_object($parameters['object']) && $parameters['object']->element === 'action') {
            $socidParam = 'socid=' . ((int) $user->socid);
            foreach ($parameters['head'] as $key => $tab) {
                if (!empty($tab[0]) && strpos($tab[0], '/comm/action/card.php?') !== false && strpos($tab[0], 'socid=') === false) {
                    $parameters['head'][$key][0] = str_replace(
                        '/comm/action/card.php?',
                        '/comm/action/card.php?' . $socidParam . '&',
                        $tab[0]
                    );
                }
            }
        }

        if ($parameters['mode'] == 'remove') {
            // utilisé si on veut faire disparaitre des onglets.
            return 0;
        } elseif ($parameters['mode'] == 'add') {
            $langs->load('anexum@anexum');
            // utilisé si on veut ajouter des onglets.
            $counter = count($parameters['head']);
            $element = $parameters['object']->element;
            $id = $parameters['object']->id;
            // verifier le type d'onglet comme member_stats où ça ne doit pas apparaitre
            // if (in_array($element, ['societe', 'member', 'contrat', 'fichinter', 'project', 'propal', 'commande', 'facture', 'order_supplier', 'invoice_supplier'])) {
            if (in_array($element, ['context1', 'context2'])) {
                $datacount = 0;

                $parameters['head'][$counter][0] = dol_buildpath('/anexum/anexum_tab.php', 1) . '?id=' . $id . '&amp;module=' . $element;
                $parameters['head'][$counter][1] = $langs->trans('AnexumTab');
                if ($datacount > 0) {
                    $parameters['head'][$counter][1] .= '<span class="badge marginleftonlyshort">' . $datacount . '</span>';
                }
                $parameters['head'][$counter][2] = 'anexumemails';
                $counter++;
            }
            if ($counter > 0 && (int) DOL_VERSION < 14) {
                $this->results = $parameters['head'];
                // return 1 to replace standard code
                return 1;
            } else {
                // en V14 et + $parameters['head'] est modifiable par référence
                return 0;
            }
        }
    }

    public function showOptionals(&$parameters, &$object, &$action, $hookmanager)
    {
        $this->replaceExtUserFilter($parameters, $action);
    }


    public function showLinkedObjectBlock(&$parameters, &$object, &$action, $hookmanager)
    {
        // Vorbereitung, um linkedObjects zu Lieferantenbestellungen etc zu entfernen, wenn keine Berechtigung
        $x = 1;
    }

    /**
     * Function to hide proposals with status "Entwurf" (draft) only for users with a specific category.
     *
     * @param   array           $parameters     Hook metadata (context, etc...)
     * @param   CommonObject    $object         The object to process (e.g., proposal, invoice, etc.)
     * @param   string          $action         Current action (if set)
     * @return  string                          Additional SQL conditions to filter out items
     */
    public function hideDraftsForExternalUsersList($parameters, &$object, &$action)
    {
        global $db, $user;

        // Check if the list context
        if (in_array($parameters['currentcontext'], array('propallist', 'orderlist'))) {
            $targetCategoryId = 8; // 8 = "Externe" Category ID

            // Load categories associated with the current user
            require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
            $category = new Categorie($db);

            // Fetch categories containing the current user
            $userCategories = $category->containing($user->id, 'user'); // Correct usage of user object

            // Check if the user belongs to the specific category
            $isInTargetCategory = false;
            foreach ($userCategories as $cat) {
                if ($cat->id == $targetCategoryId) {
                    $isInTargetCategory = true;
                    break;
                }
            }

            // Apply filtering only if the user is in the target category
            if ($isInTargetCategory) {
                // Assuming '0' is the status code for "Entwurf"
                return " AND p.fk_statut != 0";
            }
        }

        // Check if the list context
        // invoice is different because sql alias is "f" instead of "p"
        if (in_array($parameters['currentcontext'], array('invoicelist'))) {
            $targetCategoryId = 8; // 8 = "Externe" Category ID

            // Load categories associated with the current user
            require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
            $category = new Categorie($db);

            // Fetch categories containing the current user
            $userCategories = $category->containing($user->id, 'user'); // Correct usage of user object

            // Check if the user belongs to the specific category
            $isInTargetCategory = false;
            foreach ($userCategories as $cat) {
                if ($cat->id == $targetCategoryId) {
                    $isInTargetCategory = true;
                    break;
                }
            }

            // Apply filtering only if the user is in the target category
            if ($isInTargetCategory) {
                // Assuming '0' is the status code for "Entwurf"
                return " AND f.fk_statut != 0";
            }
        }


        // Return an empty string if no filtering is needed
        return '';
    }

    /**
     * Function to hide proposals with status "Entwurf" (draft) only for users with a specific category.
     *
     * @param   array           $parameters     Hook metadata (context, etc...)
     * @param   CommonObject    $object         The object to process (e.g., proposal, invoice, etc.)
     * @param   string          $action         Current action (if set)
     * @return  string                          Additional SQL conditions to filter out items
     */
    public function hideDraftsForExternalUsersCard($parameters, &$object, &$action)
    {
        global $db, $user;
        // Check for the  card context
        if (in_array($parameters['currentcontext'], array('propalcard', 'ordercard', 'invoicecard'))) {

            $targetCategoryId = 8; // 8 = "Externe" Category ID

            // Load categories associated with the current user
            require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
            $category = new Categorie($db);

            // Fetch categories containing the current user
            $userCategories = $category->containing($user->id, 'user'); // Correct usage of user object

            // Check if the user belongs to the specific category
            $isInTargetCategory = false;
            foreach ($userCategories as $cat) {
                if ($cat->id == $targetCategoryId) {
                    $isInTargetCategory = true;
                    break;
                }
            }

            // Apply filtering only if the user is in the target category
            if ($isInTargetCategory) {
                // Check if the proposal status is "Entwurf"
                if ($object->status == 0) { // Assuming '0' is the status for "Entwurf"
                    // Redirect to the list view or display an error message
                    header('Location: ' . DOL_URL_ROOT . '/index.php?');
                    exit;
                }
            }
        }
        // Return an empty string if no filtering is needed
        return 0;
    }


    public function replaceExtUserFilter(&$parameters, $action)
    {
        global $db, $extrafields, $user;


        if (
            $parameters["currentcontext"] == "publicnewticketcard" ||
            $parameters["currentcontext"] == "ticketcard" &&
            (($action == 'create') ||
                ($action == 'edit') ||
                ($action == 'edit_extras') ||
                ($action == 'update_extras'))
        ) {
            // Replace $USERSOCID$ with $user->socid in extrafields

            if (array_key_exists('extrafields', $parameters)) {
                $extrafields = $parameters['extrafields'];
            } else {
                $extrafields = new Extrafields($db);
                $extrafields->fetch_name_optionals_label('ticket');
            }

            foreach ($extrafields->attributes['ticket']['param'] as $efname => $efopt) {
                if (is_array($efopt) && is_array($efopt['options']) && (count($efopt['options']) == 1)) {
                    $oldkey = array_key_first($efopt['options']);
                    if (!empty($oldkey)) {
                        if (empty($user->socid)) {
                            // Internal user - show all records
                            $repl = "1=1";
                        } else {
                            $repl = "fk_soc=$user->socid";
                        }
                        $newkey = str_replace('$EXT_USER_FILTER$', $repl, $oldkey);
                        unset($extrafields->attributes['ticket']['param'][$efname]['options'][$oldkey]);
                        $extrafields->attributes['ticket']['param'][$efname]['options'][$newkey] = null;
                    }
                }
            }
        }
    }

    // Substitution-token resolution (__EXTRAFIELD_*_LABEL__ / __ORDER_REF__ / __DATE__)
    // moved to lib/functions_anexum.lib.php. Dolibarr core's
    // complete_substitutions_array() only calls standalone functions registered
    // via $conf->modules_parts['substitutions'] — class-method hooks are never
    // reached on that code path. See functions.lib.php:10240.

    /**
     * On the ticket "Messaging" view, inject a toggle that filters out rows
     * created by the API user (id=2, login "API") so reviewers can focus on
     * actual human interactions.
     *
     * Reference ticket from user: TS2507-9095 \u2014
     * https://erp.anexum.at/ticket/messaging.php?track_id=6nurnj5eexebk15s
     *
     * ClickUp (Internal Cockpit subtask).
     *
     * @param   array $parameters  Hook parameters
     * @return  void
     */
    private function injectTicketHumanOnlyToggle($parameters)
    {
        global $delayedhtmlcontent;

        $ctx = isset($parameters['currentcontext']) ? $parameters['currentcontext'] : '';
        // Fire on BOTH the messaging tab and the card's "last events" block.
        if (!in_array($ctx, array('ticketmessaging', 'ticketcard'), true)) {
            return;
        }

        // The core agenda renderer prints one `<tr>` per event on both
        // messaging.php and the card's event summary. We walk rows on
        // DOMContentLoaded, tag ones authored by a bot login, insert a
        // toggle above the list, and persist the choice in localStorage
        // so the user does not have to re-tick on every page load.
        $js = <<<'JS'
jQuery(function($) {
    if (window.__anxHumanToggleInit) { return; }
    window.__anxHumanToggleInit = true;

    // Login names of API / bot users whose entries the toggle hides.
    // Extend here if new service accounts land.
    var BOT_LOGINS = ["API"];
    var STORAGE_KEY = "anxHumanOnly";

    function markBotRows() {
        var rows = $("table.noborder tr, tr.oddeven, .info-box");
        var botCount = 0, humanCount = 0;
        rows.each(function () {
            var $row = $(this);
            var text = $row.text();
            var isBot = false;
            for (var i = 0; i < BOT_LOGINS.length; i++) {
                if (text.indexOf(BOT_LOGINS[i]) !== -1) { isBot = true; break; }
            }
            if (isBot) {
                $row.attr("data-anx-bot", "1");
                botCount++;
            } else if ($row.find("td, .info-box-content").length > 0) {
                $row.attr("data-anx-bot", "0");
                humanCount++;
            }
        });
        return { bots: botCount, humans: humanCount };
    }

    function applyHiding(on) {
        $("[data-anx-bot='1']").each(function () { $(this).toggle(!on); });
    }

    // Anchor above the first event list we find (messaging tab or card).
    var $anchor = $("table.noborder").first();
    if (!$anchor.length) { $anchor = $(".info-box").first(); }
    if (!$anchor.length) { return; }

    var counts = markBotRows();
    var persisted;
    try { persisted = window.localStorage.getItem(STORAGE_KEY) === "1"; } catch (e) { persisted = false; }

    var $toggle = $("<div class=\"anx-human-toggle\" style=\"margin: 8px 0; padding: 8px; background: #f6f8fa; border: 1px solid #d0d7de; border-radius: 4px; font-size: 13px;\"></div>");
    var $checkbox = $("<input type=\"checkbox\" id=\"anx-human-only\" style=\"margin-right: 6px; vertical-align: middle;\">");
    if (persisted) { $checkbox.prop("checked", true); }
    var $label = $("<label for=\"anx-human-only\" style=\"cursor: pointer; user-select: none;\"></label>");
    $label.append($checkbox);
    $label.append(document.createTextNode("Show only human interactions (" + counts.humans + " human, " + counts.bots + " automated)"));
    $toggle.append($label);

    if (counts.humans === 0) {
        var $empty = $("<div style=\"margin-top: 6px; color: #6c757d;\"><em>No manual interaction on this ticket \u2014 API only.</em></div>");
        $toggle.append($empty);
    }

    $anchor.before($toggle);
    if (persisted) { applyHiding(true); }

    $checkbox.on("change", function () {
        var on = this.checked;
        applyHiding(on);
        try { window.localStorage.setItem(STORAGE_KEY, on ? "1" : "0"); } catch (e) { /* ignore quota */ }
    });
});
JS;

        if (!isset($delayedhtmlcontent)) {
            $delayedhtmlcontent = '';
        }
        $delayedhtmlcontent .= '<script>' . $js . '</script>';
    }

    /**
     * On the ticket "presend" card (the form that composes an outgoing ticket
     * message email), prefill the CC field with the monitoring address.
     *
     * ClickUp 869b7ck49. Configurable via ANEXUM_TICKET_CC_MONITORING.
     *
     * @param   array  $parameters  Hook parameters
     * @param   string $action      Current action (e.g. 'presend')
     * @return  void
     */
    private function prefillTicketCcMonitoring($parameters, $action)
    {
        $ctx = isset($parameters['currentcontext']) ? $parameters['currentcontext'] : '';
        if ($ctx !== 'ticketcard') {
            return;
        }
        // ticket/card.php normalises the presend flow to two action names:
        // 'presend' (reply mail from the tab) and 'presend_addmessage'
        // (internal message). Both reach core/tpl/card_presend.tpl.php.
        if (!in_array($action, array('presend', 'presend_addmessage'), true)) {
            return;
        }

        // If the user has already typed a CC value on this round trip, do not overwrite it.
        if (GETPOSTISSET('sendtocc')) {
            return;
        }

        $cc = getDolGlobalString('ANEXUM_TICKET_CC_MONITORING');
        if (empty($cc)) {
            return;
        }

        // Seed the three superglobals so Dolibarr's GETPOST() picks the value up
        // when FormMail renders the CC field in core/tpl/card_presend.tpl.php.
        $_GET['sendtocc']     = $cc;
        $_POST['sendtocc']    = $cc;
        $_REQUEST['sendtocc'] = $cc;
        dol_syslog('ActionsAnexum::prefillTicketCcMonitoring seeded sendtocc=' . $cc . ' (action=' . $action . ')', LOG_DEBUG);
    }

    /**
     * Hook printFieldListSelect: injects the open-ticket lookup column into
     * contract and contact list SELECT clauses.
     *
     * @param   array           $parameters     Hook metadatas
     * @param   CommonObject    $object         Current object
     * @param   string          $action         Current action
     * @param   HookManager     $hookmanager    Hook manager
     * @return  int                             0 = allow stacking with other hooks
     */
    public function printFieldListSelect($parameters, &$object, &$action, $hookmanager)
    {
        $ctx = isset($parameters['currentcontext']) ? $parameters['currentcontext'] : '';

        if ($ctx === 'contractlist') {
            // Contract list aliases llx_contrat as "c". Pick the newest open ticket ref + rowid.
            $this->resprints .= ", (SELECT tk.ref FROM " . MAIN_DB_PREFIX . "ticket as tk"
                . " WHERE tk.fk_contract = c.rowid AND tk.fk_statut < 8"
                . " ORDER BY tk.rowid DESC LIMIT 1) as anx_open_ticket_ref";
            $this->resprints .= ", (SELECT tk.rowid FROM " . MAIN_DB_PREFIX . "ticket as tk"
                . " WHERE tk.fk_contract = c.rowid AND tk.fk_statut < 8"
                . " ORDER BY tk.rowid DESC LIMIT 1) as anx_open_ticket_id";
        } elseif ($ctx === 'contactlist') {
            // Contact list aliases llx_socpeople as "p". Walk element_contact to find
            // contracts this contact is linked to, then look up open tickets on them.
            $base = " FROM " . MAIN_DB_PREFIX . "ticket as tk"
                . " INNER JOIN " . MAIN_DB_PREFIX . "element_contact as ec ON ec.element_id = tk.fk_contract"
                . " INNER JOIN " . MAIN_DB_PREFIX . "c_type_contact as tc ON tc.rowid = ec.fk_c_type_contact AND tc.element = 'contrat'"
                . " WHERE ec.fk_socpeople = p.rowid AND tk.fk_statut < 8"
                . " ORDER BY tk.rowid DESC LIMIT 1";
            $this->resprints .= ", (SELECT tk.ref" . $base . ") as anx_open_ticket_ref";
            $this->resprints .= ", (SELECT tk.rowid" . $base . ") as anx_open_ticket_id";
        }

        return 0;
    }

    /**
     * Hook printFieldListTitle: renders the column header for the status icon.
     *
     * @param   array           $parameters     Hook metadatas
     * @param   CommonObject    $object         Current object
     * @param   string          $action         Current action
     * @param   HookManager     $hookmanager    Hook manager
     * @return  int                             0
     */
    public function printFieldListTitle($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;

        $ctx = isset($parameters['currentcontext']) ? $parameters['currentcontext'] : '';
        if (!in_array($ctx, array('contractlist', 'contactlist'), true)) {
            return 0;
        }

        $this->resprints .= '<td class="liste_titre center" title="' . dol_escape_htmltag($langs->trans('AnexumOpenTicketStatus')) . '">' . $langs->trans('Status') . '</td>';

        if (isset($parameters['totalarray']) && is_array($parameters['totalarray'])) {
            $parameters['totalarray']['nbfield']++;
        }

        return 0;
    }

    /**
     * Hook printFieldListValue: renders the status dot cell per row.
     *
     * @param   array           $parameters     Hook metadatas with obj
     * @param   CommonObject    $object         Current object
     * @param   string          $action         Current action
     * @param   HookManager     $hookmanager    Hook manager
     * @return  int                             0
     */
    public function printFieldListValue($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;

        $ctx = isset($parameters['currentcontext']) ? $parameters['currentcontext'] : '';
        if (!in_array($ctx, array('contractlist', 'contactlist'), true)) {
            return 0;
        }

        $openRef = '';
        $openId  = 0;
        if (isset($parameters['obj']) && is_object($parameters['obj'])) {
            if (!empty($parameters['obj']->anx_open_ticket_ref)) {
                $openRef = (string) $parameters['obj']->anx_open_ticket_ref;
            }
            if (!empty($parameters['obj']->anx_open_ticket_id)) {
                $openId = (int) $parameters['obj']->anx_open_ticket_id;
            }
        }

        $this->resprints .= $this->renderStatusBadgeCell($openRef, $openId, $langs);
        return 0;
    }

    /**
     * Render a `<td>` with a clickable (when there's a ticket) status dot.
     * Reused by list-row rendering (printFieldListValue) and by the
     * card-side block (formObjectOptions).
     *
     * @param  string    $openRef  Ticket ref, empty when no open ticket
     * @param  int       $openId   Ticket rowid for the href, 0 when none
     * @param  Translate $langs
     * @return string              HTML fragment
     */
    private function renderStatusBadgeCell($openRef, $openId, $langs)
    {
        if ($openRef !== '' && $openId > 0) {
            $tooltip = $langs->trans('AnexumOpenTicketOnContract', $openRef);
            $href    = DOL_URL_ROOT . '/ticket/card.php?id=' . $openId;
            return '<td class="center"><a href="' . dol_escape_htmltag($href) . '" title="' . dol_escape_htmltag($tooltip) . '" style="display:inline-block;width:14px;height:14px;background:#d94f4f;border-radius:50%;"></a></td>';
        }
        $tooltip = $langs->trans('AnexumNoOpenTicket');
        return '<td class="center"><span title="' . dol_escape_htmltag($tooltip) . '" style="display:inline-block;width:14px;height:14px;background:#d0d0d0;border-radius:50%;"></span></td>';
    }

    /**
     * Hook formObjectOptions: renders the same open-ticket dot on contract and
     * contact card views. Called from the card template when rendering the
     * extra options block. Uses a direct query against the object already
     * loaded on the page.
     *
     * @param   array           $parameters     Hook metadatas
     * @param   CommonObject    $object         Current object on the card
     * @param   string          $action         Current action
     * @param   HookManager     $hookmanager    Hook manager
     * @return  int                             0
     */
    public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $db, $langs;

        $ctx = isset($parameters['currentcontext']) ? $parameters['currentcontext'] : '';
        if (!in_array($ctx, array('contractcard', 'contactcard'), true)) {
            return 0;
        }
        if (!is_object($object) || empty($object->id)) {
            return 0;
        }

        $openRef = '';
        $openId  = 0;

        if ($ctx === 'contractcard') {
            $sql = "SELECT tk.rowid, tk.ref FROM " . MAIN_DB_PREFIX . "ticket as tk"
                . " WHERE tk.fk_contract = " . ((int) $object->id)
                . " AND tk.fk_statut < 8"
                . " ORDER BY tk.rowid DESC LIMIT 1";
        } else {
            // contactcard
            $sql = "SELECT tk.rowid, tk.ref FROM " . MAIN_DB_PREFIX . "ticket as tk"
                . " INNER JOIN " . MAIN_DB_PREFIX . "element_contact as ec ON ec.element_id = tk.fk_contract"
                . " INNER JOIN " . MAIN_DB_PREFIX . "c_type_contact as tc ON tc.rowid = ec.fk_c_type_contact AND tc.element = 'contrat'"
                . " WHERE ec.fk_socpeople = " . ((int) $object->id)
                . " AND tk.fk_statut < 8"
                . " ORDER BY tk.rowid DESC LIMIT 1";
        }

        $resql = $db->query($sql);
        if ($resql) {
            if ($obj = $db->fetch_object($resql)) {
                $openRef = (string) $obj->ref;
                $openId  = (int) $obj->rowid;
            }
            $db->free($resql);
        }

        $href = ($openRef !== '' && $openId > 0) ? (DOL_URL_ROOT . '/ticket/card.php?id=' . $openId) : '';
        $label = $openRef !== ''
            ? dol_escape_htmltag($langs->trans('AnexumOpenTicketOnContract', $openRef))
            : dol_escape_htmltag($langs->trans('AnexumNoOpenTicket'));
        $color = $openRef !== '' ? '#d94f4f' : '#d0d0d0';
        $textcolor = $openRef !== '' ? '#fff' : '#555';

        $inner = '<span title="' . $label . '" style="display:inline-block;background:' . $color . ';color:' . $textcolor . ';padding:3px 10px;border-radius:12px;">'
            . ($openRef !== '' ? dol_escape_htmltag($openRef) : $langs->trans('None'))
            . '</span>';
        if ($href !== '') {
            $inner = '<a href="' . dol_escape_htmltag($href) . '" style="text-decoration:none;">' . $inner . '</a>';
        }

        $this->resprints .= '<tr><td>' . $langs->trans('AnexumOpenTicketStatus') . '</td><td>' . $inner . '</td></tr>';

        return 0;
    }

    /* Add here any other hooked methods... */
}
