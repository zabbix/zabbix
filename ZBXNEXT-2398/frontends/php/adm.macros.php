<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Configuration of macros');
$page['file'] = 'adm.macros.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'macros_rem'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'macros'=>					array(T_ZBX_STR, O_OPT, P_SYS,			null,	null),
	'macro_new'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	'isset({macro_add})'),
	'value_new'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	'isset({macro_add})'),
	'macro_add' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

	'save'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'form_refresh' =>			array(T_ZBX_INT, O_OPT,	null,	null,	null)
);
check_fields($fields);

/*
 * Actions
 */
$result = true;
if (isset($_REQUEST['save'])) {
	try {
		DBstart();

		$globalMacros = API::UserMacro()->get(array(
			'globalmacro' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		$newMacros = getRequest('macros', array());

		// remove empty new macro lines
		foreach ($newMacros as $number => $newMacro) {
			if (!isset($newMacro['globalmacroid']) && zbx_empty($newMacro['macro']) && zbx_empty($newMacro['value'])) {
				unset($newMacros[$number]);
			}
		}

		$duplicatedMacros = array();
		foreach ($newMacros as $number => $newMacro) {
			// transform macros to uppercase {$aaa} => {$AAA}
			$newMacros[$number]['macro'] = mb_strtoupper($newMacro['macro']);
		}

		// update
		$macrosToUpdate = array();
		foreach ($newMacros as $number => $newMacro) {
			if (isset($newMacro['globalmacroid']) && isset($globalMacros[$newMacro['globalmacroid']])) {

				$dbGlobalMacro = $globalMacros[$newMacro['globalmacroid']];

				// remove item from new macros array
				unset($newMacros[$number]);
				unset($globalMacros[$newMacro['globalmacroid']]);

				// if the macro is unchanged - skip it
				if ($dbGlobalMacro == $newMacro) {
					continue;
				}

				$macrosToUpdate[$newMacro['globalmacroid']] = $newMacro;
			}
		}
		if (!empty($macrosToUpdate)) {
			if (!API::UserMacro()->updateGlobal($macrosToUpdate)) {
				throw new Exception(_('Cannot update macro.'));
			}
			foreach ($macrosToUpdate as $macro) {
				add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_MACRO, $macro['globalmacroid'], $macro['macro'].SPACE.'&rArr;'.SPACE.$macro['value'], null, null, null);
			}
		}

		// delete the remaining global macros
		if ($globalMacros) {
			$ids = zbx_objectValues($globalMacros, 'globalmacroid');
			if (!API::UserMacro()->deleteGlobal($ids)) {
				throw new Exception(_('Cannot remove macro.'));
			}
			foreach ($globalMacros as $macro) {
				add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_MACRO, $macro['globalmacroid'], $macro['macro'].SPACE.'&rArr;'.SPACE.$macro['value'], null, null, null);
			}
		}

		// create
		if (!empty($newMacros)) {
			// mark marcos as new
			foreach ($newMacros as $number => $macro) {
				$_REQUEST['macros'][$number]['type'] = 'new';
			}

			$newMacrosIds = API::UserMacro()->createGlobal(array_values($newMacros));
			if (!$newMacrosIds) {
				throw new Exception(_('Cannot add macro.'));
			}
			$newMacrosCreated = API::UserMacro()->get(array(
				'globalmacroids' => $newMacrosIds['globalmacroids'],
				'globalmacro' => 1,
				'output' => API_OUTPUT_EXTEND
			));
			foreach ($newMacrosCreated as $macro) {
				add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_MACRO, $macro['globalmacroid'], $macro['macro'].SPACE.'&rArr;'.SPACE.$macro['value'], null, null, null);
			}
		}

		// reload macros after updating to properly display them in the form
		$_REQUEST['macros'] = API::UserMacro()->get(array(
			'globalmacro' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		$result = true;
		DBend(true);
		show_message(_('Macros updated'));
	}
	catch (Exception $e) {
		$result = false;
		DBend(false);
		error($e->getMessage());
		show_error_message(_('Cannot update macros'));
	}
}

/*
 * Display
 */
$form = new CForm();
$form->cleanItems();
$cmbConf = new CComboBox('configDropDown', 'adm.macros.php', 'redirect(this.options[this.selectedIndex].value);');
$cmbConf->addItems(array(
	'adm.gui.php' => _('GUI'),
	'adm.housekeeper.php' => _('Housekeeping'),
	'adm.images.php' => _('Images'),
	'adm.iconmapping.php' => _('Icon mapping'),
	'adm.regexps.php' => _('Regular expressions'),
	'adm.macros.php' => _('Macros'),
	'adm.valuemapping.php' => _('Value mapping'),
	'adm.workingtime.php' => _('Working time'),
	'adm.triggerseverities.php' => _('Trigger severities'),
	'adm.triggerdisplayoptions.php' => _('Trigger displaying options'),
	'adm.other.php' => _('Other')
));
$form->addItem($cmbConf);

$cnf_wdgt = new CWidget();
$cnf_wdgt->addPageHeader(_('CONFIGURATION OF MACROS'), $form);

$data = array();
$data['form_refresh'] = getRequest('form_refresh', 0);
$data['macros'] = array();

if ($data['form_refresh']) {
	$data['macros'] = getRequest('macros', array());
}
else {
	$data['macros'] = API::UserMacro()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'globalmacro' => 1
	));
}
if (empty($data['macros'])) {
	$data['macros'] = array(
		0 => array(
			'macro' => '',
			'value' => ''
		)
	);
}
if ($result) {
	$data['macros'] = order_macros($data['macros'], 'macro');
}
$macrosForm = new CView('administration.general.macros.edit', $data);
$cnf_wdgt->addItem($macrosForm->render());

$cnf_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
