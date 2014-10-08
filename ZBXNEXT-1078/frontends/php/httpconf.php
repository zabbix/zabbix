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
	'hostid'          => array(T_ZBX_INT, O_OPT, P_SYS, DB_ID.NOT_ZERO,          'isset({form}) || isset({add}) || isset({update})'),
	'applicationid'   => array(T_ZBX_INT, O_OPT, null,  DB_ID,                   null, _('Application')),
	'httptestid'      => array(T_ZBX_INT, O_NO,  P_SYS, DB_ID,                   'isset({form}) && {form} == "update"'),
	'name'            => array(T_ZBX_STR, O_OPT, null,  NOT_EMPTY,               'isset({add}) || isset({update})', _('Name')),
	'delay'           => array(T_ZBX_INT, O_OPT, null,  BETWEEN(1, SEC_PER_DAY), 'isset({add}) || isset({update})', _('Update interval (in sec)')),
	'retries'         => array(T_ZBX_INT, O_OPT, null,  BETWEEN(1, 10),          'isset({add}) || isset({update})', _('Retries')),
	'status'          => array(T_ZBX_STR, O_OPT, null,  null,                    null),
	'agent'           => array(T_ZBX_STR, O_OPT, null,  null,                    'isset({add}) || isset({update})'),
	'variables'       => array(T_ZBX_STR, O_OPT, null,  null,                    'isset({add}) || isset({update})'),
	'steps'           => array(T_ZBX_STR, O_OPT, null,  null,                    'isset({add}) || isset({update})', _('Steps')),
	'authentication'  => array(T_ZBX_INT, O_OPT, null,  IN('0,1,2'),             'isset({add}) || isset({update})'),
	'http_user'       => array(T_ZBX_STR, O_OPT, null,  NOT_EMPTY,               '(isset({add}) || isset({update})) && isset({authentication}) && ({authentication} == '.HTTPTEST_AUTH_BASIC.
		' || {authentication} == '.HTTPTEST_AUTH_NTLM.')', _('User')),
	'http_password'		=> array(T_ZBX_STR, O_OPT, P_NO_TRIM,	NOT_EMPTY,		'(isset({add}) || isset({update})) && isset({authentication}) && ({authentication} == '.HTTPTEST_AUTH_BASIC.
		' || {authentication} == '.HTTPTEST_AUTH_NTLM.')', _('Password')),
	'http_proxy'		=> array(T_ZBX_STR, O_OPT, null,	null,				'isset({add}) || isset({update})'),
	'new_application'	=> array(T_ZBX_STR, O_OPT, null,	null,				null),
	'hostname'			=> array(T_ZBX_STR, O_OPT, null,	null,				null),
	'templated'			=> array(T_ZBX_STR, O_OPT, null,	null,				null),
	'verify_host'		=> array(T_ZBX_STR, O_OPT, null,	null,				null),
	'verify_peer'		=> array(T_ZBX_STR, O_OPT, null,	null,				null),
	'headers'			=> array(T_ZBX_STR, O_OPT, null, null,					'isset({add}) || isset({update})'),
	'ssl_cert_file'		=> array(T_ZBX_STR, O_OPT, null, null,					'isset({add}) || isset({update})'),
	'ssl_key_file'		=> array(T_ZBX_STR, O_OPT, null, null,					'isset({add}) || isset({update})'),
	'ssl_key_password'	=> array(T_ZBX_STR, O_OPT, P_NO_TRIM, null,				'isset({add}) || isset({update})'),
	// actions
	'action'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,
								IN('"httptest.massclearhistory","httptest.massdelete","httptest.massdisable",'.
									'"httptest.massenable"'
								),
								null
							),
	'clone'				=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null),
	'del_history'		=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null),
	'add'				=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null),
	'update'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null),
	'delete'			=> array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null),
	'cancel'			=> array(T_ZBX_STR, O_OPT, P_SYS,	null,				null),
	'form'				=> array(T_ZBX_STR, O_OPT, P_SYS,	null,				null),
	'form_refresh'		=> array(T_ZBX_INT, O_OPT, null,	null,				null),
	// sort and sortorder
	'sort'				=> array(T_ZBX_STR, O_OPT, P_SYS, IN('"hostname","name","status"'),				null),
	'sortorder'			=> array(T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null)
);
$_REQUEST['showdisabled'] = getRequest('showdisabled', CProfile::get('web.httpconf.showdisabled', 1));

check_fields($fields);

$showDisabled = getRequest('showdisabled', 1);
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
$hostId = getRequest('hostid');
if ($hostId && !API::Host()->isWritable(array($hostId))) {
	access_deny();
}

$groupId = getRequest('groupid');
if ($groupId && !API::HostGroup()->get(array('groupids' => $groupId))) {
	access_deny();
}

/*
 * Actions
 */
// add new steps
if (isset($_REQUEST['new_httpstep'])) {
	$_REQUEST['steps'] = getRequest('steps', array());
	$_REQUEST['new_httpstep']['no'] = count($_REQUEST['steps']) + 1;
	array_push($_REQUEST['steps'], $_REQUEST['new_httpstep']);

	unset($_REQUEST['new_httpstep']);
}

if (isset($_REQUEST['delete']) && isset($_REQUEST['httptestid'])) {
	$result = false;

	DBstart();

	$host = DBfetch(DBselect(
		'SELECT h.host'.
		' FROM hosts h,httptest ht'.
		' WHERE ht.hostid=h.hostid'.
			' AND ht.httptestid='.zbx_dbstr($_REQUEST['httptestid'])
	));

	$httptestData = get_httptest_by_httptestid($_REQUEST['httptestid']);
	if ($httptestData) {
		$result = API::HttpTest()->delete(array(getRequest('httptestid')));

		if ($result) {
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCENARIO,
				'Web scenario ['.$httptestData['name'].'] ['.$_REQUEST['httptestid'].'] Host ['.$host['host'].']'
			);
		}
	}
	unset($_REQUEST['httptestid'], $_REQUEST['form']);

	$result = DBend($result);

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}
	show_messages($result, _('Web scenario deleted'), _('Cannot delete web scenario'));
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['httptestid'])) {
	unset($_REQUEST['httptestid']);
	unset($_REQUEST['templated']);
	$_REQUEST['form'] = 'clone';
}
elseif (hasRequest('del_history') && hasRequest('httptestid')) {
	$result = true;

	DBstart();

	$httptestId = getRequest('httptestid');

	$httpTest = get_httptest_by_httptestid($httptestId);
	if ($httpTest) {
		$result = delete_history_by_httptestid($httptestId);
		if ($result) {

			$result = DBexecute('UPDATE httptest SET nextcheck=0 WHERE httptestid='.zbx_dbstr($httptestId));

			$host = DBfetch(DBselect(
				'SELECT h.host FROM hosts h,httptest ht WHERE ht.hostid=h.hostid AND ht.httptestid='.zbx_dbstr($httptestId)
			));

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO,
				'Scenario ['.$httpTest['name'].'] ['.$httptestId.'] Host ['.$host['host'].'] history cleared'
			);
		}
	}

	$result = DBend($result);

	show_messages($result, _('History cleared'), _('Cannot clear history'));
}
elseif (hasRequest('add') || hasRequest('update')) {

	if (hasRequest('update')) {
		$action = AUDIT_ACTION_UPDATE;
		$messageTrue = _('Scenario updated');
		$messageFalse = _('Cannot update web scenario');
	}
	else {
		$action = AUDIT_ACTION_ADD;
		$messageTrue = _('Scenario added');
		$messageFalse = _('Cannot add web scenario');
	}

	try {
		DBstart();

		if (!empty($_REQUEST['applicationid']) && !empty($_REQUEST['new_application'])) {
			throw new Exception(_('Cannot create new application, web scenario is already assigned to application.'));
		}

		$steps = getRequest('steps', array());
		if (!empty($steps)) {
			$i = 1;
			foreach ($steps as $stepNumber => &$step) {
				$step['no'] = $i++;
				$step['follow_redirects'] = $step['follow_redirects']
					? HTTPTEST_STEP_FOLLOW_REDIRECTS_ON
					: HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF;
				$step['retrieve_mode'] = $step['retrieve_mode']
					? HTTPTEST_STEP_RETRIEVE_MODE_HEADERS
					: HTTPTEST_STEP_RETRIEVE_MODE_CONTENT;
			}
			unset($step);
		}

		$httpTest = array(
			'hostid' => $_REQUEST['hostid'],
			'name' => $_REQUEST['name'],
			'authentication' => $_REQUEST['authentication'],
			'applicationid' => getRequest('applicationid'),
			'delay' => $_REQUEST['delay'],
			'retries' => $_REQUEST['retries'],
			'status' => isset($_REQUEST['status']) ? 0 : 1,
			'agent' => $_REQUEST['agent'],
			'variables' => $_REQUEST['variables'],
			'http_proxy' => $_REQUEST['http_proxy'],
			'steps' => $steps,
			'verify_peer' => getRequest('verify_peer', HTTPTEST_VERIFY_PEER_OFF),
			'verify_host' => getRequest('verify_host', HTTPTEST_VERIFY_HOST_OFF),
			'ssl_cert_file' => getRequest('ssl_cert_file'),
			'ssl_key_file' => getRequest('ssl_key_file'),
			'ssl_key_password' => getRequest('ssl_key_password'),
			'headers' => getRequest('headers')
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
				if (isset($step['httpstepid']) && isset($dbHttpSteps[$step['httpstepid']])) {
					$newStep = CArrayHelper::unsetEqualValues($step, $dbHttpSteps[$step['httpstepid']], array('httpstepid'));
					$httpTest['steps'][$snum] = $newStep;
				}
			}

			$httpTest['httptestid'] = $httpTestId = $_REQUEST['httptestid'];
			$result = API::HttpTest()->update($httpTest);
			if (!$result) {
				throw new Exception();
			}
			else {
				uncheckTableRows(getRequest('hostid'));
			}
		}
		else {
			$result = API::HttpTest()->create($httpTest);
			if (!$result) {
				throw new Exception();
			}
			else {
				uncheckTableRows(getRequest('hostid'));
			}
			$httpTestId = reset($result['httptestids']);
		}

		$host = get_host_by_hostid($_REQUEST['hostid']);
		add_audit($action, AUDIT_RESOURCE_SCENARIO, 'Scenario ['.$_REQUEST['name'].'] ['.$httpTestId.'] Host ['.$host['host'].']');

		unset($_REQUEST['httptestid'], $_REQUEST['form']);
		show_messages(true, $messageTrue);
		DBend(true);
	}
	catch (Exception $e) {
		DBend(false);

		$msg = $e->getMessage();
		if (!empty($msg)) {
			error($msg);
		}
		show_messages(false, null, $messageFalse);
	}
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), array('httptest.massenable', 'httptest.massdisable')) && hasRequest('group_httptestid')) {
	$result = true;
	$groupHttpTestId = getRequest('group_httptestid');
	$enable = (getRequest('action') == 'httptest.massenable');
	$status = $enable ? HTTPTEST_STATUS_ACTIVE : HTTPTEST_STATUS_DISABLED;
	$statusName = $enable ? 'enabled' : 'disabled';
	$auditAction = $enable ? AUDIT_ACTION_ENABLE : AUDIT_ACTION_DISABLE;
	$updated = 0;

	DBStart();

	foreach ($groupHttpTestId as $id) {
		if (!($httpTestData = get_httptest_by_httptestid($id))) {
			continue;
		}

		$result &= (bool) API::HttpTest()->update(array(
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

	$result = DBend($result);

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') == 'httptest.massclearhistory' && hasRequest('group_httptestid')) {
	$result = true;
	$group_httptestid = getRequest('group_httptestid');

	DBStart();

	foreach ($group_httptestid as $id) {
		if (!($httptest_data = get_httptest_by_httptestid($id))) {
			continue;
		}

		$result &= delete_history_by_httptestid($id);
		if ($result) {
			$result &= DBexecute('UPDATE httptest SET nextcheck=0 WHERE httptestid='.zbx_dbstr($id));

			$host = DBfetch(DBselect(
				'SELECT h.host FROM hosts h,httptest ht WHERE ht.hostid=h.hostid AND ht.httptestid='.zbx_dbstr($id)
			));

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO,
				'Scenario ['.$httptest_data['name'].'] ['.$id.'] Host ['.$host['host'].'] history cleared'
			);
		}
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}
	show_messages($result, _('History cleared'), _('Cannot clear history'));
}
elseif (hasRequest('action') && getRequest('action') == 'httptest.massdelete' && hasRequest('group_httptestid')) {
	$result = API::HttpTest()->delete(getRequest('group_httptestid'));

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}
	show_messages($result, _('Web scenario deleted'), _('Cannot delete web scenario'));
}

show_messages();

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'hostid' => getRequest('hostid', 0),
		'httptestid' => getRequest('httptestid'),
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh'),
		'templates' => array(),
		'is_template' => false
	);

	if (isset($data['httptestid'])) {
		// get templates
		$httpTestId = $data['httptestid'];
		while ($httpTestId) {
			$dbTest = DBfetch(DBselect(
				'SELECT h.hostid,h.name,h.status,ht.httptestid,ht.templateid'.
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
					$data['templates'][] = SPACE.'&rArr;'.SPACE;
				}
				$httpTestId = $dbTest['templateid'];
				$data['is_template'] = $dbTest['status'] == HOST_STATUS_TEMPLATE;
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
		$data['headers'] = $dbHttpTest['headers'];
		$data['verify_peer'] = $dbHttpTest['verify_peer'];
		$data['verify_host'] = $dbHttpTest['verify_host'];
		$data['ssl_cert_file'] = $dbHttpTest['ssl_cert_file'];
		$data['ssl_key_file'] = $dbHttpTest['ssl_key_file'];
		$data['ssl_key_password'] = $dbHttpTest['ssl_key_password'];
		$data['steps'] = DBfetchArray(DBselect('SELECT h.* FROM httpstep h WHERE h.httptestid='.zbx_dbstr($_REQUEST['httptestid']).' ORDER BY h.no'));
	}
	else {
		if (isset($_REQUEST['form_refresh'])) {
			$data['status'] = isset($_REQUEST['status']) ? HTTPTEST_STATUS_ACTIVE : HTTPTEST_STATUS_DISABLED;
		}
		else {
			$data['status'] = HTTPTEST_STATUS_ACTIVE;
		}

		$data['name'] = getRequest('name', '');
		$data['applicationid'] = getRequest('applicationid');
		$data['new_application'] = getRequest('new_application', '');
		$data['delay'] = getRequest('delay', 60);
		$data['retries'] = getRequest('retries', 1);
		$data['agent'] = getRequest('agent', '');
		$data['variables'] = getRequest('variables', array());
		$data['authentication'] = getRequest('authentication', HTTPTEST_AUTH_NONE);
		$data['http_user'] = getRequest('http_user', '');
		$data['http_password'] = getRequest('http_password', '');
		$data['http_proxy'] = getRequest('http_proxy', '');
		$data['templated'] = (bool) getRequest('templated');
		$data['steps'] = getRequest('steps', array());
		$data['headers'] = getRequest('headers');
		$data['verify_peer'] = getRequest('verify_peer');
		$data['verify_host'] = getRequest('verify_host');
		$data['ssl_cert_file'] = getRequest('ssl_cert_file');
		$data['ssl_key_file'] = getRequest('ssl_key_file');
		$data['ssl_key_password'] = getRequest('ssl_key_password');
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
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$pageFilter = new CPageFilter(array(
		'groups' => array(
			'editable' => true
		),
		'hosts' => array(
			'editable' => true,
			'templated_hosts' => true
		),
		'hostid' => getRequest('hostid'),
		'groupid' => getRequest('groupid')
	));

	$data = array(
		'hostid' => $pageFilter->hostid,
		'pageFilter' => $pageFilter,
		'showDisabled' => $showDisabled,
		'httpTests' => array(),
		'httpTestsLastData' => array(),
		'paging' => null,
		'sort' => $sortField,
		'sortorder' => $sortOrder
	);

	// show the error column only for hosts
	if (getRequest('hostid') != 0) {
		$data['showInfoColumn'] = (bool) API::Host()->get(array(
			'hostids' => getRequest('hostid'),
			'output' => array('status')
		));
	}
	else {
		$data['showInfoColumn'] = true;
	}

	if ($data['pageFilter']->hostsSelected) {
		$config = select_config();

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

		order_result($httpTests, $sortField, $sortOrder);

		$data['paging'] = getPagingLine($httpTests);

		$dbHttpTests = DBselect(
			'SELECT ht.httptestid,ht.name,ht.delay,ht.status,ht.hostid,ht.templateid,h.name AS hostname,ht.retries,'.
				'ht.authentication,ht.http_proxy,a.applicationid,a.name AS application_name'.
				' FROM httptest ht'.
				' INNER JOIN hosts h ON h.hostid=ht.hostid'.
				' LEFT JOIN applications a ON a.applicationid=ht.applicationid'.
				' WHERE '.dbConditionInt('ht.httptestid', zbx_objectValues($httpTests, 'httptestid'))
		);
		$httpTests = array();
		while ($dbHttpTest = DBfetch($dbHttpTests)) {
			$httpTests[$dbHttpTest['httptestid']] = $dbHttpTest;
		}

		if($data['showInfoColumn']) {
			$httpTestsLastData = Manager::HttpTest()->getLastData(array_keys($httpTests));

			foreach ($httpTestsLastData as $httpTestId => &$lastData) {
				if ($lastData['lastfailedstep'] !== null) {
					$lastData['failedstep'] = get_httpstep_by_no($httpTestId, $lastData['lastfailedstep']);
				}
			}
			unset($lastData);
		}
		else {
			$httpTestsLastData = array();
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

		order_result($httpTests, $sortField, $sortOrder);

		$data['parentTemplates'] = getHttpTestsParentTemplates($httpTests);

		$data['httpTests'] = $httpTests;
		$data['httpTestsLastData'] = $httpTestsLastData;
	}

	// render view
	$httpView = new CView('configuration.httpconf.list', $data);
	$httpView->render();
	$httpView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
