<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
$page['scripts'] = ['class.tagfilteritem.js'];

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'new_httpstep'		=> [T_ZBX_STR, O_OPT, P_NO_TRIM,	null,				null],
	'group_httptestid'	=> [T_ZBX_INT, O_OPT, null,	DB_ID,				null],
	// form
	'hostid'          => [T_ZBX_INT, O_OPT, P_SYS, DB_ID.NOT_ZERO,          'isset({form}) || isset({add}) || isset({update})'],
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
	'authentication' =>		[T_ZBX_INT, O_OPT, null,
								IN([HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS,
									HTTPTEST_AUTH_DIGEST
								]),
								'isset({add}) || isset({update})'
							],
	'http_user' =>			[T_ZBX_STR, O_OPT, null,  null,
								'(isset({add}) || isset({update})) && isset({authentication})'.
									' && ({authentication}=='.HTTPTEST_AUTH_BASIC.
										' || {authentication}=='.HTTPTEST_AUTH_NTLM.
										' || {authentication}=='.HTTPTEST_AUTH_KERBEROS.
										' || {authentication} == '.HTTPTEST_AUTH_DIGEST.
									')',
								_('User')
							],
	'http_password' =>		[T_ZBX_STR, O_OPT, P_NO_TRIM, null,
								'(isset({add}) || isset({update})) && isset({authentication})'.
									' && ({authentication}=='.HTTPTEST_AUTH_BASIC.
										' || {authentication}=='.HTTPTEST_AUTH_NTLM.
										' || {authentication}=='.HTTPTEST_AUTH_KERBEROS.
										' || {authentication} == '.HTTPTEST_AUTH_DIGEST.
									')',
								_('Password')
							],
	'http_proxy'		=> [T_ZBX_STR, O_OPT, null,	null,				'isset({add}) || isset({update})'],
	'hostname'			=> [T_ZBX_STR, O_OPT, null,	null,				null],
	'templated'			=> [T_ZBX_STR, O_OPT, null,	null,				null],
	'verify_host'		=> [T_ZBX_STR, O_OPT, null,	null,				null],
	'verify_peer'		=> [T_ZBX_STR, O_OPT, null,	null,				null],
	'ssl_cert_file'		=> [T_ZBX_STR, O_OPT, null, null,					'isset({add}) || isset({update})'],
	'ssl_key_file'		=> [T_ZBX_STR, O_OPT, null, null,					'isset({add}) || isset({update})'],
	'ssl_key_password'	=> [T_ZBX_STR, O_OPT, P_NO_TRIM, null,				'isset({add}) || isset({update})'],
	'context' =>			[T_ZBX_STR, O_MAND, P_SYS,	IN('"host", "template"'),	null],
	'tags'				=> [T_ZBX_STR, O_OPT, null,	null,				null],
	'show_inherited_tags' => [T_ZBX_INT, O_OPT, null, IN([0,1]),		null],
	// filter
	'filter_set' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_status' =>		[T_ZBX_INT, O_OPT, null,
		IN([-1, HTTPTEST_STATUS_ACTIVE, HTTPTEST_STATUS_DISABLED]), null
	],
	'filter_groupids'	=> [T_ZBX_INT, O_OPT, null,	DB_ID,			null],
	'filter_hostids'	=> [T_ZBX_INT, O_OPT, null,	DB_ID,			null],
	'filter_evaltype'	=> [T_ZBX_INT, O_OPT, null, IN([TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]), null],
	'filter_tags'		=> [T_ZBX_STR, O_OPT, null,	null,			null],
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
if (hasRequest('httptestid')) {
	$httptests = API::HttpTest()->get([
		'output' => [],
		'httptestids' => getRequest('httptestid'),
		'editable' => true
	]);

	if (!$httptests) {
		access_deny();
	}
}
elseif (getRequest('hostid') && !isWritableHostTemplates([getRequest('hostid')])) {
	access_deny();
}

$tags = getRequest('tags', []);
foreach ($tags as $key => $tag) {
	if ($tag['tag'] === '' && $tag['value'] === '') {
		unset($tags[$key]);
	}
	elseif (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN)) {
		unset($tags[$key]);
	}
	else {
		unset($tags[$key]['type']);
	}
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
	$result = deleteHistoryByHttpTestIds([getRequest('httptestid')]);

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

		$steps = getRequest('steps', []);
		$field_names = ['headers', 'variables', 'post_fields', 'query_fields'];
		$i = 1;

		foreach ($steps as &$step) {
			$step['no'] = $i++;
			$step['follow_redirects'] = $step['follow_redirects']
				? HTTPTEST_STEP_FOLLOW_REDIRECTS_ON
				: HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF;

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
			'headers' => [],
			'tags' => $tags
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

		if (isset($_REQUEST['httptestid'])) {
			// unset fields that did not change
			$dbHttpTest = API::HttpTest()->get([
				'httptestids' => $_REQUEST['httptestid'],
				'output' => API_OUTPUT_EXTEND,
				'selectSteps' => API_OUTPUT_EXTEND
			]);
			$dbHttpTest = reset($dbHttpTest);
			$dbHttpSteps = zbx_toHash($dbHttpTest['steps'], 'httpstepid');

			$httpTest = CArrayHelper::unsetEqualValues($httpTest, $dbHttpTest);
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
				if ($step['httpstepid'] < 1) {
					unset($step['httpstepid']);
					unset($httpTest['steps'][$snum]['httpstepid']);
				}
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
		&& hasRequest('group_httptestid') && is_array(getRequest('group_httptestid'))
		&& getRequest('group_httptestid')) {
	$result = deleteHistoryByHttpTestIds(getRequest('group_httptestid'));

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
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

if (hasRequest('action') && hasRequest('group_httptestid') && !$result) {
	$httptests = API::HttpTest()->get([
		'output' => [],
		'httptestids' => getRequest('group_httptestid'),
		'editable' => true
	]);

	uncheckTableRows(getRequest('hostid'), zbx_objectValues($httptests, 'httptestid'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = [
		'hostid' => getRequest('hostid', 0),
		'httptestid' => getRequest('httptestid'),
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh'),
		'templates' => [],
		'context' => getRequest('context'),
		'show_inherited_tags' => getRequest('show_inherited_tags', 0)
	];

	$host = API::Host()->get([
		'output' => ['status'],
		'selectParentTemplates' => ['templateid', 'name'],
		'hostids' => $data['hostid'],
		'templated_hosts' => true
	]);
	$data['host'] = reset($host);

	if (hasRequest('httptestid')) {
		$db_httptests = API::HttpTest()->get([
			'output' => ['name', 'delay', 'retries', 'status', 'agent', 'authentication',
				'http_user', 'http_password', 'http_proxy', 'templateid', 'verify_peer', 'verify_host', 'ssl_cert_file',
				'ssl_key_file', 'ssl_key_password', 'headers', 'variables'
			],
			'selectSteps' => ['httpstepid', 'name', 'no', 'url', 'timeout', 'posts', 'required', 'status_codes',
				'follow_redirects', 'retrieve_mode', 'headers', 'variables', 'query_fields', 'post_type'
			],
			'selectTags' => ['tag', 'value'],
			'httptestids' => getRequest('httptestid')
		]);

		$db_httptest = $db_httptests[0];
	}
	else {
		$db_httptest = null;
	}

	if ($db_httptest && !hasRequest('form_refresh')) {
		$data['name'] = $db_httptest['name'];
		$data['delay'] = $db_httptest['delay'];
		$data['retries'] = $db_httptest['retries'];
		$data['status'] = $db_httptest['status'];
		$data['templates'] = makeHttpTestTemplatesHtml($db_httptest['httptestid'],
			getHttpTestParentTemplates($db_httptests), CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		);

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

	$i = 1;
	foreach($data['steps'] as $stepid => $step) {
		$pairs_grouped = [
			'query_fields' => [],
			'post_fields' => [],
			'variables' => [],
			'headers' => []
		];

		if (array_key_exists('pairs', $step)) {
			foreach ($step['pairs'] as $field) {
				$pairs_grouped[$field['type']][] = $field;
			}
			$data['steps'][$stepid]['pairs'] = $pairs_grouped;
		}
		$data['steps'][$stepid]['no'] = $i++;
	}

	if ($db_httptest) {
		$parent_templates = getHttpTestParentTemplates($db_httptests)['templates'];

		$rw_templates = $data['host']['parentTemplates']
			? API::Template()->get([
				'output' => [],
				'templateids' => array_keys($parent_templates),
				'editable' => true,
				'preservekeys' => true
			])
			: [];

		foreach ($parent_templates as $templateid => &$template) {
			if (array_key_exists($templateid, $rw_templates)) {
				$template['permission'] = PERM_READ_WRITE;
			}
			else {
				$template['name'] = _('Inaccessible template');
				$template['permission'] = PERM_DENY;
			}
		}
		unset($template);

		$tags = getHttpTestTags([
			'templates' => $parent_templates,
			'hostid' => getRequest('hostid'),
			'tags' => hasRequest('form_refresh') ? $tags : $db_httptest['tags'],
			'show_inherited_tags' => $data['show_inherited_tags']
		]);
	}

	$data['tags'] = $tags;
	if (!$data['tags']) {
		$data['tags'][] = ['tag' => '', 'value' => ''];
	}
	else {
		CArrayHelper::sort($data['tags'], ['tag', 'value']);
	}

	// render view
	echo (new CView('configuration.httpconf.edit', $data))->getOutput();
}
else {
	$data = [
		'context' => getRequest('context')
	];

	$prefix = ($data['context'] === 'host') ? 'web.hosts.' : 'web.templates.';

	$sortField = getRequest('sort', CProfile::get($prefix.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get($prefix.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update($prefix.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update($prefix.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	if (hasRequest('filter_set')) {
		CProfile::update($prefix.'httpconf.filter_status', getRequest('filter_status', -1), PROFILE_TYPE_INT);
		CProfile::updateArray($prefix.'httpconf.filter_groupids', getRequest('filter_groupids', []), PROFILE_TYPE_ID);
		CProfile::updateArray($prefix.'httpconf.filter_hostids', getRequest('filter_hostids', []), PROFILE_TYPE_ID);
		CProfile::update($prefix.'httpconf.filter.evaltype', getRequest('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
			PROFILE_TYPE_INT
		);

		$filter_tags_fmt = [
			'tags' => [],
			'values' => [],
			'operators' => []
		];

		foreach (getRequest('filter_tags', []) as $filter_tag) {
			if ($filter_tag['tag'] === '' && $filter_tag['value'] === '') {
				continue;
			}

			$filter_tags_fmt['tags'][] = $filter_tag['tag'];
			$filter_tags_fmt['values'][] = $filter_tag['value'];
			$filter_tags_fmt['operators'][] = $filter_tag['operator'];
		}

		CProfile::updateArray($prefix.'httpconf.filter.tags.tag', $filter_tags_fmt['tags'], PROFILE_TYPE_STR);
		CProfile::updateArray($prefix.'httpconf.filter.tags.value', $filter_tags_fmt['values'], PROFILE_TYPE_STR);
		CProfile::updateArray($prefix.'httpconf.filter.tags.operator', $filter_tags_fmt['operators'], PROFILE_TYPE_INT);
	}
	elseif (hasRequest('filter_rst')) {
		CProfile::delete($prefix.'httpconf.filter_status');
		CProfile::deleteIdx($prefix.'httpconf.filter_groupids');

		$filter_hostids = getRequest('filter_hostids', CProfile::getArray($prefix.'httpconf.filter_hostids', []));
		if (count($filter_hostids) != 1) {
			CProfile::deleteIdx($prefix.'httpconf.filter_hostids');
		}
		CProfile::deleteIdx($prefix.'httpconf.filter.evaltype');
		CProfile::deleteIdx($prefix.'httpconf.filter.tags.tag');
		CProfile::deleteIdx($prefix.'httpconf.filter.tags.value');
		CProfile::deleteIdx($prefix.'httpconf.filter.tags.operator');
	}

	$filter = [
		'status' => CProfile::get($prefix.'httpconf.filter_status', -1),
		'groups' => CProfile::getArray($prefix.'httpconf.filter_groupids', null),
		'hosts' => CProfile::getArray($prefix.'httpconf.filter_hostids', null),
		'evaltype' => CProfile::get($prefix.'httpconf.filter.evaltype', TAG_EVAL_TYPE_AND_OR),
		'tags' => []
	];

	foreach (CProfile::getArray($prefix.'httpconf.filter.tags.tag', []) as $i => $tag) {
		$filter['tags'][] = [
			'tag' => $tag,
			'value' => CProfile::get($prefix.'httpconf.filter.tags.value', null, $i),
			'operator' => CProfile::get($prefix.'httpconf.filter.tags.operator', null, $i)
		];
	}

	// Get host groups.
	$filter['groups'] = $filter['groups']
		? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $filter['groups'],
			'editable' => true,
			'preservekeys' => true
		]), ['groupid' => 'id'])
		: [];

	$filter_groupids = $filter['groups'] ? array_keys($filter['groups']) : null;
	if ($filter_groupids) {
		$filter_groupids = getSubGroups($filter_groupids);
	}

	if ($data['context'] === 'host') {
		$filter['hosts'] = $filter['hosts']
			? CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $filter['hosts'],
				'editable' => true,
				'preservekeys' => true
			]), ['hostid' => 'id'])
			: [];
	}
	else {
		$filter['hosts'] = $filter['hosts']
			? CArrayHelper::renameObjectsKeys(API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => $filter['hosts'],
				'editable' => true,
				'preservekeys' => true
			]), ['templateid' => 'id'])
			: [];
	}

	$data += [
		'hostid' => (count($filter['hosts']) == 1)
			? reset($filter['hosts'])['id']
			: getRequest('hostid', 0),
		'filter' => $filter,
		'httpTests' => [],
		'httpTestsLastData' => [],
		'paging' => null,
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'profileIdx' => $prefix.'httpconf.filter',
		'active_tab' => CProfile::get($prefix.'httpconf.filter.active', 1)
	];

	$options = [
		'output' => ['httptestid', $sortField],
		'selectTags' => ['tag', 'value'],
		'hostids' => $filter['hosts'] ? array_keys($filter['hosts']) : null,
		'groupids' => $filter_groupids,
		'tags' => $data['filter']['tags'],
		'evaltype' => $data['filter']['evaltype'],
		'templated' => ($data['context'] === 'template'),
		'editable' => true,
		'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1,
		'preservekeys' => true
	];
	if ($data['filter']['status'] != -1) {
		$options['filter']['status'] = $data['filter']['status'];
	}

	$httpTests = API::HttpTest()->get($options);

	$dbHttpTests = DBselect(
		'SELECT ht.httptestid,ht.name,ht.delay,ht.status,ht.hostid,ht.templateid,h.name AS hostname,ht.retries,'.
			'ht.authentication,ht.http_proxy'.
			' FROM httptest ht'.
			' INNER JOIN hosts h ON h.hostid=ht.hostid'.
			' WHERE '.dbConditionInt('ht.httptestid', zbx_objectValues($httpTests, 'httptestid'))
	);
	$http_tests = [];
	while ($dbHttpTest = DBfetch($dbHttpTests)) {
		$http_tests[$dbHttpTest['httptestid']] = $dbHttpTest + [
			'tags' => $httpTests[$dbHttpTest['httptestid']]['tags']
		];
	}

	order_result($http_tests, $sortField, $sortOrder);

	// pager
	if (hasRequest('page')) {
		$page_num = getRequest('page');
	}
	elseif (isRequestMethod('get') && !hasRequest('cancel')) {
		$page_num = 1;
	}
	else {
		$page_num = CPagerHelper::loadPage($page['file']);
	}

	CPagerHelper::savePage($page['file'], $page_num);

	$data['paging'] = CPagerHelper::paginate($page_num, $http_tests, $sortOrder,
		(new CUrl('httpconf.php'))->setArgument('context', $data['context'])
	);

	// Get the error column data only for hosts.
	if ($data['context'] === 'host') {
		$httpTestsLastData = Manager::HttpTest()->getLastData(array_keys($http_tests));

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
			' WHERE '.dbConditionInt('hs.httptestid', array_column($http_tests, 'httptestid')).
			' GROUP BY hs.httptestid'
	);
	while ($dbHttpStep = DBfetch($dbHttpSteps)) {
		$http_tests[$dbHttpStep['httptestid']]['stepscnt'] = $dbHttpStep['stepscnt'];
	}

	order_result($http_tests, $sortField, $sortOrder);

	$data['parent_templates'] = getHttpTestParentTemplates($http_tests);
	$data['http_tests'] = $http_tests;
	$data['httpTestsLastData'] = $httpTestsLastData;
	$data['allowed_ui_conf_templates'] = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);

	$data['tags'] = makeTags($data['http_tests'], true, 'httptestid', ZBX_TAG_COUNT_DEFAULT);

	if (!$data['filter']['tags']) {
		$data['filter']['tags'] = [[
			'tag' => '',
			'value' => '',
			'operator' => TAG_OPERATOR_LIKE
		]];
	}

	// render view
	echo (new CView('configuration.httpconf.list', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';
