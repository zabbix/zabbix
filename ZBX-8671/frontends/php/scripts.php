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

$page['title'] = _('Configuration of scripts');
$page['file'] = 'scripts.php';
$page['hist_arg'] = array('scriptid');

if (isset($_REQUEST['form'])) {
	$page['scripts'] = array('multiselect.js');
}

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'scriptid' =>			array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		null),
	'scripts' =>			array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		null),
	'name' =>				array(T_ZBX_STR, O_OPT, null,			NOT_EMPTY,	'isset({add}) || isset({update})'),
	'type' =>				array(T_ZBX_INT, O_OPT, null,			IN('0,1'),	'isset({add}) || isset({update})'),
	'execute_on' =>			array(T_ZBX_INT, O_OPT, null,			IN('0,1'),	'(isset({add}) || isset({update})) && {type} == '.ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT),
	'command' =>			array(T_ZBX_STR, O_OPT, null,			null,		'isset({add}) || isset({update})'),
	'commandipmi' =>		array(T_ZBX_STR, O_OPT, null,			null,		'isset({add}) || isset({update})'),
	'description' =>		array(T_ZBX_STR, O_OPT, null,			null,		'isset({add}) || isset({update})'),
	'host_access' =>		array(T_ZBX_INT, O_OPT, null,			IN('0,1,2,3'), 'isset({add}) || isset({update})'),
	'groupid' =>			array(T_ZBX_INT, O_OPT, null,			DB_ID,		'(isset({add}) || isset({update})) && {hgstype} != 0'),
	'usrgrpid' =>			array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		'isset({add}) || isset({update})'),
	'hgstype' =>			array(T_ZBX_INT, O_OPT, null,			null,		null),
	'confirmation' =>		array(T_ZBX_STR, O_OPT, null,			null,		null),
	'enableConfirmation' =>	array(T_ZBX_STR, O_OPT, null,			null,		null),
	// actions
	'action' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	IN('"script.massdelete"'),		null),
	'add' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'update' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_ACT,			null,		null),
	'form' =>				array(T_ZBX_STR, O_OPT, null,			null,		null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT, null,			null,		null),
	// sort and sortorder
	'sort' =>				array(T_ZBX_STR, O_OPT, P_SYS, IN('"command","name"'),						null),
	'sortorder' =>			array(T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null)
);
check_fields($fields);

/*
 * Permissions
 */
if ($scriptId = getRequest('scriptid')) {
	$scripts = API::Script()->get(array(
		'scriptids' => $scriptId,
		'output' => array('scriptid')
	));
	if (empty($scripts)) {
		access_deny();
	}
}

/*
 * Actions
 */
if (hasRequest('add') || hasRequest('update')) {
	$confirmation = getRequest('confirmation', '');
	$enableConfirmation = getRequest('enableConfirmation', false);
	$command = ($_REQUEST['type'] == ZBX_SCRIPT_TYPE_IPMI) ? $_REQUEST['commandipmi'] : $_REQUEST['command'];

	if (empty($_REQUEST['hgstype'])) {
		$_REQUEST['groupid'] = 0;
	}

	if ($enableConfirmation && zbx_empty($confirmation)) {
		error(_('Please enter confirmation text.'));
		show_messages(null, null, _('Cannot add script'));
	}
	elseif (zbx_empty($command)) {
		error(_('Command cannot be empty.'));
		show_messages(null, null, _('Cannot add script'));
	}
	else {
		$script = array(
			'name' => $_REQUEST['name'],
			'type' => $_REQUEST['type'],
			'execute_on' => $_REQUEST['execute_on'],
			'command' => $command,
			'description' => $_REQUEST['description'],
			'usrgrpid' => $_REQUEST['usrgrpid'],
			'groupid' => $_REQUEST['groupid'],
			'host_access' => getRequest('host_access'),
			'confirmation' => getRequest('confirmation', '')
		);

		DBstart();

		if (hasRequest('update')) {
			$script['scriptid'] = getRequest('scriptid');
			$result = API::Script()->update($script);

			$messageSuccess = _('Script updated');
			$messageFailed = _('Cannot update script');
			$auditAction = AUDIT_ACTION_UPDATE;
		}
		else {
			$result = API::Script()->create($script);

			$messageSuccess = _('Script added');
			$messageFailed = _('Cannot add script');
			$auditAction = AUDIT_ACTION_ADD;
		}

		$scriptId = isset($result['scriptids']) ? reset($result['scriptids']) : null;

		if ($result) {
			add_audit($auditAction, AUDIT_RESOURCE_SCRIPT, ' Name ['.$_REQUEST['name'].'] id ['.$scriptId.']');
			unset($_REQUEST['form'], $_REQUEST['scriptid']);
		}

		$result = DBend($result);

		if ($result) {
			uncheckTableRows();
		}
		show_messages($result, $messageSuccess, $messageFailed);
	}
}
elseif (hasRequest('delete') && hasRequest('scriptid')) {
	$scriptId = getRequest('scriptid');

	DBstart();

	$result = API::Script()->delete(array($scriptId));

	if ($result) {
		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCRIPT, _('Script').' ['.$scriptId.']');
		unset($_REQUEST['form'], $_REQUEST['scriptid']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Script deleted'), _('Cannot delete script'));
}
elseif (hasRequest('action') && getRequest('action') == 'script.massdelete' && hasRequest('scripts')) {
	$scriptIds = getRequest('scripts');

	DBstart();

	$result = API::Script()->delete($scriptIds);

	if ($result) {
		foreach ($scriptIds as $scriptId) {
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCRIPT, _('Script').' ['.$scriptId.']');
		}
		unset($_REQUEST['form'], $_REQUEST['scriptid']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Script deleted'), _('Cannot delete script'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'form' => getRequest('form', 1),
		'form_refresh' => getRequest('form_refresh', 0),
		'scriptid' => getRequest('scriptid')
	);

	if (!$data['scriptid'] || isset($_REQUEST['form_refresh'])) {
		$data['name'] = getRequest('name', '');
		$data['type'] = getRequest('type', ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT);
		$data['execute_on'] = getRequest('execute_on', ZBX_SCRIPT_EXECUTE_ON_SERVER);
		$data['command'] = getRequest('command', '');
		$data['commandipmi'] = getRequest('commandipmi', '');
		$data['description'] = getRequest('description', '');
		$data['usrgrpid'] = getRequest('usrgrpid', 0);
		$data['groupid'] = getRequest('groupid', 0);
		$data['host_access'] = getRequest('host_access', 0);
		$data['confirmation'] = getRequest('confirmation', '');
		$data['enableConfirmation'] = getRequest('enableConfirmation', false);
		$data['hgstype'] = getRequest('hgstype', 0);
	}
	elseif ($data['scriptid']) {
		$script = API::Script()->get(array(
			'scriptids' => $data['scriptid'],
			'output' => API_OUTPUT_EXTEND
		));
		$script = reset($script);

		$data['name'] = $script['name'];
		$data['type'] = $script['type'];
		$data['execute_on'] = $script['execute_on'];
		$data['command'] = $data['commandipmi'] = $script['command'];
		$data['description'] = $script['description'];
		$data['usrgrpid'] = $script['usrgrpid'];
		$data['groupid'] = $script['groupid'];
		$data['host_access'] = $script['host_access'];
		$data['confirmation'] = $script['confirmation'];
		$data['enableConfirmation'] = !zbx_empty($script['confirmation']);
		$data['hgstype'] = empty($data['groupid']) ? 0 : 1;
	}

	$scriptView = new CView('administration.script.edit', $data);

	// get host gruop
	$hostGroup = null;
	if (!empty($data['groupid'])) {
		$groups = API::HostGroup()->get(array(
			'groupids' => array($data['groupid']),
			'output' => array('groupid', 'name')
		));
		$groups = reset($groups);

		$hostGroup[] = array(
			'id' => $groups['groupid'],
			'name' => $groups['name']
		);
	}
	$scriptView->set('hostGroup', $hostGroup);

	// get list of user groups
	$usergroups = API::UserGroup()->get(array(
		'output' => array('usrgrpid', 'name')
	));
	order_result($usergroups, 'name');
	$scriptView->set('usergroups', $usergroups);

	// render view
	$scriptView->render();
	$scriptView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$data = array(
		'sort' => $sortField,
		'sortorder' => $sortOrder
	);

	// list of scripts
	$data['scripts'] = API::Script()->get(array(
		'output' => array('scriptid', 'name', 'command', 'host_access', 'usrgrpid', 'groupid', 'type', 'execute_on'),
		'editable' => true,
		'selectGroups' => API_OUTPUT_EXTEND
	));

	// find script host group name and user group name. set to '' if all host/user groups used.
	foreach ($data['scripts'] as $key => $script) {
		$scriptId = $script['scriptid'];

		if ($script['usrgrpid'] > 0) {
			$userGroup = API::UserGroup()->get(array('usrgrpids' => $script['usrgrpid'], 'output' => API_OUTPUT_EXTEND));
			$userGroup = reset($userGroup);

			$data['scripts'][$key]['userGroupName'] = $userGroup['name'];
		}
		else {
			$data['scripts'][$key]['userGroupName'] = ''; // all user groups
		}

		if ($script['groupid'] > 0) {
			$group = array_pop($script['groups']);

			$data['scripts'][$key]['hostGroupName'] = $group['name'];
		}
		else {
			$data['scripts'][$key]['hostGroupName'] = ''; // all host groups
		}
	}

	// sorting & paging
	order_result($data['scripts'], $sortField, $sortOrder);
	$data['paging'] = getPagingLine($data['scripts']);

	// render view
	$scriptView = new CView('administration.script.list', $data);
	$scriptView->render();
	$scriptView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
