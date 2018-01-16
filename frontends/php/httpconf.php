<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
$page['scripts'] = ['class.cviewswitcher.js'];

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'groupid'			=> [T_ZBX_INT, O_OPT, P_SYS,	DB_ID,				null],
	'new_httpstep'		=> [T_ZBX_STR, O_OPT, P_NO_TRIM,	null,				null],
	'group_httptestid'	=> [T_ZBX_INT, O_OPT, null,	DB_ID,				null],
	// form
	'hostid'          => [T_ZBX_INT, O_OPT, P_SYS, DB_ID.NOT_ZERO,          'isset({form}) || isset({add}) || isset({update})'],
	'applicationid'   => [T_ZBX_INT, O_OPT, null,  DB_ID,                   null, _('Application')],
	'httptestid'      => [T_ZBX_INT, O_NO,  P_SYS, DB_ID,                   'isset({form}) && {form} == "update"'],
	'name'            => [T_ZBX_STR, O_OPT, null,  NOT_EMPTY,               'isset({add}) || isset({update})', _('Name')],
	'delay'           => [T_ZBX_STR, O_OPT, null,  null,					'isset({add}) || isset({update})'],
	'retries'         => [T_ZBX_INT, O_OPT, null,  BETWEEN(1, 10),          'isset({add}) || isset({update})',
		_('Attempts')
	],
	'status'          => [T_ZBX_STR, O_OPT, null,  null,                    null],
	'agent'           => [T_ZBX_STR, O_OPT, null, null,                     'isset({add}) || isset({update})'],
	'agent_other'     => [T_ZBX_STR, O_OPT, null, null,
		'(isset({add}) || isset({update})) && {agent} == '.ZBX_AGENT_OTHER
	],
	'pairs'           => [T_ZBX_STR, O_OPT, P_NO_TRIM,  null,                    null],
	'steps'           => [T_ZBX_STR, O_OPT, P_NO_TRIM,  null,                    'isset({add}) || isset({update})', _('Steps')],
	'authentication'  => [T_ZBX_INT, O_OPT, null,  IN('0,1,2'),             'isset({add}) || isset({update})'],
	'http_user'       => [T_ZBX_STR, O_OPT, null,  NOT_EMPTY,               '(isset({add}) || isset({update})) && isset({authentication}) && ({authentication} == '.HTTPTEST_AUTH_BASIC.
		' || {authentication} == '.HTTPTEST_AUTH_NTLM.')', _('User')],
	'http_password'		=> [T_ZBX_STR, O_OPT, P_NO_TRIM,	NOT_EMPTY,		'(isset({add}) || isset({update})) && isset({authentication}) && ({authentication} == '.HTTPTEST_AUTH_BASIC.
		' || {authentication} == '.HTTPTEST_AUTH_NTLM.')', _('Password')],
	'http_proxy'		=> [T_ZBX_STR, O_OPT, null,	null,				'isset({add}) || isset({update})'],
	'new_application'	=> [T_ZBX_STR, O_OPT, null,	null,				null],
	'hostname'			=> [T_ZBX_STR, O_OPT, null,	null,				null],
	'templated'			=> [T_ZBX_STR, O_OPT, null,	null,				null],
	'verify_host'		=> [T_ZBX_STR, O_OPT, null,	null,				null],
	'verify_peer'		=> [T_ZBX_STR, O_OPT, null,	null,				null],
	'ssl_cert_file'		=> [T_ZBX_STR, O_OPT, null, null,					'isset({add}) || isset({update})'],
	'ssl_key_file'		=> [T_ZBX_STR, O_OPT, null, null,					'isset({add}) || isset({update})'],
	'ssl_key_password'	=> [T_ZBX_STR, O_OPT, P_NO_TRIM, null,				'isset({add}) || isset({update})'],
	// filter
	'filter_set' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_status' =>		[T_ZBX_INT, O_OPT, null,
		IN([-1, HTTPTEST_STATUS_ACTIVE, HTTPTEST_STATUS_DISABLED]), null
	],
	// actions
	'action'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,
								IN('"httptest.massclearhistory","httptest.massdelete","httptest.massdisable",'.
									'"httptest.massenable"'
								),
								null
							],
	'clone'				=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'del_history'		=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'add'				=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'update'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'delete'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,			null],
	'cancel'			=> [T_ZBX_STR, O_OPT, P_SYS,	null,				null],
	'form'				=> [T_ZBX_STR, O_OPT, P_SYS,	null,				null],
	'form_refresh'		=> [T_ZBX_INT, O_OPT, null,	null,				null],
	// sort and sortorder
	'sort'				=> [T_ZBX_STR, O_OPT, P_SYS, IN('"hostname","name","status"'),				null],
	'sortorder'			=> [T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];

check_fields($fields);

if (!empty($_REQUEST['steps'])) {
	order_result($_REQUEST['steps'], 'no');
}

/*
 * Permissions
 */
//*
if (isset($_REQUEST['httptestid']) || !empty($_REQUEST['group_httptestid'])) {
	$testIds = [];
	if (isset($_REQUEST['httptestid'])) {
		$testIds[] = $_REQUEST['httptestid'];
	}
	if (!empty($_REQUEST['group_httptestid'])) {
		$testIds = array_merge($testIds, $_REQUEST['group_httptestid']);
	}
	if ($testIds) {
		$testIds = array_unique($testIds);

		$count = API::HttpTest()->get([
			'countOutput' => true,
			'httptestids' => $testIds,
			'editable' => true
		]);

		if ($count != count($testIds)) {
			access_deny();
		}
	}
}
if (getRequest('hostid') && !isWritableHostTemplates([getRequest('hostid')])) {
	access_deny();
}

$groupId = getRequest('groupid');
if ($groupId && !API::HostGroup()->get(['groupids' => $groupId])) {
	access_deny();
}

/*
 * Actions
 */
// add new steps
if (isset($_REQUEST['new_httpstep'])) {
	$_REQUEST['steps'] = getRequest('steps', []);
	$_REQUEST['new_httpstep']['no'] = count($_REQUEST['steps']) + 1;
	array_push($_REQUEST['steps'], $_REQUEST['new_httpstep']);

	unset($_REQUEST['new_httpstep']);
}

if (hasRequest('delete') && hasRequest('httptestid')) {
	DBstart();

	$result = API::HttpTest()->delete([getRequest('httptestid')]);

	$result = DBend($result);

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}

	show_messages($result, _('Web scenario deleted'), _('Cannot delete web scenario'));

	unset($_REQUEST['form']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['httptestid'])) {
	unset($_REQUEST['httptestid']);
	unset($_REQUEST['templated']);
	$_REQUEST['form'] = 'clone';
}
elseif (hasRequest('del_history') && hasRequest('httptestid')) {
	$result = true;

	$httpTestId = getRequest('httptestid');

	$httpTests = API::HttpTest()->get([
		'output' => ['name'],
		'httptestids' => [$httpTestId],
		'selectHosts' => ['name'],
		'editable' => true
	]);

	if ($httpTests) {
		DBstart();

		$result = deleteHistoryByHttpTestIds([$httpTestId]);
		$result = ($result && DBexecute('UPDATE httptest SET nextcheck=0 WHERE httptestid='.zbx_dbstr($httpTestId)));

		if ($result) {
			$httpTest = reset($httpTests);
			$host = reset($httpTest['hosts']);

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO,
				_('Web scenario').' ['.$httpTest['name'].'] ['.$httpTestId.'] '.
					_('Host').' ['.$host['name'].'] '._('History cleared')
			);
		}

		$result = DBend($result);
	}

	show_messages($result, _('History cleared'), _('Cannot clear history'));
}
elseif (hasRequest('add') || hasRequest('update')) {
	if (hasRequest('update')) {
		$messageTrue = _('Web scenario updated');
		$messageFalse = _('Cannot update web scenario');
	}
	else {
		$messageTrue = _('Web scenario added');
		$messageFalse = _('Cannot add web scenario');
	}

	try {
		DBstart();

		$new_application = getRequest('new_application');

		if (!empty($_REQUEST['applicationid']) && $new_application) {
			throw new Exception(_('Cannot create new application, web scenario is already assigned to application.'));
		}

		$steps = getRequest('steps', []);
		$field_names = ['headers', 'variables', 'post_fields', 'query_fields'];
		$i = 1;

		foreach ($steps as &$step) {
			$step['no'] = $i++;
			$step['follow_redirects'] = $step['follow_redirects']
				? HTTPTEST_STEP_FOLLOW_REDIRECTS_ON
				: HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF;
			$step['retrieve_mode'] = $step['retrieve_mode']
				? HTTPTEST_STEP_RETRIEVE_MODE_HEADERS
				: HTTPTEST_STEP_RETRIEVE_MODE_CONTENT;

			foreach ($field_names as $field_name) {
				$step[$field_name] = [];
			}

			if (array_key_exists('pairs', $step)) {
				foreach ($field_names as $field_name) {
					foreach ($step['pairs'] as $pair) {
						if (array_key_exists('type', $pair) && $field_name === $pair['type'] &&
							((array_key_exists('name', $pair) && $pair['name'] !== '') ||
							(array_key_exists('value', $pair) && $pair['value'] !== ''))) {
							$step[$field_name][] = [
								'name' => (array_key_exists('name', $pair) ? $pair['name'] : ''),
								'value' => (array_key_exists('value', $pair) ? $pair['value'] : '')
							];
						}
					}
				}
				unset($step['pairs']);
			}

			foreach ($step['variables'] as &$variable) {
				$variable['name'] = trim($variable['name']);
			}
			unset($variable);

			if ($step['post_type'] == ZBX_POSTTYPE_FORM) {
				$step['posts'] = $step['post_fields'];
			}
			unset($step['post_fields'], $step['post_type']);
		}
		unset($step);

		$httpTest = [
			'hostid' => $_REQUEST['hostid'],
			'name' => $_REQUEST['name'],
			'authentication' => $_REQUEST['authentication'],
			'applicationid' => getRequest('applicationid', 0),
			'delay' => getRequest('delay', DB::getDefault('httptest', 'delay')),
			'retries' => $_REQUEST['retries'],
			'status' => hasRequest('status') ? HTTPTEST_STATUS_ACTIVE : HTTPTEST_STATUS_DISABLED,
			'agent' => hasRequest('agent_other') ? getRequest('agent_other') : getRequest('agent'),
			'variables' => [],
			'http_proxy' => $_REQUEST['http_proxy'],
			'steps' => $steps,
			'http_user' => ($_REQUEST['authentication'] == HTTPTEST_AUTH_NONE) ? '' : $_REQUEST['http_user'],
			'http_password' => ($_REQUEST['authentication'] == HTTPTEST_AUTH_NONE) ? '' : $_REQUEST['http_password'],
			'verify_peer' => getRequest('verify_peer', HTTPTEST_VERIFY_PEER_OFF),
			'verify_host' => getRequest('verify_host', HTTPTEST_VERIFY_HOST_OFF),
			'ssl_cert_file' => getRequest('ssl_cert_file'),
			'ssl_key_file' => getRequest('ssl_key_file'),
			'ssl_key_password' => getRequest('ssl_key_password'),
			'headers' => []
		];

		foreach (getRequest('pairs', []) as $pair) {
			if (array_key_exists('type', $pair) && in_array($pair['type'], ['variables', 'headers']) &&
				((array_key_exists('name', $pair) && $pair['name'] !== '') ||
				(array_key_exists('value', $pair) && $pair['value'] !== ''))) {

				$httpTest[$pair['type']][] = [
					'name' => (array_key_exists('name', $pair) ? $pair['name'] : ''),
					'value' => (array_key_exists('value', $pair) ? $pair['value'] : '')
				];
			}
		}

		foreach ($httpTest['variables'] as &$variable) {
			$variable['name'] = trim($variable['name']);
		}
		unset($variable);

		if ($new_application) {
			$exApp = API::Application()->get([
				'output' => ['applicationid', 'flags'],
				'hostids' => $_REQUEST['hostid'],
				'filter' => ['name' => $new_application]
			]);

			/*
			 * If application exists and it is a discovered application, prevent adding it to web scenario. If it is
			 * a normal application, assign it to web scenario. Otherwise create new application.
			 */
			if ($exApp) {
				if ($exApp[0]['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					throw new Exception(_s('Application "%1$s" already exists.', $new_application));
				}
				else {
					$httpTest['applicationid'] = $exApp[0]['applicationid'];
				}
			}
			else {
				$result = API::Application()->create([
					'name' => $new_application,
					'hostid' => $_REQUEST['hostid']
				]);
				if ($result) {
					$httpTest['applicationid'] = reset($result['applicationids']);
				}
				else {
					throw new Exception(_s('Cannot add new application "%1$s".', $new_application));
				}
			}
		}

		if (isset($_REQUEST['httptestid'])) {
			// unset fields that did not change
			$dbHttpTest = API::HttpTest()->get([
				'httptestids' => $_REQUEST['httptestid'],
				'output' => API_OUTPUT_EXTEND,
				'selectSteps' => API_OUTPUT_EXTEND
			]);
			$dbHttpTest = reset($dbHttpTest);
			$dbHttpSteps = zbx_toHash($dbHttpTest['steps'], 'httpstepid');

			$httpTest = CArrayHelper::unsetEqualValues($httpTest, $dbHttpTest, ['applicationid']);
			foreach (['headers', 'variables'] as $field_name) {
				if (count($httpTest[$field_name]) !== count($dbHttpTest[$field_name])) {
					continue;
				}

				$changed = false;
				foreach ($httpTest[$field_name] as $key => $field) {
					if ($dbHttpTest[$field_name][$key]['name'] !== $field['name']
							|| $dbHttpTest[$field_name][$key]['value'] !== $field['value']) {
						$changed = true;
						break;
					}
				}

				if (!$changed) {
					unset($httpTest[$field_name]);
				}
			}

			foreach ($httpTest['steps'] as $snum => $step) {
				if (array_key_exists('httpstepid', $step) && array_key_exists($step['httpstepid'], $dbHttpSteps)) {
					$db_step = $dbHttpSteps[$step['httpstepid']];
					$new_step = CArrayHelper::unsetEqualValues($step, $db_step, ['httpstepid']);
					foreach (['headers', 'variables', 'posts', 'query_fields'] as $field_name) {
						if (!array_key_exists($field_name, $new_step)
								|| !is_array($new_step[$field_name]) || !is_array($db_step[$field_name])
								|| count($new_step[$field_name]) !== count($db_step[$field_name])) {
							continue;
						}

						$changed = false;
						foreach ($new_step[$field_name] as $key => $field) {
							if ($db_step[$field_name][$key]['name'] !== $field['name']
									|| $db_step[$field_name][$key]['value'] !== $field['value']) {
								$changed = true;
								break;
							}
						}

						if (!$changed) {
							unset($new_step[$field_name]);
						}
					}
					$httpTest['steps'][$snum] = $new_step;
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
			foreach ($httpTest['steps'] as &$step) {
				unset($step['httptestid'], $step['httpstepid']);
			}
			unset($step);

			$result = API::HttpTest()->create($httpTest);
			if (!$result) {
				throw new Exception();
			}
			else {
				uncheckTableRows(getRequest('hostid'));
			}
			$httpTestId = reset($result['httptestids']);
		}

		unset($_REQUEST['form']);
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
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['httptest.massenable', 'httptest.massdisable'])
		&& hasRequest('group_httptestid') && is_array(getRequest('group_httptestid'))) {
	$enable = (getRequest('action') === 'httptest.massenable');
	$status = $enable ? HTTPTEST_STATUS_ACTIVE : HTTPTEST_STATUS_DISABLED;
	$updated = 0;
	$result = true;

	$upd_httptests = [];

	foreach (getRequest('group_httptestid') as $httptestid) {
		$upd_httptests[] = [
			'httptestid' => $httptestid,
			'status' => $status
		];
	}

	if ($upd_httptests) {
		$result = (bool) API::HttpTest()->update($upd_httptests);
	}

	$updated = count($upd_httptests);

	$messageSuccess = $enable
		? _n('Web scenario enabled', 'Web scenarios enabled', $updated)
		: _n('Web scenario disabled', 'Web scenarios disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable web scenario', 'Cannot enable web scenarios', $updated)
		: _n('Cannot disable web scenario', 'Cannot disable web scenarios', $updated);

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}

	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') === 'httptest.massclearhistory'
		&& hasRequest('group_httptestid') && is_array(getRequest('group_httptestid'))) {
	$result = false;

	$httpTestIds = getRequest('group_httptestid');

	$httpTests = API::HttpTest()->get([
		'output' => ['httptestid', 'name'],
		'httptestids' => $httpTestIds,
		'selectHosts' => ['name'],
		'editable' => true
	]);

	if ($httpTests) {
		DBstart();

		$result = deleteHistoryByHttpTestIds($httpTestIds);
		$result = ($result && DBexecute(
			'UPDATE httptest SET nextcheck=0 WHERE '.dbConditionInt('httptestid', $httpTestIds)
		));

		if ($result) {
			foreach ($httpTests as $httpTest) {
				$host = reset($httpTest['hosts']);

				add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCENARIO,
					_('Web scenario').' ['.$httpTest['name'].'] ['.$httpTest['httptestid'].'] '.
						_('Host').' ['.$host['name'].'] '._('History cleared')
				);
			}
		}

		$result = DBend($result);

		if ($result) {
			uncheckTableRows(getRequest('hostid'));
		}
	}

	show_messages($result, _('History cleared'), _('Cannot clear history'));
}
elseif (hasRequest('action') && getRequest('action') === 'httptest.massdelete'
		&& hasRequest('group_httptestid') && is_array(getRequest('group_httptestid'))) {
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
	$data = [
		'hostid' => getRequest('hostid', 0),
		'httptestid' => getRequest('httptestid'),
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh'),
		'templates' => []
	];

	$host = API::Host()->get([
		'output' => ['status'],
		'hostids' => $data['hostid'],
		'templated_hosts' => true
	]);
	$data['host'] = reset($host);

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
					$data['templates'][] = new CLink($dbTest['name'],
						'httpconf.php?form=update&httptestid='.$dbTest['httptestid'].'&hostid='.$dbTest['hostid']
					);
					$data['templates'][] = SPACE.'&rArr;'.SPACE;
				}
				$httpTestId = $dbTest['templateid'];
			}
		}
		$data['templates'] = array_reverse($data['templates']);
		array_shift($data['templates']);
	}

	if (hasRequest('httptestid') && !hasRequest('form_refresh')) {
		$db_httptests = API::HttpTest()->get([
			'output' => ['name', 'applicationid', 'delay', 'retries', 'status', 'agent', 'authentication',
				'http_user', 'http_password', 'http_proxy', 'templateid', 'verify_peer', 'verify_host', 'ssl_cert_file',
				'ssl_key_file', 'ssl_key_password', 'headers', 'variables'
			],
			'selectSteps' => ['httpstepid', 'name', 'no', 'url', 'timeout', 'posts', 'required', 'status_codes',
				'follow_redirects', 'retrieve_mode', 'headers', 'variables', 'query_fields', 'post_type'
			],
			'httptestids' => getRequest('httptestid')
		]);

		$db_httptest = $db_httptests[0];

		$data['name'] = $db_httptest['name'];
		$data['applicationid'] = $db_httptest['applicationid'];
		$data['new_application'] = '';
		$data['delay'] = $db_httptest['delay'];
		$data['retries'] = $db_httptest['retries'];
		$data['status'] = $db_httptest['status'];

		$data['agent'] = ZBX_AGENT_OTHER;
		$data['agent_other'] = $db_httptest['agent'];

		foreach (userAgents() as $userAgents) {
			if (array_key_exists($db_httptest['agent'], $userAgents)) {
				$data['agent'] = $db_httptest['agent'];
				$data['agent_other'] = '';
				break;
			}
		}

		// Used for both, Scenario and Steps pairs.
		$id = 1;
		$data['pairs'] = [];

		$fields = [
			'headers' => 'headers',
			'variables' => 'variables'
		];

		CArrayHelper::sort($db_httptest['variables'], ['name']);

		foreach ($fields as $type => $field_name) {
			foreach ($db_httptest[$field_name] as $pair) {
				$data['pairs'][] = [
					'id' => $id++,
					'type' => $type,
					'name' => $pair['name'],
					'value' => $pair['value']
				];
			}
		}

		$data['authentication'] = $db_httptest['authentication'];
		$data['http_user'] = $db_httptest['http_user'];
		$data['http_password'] = $db_httptest['http_password'];
		$data['http_proxy'] = $db_httptest['http_proxy'];
		$data['templated'] = (bool) $db_httptest['templateid'];

		$data['verify_peer'] = $db_httptest['verify_peer'];
		$data['verify_host'] = $db_httptest['verify_host'];
		$data['ssl_cert_file'] = $db_httptest['ssl_cert_file'];
		$data['ssl_key_file'] = $db_httptest['ssl_key_file'];
		$data['ssl_key_password'] = $db_httptest['ssl_key_password'];
		$data['steps'] = $db_httptest['steps'];
		CArrayHelper::sort($data['steps'], ['no']);

		$fields = [
			'headers' => 'headers',
			'variables' => 'variables',
			'query_fields' => 'query_fields',
			'post_fields' => 'posts'
		];

		foreach ($data['steps'] as &$step) {
			$step['pairs'] = [];

			CArrayHelper::sort($step['variables'], ['name']);

			foreach ($fields as $type => $field_name) {
				if ($field_name !== 'posts' || $step['post_type'] == ZBX_POSTTYPE_FORM) {
					foreach ($step[$field_name] as $pair) {
						$step['pairs'][] = [
							'id' => $id++,
							'type' => $type,
							'name' => $pair['name'],
							'value' => $pair['value']
						];
					}

					if ($field_name === 'posts') {
						$step['posts'] = '';
					}
					else {
						unset($step[$field_name]);
					}
				}
			}
		}
		unset($step);

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
		$data['delay'] = getRequest('delay', DB::getDefault('httptest', 'delay'));
		$data['retries'] = getRequest('retries', 1);

		$data['agent'] = getRequest('agent', ZBX_DEFAULT_AGENT);
		$data['agent_other'] = getRequest('agent_other');

		if ($data['agent'] == ZBX_AGENT_OTHER) {
			foreach (userAgents() as $userAgents) {
				if (array_key_exists($data['agent_other'], $userAgents)) {
					$data['agent'] = $data['agent_other'];
					$data['agent_other'] = '';
					break;
				}
			}
		}

		$data['authentication'] = getRequest('authentication', HTTPTEST_AUTH_NONE);
		$data['http_user'] = getRequest('http_user', '');
		$data['http_password'] = getRequest('http_password', '');
		$data['http_proxy'] = getRequest('http_proxy', '');
		$data['templated'] = (bool) getRequest('templated');
		$data['steps'] = getRequest('steps', []);
		$data['verify_peer'] = getRequest('verify_peer');
		$data['verify_host'] = getRequest('verify_host');
		$data['ssl_cert_file'] = getRequest('ssl_cert_file');
		$data['ssl_key_file'] = getRequest('ssl_key_file');
		$data['ssl_key_password'] = getRequest('ssl_key_password');
		$data['pairs'] = array_values(getRequest('pairs', []));
	}

	$data['application_list'] = [];
	if (!empty($data['hostid'])) {
		$dbApps = DBselect(
			'SELECT a.applicationid,a.name'.
			' FROM applications a'.
			' WHERE a.hostid='.zbx_dbstr($data['hostid']).
				' AND a.flags='.ZBX_FLAG_DISCOVERY_NORMAL
		);
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

	if (hasRequest('filter_set')) {
		CProfile::update('web.httpconf.filter_status', getRequest('filter_status', -1), PROFILE_TYPE_INT);
	}
	elseif (hasRequest('filter_rst')) {
		CProfile::delete('web.httpconf.filter_status');
	}

	$pageFilter = new CPageFilter([
		'groups' => [
			'editable' => true
		],
		'hosts' => [
			'editable' => true,
			'templated_hosts' => true
		],
		'hostid' => getRequest('hostid'),
		'groupid' => getRequest('groupid')
	]);

	$data = [
		'hostid' => $pageFilter->hostid,
		'pageFilter' => $pageFilter,
		'filter_status' => CProfile::get('web.httpconf.filter_status', -1),
		'httpTests' => [],
		'httpTestsLastData' => [],
		'paging' => null,
		'sort' => $sortField,
		'sortorder' => $sortOrder
	];

	// show the error column only for hosts
	if (getRequest('hostid') != 0) {
		$data['showInfoColumn'] = (bool) API::Host()->get([
			'hostids' => getRequest('hostid'),
			'output' => ['status']
		]);
	}
	else {
		$data['showInfoColumn'] = true;
	}

	if ($data['pageFilter']->hostsSelected) {
		$config = select_config();

		$options = [
			'editable' => true,
			'output' => ['httptestid', $sortField],
			'limit' => $config['search_limit'] + 1
		];
		if ($data['filter_status'] != -1) {
			$options['filter']['status'] = $data['filter_status'];
		}
		if ($data['pageFilter']->hostid > 0) {
			$options['hostids'] = $data['pageFilter']->hostid;
		}
		elseif ($data['pageFilter']->groupid > 0) {
			$options['groupids'] = $data['pageFilter']->groupids;
		}
		$httpTests = API::HttpTest()->get($options);

		$dbHttpTests = DBselect(
			'SELECT ht.httptestid,ht.name,ht.delay,ht.status,ht.hostid,ht.templateid,h.name AS hostname,ht.retries,'.
				'ht.authentication,ht.http_proxy,a.applicationid,a.name AS application_name'.
				' FROM httptest ht'.
				' INNER JOIN hosts h ON h.hostid=ht.hostid'.
				' LEFT JOIN applications a ON a.applicationid=ht.applicationid'.
				' WHERE '.dbConditionInt('ht.httptestid', zbx_objectValues($httpTests, 'httptestid'))
		);
		$httpTests = [];
		while ($dbHttpTest = DBfetch($dbHttpTests)) {
			$httpTests[$dbHttpTest['httptestid']] = $dbHttpTest;
		}

		order_result($httpTests, $sortField, $sortOrder);

		$url = (new CUrl('httpconf.php'))
			->setArgument('hostid', $data['hostid'])
			->setArgument('groupid', $data['pageFilter']->groupid);

		$data['paging'] = getPagingLine($httpTests, $sortOrder, $url);

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
			$httpTestsLastData = [];
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
