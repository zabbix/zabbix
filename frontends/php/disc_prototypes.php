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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of item prototypes');
$page['file'] = 'disc_prototypes.php';
$page['scripts'] = ['effects.js', 'class.cviewswitcher.js', 'items.js'];

require_once dirname(__FILE__).'/include/page_header.php';

$paramsFieldName = getParamFieldNameByType(getRequest('type', 0));

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'parent_discoveryid' =>			[T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null],
	'itemid' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'(isset({form}) && ({form} == "update"))'],
	'interfaceid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null, _('Interface')],
	'name' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})',
		_('Name')
	],
	'description' =>				[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'key' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})',
		_('Key')
	],
	'master_itemid' =>				[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && {type}=='.ITEM_TYPE_DEPENDENT, _('Master item')],
	'master_itemname' =>			[T_ZBX_STR, O_OPT, null,	null,			null],
	'delay' =>						[T_ZBX_TU, O_OPT, P_ALLOW_USER_MACRO | P_ALLOW_LLD_MACRO, null,
		'(isset({add}) || isset({update}))'.
			' && (isset({type}) && ({type} != '.ITEM_TYPE_TRAPPER.' && {type} != '.ITEM_TYPE_SNMPTRAP.')'.
			' && {type}!='.ITEM_TYPE_DEPENDENT.')',
		_('Update interval')
	],
	'delay_flex' =>					[T_ZBX_STR, O_OPT, null,	null,			null],
	'status' =>						[T_ZBX_INT, O_OPT, null,	IN(ITEM_STATUS_ACTIVE), null],
	'type' =>						[T_ZBX_INT, O_OPT, null,
		IN([-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV2C,
			ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL,
			ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED,
			ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT
		]),
		'isset({add}) || isset({update})'
	],
	'value_type' =>					[T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4'), 'isset({add}) || isset({update})'],
	'valuemapid' =>					[T_ZBX_INT, O_OPT, null,	DB_ID,
		'(isset({add}) || isset({update})) && isset({value_type})'.
			' && '.IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type')
	],
	'authtype' =>					[T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHTYPE_PASSWORD.','.ITEM_AUTHTYPE_PUBLICKEY),
		'(isset({add}) || isset({update})) && isset({type}) && ({type} == '.ITEM_TYPE_SSH.')'
	],
	'username' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type'),
		_('User name')
	],
	'password' =>					[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type')
	],
	'publickey' =>					[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type})'.
			' && ({type}) == '.ITEM_TYPE_SSH.' && ({authtype}) == '.ITEM_AUTHTYPE_PUBLICKEY
	],
	'privatekey' =>					[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type})'.
			' && ({type}) == '.ITEM_TYPE_SSH.' && ({authtype}) == '.ITEM_AUTHTYPE_PUBLICKEY
	],
	$paramsFieldName =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type})'.
			' && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_DB_MONITOR.','.ITEM_TYPE_TELNET.','.ITEM_TYPE_CALCULATED, 'type'),
		getParamFieldLabelByType(getRequest('type', 0))
	],
	'snmp_community' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && '.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C, 'type'),
		_('SNMP community')
	],
	'snmp_oid' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type})'.
			' && '.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3, 'type'),
		_('SNMP OID')
	],
	'port' =>						[T_ZBX_STR, O_OPT, null,	BETWEEN(0, 65535),
		'(isset({add}) || isset({update})) && isset({type})'.
			' && '.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3, 'type'),
		_('Port')
	],
	'snmpv3_securitylevel' =>		[T_ZBX_INT, O_OPT, null,	IN('0,1,2'),
		'(isset({add}) || isset({update})) && (isset({type}) && ({type} == '.ITEM_TYPE_SNMPV3.'))'
	],
	'snmpv3_contextname' =>		[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && (isset({type}) && ({type} == '.ITEM_TYPE_SNMPV3.'))'
	],
	'snmpv3_securityname' =>		[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && (isset({type}) && ({type} == '.ITEM_TYPE_SNMPV3.'))'
	],
	'snmpv3_authprotocol' =>		[T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHPROTOCOL_MD5.','.ITEM_AUTHPROTOCOL_SHA),
		'(isset({add}) || isset({update})) && (isset({type})'.
			' && ({type} == '.ITEM_TYPE_SNMPV3.') && ({snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.
			' || {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.'))'
	],
	'snmpv3_authpassphrase' =>		[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && (isset({type})'.
			' && ({type} == '.ITEM_TYPE_SNMPV3.') && ({snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.
			' || {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.'))'
	],
	'snmpv3_privprotocol' =>		[T_ZBX_INT, O_OPT, null,	IN(ITEM_PRIVPROTOCOL_DES.','.ITEM_PRIVPROTOCOL_AES),
		'(isset({add}) || isset({update})) && (isset({type}) && ({type} == '.ITEM_TYPE_SNMPV3.')'.
			' && ({snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'))'
	],
	'snmpv3_privpassphrase' =>		[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && (isset({type}) && ({type} == '.ITEM_TYPE_SNMPV3.')'.
			' && ({snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'))'
	],
	'ipmi_sensor' =>				[T_ZBX_STR, O_OPT, P_NO_TRIM,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && (isset({type}) && ({type} == '.ITEM_TYPE_IPMI.'))', _('IPMI sensor')
	],
	'trapper_hosts' =>				[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && ({type} == 2)'
	],
	'units' =>						[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({value_type}) && '.
		IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type')
	],
	'logtimefmt' =>					[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && (isset({value_type}) && ({value_type} == 2))'
	],
	'preprocessing' =>				[T_ZBX_STR, O_OPT, P_NO_TRIM,	null,	null],
	'group_itemid' =>				[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'new_application' =>			[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'applications' =>				[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'new_application_prototype' =>	[T_ZBX_STR, O_OPT, null,	null,
		'isset({parent_discoveryid}) && (isset({add}) || isset({update}))'
	],
	'application_prototypes' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'history' =>					[T_ZBX_STR, O_OPT, null,	null, 'isset({add}) || isset({update})',
		_('History storage period')
	],
	'trends' =>						[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({value_type})'.
			' && '.IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type'),
		_('Trend storage period')
	],
	'jmx_endpoint' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_JMX
	],
	'timeout' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'url' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_HTTPAGENT, _('URL')],
	'query_fields' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
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
										IN([HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM]),
										null
									],
	'http_username' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
										'(isset({add}) || isset({update})) && isset({http_authtype})'.
											' && ({http_authtype} == '.HTTPTEST_AUTH_BASIC.
												' || {http_authtype} == '.HTTPTEST_AUTH_NTLM.')',
										_('Username')
									],
	'http_password' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
										'(isset({add}) || isset({update})) && isset({http_authtype})'.
											' && ({http_authtype} == '.HTTPTEST_AUTH_BASIC.
												' || {http_authtype} == '.HTTPTEST_AUTH_NTLM.')',
										_('Password')
									],
	// actions
	'action' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
		IN('"itemprototype.massdelete","itemprototype.massdisable","itemprototype.massenable"'), null
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
		IN('"delay","history","key_","name","status","trends","type"'), null
	],
	'sortorder' =>					[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

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
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['itemid'])) {
	unset($_REQUEST['itemid']);
	$_REQUEST['form'] = 'clone';
}
elseif (hasRequest('add') || hasRequest('update')) {
	$applications = getRequest('applications', []);
	$application = reset($applications);
	if ($application == 0) {
		array_shift($applications);
	}

	$result = true;
	DBstart();

	if (!zbx_empty($_REQUEST['new_application'])) {
		$new_appid = API::Application()->create([
			'name' => $_REQUEST['new_application'],
			'hostid' => $discoveryRule['hostid']
		]);
		if ($new_appid) {
			$new_appid = reset($new_appid['applicationids']);
			$applications[$new_appid] = $new_appid;
		}
		else {
			$result = false;
		}
	}

	$delay = getRequest('delay', DB::getDefault('items', 'delay'));
	$type = getRequest('type', ITEM_TYPE_ZABBIX);

	/*
	 * "delay_flex" is a temporary field that collects flexible and scheduling intervals separated by a semicolon.
	 * In the end, custom intervals together with "delay" are stored in the "delay" variable.
	 */
	if (!in_array($type, [ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP]) && hasRequest('delay_flex')) {
		$intervals = [];

		foreach (getRequest('delay_flex') as $interval) {
			if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
				if ($interval['delay'] === '' && $interval['period'] === '') {
					continue;
				}

				if (strpos($interval['delay'], ';') !== false) {
					$result = false;
					info(_s('Invalid interval "%1$s".', $interval['delay']));
					break;
				}
				elseif (strpos($interval['period'], ';') !== false) {
					$result = false;
					info(_s('Invalid interval "%1$s".', $interval['period']));
					break;
				}

				$intervals[] = $interval['delay'].'/'.$interval['period'];
			}
			else {
				if ($interval['schedule'] === '') {
					continue;
				}

				if (strpos($interval['schedule'], ';') !== false) {
					$result = false;
					info(_s('Invalid interval "%1$s".', $interval['schedule']));
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
		$application_prototypes = getRequest('application_prototypes', []);
		$application_prototype = reset($application_prototypes);

		if ($application_prototype === '0') {
			array_shift($application_prototypes);
		}

		if ($application_prototypes) {
			foreach ($application_prototypes as &$application_prototype) {
				$application_prototype = ['name' => $application_prototype];
			}
			unset($application_prototype);
		}

		$new_application_prototype = getRequest('new_application_prototype', '');
		if ($new_application_prototype !== '') {
			$application_prototypes[] = ['name' => $new_application_prototype];
		}

		$preprocessing = getRequest('preprocessing', []);

		foreach ($preprocessing as &$step) {
			switch ($step['type']) {
				case ZBX_PREPROC_MULTIPLIER:
				case ZBX_PREPROC_RTRIM:
				case ZBX_PREPROC_LTRIM:
				case ZBX_PREPROC_TRIM:
				case ZBX_PREPROC_XPATH:
				case ZBX_PREPROC_JSONPATH:
					$step['params'] = $step['params'][0];
					break;

				case ZBX_PREPROC_REGSUB:
					$step['params'] = implode("\n", $step['params']);
					break;

				default:
					$step['params'] = '';
			}
		}
		unset($step);

		$item = [
			'name'			=> getRequest('name'),
			'description'	=> getRequest('description'),
			'key_'			=> getRequest('key'),
			'hostid'		=> $discoveryRule['hostid'],
			'interfaceid'	=> getRequest('interfaceid'),
			'delay'			=> $delay,
			'status'		=> getRequest('status', ITEM_STATUS_DISABLED),
			'type'			=> getRequest('type'),
			'snmp_community' => getRequest('snmp_community'),
			'snmp_oid'		=> getRequest('snmp_oid'),
			'value_type'	=> getRequest('value_type'),
			'trapper_hosts'	=> getRequest('trapper_hosts'),
			'port'			=> getRequest('port'),
			'history'		=> getRequest('history'),
			'units'			=> getRequest('units'),
			'snmpv3_contextname' => getRequest('snmpv3_contextname'),
			'snmpv3_securityname' => getRequest('snmpv3_securityname'),
			'snmpv3_securitylevel' => getRequest('snmpv3_securitylevel'),
			'snmpv3_authprotocol' => getRequest('snmpv3_authprotocol'),
			'snmpv3_authpassphrase' => getRequest('snmpv3_authpassphrase'),
			'snmpv3_privprotocol' => getRequest('snmpv3_privprotocol'),
			'snmpv3_privpassphrase' => getRequest('snmpv3_privpassphrase'),
			'logtimefmt'	=> getRequest('logtimefmt'),
			'valuemapid'	=> getRequest('valuemapid'),
			'authtype'		=> getRequest('authtype'),
			'username'		=> getRequest('username'),
			'password'		=> getRequest('password'),
			'publickey'		=> getRequest('publickey'),
			'privatekey'	=> getRequest('privatekey'),
			'params'		=> getRequest('params'),
			'ipmi_sensor'	=> getRequest('ipmi_sensor'),
			'ruleid'		=> getRequest('parent_discoveryid')
		];

		if ($item['type'] == ITEM_TYPE_JMX) {
			$item['jmx_endpoint'] = getRequest('jmx_endpoint', '');
		}

		if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64) {
			$item['trends'] = getRequest('trends');
		}

		if ($item['type'] == ITEM_TYPE_DEPENDENT) {
			$item['master_itemid'] = getRequest('master_itemid');
		}

		if (hasRequest('update')) {
			$itemId = getRequest('itemid');

			$db_item = API::ItemPrototype()->get([
				'output' => ['type', 'snmp_community', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history',
					'trends', 'status', 'value_type', 'trapper_hosts', 'units', 'snmpv3_securityname',
					'snmpv3_securitylevel', 'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'logtimefmt',
					'templateid', 'valuemapid', 'params', 'ipmi_sensor', 'authtype', 'username', 'password',
					'publickey', 'privatekey', 'interfaceid', 'port', 'description', 'snmpv3_authprotocol',
					'snmpv3_privprotocol', 'snmpv3_contextname', 'jmx_endpoint', 'master_itemid', 'timeout', 'url',
					'query_fields', 'posts', 'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers',
					'retrieve_mode', 'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file',
					'ssl_key_password', 'verify_peer', 'verify_host', 'allow_traps'
				],
				'selectApplications' => ['applicationid'],
				'selectApplicationPrototypes' => ['name'],
				'selectPreprocessing' => ['type', 'params'],
				'itemids' => [$itemId]
			]);

			// unset snmpv3 fields
			if ($item['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV) {
				$item['snmpv3_authprotocol'] = ITEM_AUTHPROTOCOL_MD5;
				$item['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
			}
			elseif ($item['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
				$item['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
			}

			$db_item = $db_item[0];

			if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
				$item += [
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
					'allow_traps' => (int) getRequest('allow_traps'),
					'ssl_cert_file' => getRequest('ssl_cert_file'),
					'ssl_key_file' => getRequest('ssl_key_file'),
					'ssl_key_password' => getRequest('ssl_key_password'),
					'verify_peer' => (int) getRequest('verify_peer'),
					'verify_host' => (int) getRequest('verify_host'),
				];

				$item['authtype'] = getRequest('http_authtype', HTTPTEST_AUTH_NONE);
				$item['username'] = getRequest('http_username', '');
				$item['password'] = getRequest('http_password', '');

				if ($item['query_fields']) {
					$query_fields = [];

					foreach ($item['query_fields']['name'] as $index => $key) {
						if (array_key_exists($index, $item['query_fields']['value'])) {
							$query_fields[] = [$key => $item['query_fields']['value'][$index]];
						}
					}

					// Ignore single row if it is empty.
					if (count($query_fields) == 1 && $key === '' && $item['query_fields']['value'][$index] === '') {
						$query_fields = [];
					}

					$item['query_fields'] = $query_fields;
				}

				if ($item['headers']) {
					$headers = [];

					foreach ($item['headers']['name'] as $index => $key) {
						if (array_key_exists($index, $item['headers']['value'])) {
							$headers[$key] = $item['headers']['value'][$index];
						}
					}

					// Ignore single row if it is empty.
					if (count($headers) == 1 && $key === '' && $item['headers']['value'][$index] === '') {
						$headers = [];
					}

					$item['headers'] = $headers;
				}
			}

			$item = CArrayHelper::unsetEqualValues($item, $db_item);
			$item['itemid'] = $itemId;

			$db_item['applications'] = zbx_objectValues($db_item['applications'], 'applicationid');

			// compare applications
			natsort($db_item['applications']);
			natsort($applications);

			if (array_values($db_item['applications']) !== array_values($applications)) {
				$item['applications'] = $applications;
			}

			// compare application prototypes
			$db_application_prototype_names = zbx_objectValues($db_item['applicationPrototypes'], 'name');
			natsort($db_application_prototype_names);

			$application_prototype_names = zbx_objectValues($application_prototypes, 'name');
			natsort($application_prototype_names);

			if (array_values($db_application_prototype_names) !== array_values($application_prototype_names)) {
				$item['applicationPrototypes'] = $application_prototypes;
			}

			if ($db_item['preprocessing'] !== $preprocessing) {
				$item['preprocessing'] = $preprocessing;
			}

			$result = API::ItemPrototype()->update($item);
		}
		else {
			$item['applications'] = $applications;
			$item['applicationPrototypes'] = $application_prototypes;

			if (getRequest('type') == ITEM_TYPE_HTTPAGENT) {
				$posted = [
					'timeout' => getRequest('timeout', DB::getDefault('items', 'timeout')),
					'url' => getRequest('url'),
					'query_fields' => getRequest('query_fields', []),
					'posts' => getRequest('posts'),
					'status_codes' => getRequest('status_codes', DB::getDefault('items', 'status_codes')),
					'follow_redirects' => (int) getRequest('follow_redirects'),
					'post_type' => (int) getRequest('post_type'),
					'http_proxy' => getRequest('http_proxy'),
					'headers' => getRequest('headers', []),
					'retrieve_mode' => getRequest('retrieve_mode', HTTPTEST_STEP_RETRIEVE_MODE_CONTENT),
					'request_method' => (int) getRequest('request_method'),
					'output_format' => (int) getRequest('output_format'),
					'allow_traps' => (int) getRequest('allow_traps'),
					'ssl_cert_file' => getRequest('ssl_cert_file'),
					'ssl_key_file' => getRequest('ssl_key_file'),
					'ssl_key_password' => getRequest('ssl_key_password'),
					'verify_peer' => (int) getRequest('verify_peer'),
					'verify_host' => (int) getRequest('verify_host'),
				];

				$item['authtype'] = getRequest('http_authtype', HTTPTEST_AUTH_NONE);
				$item['username'] = getRequest('http_username', '');
				$item['password'] = getRequest('http_password', '');

				if ($posted['request_method'] == HTTPCHECK_REQUEST_HEAD) {
					$posted['retrieve_mode'] = HTTPTEST_STEP_RETRIEVE_MODE_HEADERS;
				}

				if ($posted['query_fields']) {
					$query_fields = [];

					foreach ($posted['query_fields']['name'] as $index => $key) {
						if (array_key_exists($index, $posted['query_fields']['value'])) {
							$query_fields[] = [$key => $posted['query_fields']['value'][$index]];
						}
					}

					// Ignore single row if it is empty.
					if (count($query_fields) == 1 && $key === '' && $posted['query_fields']['value'][$index] === '') {
						$query_fields = [];
					}

					$posted['query_fields'] = $query_fields;
				}

				if ($posted['headers']) {
					$headers = [];

					foreach ($posted['headers']['name'] as $index => $key) {
						if (array_key_exists($index, $posted['headers']['value'])) {
							$headers[$key] = $posted['headers']['value'][$index];
						}
					}

					// Ignore single row if it is empty.
					if (count($headers) == 1 && $key === '' && $posted['headers']['value'][$index] === '') {
						$headers = [];
					}

					$posted['headers'] = $headers;
				}

				$item += $posted;
			}

			if ($preprocessing) {
				$item['preprocessing'] = $preprocessing;
			}

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
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['itemprototype.massenable', 'itemprototype.massdisable']) && hasRequest('group_itemid')) {
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
elseif (hasRequest('action') && getRequest('action') == 'itemprototype.massdelete' && hasRequest('group_itemid')) {
	DBstart();

	$result = API::ItemPrototype()->delete(getRequest('group_itemid'));
	$result = DBend($result);

	if ($result) {
		uncheckTableRows(getRequest('parent_discoveryid'));
	}
	show_messages($result, _('Item prototypes deleted'), _('Cannot delete item prototypes'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$itemPrototype = [];
	$has_errors = false;
	$master_prototype_options = [];

	if (hasRequest('itemid')) {
		$itemPrototype = API::ItemPrototype()->get([
			'itemids' => getRequest('itemid'),
			'output' => [
				'itemid', 'type', 'snmp_community', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history',
				'trends', 'status', 'value_type', 'trapper_hosts', 'units', 'snmpv3_securityname',
				'snmpv3_securitylevel', 'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'logtimefmt', 'templateid',
				'valuemapid', 'params', 'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'interfaceid', 'port', 'description', 'snmpv3_authprotocol', 'snmpv3_privprotocol',
				'snmpv3_contextname', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'posts',
				'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode',
				'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password',
				'verify_peer', 'verify_host', 'allow_traps'
			],
			'selectPreprocessing' => ['type', 'params']
		]);
		$itemPrototype = reset($itemPrototype);
		foreach ($itemPrototype['preprocessing'] as &$step) {
			$step['params'] = explode("\n", $step['params']);
		}
		unset($step);

		if ($itemPrototype['type'] != ITEM_TYPE_JMX) {
			$itemPrototype['jmx_endpoint'] = ZBX_DEFAULT_JMX_ENDPOINT;
		}

		if ($itemPrototype['type'] == ITEM_TYPE_DEPENDENT) {
			$master_prototype_options = [
				'itemids' => $itemPrototype['master_itemid'],
				'output' => ['itemid', 'type', 'hostid', 'name', 'key_']
			];
		}
	}
	elseif (getRequest('master_itemid') && getRequest('parent_discoveryid')) {
		$discovery_rule = API::DiscoveryRule()->get([
			'output' => ['hostid'],
			'itemids' => getRequest('parent_discoveryid'),
			'editable' => true
		]);

		if ($discovery_rule) {
			$master_prototype_options = [
				'itemids' => getRequest('master_itemid'),
				'output' => ['itemid', 'type', 'hostid', 'name', 'key_'],
				'filter' => ['hostid' => $discovery_rule[0]['hostid']]
			];
		}
	}

	if ($master_prototype_options) {
		$master_prototypes = API::ItemPrototype()->get($master_prototype_options);

		if ($master_prototypes) {
			$itemPrototype['master_item'] = reset($master_prototypes);
		}
		else {
			show_messages(false, '', _('No permissions to referred object or it does not exist!'));
			$has_errors = true;
		}
	}

	$data = getItemFormData($itemPrototype);
	$data['config'] = select_config();
	$data['trends_default'] = DB::getDefault('items', 'trends');

	// Sort interfaces to be listed starting with one selected as 'main'.
	CArrayHelper::sort($data['interfaces'], [
		['field' => 'main', 'order' => ZBX_SORT_DOWN]
	]);

	// render view
	if (!$has_errors) {
		$itemView = new CView('configuration.item.prototype.edit', $data);
		$itemView->render();
		$itemView->show();
	}
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$config = select_config();

	$data = [
		'form' => getRequest('form'),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'hostid' => $discoveryRule['hostid'],
		'sort' => $sortField,
		'sortorder' => $sortOrder
	];

	$data['items'] = API::ItemPrototype()->get([
		'discoveryids' => $data['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'editable' => true,
		'selectApplications' => API_OUTPUT_EXTEND,
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
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

	$url = (new CUrl('disc_prototypes.php'))
		->setArgument('parent_discoveryid', $data['parent_discoveryid']);

	$data['paging'] = getPagingLine($data['items'], $sortOrder, $url);

	// render view
	$itemView = new CView('configuration.item.prototype.list', $data);
	$itemView->render();
	$itemView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
