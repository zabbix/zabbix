<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
$page['scripts'] = ['effects.js', 'class.cviewswitcher.js', 'multilineinput.js', 'multiselect.js', 'items.js',
	'textareaflexible.js'
];

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
												' && {type} != '.ITEM_TYPE_DEPENDENT,
										_('Update interval')
									],
	'delay_flex' =>					[T_ZBX_STR, O_OPT, null,	null,			null],
	'status' =>						[T_ZBX_INT, O_OPT, null,	IN([ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]), null],
	'type' =>						[T_ZBX_INT, O_OPT, null,
										IN([-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE,
											ITEM_TYPE_SNMPV2C, ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3,
											ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL,
											ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
											ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED, ITEM_TYPE_SNMPTRAP,
											ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT
										]),
										'isset({add}) || isset({update})'
									],
	'value_type' =>					[T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4'), 'isset({add}) || isset({update})'],
	'valuemapid' =>					[T_ZBX_INT, O_OPT, null,	DB_ID,
										'(isset({add}) || isset({update})) && isset({value_type})'.
											' && '.IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type')
									],
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
												ITEM_TYPE_CALCULATED, 'type'
											),
										getParamFieldLabelByType(getRequest('type', 0))
									],
	'snmp_community' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && '.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C, 'type'),
										_('SNMP community')
									],
	'snmp_oid' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && '.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3,
												'type'
											),
										_('SNMP OID')
									],
	'port' =>						[T_ZBX_STR, O_OPT, null,	BETWEEN(0, 65535),
										'(isset({add}) || isset({update})) && isset({type})'.
											' && '.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3,
												'type'
											),
										_('Port')
									],
	'snmpv3_securitylevel' =>		[T_ZBX_INT, O_OPT, null,	IN('0,1,2'),
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_SNMPV3
									],
	'snmpv3_contextname' =>			[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_SNMPV3
									],
	'snmpv3_securityname' =>		[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_SNMPV3
									],
	'snmpv3_authprotocol' =>		[T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHPROTOCOL_MD5.','.ITEM_AUTHPROTOCOL_SHA),
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_SNMPV3.
											' && ({snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.
												' || {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.
										')'
									],
	'snmpv3_authpassphrase' =>		[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_SNMPV3.
											' && ({snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.
												' || {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.
										')'
									],
	'snmpv3_privprotocol' =>		[T_ZBX_INT, O_OPT, null,	IN(ITEM_PRIVPROTOCOL_DES.','.ITEM_PRIVPROTOCOL_AES),
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_SNMPV3.
											' && {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV
									],
	'snmpv3_privpassphrase' =>		[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_SNMPV3.
											' && {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV
									],
	'ipmi_sensor' =>				[T_ZBX_STR, O_OPT, P_NO_TRIM,	NOT_EMPTY,
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
	'new_application' =>			[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'new_application_prototype' =>	[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({parent_discoveryid})'
									],
	'applications' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'application_prototypes' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'massupdate_app_action' =>		[T_ZBX_INT, O_OPT, null,
										IN([ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
										null
									],
	'massupdate_app_prot_action' =>	[T_ZBX_INT, O_OPT, null,
										IN([ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
										null
									],
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
	'timeout' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'url' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_HTTPAGENT,
										_('URL')
									],
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
										IN([HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM,
											HTTPTEST_AUTH_KERBEROS
										]),
										null
									],
	'http_username' =>				[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({http_authtype})'.
											' && ({http_authtype} == '.HTTPTEST_AUTH_BASIC.
												' || {http_authtype} == '.HTTPTEST_AUTH_NTLM.
												' || {http_authtype} == '.HTTPTEST_AUTH_KERBEROS.
											')',
										_('Username')
									],
	'http_password' =>				[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({http_authtype})'.
											' && ({http_authtype} == '.HTTPTEST_AUTH_BASIC.
												' || {http_authtype} == '.HTTPTEST_AUTH_NTLM.
												' || {http_authtype} == '.HTTPTEST_AUTH_KERBEROS.
											')',
										_('Password')
									],
	'visible' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	// actions
	'action' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
										IN('"itemprototype.massdelete","itemprototype.massdisable",'.
											'"itemprototype.massenable","itemprototype.massupdateform"'
										),
										null
									],
	'add' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'massupdate' =>					[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
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
					info(_s('Invalid interval "%1$s".', $interval['delay']));
					break;
				}
				elseif ($time_period_parser->parse($interval['period']) != CParser::PARSE_SUCCESS) {
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

				if ($scheduling_interval_parser->parse($interval['schedule']) != CParser::PARSE_SUCCESS) {
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
				case ZBX_PREPROC_PROMETHEUS_TO_JSON:
					$step['params'] = trim($step['params'][0]);
					break;

				case ZBX_PREPROC_RTRIM:
				case ZBX_PREPROC_LTRIM:
				case ZBX_PREPROC_TRIM:
				case ZBX_PREPROC_XPATH:
				case ZBX_PREPROC_JSONPATH:
				case ZBX_PREPROC_VALIDATE_REGEX:
				case ZBX_PREPROC_VALIDATE_NOT_REGEX:
				case ZBX_PREPROC_ERROR_FIELD_JSON:
				case ZBX_PREPROC_ERROR_FIELD_XML:
				case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
				case ZBX_PREPROC_SCRIPT:
					$step['params'] = $step['params'][0];
					break;

				case ZBX_PREPROC_VALIDATE_RANGE:
				case ZBX_PREPROC_PROMETHEUS_PATTERN:
					foreach ($step['params'] as &$param) {
						$param = trim($param);
					}
					unset($param);

					$step['params'] = implode("\n", $step['params']);
					break;

				case ZBX_PREPROC_REGSUB:
				case ZBX_PREPROC_ERROR_FIELD_REGEX:
					$step['params'] = implode("\n", $step['params']);
					break;

				default:
					$step['params'] = '';
			}

			$step += [
				'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
				'error_handler_params' => ''
			];
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
			'history'		=> (getRequest('history_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF)
				? ITEM_NO_STORAGE_VALUE
				: getRequest('history'),
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
			$item['trends'] = (getRequest('trends_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF)
				? ITEM_NO_STORAGE_VALUE
				: getRequest('trends');
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
				'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
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
				$item = prepareItemHttpAgentFormData($http_item) + $item;
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
				$item = prepareItemHttpAgentFormData($http_item) + $item;
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
elseif ($valid_input && hasRequest('massupdate') && hasRequest('group_itemid')) {
	$visible = getRequest('visible', []);
	$item_prototypeids = getRequest('group_itemid');
	$result = true;

	$applications = getRequest('applications', []);
	$applicationids = [];

	$application_prototypes = getRequest('application_prototypes', []);
	$application_prototypeids = [];

	if (isset($visible['delay'])) {
		$delay = getRequest('delay', DB::getDefault('items', 'delay'));

		if (hasRequest('delay_flex')) {
			$intervals = [];
			$simple_interval_parser = new CSimpleIntervalParser(['usermacros' => true]);
			$time_period_parser = new CTimePeriodParser(['usermacros' => true]);
			$scheduling_interval_parser = new CSchedulingIntervalParser(['usermacros' => true]);

			foreach (getRequest('delay_flex') as $interval) {
				if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
					if ($interval['delay'] === '' && $interval['period'] === '') {
						continue;
					}

					if ($simple_interval_parser->parse($interval['delay']) != CParser::PARSE_SUCCESS) {
						$result = false;
						info(_s('Invalid interval "%1$s".', $interval['delay']));
						break;
					}
					elseif ($time_period_parser->parse($interval['period']) != CParser::PARSE_SUCCESS) {
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

					if ($scheduling_interval_parser->parse($interval['schedule']) != CParser::PARSE_SUCCESS) {
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
	}
	else {
		$delay = null;
	}

	if ($result) {
		try {
			DBstart();

			// Collect submitted applications and create new applications if necessary.
			if (array_key_exists('applications', $visible)) {
				$massupdate_app_action = getRequest('massupdate_app_action');

				if ($massupdate_app_action == ZBX_ACTION_ADD || $massupdate_app_action == ZBX_ACTION_REPLACE) {
					$new_applications = [];

					foreach ($applications as $application) {
						if (is_array($application) && array_key_exists('new', $application)) {
							$new_applications[] = [
								'name' => $application['new'],
								'hostid' => getRequest('hostid')
							];
						}
						else {
							$applicationids[] = $application;
						}
					}

					if ($new_applications) {
						if ($new_application = API::Application()->create($new_applications)) {
							$applicationids = array_merge($applicationids, $new_application['applicationids']);
						}
						else {
							throw new Exception();
						}
					}
				}
				else {
					foreach ($applications as $application) {
						$applicationids[] = $application;
					}
				}
			}

			// Collect submitted application prototypes.
			if (array_key_exists('applicationPrototypes', $visible)) {
				$massupdate_app_prot_action = getRequest('massupdate_app_prot_action');

				if ($massupdate_app_prot_action == ZBX_ACTION_ADD
						|| $massupdate_app_prot_action == ZBX_ACTION_REPLACE) {
					$new_application_prototypes = [];

					foreach ($application_prototypes as $application_prototype) {
						if (is_array($application_prototype) && array_key_exists('new', $application_prototype)) {
							$new_application_prototypes[] = [
								'name' => $application_prototype['new'],
							];
						}
						else {
							$application_prototypeids[] = $application_prototype;
						}
					}
				}
				else {
					foreach ($application_prototypes as $application_prototype) {
						$application_prototypeids[] = $application_prototype;
					}
				}
			}

			$item_prototypes = API::ItemPrototype()->get([
				'output' => ['itemid', 'type'],
				'selectApplications' => ['applicationid'],
				'selectApplicationPrototypes' => ['application_prototypeid', 'name'],
				'itemids' => $item_prototypeids,
				'preservekeys' => true
			]);

			$item_prototypes_to_update = [];

			if ($item_prototypes) {
				$item_prototype = [
					'interfaceid' => getRequest('interfaceid'),
					'description' => getRequest('description'),
					'delay' => $delay,
					'history' => (getRequest('history_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF)
						? ITEM_NO_STORAGE_VALUE
						: getRequest('history'),
					'type' => getRequest('type'),
					'snmp_community' => getRequest('snmp_community'),
					'snmp_oid' => getRequest('snmp_oid'),
					'value_type' => getRequest('value_type'),
					'trapper_hosts' => getRequest('trapper_hosts'),
					'port' => getRequest('port'),
					'units' => getRequest('units'),
					'snmpv3_contextname' => getRequest('snmpv3_contextname'),
					'snmpv3_securityname' => getRequest('snmpv3_securityname'),
					'snmpv3_securitylevel' => getRequest('snmpv3_securitylevel'),
					'snmpv3_authprotocol' => getRequest('snmpv3_authprotocol'),
					'snmpv3_authpassphrase' => getRequest('snmpv3_authpassphrase'),
					'snmpv3_privprotocol' => getRequest('snmpv3_privprotocol'),
					'snmpv3_privpassphrase' => getRequest('snmpv3_privpassphrase'),
					'trends' => (getRequest('trends_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF)
						? ITEM_NO_STORAGE_VALUE
						: getRequest('trends'),
					'logtimefmt' => getRequest('logtimefmt'),
					'valuemapid' => getRequest('valuemapid'),
					'authtype' => getRequest('authtype'),
					'jmx_endpoint' => getRequest('jmx_endpoint'),
					'username' => getRequest('username'),
					'password' => getRequest('password'),
					'publickey' => getRequest('publickey'),
					'privatekey' => getRequest('privatekey'),
					'applications' => [],
					'applicationPrototypes' => [],
					'status' => getRequest('status'),
					'master_itemid' => getRequest('master_itemid'),
					'url' =>  getRequest('url'),
					'post_type' => getRequest('post_type'),
					'posts' => getRequest('posts'),
					'headers' => getRequest('headers', []),
					'allow_traps' => getRequest('allow_traps', HTTPCHECK_ALLOW_TRAPS_OFF),
					'preprocessing' => []
				];

				if ($item_prototype['headers']) {
					$headers = [];

					foreach ($item_prototype['headers']['name'] as $index => $key) {
						if (array_key_exists($index, $item_prototype['headers']['value'])) {
							$headers[$key] = $item_prototype['headers']['value'][$index];
						}
					}

					// Ignore single row if it is empty.
					if (count($headers) == 1 && $key === '' && $item_prototype['headers']['value'][$index] === '') {
						$headers = [];
					}

					$item_prototype['headers'] = $headers;
				}

				if (hasRequest('preprocessing')) {
					$preprocessing = getRequest('preprocessing');

					foreach ($preprocessing as &$step) {
						switch ($step['type']) {
							case ZBX_PREPROC_MULTIPLIER:
							case ZBX_PREPROC_PROMETHEUS_TO_JSON:
								$step['params'] = trim($step['params'][0]);
								break;

							case ZBX_PREPROC_RTRIM:
							case ZBX_PREPROC_LTRIM:
							case ZBX_PREPROC_TRIM:
							case ZBX_PREPROC_XPATH:
							case ZBX_PREPROC_JSONPATH:
							case ZBX_PREPROC_VALIDATE_REGEX:
							case ZBX_PREPROC_VALIDATE_NOT_REGEX:
							case ZBX_PREPROC_ERROR_FIELD_JSON:
							case ZBX_PREPROC_ERROR_FIELD_XML:
							case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
							case ZBX_PREPROC_SCRIPT:
								$step['params'] = $step['params'][0];
								break;

							case ZBX_PREPROC_VALIDATE_RANGE:
							case ZBX_PREPROC_PROMETHEUS_PATTERN:
								foreach ($step['params'] as &$param) {
									$param = trim($param);
								}
								unset($param);

								$step['params'] = implode("\n", $step['params']);
								break;

							case ZBX_PREPROC_REGSUB:
							case ZBX_PREPROC_ERROR_FIELD_REGEX:
								$step['params'] = implode("\n", $step['params']);
								break;

							default:
								$step['params'] = '';
						}

						$step += [
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						];
					}
					unset($step);

					$item_prototype['preprocessing'] = $preprocessing;
				}

				// Check "visible" for differences and update only necessary fields.
				$item_prototype = array_intersect_key($item_prototype, $visible);

				foreach ($item_prototypeids as $item_prototypeid) {
					if (array_key_exists($item_prototypeid, $item_prototypes)) {
						if ($item_prototype) {
							// Process applications.
							if (array_key_exists('applications', $visible)) {
								if ($applicationids) {
									// If there are existing applications submitted.
									$db_applicationids = zbx_objectValues(
										$item_prototypes[$item_prototypeid]['applications'],
										'applicationid'
									);

									switch ($massupdate_app_action) {
										case ZBX_ACTION_ADD:
											$upd_applicationids = array_merge($applicationids, $db_applicationids);
											break;

										case ZBX_ACTION_REPLACE:
											$upd_applicationids = $applicationids;
											break;

										case ZBX_ACTION_REMOVE:
											$upd_applicationids = array_diff($db_applicationids, $applicationids);
											break;
									}

									/*
									 * $upd_applicationids now contains new and existing application IDs depeding on
									 * operation we want to perform.
									 */
									$item_prototype['applications'] = array_keys(array_flip($upd_applicationids));
								}
								else {
									/*
									 * No applications were submitted in form. In case we want to replace applications,
									 * leave $item['applications'] empty, remove it otherwise.
									 */
									if ($massupdate_app_action == ZBX_ACTION_ADD
											|| $massupdate_app_action == ZBX_ACTION_REMOVE) {
										unset($item_prototype['applications']);
									}
								}
							}

							// Process application prototypes.
							if (array_key_exists('applicationPrototypes', $visible)) {
								$ex_application_prototypes
									= $item_prototypes[$item_prototypeid]['applicationPrototypes'];
								$ex_application_prototypeids = zbx_objectValues($ex_application_prototypes,
									'application_prototypeid'
								);
								$upd_application_prototypeids = [];
								$application_prototypes = [];

								switch ($massupdate_app_prot_action) {
									case ZBX_ACTION_ADD:
										// Append submitted existing application prototypes.
										if ($application_prototypeids) {
											$upd_application_prototypeids = array_unique(
												array_merge($application_prototypeids, $ex_application_prototypeids)
											);
										}

										// Append new application prototypes.
										if ($new_application_prototypes) {
											foreach ($new_application_prototypes as $new_application_prototype) {
												if (!in_array($new_application_prototype['name'],
														zbx_objectValues($application_prototypes, 'name'))) {
													$application_prototypes[] = $new_application_prototype;
												}
											}
										}

										// Append already existing application prototypes so that they are not deleted.
										if (($upd_application_prototypeids || $new_application_prototypes)
												&& $ex_application_prototypes) {
											foreach ($ex_application_prototypes as $db_application_prototype) {
												$application_prototypes[] = $db_application_prototype;
											}
										}
										break;

									case ZBX_ACTION_REPLACE:
										if ($application_prototypeids) {
											$upd_application_prototypeids = $application_prototypeids;
										}

										if ($new_application_prototypes) {
											foreach ($new_application_prototypes as $new_application_prototype) {
												if (!in_array($new_application_prototype['name'],
														zbx_objectValues($application_prototypes, 'name'))) {
													$application_prototypes[] = $new_application_prototype;
												}
											}
										}
										break;

									case ZBX_ACTION_REMOVE:
										if ($application_prototypeids) {
											$upd_application_prototypeids = array_diff($ex_application_prototypeids,
												$application_prototypeids
											);
										}
										break;
								}

								/*
								 * There might be added an existing application prototype that belongs to the discovery
								 * rule, not just chosen application prototypes ($ex_application_prototypes).
								 */
								if ($upd_application_prototypeids) {
									// Collect existing application prototype names. Those are required by API.
									$db_application_prototypes = DBfetchArray(DBselect(
										'SELECT ap.application_prototypeid,ap.name'.
										' FROM application_prototype ap'.
										' WHERE '.dbConditionId('ap.application_prototypeid',
											$upd_application_prototypeids
										)
									));

									// Append those application prototypes to update list.
									foreach ($db_application_prototypes as $db_application_prototype) {
										if (!in_array($db_application_prototype['application_prototypeid'],
												zbx_objectValues($application_prototypes,
													'application_prototypeid'))) {
											$application_prototypes[] = $db_application_prototype;
										}
									}
								}

								if ($application_prototypes) {
									$item_prototype['applicationPrototypes'] = $application_prototypes;
								}
								else {
									if ($massupdate_app_prot_action == ZBX_ACTION_REPLACE) {
										$item_prototype['applicationPrototypes'] = [];
									}
									else {
										unset($item_prototype['applicationPrototypes']);
									}
								}
							}

							$item_prototypes_to_update[] = ['itemid' => $item_prototypeid] + $item_prototype;
						}
					}
				}
			}

			if ($item_prototypes_to_update) {
				foreach ($item_prototypes_to_update as &$update_item_prototype) {
					$type = array_key_exists('type', $update_item_prototype)
						? $update_item_prototype['type']
						: $item_prototypes[$update_item_prototype['itemid']]['type'];

					if ($type != ITEM_TYPE_JMX) {
						unset($update_item_prototype['jmx_endpoint']);
					}
				}
				unset($update_item_prototype);

				$result = API::ItemPrototype()->update($item_prototypes_to_update);
			}
		}
		catch (Exception $e) {
			$result = false;
		}

		$result = DBend($result);
	}

	if ($result) {
		unset($_REQUEST['group_itemid'], $_REQUEST['massupdate'], $_REQUEST['form']);
		uncheckTableRows(getRequest('parent_discoveryid'));
	}
	show_messages($result, _('Item prototypes updated'), _('Cannot update item prototypes'));
}

if (hasRequest('action') && getRequest('action') !== 'itemprototype.massupdateform' && hasRequest('group_itemid')
		&& !$result) {
	$item_prototypes = API::ItemPrototype()->get([
		'itemids' => getRequest('group_itemid'),
		'output' => [],
		'editable' => true
	]);
	uncheckTableRows(getRequest('parent_discoveryid'), zbx_objectValues($item_prototypes, 'itemid'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$itemPrototype = [];
	$has_errors = false;

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
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params']
		]);
		$itemPrototype = reset($itemPrototype);

		foreach ($itemPrototype['preprocessing'] as &$step) {
			if ($step['type'] == ZBX_PREPROC_SCRIPT) {
				$step['params'] = [$step['params'], ''];
			}
			else {
				$step['params'] = explode("\n", $step['params']);
			}
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

	$data = getItemFormData($itemPrototype);
	$data['config'] = select_config();
	$data['preprocessing_test_type'] = CControllerPopupPreprocTestEdit::ZBX_TEST_TYPE_ITEM_PROTOTYPE;
	$data['preprocessing_types'] = CItemPrototype::$supported_preprocessing_types;
	$data['trends_default'] = DB::getDefault('items', 'trends');

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
elseif (((hasRequest('action') && getRequest('action') === 'itemprototype.massupdateform') || hasRequest('massupdate'))
		&& hasRequest('group_itemid')) {
	$data = [
		'form' => getRequest('form'),
		'action' => 'itemprototype.massupdateform',
		'hostid' => getRequest('hostid', 0),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'item_prototypeids' => getRequest('group_itemid', []),
		'description' => getRequest('description', ''),
		'delay' => getRequest('delay', ZBX_ITEM_DELAY_DEFAULT),
		'delay_flex' => getRequest('delay_flex', []),
		'history' => getRequest('history', DB::getDefault('items', 'history')),
		'status' => getRequest('status', 0),
		'type' => getRequest('type', 0),
		'interfaceid' => getRequest('interfaceid', 0),
		'snmp_community' => getRequest('snmp_community', 'public'),
		'port' => getRequest('port', ''),
		'value_type' => getRequest('value_type', ITEM_VALUE_TYPE_UINT64),
		'trapper_hosts' => getRequest('trapper_hosts', ''),
		'units' => getRequest('units', ''),
		'authtype' => getRequest('authtype', ''),
		'jmx_endpoint' => getRequest('jmx_endpoint', ''),
		'username' => getRequest('username', ''),
		'password' => getRequest('password', ''),
		'publickey' => getRequest('publickey', ''),
		'privatekey' => getRequest('privatekey', ''),
		'valuemapid' => getRequest('valuemapid', 0),
		'trends' => getRequest('trends', DB::getDefault('items', 'trends')),
		'applications' => [],
		'application_prototypes' => [],
		'snmpv3_contextname' => getRequest('snmpv3_contextname', ''),
		'snmpv3_securityname' => getRequest('snmpv3_securityname', ''),
		'snmpv3_securitylevel' => getRequest('snmpv3_securitylevel', 0),
		'snmpv3_authprotocol' => getRequest('snmpv3_authprotocol', ITEM_AUTHPROTOCOL_MD5),
		'snmpv3_authpassphrase' => getRequest('snmpv3_authpassphrase', ''),
		'snmpv3_privprotocol' => getRequest('snmpv3_privprotocol', ITEM_PRIVPROTOCOL_DES),
		'snmpv3_privpassphrase' => getRequest('snmpv3_privpassphrase', ''),
		'logtimefmt' => getRequest('logtimefmt', ''),
		'preprocessing' => getRequest('preprocessing', []),
		'initial_item_type' => null,
		'multiple_interface_types' => false,
		'visible' => getRequest('visible', []),
		'master_itemid' => getRequest('master_itemid', 0),
		'url' =>  getRequest('url', ''),
		'post_type' => getRequest('post_type', DB::getDefault('items', 'post_type')),
		'posts' => getRequest('posts', ''),
		'headers' => getRequest('headers', []),
		'allow_traps' => getRequest('allow_traps', HTTPCHECK_ALLOW_TRAPS_OFF),
		'massupdate_app_action' => getRequest('massupdate_app_action', ZBX_ACTION_ADD),
		'massupdate_app_prot_action' => getRequest('massupdate_app_prot_action', ZBX_ACTION_ADD),
		'preprocessing_test_type' => CControllerPopupPreprocTestEdit::ZBX_TEST_TYPE_ITEM_PROTOTYPE,
		'preprocessing_types' => CItemPrototype::$supported_preprocessing_types,
		'preprocessing_script_maxlength' => DB::getFieldLength('item_preproc', 'params')
	];

	foreach ($data['preprocessing'] as &$step) {
		$step += [
			'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
			'error_handler_params' => ''
		];
	}
	unset($step);

	if (hasRequest('applications')) {
		$applicationids = [];

		foreach (getRequest('applications') as $application) {
			if (is_array($application) && array_key_exists('new', $application)) {
				$data['applications'][] = [
					'id' => $application['new'],
					'name' => $application['new'].' ('._x('new', 'new element in multiselect').')',
					'isNew' => true
				];
			}
			else {
				$applicationids[] = $application;
			}
		}

		$data['applications'] = array_merge($data['applications'], $applicationids
			? CArrayHelper::renameObjectsKeys(API::Application()->get([
				'output' => ['applicationid', 'name'],
				'applicationids' => $applicationids
			]), ['applicationid' => 'id'])
			: []);
	}

	if (hasRequest('application_prototypes')) {
		$application_prototypeids = [];

		foreach (getRequest('application_prototypes') as $application_prototype) {
			if (is_array($application_prototype) && array_key_exists('new', $application_prototype)) {
				$data['application_prototypes'][] = [
					'id' => $application_prototype['new'],
					'name' => $application_prototype['new'].' ('._x('new', 'new element in multiselect').')',
					'isNew' => true
				];
			}
			else {
				$application_prototypeids[] = $application_prototype;
			}
		}

		$data['application_prototypes'] = array_merge($data['application_prototypes'], $application_prototypeids
			? CArrayHelper::renameObjectsKeys(
				DBfetchArray(DBselect(
					'SELECT ap.application_prototypeid,ap.name'.
					' FROM application_prototype ap'.
					' WHERE '.dbConditionId('ap.application_prototypeid', $application_prototypeids)
				)), ['application_prototypeid' => 'id'])
			: []);
	}

	if ($data['headers']) {
		$headers = [];

		foreach ($data['headers']['name'] as $index => $key) {
			if (array_key_exists($index, $data['headers']['value'])) {
				$headers[] = [$key => $data['headers']['value'][$index]];
			}
		}

		// Ignore single row if it is empty.
		if (count($headers) == 1 && $key === '' && $data['headers']['value'][$index] === '') {
			$headers = [];
		}

		$data['headers'] = $headers;
	}

	// hosts
	$data['hosts'] = API::Host()->get([
		'output' => ['hostid'],
		'itemids' => $data['item_prototypeids'],
		'selectInterfaces' => ['interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port']
	]);

	$data['display_interfaces'] = true;

	$templates = API::Template()->get([
		'output' => ['templateid'],
		'itemids' => $data['item_prototypeids']
	]);

	if ($templates) {
		$data['display_interfaces'] = false;

		if ($data['hostid'] == 0) {
			// If selected from filter without 'hostid'.
			$templates = reset($templates);
			$data['hostid'] = $templates['templateid'];
		}
	}

	if ($data['display_interfaces']) {
		$data['hosts'] = reset($data['hosts']);

		// Sort interfaces to be listed starting with one selected as 'main'.
		CArrayHelper::sort($data['hosts']['interfaces'], [
			['field' => 'main', 'order' => ZBX_SORT_DOWN]
		]);

		// If selected from filter without 'hostid'.
		if ($data['hostid'] == 0) {
			$data['hostid'] = $data['hosts']['hostid'];
		}

		// Set the initial chosen interface to one of the interfaces the items use.
		$item_prototypes = API::ItemPrototype()->get([
			'output' => ['itemid', 'type', 'name'],
			'itemids' => $data['item_prototypeids']
		]);
		$used_interface_types = [];

		foreach ($item_prototypes as $item_prototype) {
			$used_interface_types[$item_prototype['type']] = itemTypeInterface($item_prototype['type']);
		}

		$initial_type = min(array_keys($used_interface_types));
		$data['type'] = (getRequest('type') !== null) ? $data['type'] : $initial_type;
		$data['initial_item_type'] = $initial_type;
		$data['multiple_interface_types'] = (count(array_unique($used_interface_types)) > 1);
	}

	if ($data['master_itemid'] != 0) {
		$master_prototypes = API::Item()->get([
			'output' => ['itemid', 'hostid', 'name', 'key_'],
			'selectHosts' => ['name'],
			'itemids' => [$data['master_itemid']],
			'hostids' => [$data['hostid']],
			'webitems' => true
		])
		+ API::ItemPrototype()->get([
			'output' => ['itemid', 'hostid', 'name', 'key_'],
			'selectHosts' => ['name'],
			'itemids' => getRequest('master_itemid', $data['master_itemid'])
		]);

		if ($master_prototypes) {
			$data['master_itemname'] = $master_prototypes[0]['name'];
			$data['master_hostname'] = $master_prototypes[0]['hosts'][0]['name'];
		}
		else {
			$data['master_itemid'] = 0;
			show_messages(false, '', _('No permissions to referred object or it does not exist!'));
		}
	}

	// item types
	$data['itemTypes'] = item_type2str();
	unset($data['itemTypes'][ITEM_TYPE_HTTPTEST]);

	// valuemap
	$data['valuemaps'] = API::ValueMap()->get([
		'output' => ['valuemapid', 'name']
	]);
	CArrayHelper::sort($data['valuemaps'], ['name']);

	if (!$data['delay_flex']) {
		$data['delay_flex'][] = ['delay' => '', 'period' => '', 'type' => ITEM_DELAY_FLEXIBLE];
	}

	$data['jmx_endpoint'] = ZBX_DEFAULT_JMX_ENDPOINT;

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
		$data['trends'] = DB::getDefault('items', 'trends');
	}
	else {
		$data['trends_mode'] = getRequest('trends_mode', ITEM_STORAGE_CUSTOM);
	}

	$view = (new CView('configuration.item.prototype.massupdate', $data))
		->render()
		->show();
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
	$data['parent_templates'] = getItemParentTemplates($data['items'], ZBX_FLAG_DISCOVERY_PROTOTYPE);

	// render view
	$itemView = new CView('configuration.item.prototype.list', $data);
	$itemView->render();
	$itemView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
