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


require_once __DIR__.'/include/config.inc.php';
require_once __DIR__.'/include/hosts.inc.php';
require_once __DIR__.'/include/items.inc.php';
require_once __DIR__.'/include/forms.inc.php';

$page['title'] = _('Configuration of discovery prototypes');
$page['file'] = 'host_discovery_prototypes.php';

require_once __DIR__.'/include/page_header.php';

$paramsFieldName = getParamFieldNameByType(getRequest('type', 0));

// supported eval types
$evalTypes = [
	CONDITION_EVAL_TYPE_AND_OR,
	CONDITION_EVAL_TYPE_AND,
	CONDITION_EVAL_TYPE_OR,
	CONDITION_EVAL_TYPE_EXPRESSION
];

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hostid' =>					[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && !isset({itemid})'],
	'itemid' =>					[T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'(isset({form}) && ({form} == "update")) || (isset({action}) && {action} == "discoveryprototype.updatediscover")'],
	'interfaceid' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null, _('Interface')],
	'name' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({add}) || isset({update})', _('Name')],
	'description' =>			[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'key' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Key')],
	'master_itemid' =>			[T_ZBX_STR, O_OPT, null,	null,
									'(isset({add}) || isset({update})) && isset({type})'.
										' && {type} == '.ITEM_TYPE_DEPENDENT,
									_('Master item')
								],
	'delay' =>					[T_ZBX_TU, O_OPT, P_ALLOW_USER_MACRO, null,
									'(isset({add}) || isset({update})) && isset({type})'.
										' && {type} != '.ITEM_TYPE_TRAPPER.' && {type} != '.ITEM_TYPE_SNMPTRAP.
										' && {type} != '.ITEM_TYPE_DEPENDENT.
										' && {type} != '.ITEM_TYPE_NESTED.
										' && !({type} == '.ITEM_TYPE_ZABBIX_ACTIVE.
											' && isset({key}) && strncmp({key}, "mqtt.get", 8) === 0)',
									_('Update interval')
								],
	'delay_flex' =>				[T_ZBX_STR, O_OPT, P_ONLY_TD_ARRAY,	null,			null],
	'status' =>					[T_ZBX_INT, O_OPT, null,	IN(ITEM_STATUS_ACTIVE), null],
	'discover' =>				[T_ZBX_INT, O_OPT, null,	IN([ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]), null],
	'type' =>					[T_ZBX_INT, O_OPT, null,
									IN([-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL,
										ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR,
										ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX,
										ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT,
										ITEM_TYPE_BROWSER, ITEM_TYPE_NESTED
									]),
									'isset({add}) || isset({update})'
								],
	'authtype' =>				[T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHTYPE_PASSWORD.','.ITEM_AUTHTYPE_PUBLICKEY),
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SSH],
	'username' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
									'(isset({add}) || isset({update})) && isset({type})'.
										' && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type'),
									_('User name')
								],
	'password' =>				[T_ZBX_STR, O_OPT, null,	null,
									'(isset({add}) || isset({update})) && isset({type})'.
										' && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type')
								],
	'publickey' =>				[T_ZBX_STR, O_OPT, null,	null,
									'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SSH.
										' && {authtype} == '.ITEM_AUTHTYPE_PUBLICKEY
								],
	'privatekey' =>				[T_ZBX_STR, O_OPT, null,	null,
									'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SSH.
										' && {authtype} == '.ITEM_AUTHTYPE_PUBLICKEY
								],
	$paramsFieldName =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({add}) || isset({update}))'.
									' && isset({type}) && '.IN([
											ITEM_TYPE_SSH, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED,
											ITEM_TYPE_SCRIPT
										],
										'type'
									),
									getParamFieldLabelByType(getRequest('type', 0))
								],
	'browser_script' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({add}) || isset({update})) '.
									' && isset({type}) && {type} == '.ITEM_TYPE_BROWSER,
									_('Script')
								],
	'snmp_oid' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
									'(isset({add}) || isset({update})) && isset({type})'.
										' && {type} == '.ITEM_TYPE_SNMP,
									_('SNMP OID')
								],
	'ipmi_sensor' =>			[T_ZBX_STR, O_OPT, P_NO_TRIM, null,
									'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_IPMI,
									_('IPMI sensor')
								],
	'trapper_hosts' =>			[T_ZBX_STR, O_OPT, null,	null,
									'(isset({add}) || isset({update})) && isset({type}) && {type} == 2'
	],
	'lifetime_type' =>			[T_ZBX_INT, O_OPT, null,
									IN([ZBX_LLD_DELETE_AFTER.','.ZBX_LLD_DELETE_NEVER.','.ZBX_LLD_DELETE_IMMEDIATELY]),
									'(isset({add}) || isset({update}))'
								],
	'lifetime' =>				[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'enabled_lifetime_type' =>	[T_ZBX_INT, O_OPT, null,
									IN([ZBX_LLD_DISABLE_AFTER.','.ZBX_LLD_DISABLE_NEVER.','.ZBX_LLD_DISABLE_IMMEDIATELY]),
									'(isset({add}) || isset({update}))'
								],
	'enabled_lifetime' =>		[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'evaltype' =>				[T_ZBX_INT, O_OPT, null, 	IN($evalTypes), 'isset({add}) || isset({update})'],
	'formula' =>				[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'conditions' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ONLY_TD_ARRAY,	null,	null],
	'lld_macro_paths' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ONLY_TD_ARRAY,	null,	null],
	'jmx_endpoint' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_JMX
	],
	'custom_timeout' =>			[T_ZBX_INT, O_OPT, null,
									IN([ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED, ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED]),
									null
								],
	'output_format' =>			[T_ZBX_INT, O_OPT, null,
									IN([HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON]),
									null
								],
	'timeout' =>				[T_ZBX_TU, O_OPT, P_ALLOW_USER_MACRO,	null,
									'(isset({add}) || isset({update})) && isset({custom_timeout})'.
									' && {custom_timeout} == '.ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED.
									' && isset({type}) && '.IN([ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE,
										ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR,
										ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP,
										ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER
									], 'type'),
									_('Timeout')
								],
	'url' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
									'(isset({add}) || isset({update})) && isset({type})'.
										' && {type} == '.ITEM_TYPE_HTTPAGENT,
									_('URL')
								],
	'query_fields' =>			[T_ZBX_STR, O_OPT, P_ONLY_TD_ARRAY,	null,	null],
	'parameters' =>				[T_ZBX_STR, O_OPT, P_ONLY_TD_ARRAY,	null,	null],
	'posts' =>					[T_ZBX_STR, O_OPT, null,	null,			null],
	'status_codes' =>			[T_ZBX_STR, O_OPT, null,	null,			null],
	'follow_redirects' =>		[T_ZBX_INT, O_OPT, null,
									IN([HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON]),
									null
								],
	'post_type' =>				[T_ZBX_INT, O_OPT, null,
									IN([ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
									null
								],
	'http_proxy' =>				[T_ZBX_STR, O_OPT, null,			null,	null],
	'headers' => 				[T_ZBX_STR, O_OPT, P_ONLY_TD_ARRAY,	null,	null],
	'retrieve_mode' =>			[T_ZBX_INT, O_OPT, null,
									IN([HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS,
										HTTPTEST_STEP_RETRIEVE_MODE_BOTH
									]),
									null
								],
	'request_method' =>			[T_ZBX_INT, O_OPT, null,
									IN([HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT,
										HTTPCHECK_REQUEST_HEAD
									]),
									null
								],
	'allow_traps' =>			[T_ZBX_INT, O_OPT, null,	IN([HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON]),
									null
								],
	'ssl_cert_file' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'ssl_key_file' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'ssl_key_password' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'verify_peer' =>			[T_ZBX_INT, O_OPT, null,
									IN([ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON]),
									null
								],
	'verify_host' =>			[T_ZBX_INT, O_OPT, null,
									IN([ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON]),
									null
								],
	'http_authtype' =>			[T_ZBX_INT, O_OPT, null,
									IN([ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM,
										ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST
									]),
									null
								],
	'http_username' =>			[T_ZBX_STR, O_OPT, null,	null,
									'(isset({add}) || isset({update})) && isset({http_authtype})'.
										' && ({http_authtype} == '.ZBX_HTTP_AUTH_BASIC.
											' || {http_authtype} == '.ZBX_HTTP_AUTH_NTLM.
											' || {http_authtype} == '.ZBX_HTTP_AUTH_KERBEROS.
											' || {http_authtype} == '.ZBX_HTTP_AUTH_DIGEST.
										')',
									_('Username')
								],
	'http_password' =>			[T_ZBX_STR, O_OPT, null,	null,
									'(isset({add}) || isset({update})) && isset({http_authtype})'.
										' && ({http_authtype} == '.ZBX_HTTP_AUTH_BASIC.
											' || {http_authtype} == '.ZBX_HTTP_AUTH_NTLM.
											' || {http_authtype} == '.ZBX_HTTP_AUTH_KERBEROS.
											' || {http_authtype} == '.ZBX_HTTP_AUTH_DIGEST.
										')',
									_('Password')
								],
	'preprocessing' =>			[null,      O_OPT, P_NO_TRIM|P_ONLY_TD_ARRAY,	null,	null],
	'overrides' =>				[null,      O_OPT, P_NO_TRIM|P_ONLY_TD_ARRAY,	null,	null],
	'context' =>				[T_ZBX_STR, O_MAND, P_SYS,		IN('"host", "template"'),	null],
	// actions
	'parent_discoveryid' => 	[T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, null],
	'action' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
									IN('"discoveryprototype.massdelete","discoveryprototype.massdisable",'.
										'"discoveryprototype.massenable","discoveryprototype.updatediscover"'
									),
									null
								],
	'g_hostdruleid' =>			[T_ZBX_INT, O_OPT, P_ONLY_ARRAY,	DB_ID,		null],
	'add' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>			[T_ZBX_INT, O_OPT, P_SYS,	null,		null],
	'backurl' =>						[T_ZBX_STR, O_OPT, null,	null,		null],
	// sort and sortorder
	'sort' =>							[T_ZBX_STR, O_OPT, P_SYS, IN('"name","key_","delay","type","status","discover"'),	null],
	'sortorder' =>						[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];

check_fields($fields);

$_REQUEST['params'] = getRequest($paramsFieldName, '');
unset($_REQUEST[$paramsFieldName]);

/*
 * Permissions
 */
$itemid = getRequest('itemid');

if (getRequest('parent_discoveryid')) {
	$options = [
		'output' => ['itemid', 'name', 'flags'],
		'selectDiscoveryData' => ['parent_itemid'],
		'itemids' => getRequest('parent_discoveryid'),
		'selectHosts' => ['hostid', 'name', 'monitored_by', 'proxyid', 'assigned_proxyid', 'status', 'flags'],
		'editable' => true
	];

	$parent_discovery = API::DiscoveryRule()->get($options) ?: API::DiscoveryRulePrototype()->get($options);

	if (!$parent_discovery) {
		access_deny();
	}

	$parent_discovery = reset($parent_discovery);

	if ($parent_discovery['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
		if (hasRequest('add') || hasRequest('update')) {
			access_deny();
		}
	}

	$hosts = $parent_discovery['hosts'];

	if ($itemid != 0) {
		$discovery_prototype = API::DiscoveryRulePrototype()->get([
			'output' => API_OUTPUT_EXTEND,
			'itemids' => $itemid,
			'discoveryids' => $parent_discovery['itemid']
		]);

		if (!$discovery_prototype) {
			access_deny();
		}
	}
}
else {
	access_deny();
}

// Validate backurl.
if (hasRequest('backurl') && !CHtmlUrlValidator::validateSameSite(getRequest('backurl'))) {
	access_deny();
}

$prefix = (getRequest('context') === 'host') ? 'web.hosts.' : 'web.templates.';
$sort_field = getRequest('sort', CProfile::get($prefix.$page['file'].'.sort', 'name'));
$sort_order = getRequest('sortorder', CProfile::get($prefix.$page['file'].'.sortorder', ZBX_SORT_UP));

CProfile::update($prefix.$page['file'].'.sort', $sort_field, PROFILE_TYPE_STR);
CProfile::update($prefix.$page['file'].'.sortorder', $sort_order, PROFILE_TYPE_STR);

/*
 * Actions
 */
if (hasRequest('delete') && hasRequest('itemid')) {
	$result = API::DiscoveryRulePrototype()->delete([$itemid]);

	if ($result) {
		uncheckTableRows($parent_discovery['itemid']);
	}

	show_messages($result, _('Discovery prototype deleted'), _('Cannot delete discovery prototype'));

	unset($_REQUEST['itemid'], $_REQUEST['form']);
}
elseif (hasRequest('add') || hasRequest('update')) {
	try {
		$type = (int) getRequest('type', DB::getDefault('items', 'type'));
		$key = getRequest('key', DB::getDefault('items', 'key_'));

		if (isItemExampleKey($type, $key)) {
			throw new Exception();
		}

		$overrides = getRequest('overrides', []);
		$db_item = null;

		if (hasRequest('update')) {
			$options = $overrides ? ['selectOverrides' => ['step']] : [];

			$db_item = API::DiscoveryRulePrototype()->get([
				'output' => ['itemid', 'templateid'],
				'itemids' => $itemid
			] + $options)[0];
		}

		$delay_flex = getRequest('delay_flex', []);

		if (!isValidCustomIntervals($delay_flex, true)) {
			throw new Exception();
		}

		$request_method = getRequest('request_method', DB::getDefault('items', 'request_method'));
		$retrieve_mode_default = $request_method == HTTPCHECK_REQUEST_HEAD
			? HTTPTEST_STEP_RETRIEVE_MODE_HEADERS
			: DB::getDefault('items', 'retrieve_mode');

		$input = [
			'name' => getRequest('name', DB::getDefault('items', 'name')),
			'type' => $type,
			'key_' => $key,
			'description' => getRequest('description', DB::getDefault('items', 'description')),
			'status' => getRequest('status', ITEM_STATUS_DISABLED),
			'discover' => getRequest('discover', ITEM_NO_DISCOVER),
			'preprocessing' => normalizeItemPreprocessingSteps(getRequest('preprocessing', [])),
			'lld_macro_paths' => prepareLldMacroPaths(getRequest('lld_macro_paths', [])),
			'filter' => prepareLldFilter([
				'evaltype' => getRequest('evaltype', DB::getDefault('items', 'evaltype')),
				'formula' => getRequest('formula', DB::getDefault('items', 'formula')),
				'conditions' => getRequest('conditions', [])
			]),
			'overrides' => prepareLldOverrides($overrides),
			'lifetime_type' => getRequest('lifetime_type', DB::getDefault('items', 'lifetime_type')),
			'lifetime' => getRequest('lifetime', DB::getDefault('items', 'lifetime')),
			'enabled_lifetime_type' => getRequest('enabled_lifetime_type',
				DB::getDefault('items', 'enabled_lifetime_type')
			),
			'enabled_lifetime' => getRequest('enabled_lifetime', DB::getDefault('items', 'enabled_lifetime')),

			// Type fields.
			// The fields used for multiple item types.
			'interfaceid' => getRequest('interfaceid', 0),
			'authtype' => $type == ITEM_TYPE_HTTPAGENT
				? getRequest('http_authtype', DB::getDefault('items', 'authtype'))
				: getRequest('authtype', DB::getDefault('items', 'authtype')),
			'username' => $type == ITEM_TYPE_HTTPAGENT
				? getRequest('http_username', DB::getDefault('items', 'username'))
				: getRequest('username', DB::getDefault('items', 'username')),
			'password' => $type == ITEM_TYPE_HTTPAGENT
				? getRequest('http_password', DB::getDefault('items', 'password'))
				: getRequest('password', DB::getDefault('items', 'password')),
			'params' => getRequest('params', DB::getDefault('items', 'params')),
			'delay' => getDelayWithCustomIntervals(getRequest('delay', DB::getDefault('items', 'delay')), $delay_flex),
			'timeout' => getRequest('custom_timeout') == ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED
				? getRequest('timeout', DB::getDefault('items', 'timeout'))
				: DB::getDefault('items', 'timeout'),
			'trapper_hosts' => getRequest('trapper_hosts', DB::getDefault('items', 'trapper_hosts')),

			// Dependent item type specific fields.
			'master_itemid' => getRequest('master_itemid', 0),

			// HTTP Agent item type specific fields.
			'url' => getRequest('url', DB::getDefault('items', 'url')),
			'query_fields' => prepareItemQueryFields(getRequest('query_fields', [])),
			'request_method' => $request_method,
			'post_type' => getRequest('post_type', DB::getDefault('items', 'post_type')),
			'posts' => getRequest('posts', DB::getDefault('items', 'posts')),
			'headers' => prepareItemHeaders(getRequest('headers', [])),
			'status_codes' => getRequest('status_codes', DB::getDefault('items', 'status_codes')),
			'follow_redirects' => getRequest('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF),
			'retrieve_mode' => getRequest('retrieve_mode', $retrieve_mode_default),
			'output_format' => getRequest('output_format', DB::getDefault('items', 'output_format')),
			'http_proxy' => getRequest('http_proxy', DB::getDefault('items', 'http_proxy')),
			'verify_peer' => getRequest('verify_peer', DB::getDefault('items', 'verify_peer')),
			'verify_host' => getRequest('verify_host', DB::getDefault('items', 'verify_host')),
			'ssl_cert_file' => getRequest('ssl_cert_file', DB::getDefault('items', 'ssl_cert_file')),
			'ssl_key_file' => getRequest('ssl_key_file', DB::getDefault('items', 'ssl_key_file')),
			'ssl_key_password' => getRequest('ssl_key_password', DB::getDefault('items', 'ssl_key_password')),
			'allow_traps' => getRequest('allow_traps', DB::getDefault('items', 'allow_traps')),

			// IPMI item type specific fields.
			'ipmi_sensor' => getRequest('ipmi_sensor', DB::getDefault('items', 'ipmi_sensor')),

			// JMX item type specific fields.
			'jmx_endpoint' => getRequest('jmx_endpoint', DB::getDefault('items', 'jmx_endpoint')),

			// Script item type specific fields.
			'parameters' => prepareItemParameters(getRequest('parameters', [])),

			// SNMP item type specific fields.
			'snmp_oid' => getRequest('snmp_oid', DB::getDefault('items', 'snmp_oid')),

			// SSH item type specific fields.
			'publickey' => getRequest('publickey', DB::getDefault('items', 'publickey')),
			'privatekey' => getRequest('privatekey', DB::getDefault('items', 'privatekey'))
		];

		if ($input['type'] == ITEM_TYPE_BROWSER) {
			$input['params'] = getRequest('browser_script', '');
		}

		if ($input['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
			foreach ($input['filter']['conditions'] as &$condition) {
				unset($condition['formulaid']);
			}
			unset($condition);
		}

		foreach ($input['overrides'] as &$override) {
			if ($override['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
				foreach ($override['filter']['conditions'] as &$condition) {
					unset($condition['formulaid']);
				}
				unset($condition);
			}
		}
		unset($override);

		$result = true;

		if ($input['lifetime_type'] == ZBX_LLD_DELETE_IMMEDIATELY) {
			$input['enabled_lifetime_type'] = DB::getDefault('items', 'enabled_lifetime_type');
			$input['enabled_lifetime'] = DB::getDefault('items', 'enabled_lifetime');
		}

		$converted_lifetime = timeUnitToSeconds($input['lifetime']);
		$converted_enabled_lifetime = timeUnitToSeconds($input['enabled_lifetime']);
		$lifetime_valid = $input['lifetime_type'] == ZBX_LLD_DELETE_AFTER && $input['lifetime'] !== ''
			&& $input['lifetime'][0] !== '{';
		$enabled_lifetime_valid = $input['enabled_lifetime_type'] == ZBX_LLD_DISABLE_AFTER
			&& $input['enabled_lifetime'] !== '' && $input['enabled_lifetime'][0] !== '{';

		if ($lifetime_valid && $enabled_lifetime_valid
				&& $converted_enabled_lifetime !== null && $converted_lifetime !== null
				&& $converted_enabled_lifetime >= $converted_lifetime) {
			$result = false;

			error(_s('Incorrect value for field "%1$s": %2$s.', 'Disable lost resources',
					_s('cannot be greater than or equal to the value of field "%1$s"', 'Delete lost resources')
				)
			);
		}

		if (!hasErrorMessages()) {
			if (hasRequest('add')) {
				$item = [
					'ruleid' => getRequest('parent_discoveryid'),
					'hostid' => $hosts[0]['hostid']
				];

				$item += getSanitizedItemFields($input + [
						'templateid' => 0,
						'flags' => ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE,
						'hosts' => $hosts
					]);

				$response = API::DiscoveryRulePrototype()->create($item);

				if ($response === false) {
					throw new Exception();
				}
			}

			if (hasRequest('update')) {
				$item = getSanitizedItemFields($input + $db_item + [
					'flags' => ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE,
					'hosts' => $hosts
				]);

				$response = API::DiscoveryRulePrototype()->update(['itemid' => $itemid] + $item);

				if ($response === false) {
					throw new Exception();
				}
			}
		}
	}
	catch (Exception $e) {
		$result = false;
	}

	if (hasRequest('add')) {
		if ($result) {
			CMessageHelper::setSuccessTitle(_('Discovery prototype created'));
		}
		else {
			CMessageHelper::setErrorTitle(_('Cannot add discovery prototype'));
		}
	}
	else {
		if ($result) {
			CMessageHelper::setSuccessTitle(_('Discovery prototype updated'));
		}
		else {
			CMessageHelper::setErrorTitle(_('Cannot update discovery prototype'));
		}
	}

	if ($result) {
		unset($_REQUEST['itemid'], $_REQUEST['form']);
		uncheckTableRows($parent_discovery['itemid']);

		if (hasRequest('backurl')) {
			$response = new CControllerResponseRedirect(new CUrl(getRequest('backurl')));
			$response->redirect();
		}
	}
}
elseif ($itemid != 0 && getRequest('action', '') === 'discoveryprototype.updatediscover') {
	$result = API::DiscoveryRulePrototype()->update([
		'itemid' => $itemid,
		'discover' => getRequest('discover', DB::getDefault('items', 'discover'))
	]);

	if ($result) {
		CMessageHelper::setSuccessTitle(_('Discovery prototype updated'));
	}
	else {
		CMessageHelper::setErrorTitle(_('Cannot update discovery prototype'));
	}

	if (hasRequest('backurl')) {
		$response = new CControllerResponseRedirect(new CUrl(getRequest('backurl')));
		$response->redirect();
	}
}
elseif (hasRequest('action')
		&& str_in_array(getRequest('action'), ['discoveryprototype.massenable', 'discoveryprototype.massdisable'])
		&& hasRequest('g_hostdruleid')) {
	$itemids = getRequest('g_hostdruleid');
	$status = (getRequest('action') === 'discoveryprototype.massenable') ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;

	$discovery_prototypes = [];

	foreach ($itemids as $itemid) {
		$discovery_prototypes[] = ['itemid' => $itemid, 'status' => $status];
	}

	$result = (bool) API::DiscoveryRulePrototype()->update($discovery_prototypes);

	$updated = count($itemids);

	if ($result) {
		uncheckTableRows();

		$message = $status == ITEM_STATUS_ACTIVE
			? _n('Discovery prototype enabled', 'Discovery prototypes enabled', $updated)
			: _n('Discovery prototype disabled', 'Discovery prototypes disabled', $updated);

		CMessageHelper::setSuccessTitle($message);
	}
	else {
		$message = $status == ITEM_STATUS_ACTIVE
			? _n('Cannot enable discovery prototype', 'Cannot enable discovery prototypes', $updated)
			: _n('Cannot disable discovery prototype', 'Cannot disable discovery prototypes', $updated);

		CMessageHelper::setErrorTitle($message);
	}

	if (hasRequest('backurl')) {
		$response = new CControllerResponseRedirect(new CUrl(getRequest('backurl')));
		$response->redirect();
	}
}
elseif (hasRequest('action') && getRequest('action') === 'discoveryprototype.massdelete' && hasRequest('g_hostdruleid')) {
	$result = API::DiscoveryRulePrototype()->delete(getRequest('g_hostdruleid'));

	if ($result) {
		uncheckTableRows();
	}

	$host_drules_count = count(getRequest('g_hostdruleid'));
	$messageSuccess = _n('Discovery prototype deleted', 'Discovery prototypes deleted', $host_drules_count);
	$messageFailed = _n('Cannot delete discovery prototype', 'Cannot delete discovery prototypes', $host_drules_count);

	show_messages($result, $messageSuccess, $messageFailed);
}

if (hasRequest('action') && hasRequest('g_hostdruleid') && !$result) {
	$hostdrules = API::DiscoveryRulePrototype()->get([
		'output' => [],
		'itemids' => getRequest('g_hostdruleid'),
		'editable' => true
	]);

	uncheckTableRows($parent_discovery['itemid'], zbx_objectValues($hostdrules, 'itemid'));
}

/*
 * Display
 */
if (hasRequest('form')) {
	$master_itemid = getRequest('master_itemid', 0);

	if (hasRequest('itemid') && !hasRequest('clone')) {
		$items = API::DiscoveryRulePrototype()->get([
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => ['hostid', 'name', 'monitored_by', 'proxyid', 'assigned_proxyid', 'status', 'flags'],
			'selectFilter' => ['formula', 'evaltype', 'conditions'],
			'selectLLDMacroPaths' => ['lld_macro', 'path'],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectOverrides' => ['name', 'step', 'stop', 'filter', 'operations'],
			'selectDiscoveryRule' => ['itemid', 'name'],
			'selectDiscoveryRulePrototype' => ['itemid', 'name'],
			'selectDiscoveryData' => ['parent_itemid'],
			'itemids' => $itemid
		]);

		$item = $items[0];
		$host = $item['hosts'][0];

		unset($item['hosts']);

		if (!hasRequest('form_refresh')) {
			$master_itemid = $item['master_itemid'];
		}
	}
	else {
		$item = [];
		$host = $hosts[0];
	}

	if (getRequest('type', $item ? $item['type'] : null) == ITEM_TYPE_DEPENDENT && $master_itemid != 0) {
		$db_master_items = API::Item()->get([
			'output' => ['itemid', 'name'],
			'itemids' => $master_itemid,
			'webitems' => true
		]);

		if (!$db_master_items) {
			$db_master_items = API::ItemPrototype()->get([
				'output' => ['itemid', 'name'],
				'itemids' => $master_itemid,
				'webitems' => true
			]);
		}

		if ($db_master_items) {
			$item['master_item'] = $db_master_items[0];
		}
	}

	if ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
		$host['proxyid'] = $host['assigned_proxyid'];
	}
	unset($host['monitored_by'], $host['assigned_proxyid']);

	$data = getItemFormData($item);

	$data['evaltype'] = getRequest('evaltype', CONDITION_EVAL_TYPE_AND_OR);
	$data['formula'] = getRequest('formula');
	$data['conditions'] = getRequest('conditions', []);
	$data['lld_macro_paths'] = getRequest('lld_macro_paths', []);
	$data['overrides'] = getRequest('overrides', []);
	$data['host'] = $host;
	$data['preprocessing_test_type'] = CControllerPopupItemTestEdit::ZBX_TEST_TYPE_LLD_PROTOTYPE;
	$data['preprocessing_types'] = CDiscoveryRule::SUPPORTED_PREPROCESSING_TYPES;
	$data['display_interfaces'] = in_array($host['status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);
	$data['backurl'] = getRequest('backurl');
	$data['is_discovered_prototype'] = false;

	if ($data['backurl'] && !CHtmlUrlValidator::validateSameSite($data['backurl'])) {
		throw new CAccessDeniedException();
	}

	$default_timeout = DB::getDefault('items', 'timeout');
	$data['custom_timeout'] = (int) getRequest('custom_timeout', $data['timeout'] !== $default_timeout);
	$data['inherited_timeouts'] = getInheritedTimeouts($host['proxyid']);
	$data['can_edit_source_timeouts'] = $data['inherited_timeouts']['source'] === 'proxy'
		? CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)
		: CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);

	$data['inherited_timeout'] = array_key_exists($data['type'], $data['inherited_timeouts']['timeouts'])
		? $data['inherited_timeouts']['timeouts'][$data['type']]
		: $default_timeout;

	if (!$data['custom_timeout']) {
		$data['timeout'] = $data['inherited_timeout'];
	}

	if (!hasRequest('form_refresh')) {
		$i = 0;
		foreach ($data['preprocessing'] as &$step) {
			if ($step['type'] == ZBX_PREPROC_SCRIPT) {
				$step['params'] = [$step['params'], ''];
			}
			else {
				$step['params'] = explode("\n", $step['params']);
			}
			$step['sortorder'] = $i++;
		}
		unset($step);
	}

	// update form
	if (hasRequest('itemid') && !getRequest('form_refresh')) {
		$lifetime_type = $item['lifetime_type'];
		$lifetime = $item['lifetime'];
		$enabled_lifetime_type = $item['enabled_lifetime_type'];
		$enabled_lifetime = $item['enabled_lifetime'];

		$converted_lifetime = timeUnitToSeconds($lifetime);
		$converted_enabled_lifetime = timeUnitToSeconds($enabled_lifetime);

		if ($lifetime_type == ZBX_LLD_DELETE_AFTER && $converted_lifetime === 0) {
			$lifetime_type = ZBX_LLD_DELETE_IMMEDIATELY;
		}

		if ($enabled_lifetime_type == ZBX_LLD_DISABLE_AFTER && $converted_enabled_lifetime === 0) {
			$enabled_lifetime_type = ZBX_LLD_DISABLE_IMMEDIATELY;
		}

		if ($lifetime_type == ZBX_LLD_DELETE_IMMEDIATELY) {
			$enabled_lifetime_type = DB::getDefault('items', 'enabled_lifetime_type');
		}

		if ($enabled_lifetime_type == ZBX_LLD_DISABLE_IMMEDIATELY) {
			$enabled_lifetime_type = DB::getDefault('items', 'enabled_lifetime_type');
		}

		$data['lifetime_type'] = $lifetime_type;
		$data['lifetime'] = $lifetime_type == ZBX_LLD_DELETE_AFTER
			? $lifetime
			: DB::getDefault('items', 'lifetime');
		$data['enabled_lifetime_type'] = $enabled_lifetime_type;
		$data['enabled_lifetime'] = $enabled_lifetime_type == ZBX_LLD_DISABLE_AFTER
			? $enabled_lifetime
			: ZBX_LLD_RULE_ENABLED_LIFETIME;
		$data['evaltype'] = $item['filter']['evaltype'];
		$data['formula'] = $item['filter']['formula'];
		$data['conditions'] = $item['filter']['conditions'];
		$data['lld_macro_paths'] = $item['lld_macro_paths'];
		$data['overrides'] = $item['overrides'];
		// Sort overrides to be listed in step order.
		CArrayHelper::sort($data['overrides'], ['step']);

		$data['is_discovered_prototype'] = $item['flags'] & ZBX_FLAG_DISCOVERY_CREATED;

		if ($data['is_discovered_prototype']) {
			$data['parent_lld'] = $item['discoveryRule'] ?: $item['discoveryRulePrototype'];
			$data['discoveryData'] = $item['discoveryData'];

			$db_parent = API::DiscoveryRulePrototype()->get([
				'itemids' => $item['discoveryData']['parent_itemid'],
				'selectDiscoveryRule' => ['itemid'],
				'selectDiscoveryRulePrototype' => ['itemid'],
				'nopermissions' => true
			]);
			$db_parent = reset($db_parent);

			$parent_lld = $db_parent['discoveryRule'] ?: $db_parent['discoveryRulePrototype'];
			$data['discoveryData']['lldruleid'] = $parent_lld['itemid'];
		}
	}
	// clone form
	elseif (hasRequest('clone')) {
		unset($data['itemid']);
		$data['form'] = 'clone';
	}

	if (!$data['conditions']) {
		$data['conditions'] = [[
			'macro' => '',
			'operator' => CONDITION_OPERATOR_REGEXP,
			'value' => '',
			'formulaid' => num2letter(0)
		]];
	}

	if ($data['type'] != ITEM_TYPE_JMX) {
		$data['jmx_endpoint'] = ZBX_DEFAULT_JMX_ENDPOINT;
	}

	$data['counter'] = null;
	if (hasRequest('conditions')) {
		$conditions = getRequest('conditions');
		krsort($conditions);
		$data['counter'] = key($conditions) + 1;
	}

	echo (new CView('configuration.host.discovery.prototype.edit', $data))->getOutput();
}
else {
	$data = [
		'hostid' => $hosts[0]['hostid'],
		'parent_discoveryid' => $parent_discovery['itemid'],
		'is_parent_discovered' => $parent_discovery['flags'] & ZBX_FLAG_DISCOVERY_CREATED,
		'sort' => $sort_field,
		'sortorder' => $sort_order,
		'active_tab' => CProfile::get($prefix.'discovery_prototypes.filter.active', 1),
		'checkbox_hash' => $parent_discovery['itemid'],
		'context' => getRequest('context')
	];

	// Select LLD prototypes.
	$options = [
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => ['hostid', 'name', 'status', 'flags'],
		'selectItems' => API_OUTPUT_COUNT,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectHostPrototypes' => API_OUTPUT_COUNT,
		'selectDiscoveryRulePrototypes' => API_OUTPUT_COUNT,
		'selectDiscoveryData' => ['parent_itemid'],
		'discoveryids' => $parent_discovery['itemid'],
		'templated' => ($data['context'] === 'template'),
		'sortfield' => $sort_field,
		'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1,
		'editable' => true
	];

	$data['discoveries'] = API::DiscoveryRulePrototype()->get($options);

	if ($sort_field === 'delay') {
		orderItemsByDelay($data['discoveries'], $sort_order, ['usermacros' => true, 'lldmacros' => true]);
	}
	else {
		order_result($data['discoveries'], $sort_field, $sort_order);
	}

	$data['discoveries'] = expandItemNamesWithMasterItems($data['discoveries'], 'items');

	if ($parent_discovery['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
		$data['source_link_data'] = [
			'parent_itemid' => $parent_discovery['discoveryData']['parent_itemid'],
			'name' => $parent_discovery['name']
		];
	}

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

	$data['paging'] = CPagerHelper::paginate($page_num, $data['discoveries'], $sort_order,
		(new CUrl('host_discovery_prototypes.php'))
			->setArgument('context', $data['context'])
			->setArgument('parent_discoveryid', $data['parent_discoveryid'])
	);

	$data['parent_templates'] = getItemParentTemplates($data['discoveries'], ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE);
	$data['allowed_ui_conf_templates'] = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);

	// render view
	echo (new CView('configuration.host.discovery.prototype.list', $data))->getOutput();
}

require_once __DIR__.'/include/page_footer.php';
