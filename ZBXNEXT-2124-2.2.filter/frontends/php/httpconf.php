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
	'groupid'			=> array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,				null),
	'new_httpstep'		=> array(T_ZBX_STR, O_OPT, null,	null,				null),
	'sel_step'			=> array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65534),	null),
	'group_httptestid'	=> array(T_ZBX_INT, O_OPT, null,	DB_ID,				null),
	'showdisabled'		=> array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),			null),
	// form
	'hostid'          => array(T_ZBX_INT, O_OPT, P_SYS, DB_ID.NOT_ZERO,          'isset({form})||isset({save})'),
	'applicationid'   => array(T_ZBX_INT, O_OPT, null,  DB_ID,                   null, _('Application')),
	'httptestid'      => array(T_ZBX_INT, O_NO,  P_SYS, DB_ID,                   '(isset({form})&&({form}=="update"))'),
	'name'            => array(T_ZBX_STR, O_OPT, null,  NOT_EMPTY,               'isset({save})', _('Name')),
	'delay'           => array(T_ZBX_INT, O_OPT, null,  BETWEEN(1, SEC_PER_DAY), 'isset({save})', _('Update interval (in sec)')),
	'retries'         => array(T_ZBX_INT, O_OPT, null,  BETWEEN(1, 10),          'isset({save})', _('Retries')),
	'status'          => array(T_ZBX_STR, O_OPT, null,  null,                    null),
	'agent'           => array(T_ZBX_STR, O_OPT, null,  null,                    'isset({save})'),
	'variables'       => array(T_ZBX_STR, O_OPT, null,  null,                    'isset({save})'),
	'steps'           => array(T_ZBX_STR, O_OPT, null,  null,                    'isset({save})', _('Steps')),
	'authentication'  => array(T_ZBX_INT, O_OPT, null,  IN('0,1,2'),             'isset({save})'),
	'http_user'       => array(T_ZBX_STR, O_OPT, null,  NOT_EMPTY,               'isset({save})&&isset({authentication})&&({authentication}=='.HTTPTEST_AUTH_BASIC.
		'||{authentication}=='.HTTPTEST_AUTH_NTLM.')', _('User')),
	'http_password'		=> array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,			'isset({save})&&isset({authentication})&&({authentication}=='.HTTPTEST_AUTH_BASIC.
		'||{authentication}=='.HTTPTEST_AUTH_NTLM.')', _('Password')),
	'http_proxy'		=> array(T_ZBX_STR, O_OPT, null,	null,				'isset({save})'),
	'new_application'	=> array(T_ZBX_STR, O_OPT, null,	null,				null),
	'hostname'			=> array(T_ZBX_STR, O_OPT, null,	null,				null),
	'templated'			=> array(T_ZBX_STR, O_OPT, null,	null,				null),
	// actions
	'go'				=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null),
	'clone'				=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null),
	'save'				=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null),
	'delete'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null),
	'cancel'			=> array(T_ZBX_STR, O_OPT, P_SYS,	null,				null),
	'form'				=> array(T_ZBX_STR, O_OPT, P_SYS,	null,				null),
	'form_refresh'		=> array(T_ZBX_INT, O_OPT, null,	null,				null)
);
$_REQUEST['showdisabled'] = get_request('showdisabled', CProfile::get('web.httpconf.showdisabled', 1));

check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

$showDisabled = get_request('showdisabled', 1);
CProfile::update('web.httpconf.showdisabled', $showDisabled, PROFILE_TYPE_INT);

if (!empty($_REQUEST['steps'])) {
	order_result($_REQUEST['steps'], 'no');
}

/*
 * Permissions
 */
//*
if (isset($_REQUEST['httptestid']) || !empty($_REQUEST['group_httptestid'])) {
	$testIds = array();
	if (isset($_REQUEST['httptestid'])) {
		$testIds[] = $_REQUEST['httptestid'];
	}
	if (!empty($_REQUEST['group_httptestid'])) {
		$testIds = array_merge($testIds, $_REQUEST['group_httptestid']);
	}
	if (!API::HttpTest()->isWritable($testIds)) {
		access_deny();
	}
}
$_REQUEST['go'] = get_request('go', 'none');


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

if (isset($_REQUEST['delete']) && isset($_REQUEST['httptestid'])) {
	$result = false;

	$host = DBfetch(DBselect(
		'SELECT h.host FROM hosts h,httptest ht WHERE ht.hostid=h.hostid AND ht.httptestid='.zbx_dbstr($_REQUEST['httptestid'])));

	if ($httptest_data = get_httptest_by_httptestid($_REQUEST['httptestid'])) {
		$result = API::HttpTest()->delete($_REQUEST['httptestid']);
	}

	show_messages($result, _('Web scenario deleted'), _('Cannot delete web scenario'));
	if ($result) {
		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCENARIO, 'Web scenario ['.$httptest_data['name'].'] ['.
			$_REQUEST['httptestid'].'] Host ['.$host['host'].']');
	}
	unset($_REQUEST['httptestid'], $_REQUEST['form']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['httptestid'])) {
	unset($_REQUEST['httptestid']);
	unset($_REQUEST['templated']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	try {
		DBstart();

		if (isset($_REQUEST['httptestid'])) {
			$action = AUDIT_ACTION_UPDATE;
			$message_true = _('Scenario updated');
			$message_false = _('Cannot update web scenario');
		}
		else {
			$action = AUDIT_ACTION_ADD;
			$message_true = _('Scenario added');
			$message_false = _('Cannot add web scenario');
		}

		if (!empty($_REQUEST['applicationid']) && !empty($_REQUEST['new_application'])) {
			throw new Exception(_('Cannot create new application, web scenario is already assigned to application.'));
		}

		$steps = get_request('steps', array());
		if (!empty($steps)) {
			$i = 1;
			foreach ($steps as $snum => $step) {
				$steps[$snum]['no'] = $i++;
			}
		}

		$httpTest = array(
			'hostid' => $_REQUEST['hostid'],
			'name' => $_REQUEST['name'],
			'authentication' => $_REQUEST['authentication'],
			'applicationid' => get_request('applicationid'),
			'delay' => $_REQUEST['delay'],
			'retries' => $_REQUEST['retries'],
			'status' => isset($_REQUEST['status']) ? 0 : 1,
			'agent' => $_REQUEST['agent'],
			'variables' => $_REQUEST['variables'],
			'http_proxy' => $_REQUEST['http_proxy'],
			'steps' => $steps
		);

		if (!empty($_REQUEST['new_application'])) {
			$exApp = API::Application()->get(array(
				'output' => array('applicationid'),
				'hostids' => $_REQUEST['hostid'],
				'filter' => array('name' => $_REQUEST['new_application'])
			));
			if ($exApp) {
				$httpTest['applicationid'] = $exApp[0]['applicationid'];
			}
			else {
				$result = API::Application()->create(array(
					'name' => $_REQUEST['new_application'],
					'hostid' => $_REQUEST['hostid']
				));
				if ($result) {
					$httpTest['applicationid'] = reset($result['applicationids']);
				}
				else {
					throw new Exception(_s('Cannot add new application "%1$s".', $_REQUEST['new_application']));
				}
			}
		}

		if ($_REQUEST['authentication'] != HTTPTEST_AUTH_NONE) {
			$httpTest['http_user'] = $_REQUEST['http_user'];
			$httpTest['http_password'] = $_REQUEST['http_password'];
		}
		else {
			$httpTest['http_user'] = '';
			$httpTest['http_password'] = '';
		}

		if (isset($_REQUEST['httptestid'])) {
			// unset fields that did not change
			$dbHttpTest = API::HttpTest()->get(array(
				'httptestids' => $_REQUEST['httptestid'],
				'output' => API_OUTPUT_EXTEND,
				'selectSteps' => API_OUTPUT_EXTEND
			));
			$dbHttpTest = reset($dbHttpTest);
			$dbHttpSteps = zbx_toHash($dbHttpTest['steps'], 'httpstepid');

			$httpTest = CArrayHelper::unsetEqualValues($httpTest, $dbHttpTest, array('applicationid'));
			foreach ($httpTest['steps'] as $snum => $step) {
				if (isset($step['httpstepid'])) {
					$newStep = CArrayHelper::unsetEqualValues($step, $dbHttpSteps[$step['httpstepid']], array('httpstepid'));
					$httpTest['steps'][$snum] = $newStep;
				}
			}

			$httpTest['httptestid'] = $httptestid = $_REQUEST['httptestid'];
			$result = API::HttpTest()->update($httpTest);
			if (!$result) {
				throw new Exception();
			}
			else {
				clearCookies($result, $_REQUEST['hostid']);
			}

		}
		else {
			$result = API::HttpTest()->create($httpTest);
			if (!$result) {
				throw new Exception();
			}
			else {
				clearCookies($result, $_REQUEST['hostid']);
			}
			$httptestid = reset($result['httptestids']);
		}

		$host = get_host_by_hostid($_REQUEST['hostid']);
		add_audit($action, AUDIT_RESOURCE_SCENARIO, 'Scenario ['.$_REQUEST['name'].'] ['.$httptestid.'] Host ['.$host['host'].']');

		unset($_REQUEST['httptestid'], $_REQUEST['form']);
		show_messages(true, $message_true);
		DBend(true);
	}
	catch (Exception $e) {
		DBend(false);

		$msg = $e->getMessage();
		if (!empty($msg)) {
			error($msg);
		}
		show_messages(false, null, $message_false);
	}
}
elseif (str_in_array(getRequest('go'), array('activate', 'disable')) && hasRequest('group_httptestid')) {
	$result = true;
	$groupHttpTestId = getRequest('group_httptestid');
	$enable = (getRequest('go') == 'activate');
	$status = $enable ? HTTPTEST_STATUS_ACTIVE : HTTPTEST_STATUS_DISABLED;
	$statusName = $enable ? 'enabled' : 'disabled';
	$auditAction = $enable ? AUDIT_ACTION_ENABLE : AUDIT_ACTION_DISABLE;
	$updated = 0;

	foreach ($groupHttpTestId as $id) {
		if (!($httpTestData = get_httptest_by_httptestid($id))) {
			continue;
		}
		$result &= API::HttpTest()->update(array(
			'httptestid' => $id,
			'status' => $status
		));

		if ($result) {
			$host = DBfetch(DBselect(
				'SELECT h.host FROM hosts h,httptest ht WHERE ht.hostid=h.hostid AND ht.httptestid='.zbx_dbstr($id)
			));
			add_audit($auditAction, AUDIT_RESOURCE_SCENARIO,
				'Scenario ['.$httpTestData['name'].'] ['.$id.'] Host ['.$host['host'].'] '.$statusName
			);
		}
		$updated++;
	}

	$messageSuccess = $enable
		? _n('Web scenario enabled', 'Web scenarios enabled', $updated)
		: _n('Web scenario disabled', 'Web scenarios disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable web scenario', 'Cannot enable web scenarios', $updated)
		: _n('Cannot disable web scenario', 'Cannot disable web scenarios', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
	clearCookies($result, getRequest('hostid'));
}
elseif ($_REQUEST['go'] == 'clean_history' && isset($_REQUEST['group_httptestid'])) {
	$goResult = false;
	$group_httptestid = $_REQUEST['group_httptestid'];
	foreach ($group_httptestid as $id) {
		if (!($httptest_data = get_httptest_by_httptestid($id))) {
			continue;
		}
		if (delete_history_by_httptestid($id)) {
			$goResult = true;
			DBexecute('UPDATE httptest SET nextcheck=0 WHERE httptestid='.zbx_dbstr($id));
			$host = DBfetch(DBselect(
				'SELECT h.host FROM hosts h,httptest ht WHERE ht.hostid=h.hostid AND ht.httptestid='.zbx_dbstr($id)));

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO, 'Scenario ['.$httptest_data['name'].'] ['.$id.
				'] Host ['.$host['host'].'] history cleared');
		}
	}

	show_messages($goResult, _('History cleared'), null);
	clearCookies($goResult, $_REQUEST['hostid']);
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['group_httptestid'])) {
	$goResult = API::HttpTest()->delete($_REQUEST['group_httptestid']);

	show_messages($goResult, _('Web scenario deleted'), _('Cannot delete web scenario'));
	clearCookies($goResult, $_REQUEST['hostid']);
}

show_messages();

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'hostid' => get_request('hostid', 0),
		'httptestid' => get_request('httptestid', null),
		'form' => get_request('form'),
		'form_refresh' => get_request('form_refresh'),
		'templates' => array()
	);

	if (isset($data['httptestid'])) {
		// get templates
		$httpTestId = $data['httptestid'];
		while ($httpTestId) {
			$dbTest = DBfetch(DBselect(
				'SELECT h.hostid,h.name,ht.httptestid,ht.templateid'.
					' FROM hosts h,httptest ht'.
					' WHERE ht.hostid=h.hostid'.
					' AND ht.httptestid='.zbx_dbstr($httpTestId)
			));
			$httpTestId = null;

			if (!empty($dbTest)) {
				if (!idcmp($data['httptestid'], $dbTest['httptestid'])) {
					$data['templates'][] = new CLink(
						$dbTest['name'],
						'httpconf.php?form=update&httptestid='.$dbTest['httptestid'].'&hostid='.$dbTest['hostid'],
						'highlight underline weight_normal'
					);
					$data['templates'][] = SPACE.RARR.SPACE;
				}
				$httpTestId = $dbTest['templateid'];
			}
		}
		$data['templates'] = array_reverse($data['templates']);
		array_shift($data['templates']);
	}

	if ((isset($_REQUEST['httptestid']) && !isset($_REQUEST['form_refresh']))) {
		$dbHttpTest = DBfetch(DBselect(
			'SELECT ht.*'.
			' FROM httptest ht'.
			' WHERE ht.httptestid='.zbx_dbstr($_REQUEST['httptestid'])
		));

		$data['name'] = $dbHttpTest['name'];
		$data['applicationid'] = $dbHttpTest['applicationid'];
		$data['new_application'] = '';
		$data['delay'] = $dbHttpTest['delay'];
		$data['retries'] = $dbHttpTest['retries'];
		$data['status'] = $dbHttpTest['status'];
		$data['agent'] = $dbHttpTest['agent'];
		$data['variables'] = $dbHttpTest['variables'];
		$data['authentication'] = $dbHttpTest['authentication'];
		$data['http_user'] = $dbHttpTest['http_user'];
		$data['http_password'] = $dbHttpTest['http_password'];
		$data['http_proxy'] = $dbHttpTest['http_proxy'];
		$data['templated'] = (bool) $dbHttpTest['templateid'];
		$data['steps'] = DBfetchArray(DBselect('SELECT h.* FROM httpstep h WHERE h.httptestid='.zbx_dbstr($_REQUEST['httptestid']).' ORDER BY h.no'));
	}
	else {
		if (isset($_REQUEST['form_refresh'])) {
			$data['status'] = isset($_REQUEST['status']) ? HTTPTEST_STATUS_ACTIVE : HTTPTEST_STATUS_DISABLED;
		}
		else {
			$data['status'] = HTTPTEST_STATUS_ACTIVE;
		}

		$data['name'] = get_request('name', '');
		$data['applicationid'] = get_request('applicationid');
		$data['new_application'] = get_request('new_application', '');
		$data['delay'] = get_request('delay', 60);
		$data['retries'] = get_request('retries', 1);
		$data['agent'] = get_request('agent', '');
		$data['variables'] = get_request('variables', array());
		$data['authentication'] = get_request('authentication', HTTPTEST_AUTH_NONE);
		$data['http_user'] = get_request('http_user', '');
		$data['http_password'] = get_request('http_password', '');
		$data['http_proxy'] = get_request('http_proxy', '');
		$data['templated'] = get_request('templated');
		$data['steps'] = get_request('steps', array());
	}

	$data['application_list'] = array();
	if (!empty($data['hostid'])) {
		$dbApps = DBselect('SELECT a.applicationid,a.name FROM applications a WHERE a.hostid='.zbx_dbstr($data['hostid']));
		while ($dbApp = DBfetch($dbApps)) {
			$data['application_list'][$dbApp['applicationid']] = $dbApp['name'];
		}
	}

	// render view
	$httpView = new CView('configuration.httpconf.edit', $data);
	$httpView->render();
	$httpView->show();
}
else {
	$pageFilter = new CPageFilter(array(
		'groups' => array(
			'editable' => true
		),
		'hosts' => array(
			'editable' => true,
			'templated_hosts' => true
		),
		'hostid' => get_request('hostid'),
		'groupid' => get_request('groupid')
	));

	$data = array(
		'hostid' => $pageFilter->hostid,
		'pageFilter' => $pageFilter,
		'showDisabled' => $showDisabled,
		'httpTests' => array(),
		'paging' => null,
		'displayNodes' => (is_array(get_current_nodeid()) && empty($_REQUEST['groupid']) && empty($_REQUEST['hostid']))
	);

	if ($data['pageFilter']->hostsSelected) {
		$sortfield = getPageSortField('hostname');

		$options = array(
			'editable' => true,
			'output' => array('httptestid'),
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

		order_result($httpTests, $sortfield, getPageSortOrder());

		$data['paging'] = getPagingLine($httpTests, array('httptestid'));

		$dbHttpTests = DBselect(
			'SELECT ht.httptestid,ht.name,ht.delay,ht.status,ht.hostid,ht.templateid,h.name AS hostname'.
				' FROM httptest ht'.
				' INNER JOIN hosts h ON h.hostid=ht.hostid'.
				' WHERE '.dbConditionInt('ht.httptestid', zbx_objectValues($httpTests, 'httptestid'))
		);
		$httpTests = array();
		while ($dbHttpTest = DBfetch($dbHttpTests)) {
			$httpTests[$dbHttpTest['httptestid']] = $dbHttpTest;
		}

		$dbHttpSteps = DBselect(
			'SELECT hs.httptestid,COUNT(*) AS stepscnt'.
				' FROM httpstep hs'.
				' WHERE '.dbConditionInt('hs.httptestid', zbx_objectValues($httpTests, 'httptestid')).
				' GROUP BY hs.httptestid'
		);
		while ($dbHttpStep = DBfetch($dbHttpSteps)) {
			$httpTests[$dbHttpStep['httptestid']]['stepscnt'] = $dbHttpStep['stepscnt'];
		}

		order_result($httpTests, $sortfield, getPageSortOrder());

		$data['parentTemplates'] = getHttpTestsParentTemplates($httpTests);

		$data['httpTests'] = $httpTests;
	}

	// nodes
	if ($data['displayNodes']) {
		foreach ($data['httpTests'] as &$httpTest) {
			$httpTest['nodename'] = get_node_name_by_elid($httpTest['httptestid'], true);
		}
		unset($httpTest);
	}

	// render view
	$httpView = new CView('configuration.httpconf.list', $data);
	$httpView->render();
	$httpView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
