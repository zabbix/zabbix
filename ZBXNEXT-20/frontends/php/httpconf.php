<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/httptest.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of web monitoring');
$page['file'] = 'httpconf.php';
$page['hist_arg'] = array('groupid', 'hostid');

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'applicationid' =>	array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'hostid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form})||isset({save})'),
	'httptestid' =>		array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'(isset({form})&&({form}=="update"))'),
	'application' =>	array(T_ZBX_STR, O_OPT, null, null, null, _('Application')),
	'name' =>			array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({save})', _('Name')),
	'delay' =>			array(T_ZBX_INT, O_OPT, null, BETWEEN(0, SEC_PER_DAY), 'isset({save})', _('Update interval (in sec)')),
	'status' =>			array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	'isset({save})'),
	'agent' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'macros' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'steps' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})', _('Steps')),
	'authentication' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1,2'), 'isset({save})'),
	'http_user' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'isset({save})&&isset({authentication})&&({authentication}=='.HTTPTEST_AUTH_BASIC.
		'||{authentication}=='.HTTPTEST_AUTH_NTLM.')', _('User')),
	'http_password' =>	array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'isset({save})&&isset({authentication})&&({authentication}=='.HTTPTEST_AUTH_BASIC.
		'||{authentication}=='.HTTPTEST_AUTH_NTLM.')', _('Password')),
	'new_httpstep' =>	array(T_ZBX_STR, O_OPT, null,	null,		null),
	'move_up' =>		array(T_ZBX_INT, O_OPT, P_ACT,	BETWEEN(0, 65534), null),
	'move_down' =>		array(T_ZBX_INT, O_OPT, P_ACT,	BETWEEN(0, 65534), null),
	'sel_step' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65534), null),
	'group_httptestid' => array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'showdisabled' =>	array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	'new_application' => array(T_ZBX_STR, O_OPT, null, null, null),
	'hostname' => array(T_ZBX_STR, O_OPT, null, null, null),
	// actions
	'go' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,	null,		null)
);
$_REQUEST['showdisabled'] = get_request('showdisabled', CProfile::get('web.httpconf.showdisabled', 1));
$_REQUEST['status'] = isset($_REQUEST['status']) ? 0 : 1;

check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

$showDisabled = get_request('showdisabled', 1);
CProfile::update('web.httpconf.showdisabled', $showDisabled, PROFILE_TYPE_STR);
$_REQUEST['go'] = get_request('go', 'none');

if (!empty($_REQUEST['steps'])) {
	order_result($_REQUEST['steps'], 'no');
}

/*
 * Filter
 */
$options = array(
	'groups' => array(
		'editable' => true
	),
	'hosts' => array(
		'editable' => true,
		'templated_hosts' => true
	),
	'hostid' => get_request('hostid', null),
	'groupid' => get_request('groupid', null)
);
$pageFilter = new CPageFilter($options);
$_REQUEST['groupid'] = $pageFilter->groupid;
$_REQUEST['hostid'] = $pageFilter->hostid;

/*
 * Actions
 */

// add new steps
if (isset($_REQUEST['new_httpstep'])) {
	$_REQUEST['steps'] = get_request('steps', array());
	$_REQUEST['new_httpstep']['no'] = count($_REQUEST['steps']) + 1;
	array_push($_REQUEST['steps'], $_REQUEST['new_httpstep']);

	unset($_REQUEST['new_httpstep']);
}

// check for duplicate step names
$isDuplicateStepsFound = !empty($_REQUEST['steps']) ? validateHttpDuplicateSteps($_REQUEST['steps']) : false;

if (isset($_REQUEST['delete']) && isset($_REQUEST['httptestid'])) {
	$result = false;
	if ($httptest_data = get_httptest_by_httptestid($_REQUEST['httptestid'])) {
		$result = API::HttpTest()->delete($_REQUEST['httptestid']);
	}
	show_messages($result, _('Scenario deleted'), _('Cannot delete scenario'));
	if ($result) {
		$host = get_host_by_applicationid($httptest_data['applicationid']);

		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCENARIO, _('Scenario').' ['.$httptest_data['name'].'] ['.
			$_REQUEST['httptestid'].'] '._('Host').' ['.$host['host'].']');
	}
	unset($_REQUEST['httptestid'], $_REQUEST['form']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['httptestid'])) {
	unset($_REQUEST['httptestid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	try {
		DBstart();

		if (isset($_REQUEST['httptestid'])) {
			$action = AUDIT_ACTION_UPDATE;
			$message_true = _('Scenario updated');
			$message_false = _('Cannot update scenario');
		}
		else {
			$action = AUDIT_ACTION_ADD;
			$message_true = _('Scenario added');
			$message_false = _('Cannot add scenario');
		}

		if (!empty($_REQUEST['application']) && !empty($_REQUEST['new_application'])) {
			throw new Exception(_('Cannot create new application, web scenario is already assigned to application.'));
		}

		$steps = get_request('steps', array());
		if (!empty($steps)) {
			if ($isDuplicateStepsFound) {
				throw new Exception();
			}

			$i = 1;
			foreach ($steps as $snum => $step) {
				$steps[$snum]['no'] = $i++;
				$stepid = isset($step['httpstepid']) ? $step['httpstepid'] : null;
				if (!is_null($stepid)) {
					$steps[$snum]['webstepid'] = $stepid;
					unset($steps[$snum]['httpstepid']);
				}
			}
		}

		$httpTest = array(
			'hostid' => $_REQUEST['hostid'],
			'name' => $_REQUEST['name'],
			'authentication' => $_REQUEST['authentication'],
			'delay' => $_REQUEST['delay'],
			'status' => $_REQUEST['status'],
			'agent' => $_REQUEST['agent'],
			'macros' => $_REQUEST['macros'],
			'steps' => $steps
		);

		if (!empty($_REQUEST['application'])) {
			$dbApplication = DBfetch(DBselect(
				'SELECT a.applicationid'.
						' FROM applications a'.
						' WHERE a.name='.zbx_dbstr($_REQUEST['application']).
						' AND a.hostid='.zbx_dbstr($_REQUEST['hostid'])
			));
			$httpTest['applicationid'] = $dbApplication['applicationid'];
		}

		if (!empty($_REQUEST['new_application'])) {
			$result = API::Application()->create(array(
				'name' => $_REQUEST['new_application'],
				'hostid' => $_REQUEST['hostid']
			));
			if (!$result) {
				throw new Exception(_s('Cannot add new application "%1$s".', $_REQUEST['new_application']));
			}
			else {
				$httpTest['applicationid'] = reset($result['applicationids']);
			}
		}

		if ($_REQUEST['authentication'] != HTTPTEST_AUTH_NONE) {
			$httpTest['http_user'] = htmlspecialchars($_REQUEST['http_user']);
			$httpTest['http_password'] = htmlspecialchars($_REQUEST['http_password']);
		}
		else {
			$httpTest['http_user'] = '';
			$httpTest['http_password'] = '';
		}

		if (isset($_REQUEST['httptestid'])) {
			$httpTest['httptestid'] = $httptestid = $_REQUEST['httptestid'];
			$result = API::HttpTest()->update($httpTest);
			if (!$result) {
				throw new Exception();
			}
		}
		else {
			$result = API::HttpTest()->create($httpTest);
			if (!$result) {
				throw new Exception();
			}
			$httptestid = reset($result['httptestids']);
		}

		$host = get_host_by_hostid($_REQUEST['hostid']);
		add_audit($action, AUDIT_RESOURCE_SCENARIO, _('Scenario').' ['.$_REQUEST['name'].'] ['.$httptestid.'] '.
			_('Host').' ['.$host['host'].']');

		unset($_REQUEST['httptestid'], $_REQUEST['form']);
		show_messages(true, $message_true);
		DBend(true);
	}
	catch (Exception $e) {
		DBend(false);
		error($e->getMessage());
		show_messages(false, null, $message_false);
	}
}
elseif ($_REQUEST['go'] == 'activate' && isset($_REQUEST['group_httptestid'])) {
	$go_result = false;
	$group_httptestid = $_REQUEST['group_httptestid'];
	foreach ($group_httptestid as $id) {
		if (!($httptest_data = get_httptest_by_httptestid($id))) {
			continue;
		}

		if (activate_httptest($id)) {
			$go_result = true;
			$host = get_host_by_applicationid($httptest_data['applicationid']);

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO, _('Scenario').' ['.$httptest_data['name'].'] ['.$id.'] '.
				_('Host').' ['.$host['host'].']'._('Scenario activated'));
		}
	}
	show_messages($go_result, _('Scenario activated'), null);
}
elseif ($_REQUEST['go'] == 'disable' && isset($_REQUEST['group_httptestid'])) {
	$go_result = false;
	$group_httptestid = $_REQUEST['group_httptestid'];
	foreach ($group_httptestid as $id) {
		if (!($httptest_data = get_httptest_by_httptestid($id))) {
			continue;
		}

		if (disable_httptest($id)) {
			$go_result = true;
			$host = get_host_by_applicationid($httptest_data['applicationid']);

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO, _('Scenario').' ['.$httptest_data['name'].'] ['.$id.'] '.
				_('Host').' ['.$host['host'].']'._('Scenario disabled'));
		}
	}
	show_messages($go_result, _('Scenario disabled'), null);
}
elseif ($_REQUEST['go'] == 'clean_history' && isset($_REQUEST['group_httptestid'])) {
	$go_result = false;
	$group_httptestid = $_REQUEST['group_httptestid'];
	foreach ($group_httptestid as $id) {
		if (!($httptest_data = get_httptest_by_httptestid($id))) {
			continue;
		}

		if (delete_history_by_httptestid($id)) {
			$go_result = true;
			DBexecute('UPDATE httptest SET nextcheck=0 WHERE httptestid='.$id);

			$host = get_host_by_applicationid($httptest_data['applicationid']);

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO, _('Scenario').' ['.$httptest_data['name'].'] ['.$id.'] '.
				_('Host').' ['.$host['host'].']'._('History cleared'));
		}
	}
	show_messages($go_result, _('History cleared'), null);
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['group_httptestid'])) {
	$go_result = API::HttpTest()->delete($_REQUEST['group_httptestid']);
	show_messages($go_result, _('Scenario deleted'), null);
}

if ($_REQUEST['go'] != 'none' && isset($go_result) && $go_result) {
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray("'.$path.'")');
}

show_messages();

/*
 * Display
 */
$data = array(
	'hostid' => get_request('hostid', 0)
);

if (isset($_REQUEST['form']) && !empty($data['hostid'])) {
	$data['httptestid'] = get_request('httptestid', null);
	$data['form'] = get_request('form');
	$data['form_refresh'] = get_request('form_refresh');
	$data['hostname'] = get_request('hostname', '');
	if (empty($data['hostname'])) {
		$hostInfo = get_host_by_hostid($data['hostid'], true);
		$data['hostname'] = $hostInfo['name'];
	}

	if ((isset($_REQUEST['httptestid']) && !isset($_REQUEST['form_refresh']))) {
		$dbHttpTest = DBfetch(DBselect(
			'SELECT ht.*,a.name AS application'.
			' FROM httptest ht'.
			' LEFT JOIN applications a ON a.applicationid=ht.applicationid'.
			' WHERE ht.httptestid='.zbx_dbstr($_REQUEST['httptestid'])
		));

		$data['name'] = $dbHttpTest['name'];
		$data['application'] = $dbHttpTest['application'] ? $dbHttpTest['application'] : '';
		$data['new_application'] = '';
		$data['delay'] = $dbHttpTest['delay'];
		$data['status'] = $dbHttpTest['status'];
		$data['agent'] = $dbHttpTest['agent'];
		$data['macros'] = $dbHttpTest['macros'];
		$data['authentication'] = $dbHttpTest['authentication'];
		$data['http_user'] = $dbHttpTest['http_user'];
		$data['http_password'] = $dbHttpTest['http_password'];
		$data['steps'] = DBfetchArray(DBselect('SELECT h.* FROM httpstep h WHERE h.httptestid='.$_REQUEST['httptestid'].' ORDER BY h.no'));
	}
	else {
		$data['name'] = get_request('name', '');
		$data['application'] = get_request('application', '');
		$data['new_application'] = get_request('new_application', '');
		$data['delay'] = get_request('delay', 60);
		$data['status'] = get_request('status', HTTPTEST_STATUS_ACTIVE);
		$data['agent'] = get_request('agent', '');
		$data['macros'] = get_request('macros', array());
		$data['authentication'] = get_request('authentication', HTTPTEST_AUTH_NONE);
		$data['http_user'] = get_request('http_user', '');
		$data['http_password'] = get_request('http_password', '');
		$data['steps'] = get_request('steps', array());
	}

	if (!empty($data['application'])) {
		$appExists = DBfetch(DBselect('SELECT a.name FROM applications a WHERE a.name='.zbx_dbstr($data['application']).' AND a.hostid='.zbx_dbstr($data['hostid'])));
		if (!$appExists) {
			$data['application'] = '';
		}
	}

	// render view
	$httpView = new CView('configuration.httpconf.edit', $data);
	$httpView->render();
	$httpView->show();
}
else {
	$data['pageFilter'] = $pageFilter;
	$data['showDisabled'] = $showDisabled;
	$data['httpTests'] = array();

	if ($data['pageFilter']->hostsSelected) {
		$sortfield = getPageSortField('hostname');
		$options = array(
			'editable' => true,
			'output' => API_OUTPUT_SHORTEN,
			'sortfield' => $sortfield,
			'limit' => $config['search_limit'] + 1
		);
		if (empty($data['showDisabled'])) {
			$options['filter']['status'] = HTTPTEST_STATUS_ACTIVE;
		}
		if ($data['pageFilter']->hostid > 0) {
			$options['hostids'] = $data['pageFilter']->hostid;
		}
		elseif ($data['pageFilter']->groupid > 0) {
			$options['groupids'] = $data['pageFilter']->groupid;
		}
		$httpTests = API::HttpTest()->get($options);

		$data['paging'] = getPagingLine($httpTests);

		$dbHttpTests = DBselect(
			'SELECT ht.httptestid,ht.name,ht.delay,ht.status,ht.hostid,h.name AS hostname,COUNT(hs.httpstepid) AS stepsCnt'.
				' FROM httptest ht'.
				' INNER JOIN httpstep hs ON hs.httptestid=ht.httptestid'.
				' INNER JOIN hosts h ON h.hostid=ht.hostid'.
				' WHERE '.DBcondition('ht.httptestid', zbx_objectValues($httpTests, 'httptestid')).
				' GROUP BY ht.httptestid'
		);
		$httpTests = array();
		while ($dbHttpTest = DBfetch($dbHttpTests)) {
			$httpTests[$dbHttpTest['httptestid']] = $dbHttpTest;
		}

		order_result($httpTests, $sortfield, getPageSortOrder());

		$data['httpTests'] = $httpTests;
	}

	// render view
	$httpView = new CView('configuration.httpconf.list', $data);
	$httpView->render();
	$httpView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
