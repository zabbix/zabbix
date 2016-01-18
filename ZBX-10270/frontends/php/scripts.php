<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	'name' =>				array(T_ZBX_STR, O_OPT, null,			NOT_EMPTY,	'isset({save})'),
	'type' =>				array(T_ZBX_INT, O_OPT, null,			IN('0,1'),	'isset({save})'),
	'execute_on' =>			array(T_ZBX_INT, O_OPT, null,			IN('0,1'),	'isset({save})&&{type}=='.ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT),
	'command' =>			array(T_ZBX_STR, O_OPT, null,			null,		'isset({save})'),
	'commandipmi' =>		array(T_ZBX_STR, O_OPT, null,			null,		'isset({save})'),
	'description' =>		array(T_ZBX_STR, O_OPT, null,			null,		'isset({save})'),
	'access' =>				array(T_ZBX_INT, O_OPT, null,			IN('0,1,2,3'), 'isset({save})'),
	'groupid' =>			array(T_ZBX_INT, O_OPT, null,			DB_ID,		'isset({save})&&{hgstype}!=0'),
	'usrgrpid' =>			array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		'isset({save})'),
	'hgstype' =>			array(T_ZBX_INT, O_OPT, null,			null,		null),
	'confirmation' =>		array(T_ZBX_STR, O_OPT, null,			null,		null),
	'enableConfirmation' =>	array(T_ZBX_STR, O_OPT, null,			null,		null),
	// actions
	'go' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'action' =>				array(T_ZBX_INT, O_OPT, P_ACT,			IN('0,1'),	null),
	'save' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_ACT,			null,		null),
	'clone' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'form' =>				array(T_ZBX_STR, O_OPT, null,			null,		null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT, null,			null,		null)
);
check_fields($fields);

$_REQUEST['go'] = get_request('go', 'none');

validate_sort_and_sortorder('name', ZBX_SORT_UP);

/*
 * Permissions
 */
if ($scriptId = get_request('scriptid')) {
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
if (isset($_REQUEST['clone']) && isset($_REQUEST['scriptid'])) {
	unset($_REQUEST['scriptid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	$confirmation = get_request('confirmation', '');
	$enableConfirmation = get_request('enableConfirmation', false);
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
			'host_access' => $_REQUEST['access'],
			'confirmation' => get_request('confirmation', '')
		);

		if (isset($_REQUEST['scriptid'])) {
			$script['scriptid'] = $_REQUEST['scriptid'];
			$result = API::Script()->update($script);

			show_messages($result, _('Script updated'), _('Cannot update script'));

			$auditAction = AUDIT_ACTION_UPDATE;
		}
		else {
			$result = API::Script()->create($script);

			show_messages($result, _('Script added'), _('Cannot add script'));

			$auditAction = AUDIT_ACTION_ADD;
		}

		$scriptId = isset($result['scriptids']) ? reset($result['scriptids']) : null;

		if ($result) {
			add_audit($auditAction, AUDIT_RESOURCE_SCRIPT, ' Name ['.$_REQUEST['name'].'] id ['.$scriptId.']');
			unset($_REQUEST['action'], $_REQUEST['form'], $_REQUEST['scriptid']);
			clearCookies($result);
		}
	}
}
elseif (isset($_REQUEST['delete'])) {
	$scriptId = get_request('scriptid', 0);

	$result = API::Script()->delete($scriptId);

	if ($result) {
		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCRIPT, _('Script').' ['.$scriptId.']');
	}

	show_messages($result, _('Script deleted'), _('Cannot delete script'));
	clearCookies($result);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['scriptid']);
	}
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['scripts'])) {
	$scriptIds = $_REQUEST['scripts'];

	DBstart();

	$goResult = API::Script()->delete($scriptIds);

	if ($goResult) {
		foreach ($scriptIds as $scriptId) {
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCRIPT, _('Script').' ['.$scriptId.']');
		}
	}

	$goResult = DBend($goResult);

	show_messages($goResult, _('Script deleted'), _('Cannot delete script'));
	clearCookies($goResult);

	if ($goResult) {
		unset($_REQUEST['form'], $_REQUEST['scriptid']);
	}
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'form' => get_request('form', 1),
		'form_refresh' => get_request('form_refresh', 0),
		'scriptid' => get_request('scriptid')
	);

	if (!$data['scriptid'] || isset($_REQUEST['form_refresh'])) {
		$data['name'] = get_request('name', '');
		$data['type'] = get_request('type', ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT);
		$data['execute_on'] = get_request('execute_on', ZBX_SCRIPT_EXECUTE_ON_SERVER);
		$data['command'] = get_request('command', '');
		$data['commandipmi'] = get_request('commandipmi', '');
		$data['description'] = get_request('description', '');
		$data['usrgrpid'] = get_request('usrgrpid', 0);
		$data['groupid'] = get_request('groupid', 0);
		$data['access'] = get_request('host_access', 0);
		$data['confirmation'] = get_request('confirmation', '');
		$data['enableConfirmation'] = get_request('enableConfirmation', false);
		$data['hgstype'] = get_request('hgstype', 0);
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
		$data['access'] = $script['host_access'];
		$data['confirmation'] = $script['confirmation'];
		$data['enableConfirmation'] = !zbx_empty($script['confirmation']);
		$data['hgstype'] = empty($data['groupid']) ? 0 : 1;
	}

	$scriptView = new CView('administration.script.edit');

	$scriptView->set('form', $data['form']);
	$scriptView->set('form_refresh', $data['form_refresh']);
	$scriptView->set('scriptid', $data['scriptid']);
	$scriptView->set('name', $data['name']);
	$scriptView->set('type', $data['type']);
	$scriptView->set('execute_on', $data['execute_on']);
	$scriptView->set('command', $data['command']);
	$scriptView->set('commandipmi', $data['commandipmi']);
	$scriptView->set('description', $data['description']);
	$scriptView->set('usrgrpid', $data['usrgrpid']);
	$scriptView->set('groupid', $data['groupid']);
	$scriptView->set('access', $data['access']);
	$scriptView->set('confirmation', $data['confirmation']);
	$scriptView->set('enableConfirmation', $data['enableConfirmation']);
	$scriptView->set('hgstype', $data['hgstype']);

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
	$data = array(
		'displayNodes' => is_array(get_current_nodeid())
	);

	$config = select_config();

	// list of scripts
	$data['scripts'] = API::Script()->get(array(
		'output' => array('scriptid', 'name', 'command', 'host_access', 'usrgrpid', 'groupid', 'type', 'execute_on'),
		'editable' => true,
		'selectGroups' => API_OUTPUT_EXTEND,
		'limit' => $config['search_limit'] + 1
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
	order_result($data['scripts'], getPageSortField('name'), getPageSortOrder());
	$data['paging'] = getPagingLine($data['scripts'], array('scriptid'));

	// nodes
	if ($data['displayNodes']) {
		foreach ($data['scripts'] as $key => $script) {
			$data['scripts'][$key]['nodename'] = get_node_name_by_elid($script['scriptid'], true);
		}
	}

	// render view
	$scriptView = new CView('administration.script.list', $data);
	$scriptView->render();
	$scriptView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
