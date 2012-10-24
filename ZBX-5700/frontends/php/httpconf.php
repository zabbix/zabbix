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
	'applications' =>	array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'applicationid' =>	array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'close' =>			array(T_ZBX_INT, O_OPT, null,	IN('1'),	null),
	'open' =>			array(T_ZBX_INT, O_OPT, null,	IN('1'),	null),
	'groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'hostid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form})||isset({save})'),
	'httptestid' =>		array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'(isset({form})&&({form}=="update"))'),
	'application' =>	array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({save})', _('Application')),
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

if (!empty($_REQUEST['steps'])) {
	order_result($_REQUEST['steps'], 'no');
}

/*
 * Permissions
 */
if (isset($_REQUEST['httptestid'])) {
	$dbHttpTest = DBfetch(DBselect(
		'SELECT wt.*,a.name AS application'.
		' FROM httptest wt,applications a'.
		' WHERE a.applicationid=wt.applicationid'.
			' AND wt.httptestid='.get_request('httptestid')
	));
	if (empty($dbHttpTest)) {
		access_deny();
	}
}
if (isset($_REQUEST['go'])) {
	if (!isset($_REQUEST['group_httptestid']) || !is_array($_REQUEST['group_httptestid'])) {
		access_deny();
	}
	else {
		foreach ($_REQUEST['group_httptestid'] as $group_httptestid) {
			$dbHttpTests = DBfetch(DBselect(
				'SELECT wt.applicationid'.
				' FROM httptest wt,applications a'.
				' WHERE a.applicationid=wt.applicationid'.
					' AND wt.httptestid='.$group_httptestid
			));
			if (empty($dbHttpTests)) {
				access_deny();
			}
		}
	}
}
$_REQUEST['go'] = get_request('go', 'none');

/*
 * Filter
 */
$options = array(
	'groups' => array(
		'real_hosts' => true,
		'not_proxy_hosts' => true,
		'editable' => true
	),
	'hosts' => array(
		'editable' => true
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
$_REQUEST['applications'] = get_request('applications', get_favorites('web.httpconf.applications'));
$_REQUEST['applications'] = zbx_objectValues($_REQUEST['applications'], 'value');

$showAllApps = null;
if (isset($_REQUEST['open'])) {
	if (!isset($_REQUEST['applicationid'])) {
		$_REQUEST['applications'] = array();
		$showAllApps = 1;
	}
	elseif (!uint_in_array($_REQUEST['applicationid'], $_REQUEST['applications'])) {
		array_push($_REQUEST['applications'], $_REQUEST['applicationid']);
	}
}
elseif (isset($_REQUEST['close'])) {
	if (!isset($_REQUEST['applicationid'])) {
		$_REQUEST['applications'] = array();
	}
	elseif (($i = array_search($_REQUEST['applicationid'], $_REQUEST['applications'])) !== false) {
		unset($_REQUEST['applications'][$i]);
	}
}

// limit opened application count
if (count($_REQUEST['applications']) > 25) {
	$_REQUEST['applications'] = array_slice($_REQUEST['applications'], -25);
}
rm4favorites('web.httpconf.applications');
foreach ($_REQUEST['applications'] as $application) {
	add2favorites('web.httpconf.applications', $application);
}

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
		$result = API::WebCheck()->delete($_REQUEST['httptestid']);
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

		$db_app_result = DBselect(
			'SELECT a.applicationid'.
			' FROM applications a'.
			' WHERE a.name='.zbx_dbstr($_REQUEST['application']).
				' AND a.hostid='.$_REQUEST['hostid']
		);
		if ($applicationid = DBfetch($db_app_result)) {
			$httpTest['applicationid'] = $applicationid['applicationid'];
		}
		else {
			$result = API::Application()->create(array(
				'name' => $_REQUEST['application'],
				'hostid' => $_REQUEST['hostid']
			));
			if (!$result) {
				throw new Exception(_('Cannot add new application.').' [ '.$application.' ]');
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
			$result = API::WebCheck()->update($httpTest);
			if (!$result) {
				throw new Exception();
			}
		}
		else {
			$result = API::WebCheck()->create($httpTest);
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
	$go_result = API::WebCheck()->delete($_REQUEST['group_httptestid']);
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
	$data['groupid'] = get_request('groupid', 0);
	$data['httptestid'] = get_request('httptestid', null);
	$data['form'] = get_request('form');
	$data['form_refresh'] = get_request('form_refresh', 0);

	if ((isset($_REQUEST['httptestid']) && !isset($_REQUEST['form_refresh'])) || isset($limited)) {
		$data['name'] = $dbHttpTest['name'];
		$data['application'] = $dbHttpTest['application'];
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
		$data['delay'] = get_request('delay', 60);
		$data['status'] = get_request('status', HTTPTEST_STATUS_ACTIVE);
		$data['agent'] = get_request('agent', '');
		$data['macros'] = get_request('macros', array());
		$data['authentication'] = get_request('authentication', HTTPTEST_AUTH_NONE);
		$data['http_user'] = get_request('http_user', '');
		$data['http_password'] = get_request('http_password', '');
		$data['steps'] = get_request('steps', array());
	}

	// render view
	$httpView = new CView('configuration.httpconf.edit', $data);
	$httpView->render();
	$httpView->show();
}
else {
	$data['pageFilter'] = $pageFilter;
	$data['showDisabled'] = $showDisabled;
	$data['showAllApps'] = $showAllApps;

	$data['db_apps'] = array();
	$db_app_result = DBselect(
		'SELECT DISTINCT h.name AS hostname,a.*'.
		' FROM applications a,hosts h'.
		' WHERE a.hostid=h.hostid'.
			($data['hostid'] > 0 ? ' AND h.hostid='.$data['hostid'] : '').
			' AND '.DBcondition('h.hostid', $pageFilter->hostsSelected ? array_keys($pageFilter->hosts) : array())
	);
	while ($db_app = DBfetch($db_app_result)) {
		$db_app['scenarios_cnt'] = 0;
		$data['db_apps'][$db_app['applicationid']] = $db_app;
	}

	// get http tests
	$data['db_httptests'] = array();
	$dbHttpTests_result = DBselect(
		'SELECT wt.*,a.name AS application,h.name AS hostname,h.hostid'.
		' FROM httptest wt,applications a,hosts h'.
		' WHERE wt.applicationid=a.applicationid'.
			' AND a.hostid=h.hostid'.
			' AND '.DBcondition('a.applicationid', array_keys($data['db_apps'])).
			($showDisabled == 0 ? ' AND wt.status='.HTTPTEST_STATUS_ACTIVE : '')
	);
	while ($httptest_data = DBfetch($dbHttpTests_result)) {
		$data['db_apps'][$httptest_data['applicationid']]['scenarios_cnt']++;
		$httptest_data['step_count'] = null;
		$data['db_httptests'][$httptest_data['httptestid']] = $httptest_data;
	}

	// get http steps
	$httpstep_res = DBselect(
		'SELECT hs.httptestid,COUNT(hs.httpstepid) AS cnt'.
		' FROM httpstep hs'.
		' WHERE '.DBcondition('hs.httptestid', array_keys($data['db_httptests'])).
		' GROUP BY hs.httptestid'
	);
	while ($step_count = DBfetch($httpstep_res)) {
		$data['db_httptests'][$step_count['httptestid']]['step_count'] = $step_count['cnt'];
	}

	order_result($data['db_httptests'], getPageSortField('host'), getPageSortOrder());
	$data['paging'] = getPagingLine($data['db_httptests']);

	// render view
	$httpView = new CView('configuration.httpconf.list', $data);
	$httpView->render();
	$httpView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
