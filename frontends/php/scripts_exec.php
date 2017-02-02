<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

$page['title'] = _('Scripts');
$page['file'] = 'scripts_exec.php';

define('ZBX_PAGE_NO_MENU', 1);

ob_start();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hostid' =>		[T_ZBX_INT, O_MAND, P_SYS, DB_ID, null],
	'scriptid' =>	[T_ZBX_INT, O_MAND, P_SYS, DB_ID, null]
];
check_fields($fields);

ob_end_flush();

$scriptid = getRequest('scriptid');
$hostid = getRequest('hostid');
$data = [
	'name' => '',
	'command' => '',
	'message' => ''
];

$scripts = API::Script()->get([
	'scriptids' => $scriptid,
	'output' => ['name', 'command']
]);

$error_exist = false;

if ($scripts) {
	$script = $scripts[0];

	$macros_data = CMacrosResolverHelper::resolve([
		'config' => 'scriptConfirmation',
		'data' => [$hostid => [$scriptid => $script['command']]]
	]);

	$data['name'] = $script['name'];
	$data['command'] = $macros_data[$hostid][$scriptid];

	$result = API::Script()->execute([
		'hostid' => $hostid,
		'scriptid' => $scriptid
	]);

	if (!$result) {
		$error_exist = true;
	}
	elseif ($result['response'] == 'failed') {
		error($result['value']);
		$error_exist = true;
	}
	else {
		$data['message'] = $result['value'];
	}
}
else {
	error(_('No permissions to referred object or it does not exist!'));
	$error_exist = true;
}

if ($error_exist) {
	show_error_message(_('Cannot execute script'));
}

// render view
(new CView('general.script.execute', $data))
	->render()
	->show();

require_once dirname(__FILE__).'/include/page_footer.php';
