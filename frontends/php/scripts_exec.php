<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once 'include/config.inc.php';
require_once 'include/hosts.inc.php';
require_once 'include/forms.inc.php';

$page['title'] = _('Scripts');
$page['file'] = 'scripts_exec.php';

define('ZBX_PAGE_NO_MENU', 1);

require_once ('include/page_header.php');

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'hostid' =>		array(T_ZBX_INT, O_OPT, P_SYS, DB_ID,		'isset({execute})'),
	'scriptid' =>	array(T_ZBX_INT, O_OPT, P_SYS, DB_ID,		'isset({execute})'),
	'execute' =>	array(T_ZBX_INT, O_OPT, P_ACT, IN('0,1'),	null)
);
check_fields($fields);

if (isset($_REQUEST['execute'])) {
	$scriptid = get_request('scriptid');
	$hostid = get_request('hostid');

	$data = array(
		'message' => '',
		'info' => DBfetch(DBselect('SELECT s.name FROM scripts s WHERE s.scriptid='.$scriptid))
	);

	$result = API::Script()->execute(array('hostid' => $hostid, 'scriptid' => $scriptid));

	$isErrorExist = false;
	if (empty($result)) {
		$isErrorExist = true;
	}
	elseif ($result['response'] == 'failed') {
		error($result['value']);
		$isErrorExist = true;
	}
	else {
		$data['message'] = $result['value'];
	}

	if ($isErrorExist) {
		show_error_message(_('Cannot connect to the trapper port of zabbix server daemon, but it should be available to run the script.'));
	}

	// render view
	$scriptView = new CView('general.script.execute', $data);
	$scriptView->render();
	$scriptView->show();
}

require_once 'include/page_footer.php';
