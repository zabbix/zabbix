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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of item prototypes');
$page['file'] = 'disc_prototypes.php';
$page['scripts'] = ['effects.js', 'multilineinput.js', 'items.js'];

require_once dirname(__FILE__).'/include/page_header.php';

$paramsFieldName = getParamFieldNameByType(getRequest('type', 0));

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'parent_discoveryid' =>			[T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null],
	'hostid' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'itemid' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'],
	'interfaceid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null, _('Interface')],
	'name' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})',
										_('Name')
									],
	'description' =>				[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'key' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})',
										_('Key')
									],
	'master_itemid' =>				[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_DEPENDENT,
										_('Master item')
									],
	'delay' =>						[T_ZBX_TU, O_OPT, P_ALLOW_USER_MACRO | P_ALLOW_LLD_MACRO, null,
										'(isset({add}) || isset({update}))'.
											' && isset({type}) && {type} != '.ITEM_TYPE_TRAPPER.
												' && {type} != '.ITEM_TYPE_SNMPTRAP.
												' && {type} != '.ITEM_TYPE_DEPENDENT.
												' && !({type} == '.ITEM_TYPE_ZABBIX_ACTIVE.
													' && isset({key}) && strncmp({key}, "mqtt.get", 8) === 0)',
										_('Update interval')
									],
	'delay_flex' =>					[T_ZBX_STR, O_OPT, null,	null,			null],
	'status' =>						[T_ZBX_INT, O_OPT, null,	IN([ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]), null],
	'discover' =>					[T_ZBX_INT, O_OPT, null,	IN([ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]), null],
	'type' =>						[T_ZBX_INT, O_OPT, null,
										IN([-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE,
											ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE,
											ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH,
											ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED, ITEM_TYPE_SNMPTRAP,
											ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
										]),
										'isset({add}) || isset({update})'
									],
	'value_type' =>					[T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4'), 'isset({add}) || isset({update})'],
	'valuemapid' =>					[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'authtype' =>					[T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHTYPE_PASSWORD.','.ITEM_AUTHTYPE_PUBLICKEY),
										'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SSH
									],
	'username' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type'),
										_('User name')
									],
	'password' =>					[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type')
									],
	'publickey' =>					[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_SSH.' && {authtype} == '.ITEM_AUTHTYPE_PUBLICKEY
									],
	'privatekey' =>					[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_SSH.' && {authtype} == '.ITEM_AUTHTYPE_PUBLICKEY
									],
	$paramsFieldName =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_DB_MONITOR.','.ITEM_TYPE_TELNET.','.
												ITEM_TYPE_CALCULATED.','.ITEM_TYPE_SCRIPT, 'type'
											),
										getParamFieldLabelByType(getRequest('type', 0))
									],
	'snmp_oid' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_SNMP,
										_('SNMP OID')
									],
	'ipmi_sensor' =>				[T_ZBX_STR, O_OPT, P_NO_TRIM, null,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_IPMI,
										_('IPMI sensor')
									],
	'trapper_hosts' =>				[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_TRAPPER
									],
	'units' =>						[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({value_type})'.
											' && '.IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type')
									],
	'logtimefmt' =>					[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({value_type})'.
											' && {value_type} == '.ITEM_VALUE_TYPE_LOG
									],
	'preprocessing' =>				[T_ZBX_STR, O_OPT, P_NO_TRIM,	null,	null],
	'group_itemid' =>				[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'history_mode' =>				[T_ZBX_INT, O_OPT, null,	IN([ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]), null],
	'history' =>					[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update}))'.
											' && isset({history_mode}) && {history_mode}=='.ITEM_STORAGE_CUSTOM,
										_('History storage period')
									],
	'trends_mode' =>				[T_ZBX_INT, O_OPT, null,	IN([ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]), null],
	'trends' =>						[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update}))'.
											' && isset({trends_mode}) && {trends_mode}=='.ITEM_STORAGE_CUSTOM.
											' && isset({value_type})'.
											' && '.IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type'),
										_('Trend storage period')
									],
	'jmx_endpoint' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
										'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_JMX
									],
	'timeout' =>	 				[T_ZBX_TU, O_OPT, P_ALLOW_USER_MACRO|P_ALLOW_LLD_MACRO,	null,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && '.IN(ITEM_TYPE_HTTPAGENT.','.ITEM_TYPE_SCRIPT, 'type'),
										_('Timeout')
									],
	'url' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_HTTPAGENT,
										_('URL')
									],
	'query_fields' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'parameters' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'posts' =>						[T_ZBX_STR, O_OPT, null,	null,		null],
	'status_codes' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'follow_redirects' =>			[T_ZBX_INT, O_OPT, null,
										IN([HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON]),
										null
									],
	'post_type' =>					[T_ZBX_INT, O_OPT, null,
										IN([ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
										null
									],
	'http_proxy' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'headers' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'retrieve_mode' =>				[T_ZBX_INT, O_OPT, null,
										IN([HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS,
											HTTPTEST_STEP_RETRIEVE_MODE_BOTH
										]),
										null
									],
	'request_method' =>				[T_ZBX_INT, O_OPT, null,
										IN([HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT,
											HTTPCHECK_REQUEST_HEAD
										]),
										null
									],
	'output_format' =>				[T_ZBX_INT, O_OPT, null,	IN([HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON]), null],
	'allow_traps' =>				[T_ZBX_INT, O_OPT, null,
										IN([HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON]),
										null
									],
	'ssl_cert_file' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'ssl_key_file' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'ssl_key_password' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'verify_peer' =>				[T_ZBX_INT, O_OPT, null,
										IN([HTTPTEST_VERIFY_PEER_OFF, HTTPTEST_VERIFY_PEER_ON]),
										null
									],
	'verify_host' =>				[T_ZBX_INT, O_OPT, null,
										IN([HTTPTEST_VERIFY_HOST_OFF, HTTPTEST_VERIFY_HOST_ON]),
										null
									],
	'http_authtype' =>				[T_ZBX_INT, O_OPT, null,
										IN([HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM,
											HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST
										]),
										null
									],
	'http_username' =>				[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({http_authtype})'.
											' && ({http_authtype} == '.HTTPTEST_AUTH_BASIC.
												' || {http_authtype} == '.HTTPTEST_AUTH_NTLM.
												' || {http_authtype} == '.HTTPTEST_AUTH_KERBEROS.
												' || {http_authtype} == '.HTTPTEST_AUTH_DIGEST.
											')',
										_('Username')
									],
	'http_password' =>				[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({http_authtype})'.
											' && ({http_authtype} == '.HTTPTEST_AUTH_BASIC.
												' || {http_authtype} == '.HTTPTEST_AUTH_NTLM.
												' || {http_authtype} == '.HTTPTEST_AUTH_KERBEROS.
												' || {http_authtype} == '.HTTPTEST_AUTH_DIGEST.
											')',
										_('Password')
									],
	'visible' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'context' =>					[T_ZBX_STR, O_MAND, P_SYS,	IN('"host", "template"'),	null],
	'tags' =>						[T_ZBX_STR, O_OPT, null,	null,		null],
	'show_inherited_tags' =>		[T_ZBX_INT, O_OPT, null,	IN([0,1]),	null],
	// actions
	'action' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
										IN('"itemprototype.massdelete","itemprototype.massdisable",'.
											'"itemprototype.massenable","itemprototype.massdiscover.enable",'.
											'"itemprototype.massdiscover.disable"'
										),
										null
									],
	'add' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>						[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>						[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>				[T_ZBX_INT, O_OPT, null,	null,		null],
	// filter
	'filter_set' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	// sort and sortorder
	'sort' =>						[T_ZBX_STR, O_OPT, P_SYS,
										IN('"delay","history","key_","name","status","trends","type","discover"'), null
									],
	'sortorder' =>					[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];

if (getRequest('type') == ITEM_TYPE_HTTPAGENT && getRequest('interfaceid') == INTERFACE_TYPE_OPT) {
	unset($fields['interfaceid']);
	unset($_REQUEST['interfaceid']);
}

$valid_input = check_fields($fields);

$_REQUEST['params'] = getRequest($paramsFieldName, '');
unset($_REQUEST[$paramsFieldName]);

// permissions
$discoveryRule = API::DiscoveryRule()->get([
	'output' => ['hostid'],
	'itemids' => getRequest('parent_discoveryid'),
	'editable' => true
]);
$discoveryRule = reset($discoveryRule);
if (!$discoveryRule) {
	access_deny();
}

$itemPrototypeId = getRequest('itemid');
if ($itemPrototypeId) {
	$item_prorotypes = API::ItemPrototype()->get([
		'output' => [],
		'itemids' => $itemPrototypeId,
		'editable' => true
	]);

	if (!$item_prorotypes) {
		access_deny();
	}
}

// Convert CR+LF to LF in preprocessing script.
if (hasRequest('preprocessing')) {
	foreach ($_REQUEST['preprocessing'] as &$step) {
		if ($step['type'] == ZBX_PREPROC_SCRIPT) {
			$step['params'][0] = CRLFtoLF($step['params'][0]);
		}
	}
	unset($step);
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
if (hasRequest('delete') && hasRequest('itemid')) {
	DBstart();
	$result = API::ItemPrototype()->delete([getRequest('itemid')]);
	$result = DBend($result);

	if ($result) {
		uncheckTableRows(getRequest('parent_discoveryid'));
	}
	show_messages($result, _('Item prototype deleted'), _('Cannot delete item prototype'));

	unset($_REQUEST['itemid'], $_REQUEST['form']);
}
elseif (hasRequest('add') || hasRequest('update')) {
	$result = true;
	DBstart();

	$delay = getRequest('delay', DB::getDefault('items', 'delay'));
	$type = getRequest('type', ITEM_TYPE_ZABBIX);

	/*
	 * "delay_flex" is a temporary field that collects flexible and scheduling intervals separated by a semicolon.
	 * In the end, custom intervals together with "delay" are stored in the "delay" variable.
	 */
	if ($type != ITEM_TYPE_TRAPPER && $type != ITEM_TYPE_SNMPTRAP
			&& ($type != ITEM_TYPE_ZABBIX_ACTIVE || strncmp(getRequest('key'), 'mqtt.get', 8) !== 0)
			&& hasRequest('delay_flex')) {
		$intervals = [];
		$simple_interval_parser = new CSimpleIntervalParser([
			'usermacros' => true,
			'lldmacros' => true
		]);
		$time_period_parser = new CTimePeriodParser([
			'usermacros' => true,
			'lldmacros' => true
		]);
		$scheduling_interval_parser = new CSchedulingIntervalParser([
			'usermacros' => true,
			'lldmacros' => true
		]);

		foreach (getRequest('delay_flex') as $interval) {
			if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
				if ($interval['delay'] === '' && $interval['period'] === '') {
					continue;
				}

				if ($simple_interval_parser->parse($interval['delay']) != CParser::PARSE_SUCCESS) {
					$result = false;
					error(_s('Invalid interval "%1$s".', $interval['delay']));
					break;
				}
				elseif ($time_period_parser->parse($interval['period']) != CParser::PARSE_SUCCESS) {
					$result = false;
					error(_s('Invalid interval "%1$s".', $interval['period']));
					break;
				}

				$intervals[] = $interval['delay'].'/'.$interval['period'];
			}
			else {
				if ($interval['schedule'] === '') {
					continue;
				}

				if ($scheduling_interval_parser->parse($interval['schedule']) != CParser::PARSE_SUCCESS) {
					$result = false;
					error(_s('Invalid interval "%1$s".', $interval['schedule']));
					break;
				}

				$intervals[] = $interval['schedule'];
			}
		}

		if ($intervals) {
			$delay .= ';'.implode(';', $intervals);
		}
	}

	if ($result) {
		$preprocessing = getRequest('preprocessing', []);
		$preprocessing = normalizeItemPreprocessingSteps($preprocessing);

		$item = [
			'name'			=> getRequest('name'),
			'description'	=> getRequest('description'),
			'key_'			=> getRequest('key'),
			'hostid'		=> $discoveryRule['hostid'],
			'interfaceid'	=> getRequest('interfaceid'),
			'delay'			=> $delay,
			'status'		=> getRequest('status', ITEM_STATUS_DISABLED),
			'discover'		=> getRequest('discover', ZBX_PROTOTYPE_DISCOVER),
			'type'			=> getRequest('type'),
			'snmp_oid'		=> getRequest('snmp_oid'),
			'value_type'	=> getRequest('value_type'),
			'trapper_hosts'	=> getRequest('trapper_hosts'),
			'history'		=> (getRequest('history_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF)
				? ITEM_NO_STORAGE_VALUE
				: getRequest('history'),
			'units'			=> getRequest('units'),
			'logtimefmt'	=> getRequest('logtimefmt'),
			'valuemapid'	=> getRequest('valuemapid', 0),
			'authtype'		=> getRequest('authtype'),
			'username'		=> getRequest('username'),
			'password'		=> getRequest('password'),
			'publickey'		=> getRequest('publickey'),
			'privatekey'	=> getRequest('privatekey'),
			'params'		=> getRequest('params'),
			'ipmi_sensor'	=> getRequest('ipmi_sensor'),
			'ruleid'		=> getRequest('parent_discoveryid')
		];

		switch ($item['type']) {
			case ITEM_TYPE_SCRIPT:
				$script_item = [
					'parameters' => getRequest('parameters', []),
					'timeout' => getRequest('timeout', DB::getDefault('items', 'timeout'))
				];

				$item = prepareScriptItemFormData($script_item) + $item;
				break;

			case ITEM_TYPE_JMX:
				$item['jmx_endpoint'] = getRequest('jmx_endpoint', '');
				break;

			case ITEM_TYPE_DEPENDENT:
				$item['master_itemid'] = getRequest('master_itemid');
				break;

			case ITEM_TYPE_HTTPAGENT:
				$http_item = [
					'timeout' => getRequest('timeout', DB::getDefault('items', 'timeout')),
					'url' => getRequest('url'),
					'query_fields' => getRequest('query_fields', []),
					'posts' => getRequest('posts'),
					'status_codes' => getRequest('status_codes', DB::getDefault('items', 'status_codes')),
					'follow_redirects' => (int) getRequest('follow_redirects'),
					'post_type' => (int) getRequest('post_type'),
					'http_proxy' => getRequest('http_proxy'),
					'headers' => getRequest('headers', []),
					'retrieve_mode' => (int) getRequest('retrieve_mode'),
					'request_method' => (int) getRequest('request_method'),
					'output_format' => (int) getRequest('output_format'),
					'allow_traps' => (int) getRequest('allow_traps', HTTPCHECK_ALLOW_TRAPS_OFF),
					'ssl_cert_file' => getRequest('ssl_cert_file'),
					'ssl_key_file' => getRequest('ssl_key_file'),
					'ssl_key_password' => getRequest('ssl_key_password'),
					'verify_peer' => (int) getRequest('verify_peer'),
					'verify_host' => (int) getRequest('verify_host'),
					'authtype' => getRequest('http_authtype', HTTPTEST_AUTH_NONE),
					'username' => getRequest('http_username', ''),
					'password' => getRequest('http_password', '')
				];
				break;
		}

		if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64) {
			$item['trends'] = (getRequest('trends_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF)
				? ITEM_NO_STORAGE_VALUE
				: getRequest('trends');
		}

		if (hasRequest('update')) {
			$itemId = getRequest('itemid');

			$db_item = API::ItemPrototype()->get([
				'output' => ['type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history',
					'trends', 'status', 'value_type', 'trapper_hosts', 'units',
					'logtimefmt', 'templateid', 'valuemapid', 'params', 'ipmi_sensor', 'authtype', 'username',
					'password', 'publickey', 'privatekey', 'interfaceid', 'description', 'jmx_endpoint',
					'master_itemid', 'timeout', 'url', 'query_fields', 'posts', 'status_codes', 'follow_redirects',
					'post_type', 'http_proxy', 'headers', 'retrieve_mode', 'request_method', 'output_format',
					'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer', 'verify_host', 'allow_traps',
					'discover', 'parameters'
				],
				'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
				'selectTags' => ['tag', 'value'],
				'itemids' => [$itemId]
			]);

			$db_item = $db_item[0];

			if ($item['type'] == ITEM_TYPE_HTTPAGENT && $db_item['templateid'] == 0) {
				$item = prepareItemHttpAgentFormData($http_item) + $item;
			}

			if ($db_item['type'] == $item['type']) {
				$item = CArrayHelper::unsetEqualValues($item, $db_item);
			}

			$item['itemid'] = $itemId;

			if ($db_item['preprocessing'] !== $preprocessing) {
				$item['preprocessing'] = $preprocessing;
			}

			function parameters_equal(array $stored_parameters, array $input_parameters): bool {
				return (array_column($stored_parameters, 'value') == array_column($input_parameters, 'value')
						&& array_column($stored_parameters, 'name') == array_column($input_parameters, 'name'));
			}

			if (getRequest('type') == ITEM_TYPE_SCRIPT && $db_item['type'] == getRequest('type')
					&& parameters_equal($db_item['parameters'], $item['parameters'])) {
				unset($item['parameters']);
			}

			CArrayHelper::sort($db_item['tags'], ['tag', 'value']);
			CArrayHelper::sort($tags, ['tag', 'value']);

			if (array_values($db_item['tags']) !== array_values($tags)) {
				$item['tags'] = $tags;
			}

			if ($db_item['templateid'] != 0) {
				$allowed_fields = array_fill_keys([
					'itemid', 'delay', 'delay_flex', 'history', 'trends', 'history_mode', 'trends_mode', 'allow_traps',
					'description', 'status', 'discover', 'tags'
				], true);

				if ($db_item['type'] != ITEM_TYPE_HTTPAGENT) {
					$allowed_fields += array_fill_keys([
						'authtype', 'username', 'password', 'params', 'publickey', 'privatekey', 'interfaceid'
					], true);
				}

				foreach ($item as $field => $value) {
					if (!array_key_exists($field, $allowed_fields)) {
						unset($item[$field]);
					}
				}
			}

			$result = API::ItemPrototype()->update($item);
		}
		else {
			if (getRequest('type') == ITEM_TYPE_HTTPAGENT) {
				$item = prepareItemHttpAgentFormData($http_item) + $item;
			}

			if ($preprocessing) {
				$item['preprocessing'] = $preprocessing;
			}

			$item['tags'] = $tags;

			$result = API::ItemPrototype()->create($item);
		}
	}

	$result = DBend($result);

	if (hasRequest('add')) {
		show_messages($result, _('Item prototype added'), _('Cannot add item prototype'));
	}
	else {
		show_messages($result, _('Item prototype updated'), _('Cannot update item prototype'));
	}

	if ($result) {
		unset($_REQUEST['itemid'], $_REQUEST['form']);
		uncheckTableRows(getRequest('parent_discoveryid'));
	}
}
elseif (hasRequest('action') && hasRequest('group_itemid')
		&& str_in_array(getRequest('action'), ['itemprototype.massenable', 'itemprototype.massdisable'])) {
	$itemids = getRequest('group_itemid');
	$status = (getRequest('action') == 'itemprototype.massenable') ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;

	$item_prototypes = [];
	foreach ($itemids as $itemid) {
		$item_prototypes[] = ['itemid' => $itemid, 'status' => $status];
	}

	$result = (bool) API::ItemPrototype()->update($item_prototypes);

	if ($result) {
		uncheckTableRows(getRequest('parent_discoveryid'));
	}

	$updated = count($itemids);

	$messageSuccess = _n('Item prototype updated', 'Item prototypes updated', $updated);
	$messageFailed = _n('Cannot update item prototype', 'Cannot update item prototypes', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') === 'itemprototype.massdelete' && hasRequest('group_itemid')) {
	DBstart();

	$result = API::ItemPrototype()->delete(getRequest('group_itemid'));
	$result = DBend($result);

	if ($result) {
		uncheckTableRows(getRequest('parent_discoveryid'));
	}
	show_messages($result, _('Item prototypes deleted'), _('Cannot delete item prototypes'));
}
elseif (hasRequest('action') && hasRequest('group_itemid')
		&& in_array(getRequest('action'), ['itemprototype.massdiscover.enable', 'itemprototype.massdiscover.disable'])) {
	$itemids = getRequest('group_itemid');
	$discover = (getRequest('action') == 'itemprototype.massdiscover.enable') ? ITEM_DISCOVER : ITEM_NO_DISCOVER;

	$item_prototypes = [];
	foreach ($itemids as $itemid) {
		$item_prototypes[] = ['itemid' => $itemid, 'discover' => $discover];
	}

	$result = (bool) API::ItemPrototype()->update($item_prototypes);

	if ($result) {
		uncheckTableRows(getRequest('parent_discoveryid'));
	}

	$updated = count($itemids);

	$messageSuccess = _n('Item prototype updated', 'Item prototypes updated', $updated);
	$messageFailed = _n('Cannot update item prototype', 'Cannot update item prototypes', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
}

/*
 * Display
 */
if (hasRequest('form') || (hasRequest('clone') && getRequest('itemid') != 0)) {
	$itemPrototype = [];
	$has_errors = false;

	if (hasRequest('itemid') && !hasRequest('clone')) {
		$itemPrototype = API::ItemPrototype()->get([
			'itemids' => getRequest('itemid'),
			'output' => [
				'itemid', 'type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
				'value_type', 'trapper_hosts', 'units', 'logtimefmt', 'templateid', 'valuemapid', 'params',
				'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'interfaceid',
				'description', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'parameters', 'posts',
				'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode',
				'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer',
				'verify_host', 'allow_traps', 'discover'
			],
			'selectDiscoveryRule' => ['itemid', 'templateid'],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectTags' => ['tag', 'value']
		]);
		$itemPrototype = reset($itemPrototype);

		$i = 0;
		foreach ($itemPrototype['preprocessing'] as &$step) {
			if ($step['type'] == ZBX_PREPROC_SCRIPT) {
				$step['params'] = [$step['params'], ''];
			}
			else {
				$step['params'] = explode("\n", $step['params']);
			}
			$step['sortorder'] = $i++;
		}
		unset($step);

		if ($itemPrototype['type'] != ITEM_TYPE_JMX) {
			$itemPrototype['jmx_endpoint'] = ZBX_DEFAULT_JMX_ENDPOINT;
		}

		if (getRequest('type', $itemPrototype['type']) == ITEM_TYPE_DEPENDENT) {
			$master_prototypes = API::Item()->get([
				'output' => ['itemid', 'hostid', 'name', 'key_'],
				'itemids' => [getRequest('master_itemid', $itemPrototype['master_itemid'])],
				'hostids' => [$itemPrototype['hostid']],
				'webitems' => true
			])
			+ API::ItemPrototype()->get([
				'output' => ['itemid', 'hostid', 'name', 'key_'],
				'itemids' => getRequest('master_itemid', $itemPrototype['master_itemid'])
			]);

			if ($master_prototypes) {
				$itemPrototype['master_item'] = reset($master_prototypes);
			}
		}
	}
	elseif (getRequest('master_itemid')) {
		$master_prototypes = API::Item()->get([
			'output' => ['itemid', 'hostid', 'name', 'key_'],
			'itemids' => getRequest('master_itemid'),
			'webitems' => true
		])
		+ API::ItemPrototype()->get([
			'output' => ['itemid', 'hostid', 'name', 'key_'],
			'itemids' => getRequest('master_itemid')
		]);

		if ($master_prototypes) {
			$itemPrototype['master_item'] = reset($master_prototypes);
		}
		else {
			show_messages(false, '', _('No permissions to referred object or it does not exist!'));
			$has_errors = true;
		}
	}

	$form_action = (hasRequest('clone') && getRequest('itemid') != 0) ? 'clone' : getRequest('form');
	$data = getItemFormData($itemPrototype, ['form' => $form_action]);
	CArrayHelper::sort($data['preprocessing'], ['sortorder']);
	$data['preprocessing_test_type'] = CControllerPopupItemTestEdit::ZBX_TEST_TYPE_ITEM_PROTOTYPE;
	$data['preprocessing_types'] = CItemPrototype::SUPPORTED_PREPROCESSING_TYPES;
	$data['trends_default'] = DB::getDefault('items', 'trends');

	$data['display_interfaces'] = $data['hostid']
		? (bool) API::Host()->get([
			'countOutput' => true,
			'hostids' => $data['hostid'],
			'filter' => [
				'status' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]
			]
		])
		: false;

	$history_in_seconds = timeUnitToSeconds($data['history']);
	if (!getRequest('form_refresh') && $history_in_seconds !== null && $history_in_seconds == ITEM_NO_STORAGE_VALUE) {
		$data['history_mode'] = getRequest('history_mode', ITEM_STORAGE_OFF);
		$data['history'] = DB::getDefault('items', 'history');
	}
	else {
		$data['history_mode'] = getRequest('history_mode', ITEM_STORAGE_CUSTOM);
	}

	$trends_in_seconds = timeUnitToSeconds($data['trends']);
	if (!getRequest('form_refresh') && $trends_in_seconds !== null && $trends_in_seconds == ITEM_NO_STORAGE_VALUE) {
		$data['trends_mode'] = getRequest('trends_mode', ITEM_STORAGE_OFF);
		$data['trends'] = $data['trends_default'];
	}
	else {
		$data['trends_mode'] = getRequest('trends_mode', ITEM_STORAGE_CUSTOM);
	}

	// render view
	if (!$has_errors) {
		echo (new CView('configuration.item.prototype.edit', $data))->getOutput();
	}
}
else {
	$prefix = (getRequest('context') === 'host') ? 'web.hosts.' : 'web.templates.';

	$sortField = getRequest('sort', CProfile::get($prefix.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get($prefix.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update($prefix.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update($prefix.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$data = [
		'form' => getRequest('form'),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'hostid' => $discoveryRule['hostid'],
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'context' => getRequest('context')
	];

	$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
	$data['items'] = API::ItemPrototype()->get([
		'discoveryids' => $data['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'editable' => true,
		'selectTags' => ['tag', 'value'],
		'sortfield' => $sortField,
		'limit' => $limit
	]);

	$data['items'] = expandItemNamesWithMasterItems($data['items'], 'itemprototypes');

	switch ($sortField) {
		case 'delay':
			orderItemsByDelay($data['items'], $sortOrder, ['usermacros' => true, 'lldmacros' => true]);
			break;

		case 'history':
			orderItemsByHistory($data['items'], $sortOrder);
			break;

		case 'trends':
			orderItemsByTrends($data['items'], $sortOrder);
			break;

		default:
			order_result($data['items'], $sortField, $sortOrder);
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

	$data['paging'] = CPagerHelper::paginate($page_num, $data['items'], $sortOrder,
		(new CUrl('disc_prototypes.php'))
			->setArgument('parent_discoveryid', $data['parent_discoveryid'])
			->setArgument('context', $data['context'])
	);

	$data['parent_templates'] = getItemParentTemplates($data['items'], ZBX_FLAG_DISCOVERY_PROTOTYPE);
	$data['allowed_ui_conf_templates'] = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);

	$data['tags'] = makeTags($data['items'], true, 'itemid', ZBX_TAG_COUNT_DEFAULT);

	// render view
	echo (new CView('configuration.item.prototype.list', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';
