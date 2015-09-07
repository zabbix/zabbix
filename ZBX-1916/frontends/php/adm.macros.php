<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'macros'		=> [T_ZBX_STR, O_OPT, P_SYS,			null,	null],
	// actions
	'update'		=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'form_refresh'	=> [T_ZBX_INT, O_OPT,	null,	null,	null]
];
check_fields($fields);

/*
 * Actions
 */
if (hasRequest('update')) {
	$dbMacros = API::UserMacro()->get([
		'output' => ['globalmacroid', 'macro', 'value'],
		'globalmacro' => true,
		'preservekeys' => true
	]);

	$macros = getRequest('macros', []);

	// remove empty new macro lines
	foreach ($macros as $idx => $macro) {
		if (!array_key_exists('globalmacroid', $macro) && $macro['macro'] === '' && $macro['value'] === '') {
			unset($macros[$idx]);
		}
	}

	foreach ($macros as &$macro) {
		// transform macros to uppercase {$aaa} => {$AAA}
		$macro['macro'] = mb_strtoupper($macro['macro']);
	}
	unset($macro);

	// update
	$macrosToUpdate = [];
	foreach ($macros as $idx => $macro) {
		if (array_key_exists('globalmacroid', $macro) && array_key_exists($macro['globalmacroid'], $dbMacros)) {
			$dbMacro = $dbMacros[$macro['globalmacroid']];

			// remove item from new macros array
			unset($macros[$idx], $dbMacros[$macro['globalmacroid']]);

			// if the macro is unchanged - skip it
			if ($dbMacro['macro'] === $macro['macro'] && $dbMacro['value'] === $macro['value']) {
				continue;
			}

			$macrosToUpdate[] = $macro;
		}
	}

	$result = true;

	if ($macrosToUpdate || $dbMacros || $macros) {
		DBstart();

		// update
		if ($macrosToUpdate) {
			$result = (bool) API::UserMacro()->updateGlobal($macrosToUpdate);
		}

		// deletehe
		if ($dbMacros) {
			$result = $result && (bool) API::UserMacro()->deleteGlobal(array_keys($dbMacros));
		}

		// create
		if ($macros) {
			$result = $result && (bool) API::UserMacro()->createGlobal(array_values($macros));
		}

		$result = DBend($result);
	}

	show_messages($result, _('Macros updated'), _('Cannot update macros'));

	if ($result) {
		unset($_REQUEST['form_refresh']);
	}
}

/*
 * Display
 */
$data = [];

if (hasRequest('form_refresh')) {
	$data['macros'] = getRequest('macros', []);
}
else {
	$data['macros'] = API::UserMacro()->get([
		'output' => ['globalmacroid', 'macro', 'value'],
		'globalmacro' => true
	]);
	$data['macros'] = array_values(order_macros($data['macros'], 'macro'));
}

if (!$data['macros']) {
	$data['macros'][] = ['macro' => '', 'value' => ''];
}

(new CView('administration.general.macros.edit', $data))->render()->show();

require_once dirname(__FILE__).'/include/page_footer.php';
