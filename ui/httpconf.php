<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/httptest.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of web monitoring');
$page['file'] = 'httpconf.php';
$page['scripts'] = ['class.tagfilteritem.js', 'items.js', 'multilineinput.js'];

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'new_httpstep'		=> [T_ZBX_STR, O_OPT, P_NO_TRIM,	null,	null],
	'group_httptestid'	=> [T_ZBX_INT, O_OPT, P_ONLY_ARRAY,	DB_ID,	null],
	// form
	'hostid'          => [T_ZBX_INT, O_OPT, P_SYS,	DB_ID.NOT_ZERO,	'isset({form}) || isset({add}) || isset({update})'],
	'httptestid'      => [T_ZBX_INT, O_NO,  P_SYS,	DB_ID,			'isset({form}) && {form} == "update"'],
	'name'            => [T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'isset({add}) || isset({update})', _('Name')],
	'delay'           => [T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})'],
	'retries'         => [T_ZBX_INT, O_OPT, null,	BETWEEN(1, 10),	'isset({add}) || isset({update})',
		_('Attempts')
	],
	'status'          => [T_ZBX_STR, O_OPT, null,	null,	null],
	'agent'           => [T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'agent_other'     => [T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({agent}) && {agent} == '.ZBX_AGENT_OTHER
	],
	'variables'			=> [T_ZBX_STR, O_OPT, P_NO_TRIM|P_ONLY_TD_ARRAY,	null,	null],
	'headers'			=> [T_ZBX_STR, O_OPT, P_NO_TRIM|P_ONLY_TD_ARRAY,	null,	null],
	'steps'           => [null,      O_OPT, P_NO_TRIM|P_ONLY_TD_ARRAY,	null,	'isset({add}) || isset({update})', _('Steps')],
	'authentication'  => [T_ZBX_INT, O_OPT, null,
								IN([ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS,
									ZBX_HTTP_AUTH_DIGEST
								]),
								'isset({add}) || isset({update})'
							],
	'http_user' =>			[T_ZBX_STR, O_OPT, null,  null,
								'(isset({add}) || isset({update})) && isset({authentication})'.
									' && ({authentication} == '.ZBX_HTTP_AUTH_BASIC.
										' || {authentication} == '.ZBX_HTTP_AUTH_NTLM.
										' || {authentication} == '.ZBX_HTTP_AUTH_KERBEROS.
										' || {authentication} == '.ZBX_HTTP_AUTH_DIGEST.
									')',
								_('User')
							],
	'http_password' =>		[T_ZBX_STR, O_OPT, P_NO_TRIM, null,
								'(isset({add}) || isset({update})) && isset({authentication})'.
									' && ({authentication} == '.ZBX_HTTP_AUTH_BASIC.
										' || {authentication} == '.ZBX_HTTP_AUTH_NTLM.
										' || {authentication} == '.ZBX_HTTP_AUTH_KERBEROS.
										' || {authentication} == '.ZBX_HTTP_AUTH_DIGEST.
									')',
								_('Password')
							],
	'http_proxy'		=> [T_ZBX_STR, O_OPT, null,	null,				'isset({add}) || isset({update})'],
	'hostname'			=> [T_ZBX_STR, O_OPT, null,	null,				null],
	'templated'			=> [T_ZBX_STR, O_OPT, null,	null,				null],
	'verify_host'		=> [T_ZBX_STR, O_OPT, null,	null,				null],
	'verify_peer'		=> [T_ZBX_STR, O_OPT, null,	null,				null],
	'ssl_cert_file'		=> [T_ZBX_STR, O_OPT, null, null,				'isset({add}) || isset({update})'],
	'ssl_key_file'		=> [T_ZBX_STR, O_OPT, null, null,				'isset({add}) || isset({update})'],
	'ssl_key_password'	=> [T_ZBX_STR, O_OPT, P_NO_TRIM, null,			'isset({add}) || isset({update})'],
	'context'			=> [T_ZBX_STR, O_MAND, P_SYS,	IN('"host", "template"'),	null],
	'tags'				=> [T_ZBX_STR, O_OPT, P_ONLY_TD_ARRAY,	null,				null],
	'show_inherited_tags' => [T_ZBX_INT, O_OPT, null,	IN([0,1]),					null],
	// filter
	'filter_set' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_status' =>		[T_ZBX_INT, O_OPT, null,
		IN([-1, HTTPTEST_STATUS_ACTIVE, HTTPTEST_STATUS_DISABLED]), null
	],
	'filter_groupids'	=> [T_ZBX_INT, O_OPT, P_ONLY_ARRAY,	DB_ID,		null],
	'filter_hostids'	=> [T_ZBX_INT, O_OPT, P_ONLY_ARRAY,	DB_ID,		null],
	'filter_evaltype'	=> [T_ZBX_INT, O_OPT, null, IN([TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]), null],
	'filter_tags'		=> [T_ZBX_STR, O_OPT, P_ONLY_TD_ARRAY,	null,	null],
	// actions
	'action'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,
								IN('"httptest.massclearhistory","httptest.massdelete","httptest.massdisable",'.
									'"httptest.massenable"'
								),
								null
							],
	'clone'				=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'del_history'		=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add'				=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel'			=> [T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form'				=> [T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh'		=> [T_ZBX_INT, O_OPT, P_SYS,	null,		null],
	'backurl'			=> [T_ZBX_STR, O_OPT, null,		null,		null],
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

// Validate backurl.
if (hasRequest('backurl') && !CHtmlUrlValidator::validateSameSite(getRequest('backurl'))) {
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

	show_messages($result, _('History and trends cleared'), _('Cannot clear history and trends'));
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

		$i = 1;
		foreach ($steps as &$step) {
			$step['no'] = $i++;

			foreach (['query_fields', 'variables', 'headers'] as $field) {
				$step[$field] = array_key_exists($field, $step) ? $step[$field] : [];
			}

			if ($step['post_type'] == ZBX_POSTTYPE_FORM) {
				$step['posts'] = array_key_exists('post_fields', $step) ? $step['post_fields'] : [];
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
			'agent' => getRequest('agent') == ZBX_AGENT_OTHER ? getRequest('agent_other') : getRequest('agent'),
			'variables' => [],
			'http_proxy' => $_REQUEST['http_proxy'],
			'steps' => $steps,
			'http_user' => $_REQUEST['authentication'] == ZBX_HTTP_AUTH_NONE ? '' : $_REQUEST['http_user'],
			'http_password' => $_REQUEST['authentication'] == ZBX_HTTP_AUTH_NONE ? '' : $_REQUEST['http_password'],
			'verify_peer' => getRequest('verify_peer', ZBX_HTTP_VERIFY_PEER_OFF),
			'verify_host' => getRequest('verify_host', ZBX_HTTP_VERIFY_HOST_OFF),
			'ssl_cert_file' => getRequest('ssl_cert_file'),
			'ssl_key_file' => getRequest('ssl_key_file'),
			'ssl_key_password' => getRequest('ssl_key_password'),
			'headers' => [],
			'tags' => $tags
		];

		foreach (['variables', 'headers'] as $pair_type) {
			foreach (getRequest($pair_type, []) as $pair) {
				$pair['name'] = array_key_exists('name', $pair) ? trim($pair['name']) : '';
				$pair['value'] = array_key_exists('value', $pair) ? trim($pair['value']) : '';

				if ($pair['name'] === '' && $pair['value'] === '') {
					continue;
				}

				$httpTest[$pair_type][] = $pair;
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
	$status = getRequest('action') === 'httptest.massenable' ? HTTPTEST_STATUS_ACTIVE : HTTPTEST_STATUS_DISABLED;
	$upd_httptests = [];

	foreach (getRequest('group_httptestid') as $httptestid) {
		$upd_httptests[] = [
			'httptestid' => $httptestid,
			'status' => $status
		];
	}

	$result = (bool) API::HttpTest()->update($upd_httptests);

	$updated = count($upd_httptests);

	if ($result) {
		uncheckTableRows(getRequest('hostid'));

		$message = $status == HTTPTEST_STATUS_ACTIVE
			? _n('Web scenario enabled', 'Web scenarios enabled', $updated)
			: _n('Web scenario disabled', 'Web scenarios disabled', $updated);

		CMessageHelper::setSuccessTitle($message);
	}
	else {
		$message = $status == HTTPTEST_STATUS_ACTIVE
			? _n('Cannot enable web scenario', 'Cannot enable web scenarios', $updated)
			: _n('Cannot disable web scenario', 'Cannot disable web scenarios', $updated);

		CMessageHelper::setErrorTitle($message);
	}

	if (hasRequest('backurl')) {
		$response = new CControllerResponseRedirect(getRequest('backurl'));
		$response->redirect();
	}
}
elseif (hasRequest('action') && getRequest('action') === 'httptest.massclearhistory'
		&& hasRequest('group_httptestid') && is_array(getRequest('group_httptestid'))
		&& getRequest('group_httptestid')) {
	$result = deleteHistoryByHttpTestIds(getRequest('group_httptestid'));

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}

	show_messages($result, _('History and trends cleared'), _('Cannot clear history and trends'));
}
elseif (hasRequest('action') && getRequest('action') === 'httptest.massdelete'
		&& hasRequest('group_httptestid') && is_array(getRequest('group_httptestid'))) {
	$result = API::HttpTest()->delete(getRequest('group_httptestid'));

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}

	$web_scenarios_count = count(getRequest('group_httptestid'));
	$messageSuccess = _n('Web scenario deleted', 'Web scenarios deleted', $web_scenarios_count);
	$messageFailed = _n('Cannot delete web scenario', 'Cannot delete web scenarios', $web_scenarios_count);

	show_messages($result, $messageSuccess, $messageFailed);
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
		'form_refresh' => getRequest('form_refresh', 0),
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
			'output' => ['httptestid', 'name', 'delay', 'status', 'agent', 'authentication', 'http_user',
				'http_password', 'templateid', 'http_proxy', 'retries', 'ssl_cert_file', 'ssl_key_file',
				'ssl_key_password', 'verify_peer', 'verify_host', 'headers', 'variables'
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
		$data['agent'] = ZBX_AGENT_OTHER;
		$data['agent_other'] = $db_httptest['agent'];

		foreach (userAgents() as $userAgents) {
			if (array_key_exists($db_httptest['agent'], $userAgents)) {
				$data['agent'] = $db_httptest['agent'];
				$data['agent_other'] = '';
				break;
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

		$data['variables'] = $db_httptest['variables'];
		$data['headers'] = $db_httptest['headers'];

		CArrayHelper::sort($db_httptest['steps'], ['no']);
		$data['steps'] = array_values($db_httptest['steps']);

		foreach ($data['steps'] as &$step) {
			if ($step['post_type'] == ZBX_POSTTYPE_FORM) {
				$step['post_fields'] = $step['posts'];
				$step['posts'] = '';
			}

			CArrayHelper::sort($step['variables'], ['name']);
			$step['variables'] = array_values($step['variables']);
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

		$data['authentication'] = getRequest('authentication', ZBX_HTTP_AUTH_NONE);
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
		$data['variables'] = getRequest('variables', []);
		$data['headers'] = array_values(getRequest('headers', []));
		$data['steps'] = array_values(getRequest('steps', []));
	}

	if ($db_httptest) {
		$data['templates'] = makeHttpTestTemplatesHtml($db_httptest['httptestid'],
			getHttpTestParentTemplates($db_httptests), CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		);

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

	if ($data['variables']) {
		CArrayHelper::sort($data['variables'], ['name']);
		$data['variables'] = array_values($data['variables']);
	}
	else {
		$data['variables'] = [['name' => '', 'value' => '']];
	}

	if (!$data['headers']) {
		$data['headers'] = [['name' => '', 'value' => '']];
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
		'groups' => [],
		'hosts' => [],
		'evaltype' => CProfile::get($prefix.'httpconf.filter.evaltype', TAG_EVAL_TYPE_AND_OR),
		'tags' => []
	];

	$filter_groupids = CProfile::getArray($prefix.'httpconf.filter_groupids', []);
	$filter_hostids = CProfile::getArray($prefix.'httpconf.filter_hostids', []);

	foreach (CProfile::getArray($prefix.'httpconf.filter.tags.tag', []) as $i => $tag) {
		$filter['tags'][] = [
			'tag' => $tag,
			'value' => CProfile::get($prefix.'httpconf.filter.tags.value', null, $i),
			'operator' => CProfile::get($prefix.'httpconf.filter.tags.operator', null, $i)
		];
	}

	// Get host groups.
	$filter_groupids = getSubGroups($filter_groupids, $filter['groups'], $data['context']);

	if ($data['context'] === 'host') {
		$filter['hosts'] = $filter_hostids
			? CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $filter_hostids,
				'editable' => true,
				'preservekeys' => true
			]), ['hostid' => 'id'])
			: [];
	}
	else {
		$filter['hosts'] = $filter_hostids
			? CArrayHelper::renameObjectsKeys(API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => $filter_hostids,
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
		'groupids' => $filter_groupids ? $filter_groupids : null,
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
