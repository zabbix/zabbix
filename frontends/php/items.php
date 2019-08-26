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

$page['title'] = _('Configuration of items');
$page['file'] = 'items.php';
$page['scripts'] = ['class.cviewswitcher.js', 'multilineinput.js', 'multiselect.js', 'items.js', 'textareaflexible.js'];

require_once dirname(__FILE__).'/include/page_header.php';

$paramsFieldName = getParamFieldNameByType(getRequest('type', 0));

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hostid' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID.NOT_ZERO, 'isset({form}) && !isset({itemid})'],
	'interfaceid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null, _('Interface')],
	'copy_type' =>					[T_ZBX_INT, O_OPT, P_SYS,
										IN([COPY_TYPE_TO_HOST_GROUP, COPY_TYPE_TO_HOST, COPY_TYPE_TO_TEMPLATE]),
										'isset({copy})'
									],
	'copy_mode' =>					[T_ZBX_INT, O_OPT, P_SYS,	IN('0'),	null],
	'itemid' =>						[T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'],
	'name' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({add}) || isset({update})',
										_('Name')
									],
	'description' =>				[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'key' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({add}) || isset({update})', _('Key')],
	'master_itemid' =>				[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_DEPENDENT,
										_('Master item')
									],
	'delay' =>						[T_ZBX_TU, O_OPT, P_ALLOW_USER_MACRO, null,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} != '.ITEM_TYPE_TRAPPER.' && {type} != '.ITEM_TYPE_SNMPTRAP.
											' && {type} != '.ITEM_TYPE_DEPENDENT,
										_('Update interval')
									],
	'delay_flex' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'history_mode' =>				[T_ZBX_INT, O_OPT, null,	IN([ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]), null],
	'history' =>					[T_ZBX_STR, O_OPT, null,	null, '(isset({add}) || isset({update}))'.
										' && isset({history_mode}) && {history_mode}=='.ITEM_STORAGE_CUSTOM,
										_('History storage period')
									],
	'status' =>						[T_ZBX_INT, O_OPT, null,	IN([ITEM_STATUS_DISABLED, ITEM_STATUS_ACTIVE]), null],
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
	'trends_mode' =>				[T_ZBX_INT, O_OPT, null,	IN([ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]), null],
	'trends' =>						[T_ZBX_STR, O_OPT, null,	null,	'(isset({add}) || isset({update}))'.
										' && isset({trends_mode}) && {trends_mode}=='.ITEM_STORAGE_CUSTOM.
										' && isset({value_type})'.
										' && '.IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type'),
										_('Trend storage period')
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
	'publickey' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_SSH.' && {authtype} == '.ITEM_AUTHTYPE_PUBLICKEY
									],
	'privatekey' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
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
	'inventory_link' =>				[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535),
										'(isset({add}) || isset({update})) && {value_type} != '.ITEM_VALUE_TYPE_LOG
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
											' &&  {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV
									],
	'snmpv3_privpassphrase' =>		[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_SNMPV3.
											' && {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV
									],
	'ipmi_sensor' =>				[T_ZBX_STR, O_OPT, P_NO_TRIM, NOT_EMPTY,
										'(isset({add}) || isset({update})) && isset({type})'.
											' && {type} == '.ITEM_TYPE_IPMI,
										_('IPMI sensor')
									],
	'trapper_hosts' =>				[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update}))'.
											' && isset({type}) && {type} == '.ITEM_TYPE_TRAPPER
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
	'copy_targetids' =>				[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'new_application' =>			[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'visible' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'applications' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'massupdate_app_action' =>		[T_ZBX_INT, O_OPT, null,
										IN([ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
										null
									],
	'del_history' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
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
										IN([HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON]), null
									],
	'ssl_cert_file' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'ssl_key_file' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'ssl_key_password' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'verify_peer' =>				[T_ZBX_INT, O_OPT, null, IN([HTTPTEST_VERIFY_PEER_OFF, HTTPTEST_VERIFY_PEER_ON]),
										null
									],
	'verify_host' =>				[T_ZBX_INT, O_OPT, null, IN([HTTPTEST_VERIFY_HOST_OFF, HTTPTEST_VERIFY_HOST_ON]),
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
												' || {http_authtype} == '.HTTPTEST_AUTH_KERBEROS.')',
										_('Username')
									],
	'http_password' =>				[T_ZBX_STR, O_OPT, null,	null,
										'(isset({add}) || isset({update})) && isset({http_authtype})'.
											' && ({http_authtype} == '.HTTPTEST_AUTH_BASIC.
												' || {http_authtype} == '.HTTPTEST_AUTH_NTLM.
												' || {http_authtype} == '.HTTPTEST_AUTH_KERBEROS.')',
										_('Password')
									],
	// actions
	'action' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
										IN('"item.massclearhistory","item.masscopyto","item.massdelete",'.
											'"item.massdisable","item.massenable","item.massupdateform",'.
											'"item.masscheck_now"'
										),
										null
									],
	'add' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'copy' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'massupdate' =>					[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'cancel' =>						[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'check_now' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'form' =>						[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>				[T_ZBX_INT, O_OPT, null,	null,		null],
	// filter
	'filter_set' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_rst' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_groupids' =>			[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'filter_hostids' =>				[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'filter_application' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_name' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_type' =>				[T_ZBX_INT, O_OPT, null,
										IN([-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE,
											ITEM_TYPE_SNMPV2C, ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3,
											ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL,
											ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
											ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED, ITEM_TYPE_SNMPTRAP,
											ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT
										]),
										null
									],
	'filter_key' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_snmp_community' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_snmpv3_securityname' => [T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_snmp_oid' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_port' =>				[T_ZBX_INT, O_OPT, P_UNSET_EMPTY, BETWEEN(0, 65535), null, _('Port')],
	'filter_value_type' =>			[T_ZBX_INT, O_OPT, null,	IN('-1,0,1,2,3,4'), null],
	'filter_delay' =>				[T_ZBX_STR, O_OPT, P_UNSET_EMPTY, null, null, _('Update interval')],
	'filter_history' =>				[T_ZBX_STR, O_OPT, P_UNSET_EMPTY, null, null, _('History')],
	'filter_trends' =>				[T_ZBX_STR, O_OPT, P_UNSET_EMPTY, null, null, _('Trends')],
	'filter_status' =>				[T_ZBX_INT, O_OPT, null,	IN([-1, ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]),
										null
									],
	'filter_state' =>				[T_ZBX_INT, O_OPT, null,	IN([-1, ITEM_STATE_NORMAL, ITEM_STATE_NOTSUPPORTED]),
										null
									],
	'filter_templated_items' =>		[T_ZBX_INT, O_OPT, null,	IN('-1,0,1'), null],
	'filter_with_triggers' =>		[T_ZBX_INT, O_OPT, null,	IN('-1,0,1'), null],
	'filter_discovery' =>			[T_ZBX_INT, O_OPT, null,
										IN([-1, ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]),
										null
									],
	'filter_ipmi_sensor' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	// subfilters
	'subfilter_set' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'subfilter_apps' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'subfilter_types' =>			[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_value_types' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_status' =>			[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_state' =>			[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_templated_items' =>	[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_with_triggers' =>	[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_discovery' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_hosts' =>			[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_interval' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'subfilter_history' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'subfilter_trends' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'checkbox_hash' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	// sort and sortorder
	'sort' =>						[T_ZBX_STR, O_OPT, P_SYS,
										IN('"delay","history","key_","name","status","trends","type"'),
										null
									],
	'sortorder' =>					[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'), null]
];

$valid_input = check_fields($fields);

$_REQUEST['params'] = getRequest($paramsFieldName, '');
unset($_REQUEST[$paramsFieldName]);

$subfiltersList = ['subfilter_apps', 'subfilter_types', 'subfilter_value_types', 'subfilter_status',
	'subfilter_state', 'subfilter_templated_items', 'subfilter_with_triggers', 'subfilter_hosts', 'subfilter_interval',
	'subfilter_history', 'subfilter_trends', 'subfilter_discovery'
];

/*
 * Permissions
 */
$itemId = getRequest('itemid');
if ($itemId) {
	$items = API::Item()->get([
		'output' => ['itemid'],
		'selectHosts' => ['hostid', 'status'],
		'itemids' => $itemId,
		'editable' => true
	]);
	if (!$items) {
		access_deny();
	}
	$hosts = $items[0]['hosts'];
}
else {
	$hostId = getRequest('hostid');
	if ($hostId) {
		$hosts = API::Host()->get([
			'output' => ['hostid', 'status'],
			'hostids' => $hostId,
			'templated_hosts' => true,
			'editable' => true
		]);
		if (!$hosts) {
			access_deny();
		}
	}
}

// Set sub-groups of selected groups.
if (!empty($hosts)) {
	$host = reset($hosts);
	$_REQUEST['filter_hostids'] = [$host['hostid']];
}

/*
 * Filter
 */
if (hasRequest('filter_set')) {
	CProfile::updateArray('web.items.filter_groupids', getRequest('filter_groupids', []), PROFILE_TYPE_ID);
	CProfile::updateArray('web.items.filter_hostids', getRequest('filter_hostids', []), PROFILE_TYPE_ID);
	CProfile::update('web.items.filter_application', getRequest('filter_application', ''), PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_type', getRequest('filter_type', -1), PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_key', getRequest('filter_key', ''), PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_snmp_community', getRequest('filter_snmp_community', ''), PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_snmpv3_securityname', getRequest('filter_snmpv3_securityname', ''),
		PROFILE_TYPE_STR
	);
	CProfile::update('web.items.filter_snmp_oid', getRequest('filter_snmp_oid', ''), PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_port', getRequest('filter_port', ''), PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_value_type', getRequest('filter_value_type', -1), PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_delay', getRequest('filter_delay', ''), PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_history', getRequest('filter_history', ''), PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_trends', getRequest('filter_trends', ''), PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_status', getRequest('filter_status', -1), PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_state', getRequest('filter_state', -1), PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_templated_items', getRequest('filter_templated_items', -1), PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_with_triggers', getRequest('filter_with_triggers', -1), PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_discovery', getRequest('filter_discovery', -1), PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_ipmi_sensor', getRequest('filter_ipmi_sensor', ''), PROFILE_TYPE_STR);

	// subfilters
	foreach ($subfiltersList as $name) {
		$_REQUEST[$name] = [];
		CProfile::update('web.items.'.$name, '', PROFILE_TYPE_STR);
	}
}
elseif (hasRequest('filter_rst')) {
	DBStart();
	if (count(CProfile::getArray('web.items.filter_hostids', [])) != 1) {
		CProfile::deleteIdx('web.items.filter_hostids');
	}
	CProfile::deleteIdx('web.items.filter_groupids');
	CProfile::deleteIdx('web.items.filter_application');
	CProfile::deleteIdx('web.items.filter_name');
	CProfile::deleteIdx('web.items.filter_type');
	CProfile::deleteIdx('web.items.filter_key');
	CProfile::deleteIdx('web.items.filter_snmp_community');
	CProfile::deleteIdx('web.items.filter_snmpv3_securityname');
	CProfile::deleteIdx('web.items.filter_snmp_oid');
	CProfile::deleteIdx('web.items.filter_port');
	CProfile::deleteIdx('web.items.filter_value_type');
	CProfile::deleteIdx('web.items.filter_delay');
	CProfile::deleteIdx('web.items.filter_history');
	CProfile::deleteIdx('web.items.filter_trends');
	CProfile::deleteIdx('web.items.filter_status');
	CProfile::deleteIdx('web.items.filter_state');
	CProfile::deleteIdx('web.items.filter_templated_items');
	CProfile::deleteIdx('web.items.filter_with_triggers');
	CProfile::deleteIdx('web.items.filter_ipmi_sensor');
	CProfile::deleteIdx('web.items.filter_discovery');
	DBend();
}

$_REQUEST['filter_groupids'] = CProfile::getArray('web.items.filter_groupids', []);
$_REQUEST['filter_hostids'] = CProfile::getArray('web.items.filter_hostids', []);
$_REQUEST['filter_application'] = CProfile::get('web.items.filter_application', '');
$_REQUEST['filter_name'] = CProfile::get('web.items.filter_name', '');
$_REQUEST['filter_type'] = CProfile::get('web.items.filter_type', -1);
$_REQUEST['filter_key'] = CProfile::get('web.items.filter_key', '');
$_REQUEST['filter_snmp_community'] = CProfile::get('web.items.filter_snmp_community', '');
$_REQUEST['filter_snmpv3_securityname'] = CProfile::get('web.items.filter_snmpv3_securityname', '');
$_REQUEST['filter_snmp_oid'] = CProfile::get('web.items.filter_snmp_oid', '');
$_REQUEST['filter_port'] = CProfile::get('web.items.filter_port', '');
$_REQUEST['filter_value_type'] = CProfile::get('web.items.filter_value_type', -1);
$_REQUEST['filter_delay'] = CProfile::get('web.items.filter_delay', '');
$_REQUEST['filter_history'] = CProfile::get('web.items.filter_history', '');
$_REQUEST['filter_trends'] = CProfile::get('web.items.filter_trends', '');
$_REQUEST['filter_status'] = CProfile::get('web.items.filter_status', -1);
$_REQUEST['filter_state'] = CProfile::get('web.items.filter_state', -1);
$_REQUEST['filter_templated_items'] = CProfile::get('web.items.filter_templated_items', -1);
$_REQUEST['filter_discovery'] = CProfile::get('web.items.filter_discovery', -1);
$_REQUEST['filter_with_triggers'] = CProfile::get('web.items.filter_with_triggers', -1);
$_REQUEST['filter_ipmi_sensor'] = CProfile::get('web.items.filter_ipmi_sensor', '');

// subfilters
foreach ($subfiltersList as $name) {
	if (isset($_REQUEST['subfilter_set'])) {
		$_REQUEST[$name] = getRequest($name, []);
		CProfile::update('web.items.'.$name, implode(';', $_REQUEST[$name]), PROFILE_TYPE_STR);
	}
	else {
		$_REQUEST[$name] = [];
		$subfiltersVal = CProfile::get('web.items.'.$name);
		if (!zbx_empty($subfiltersVal)) {
			$_REQUEST[$name] = explode(';', $subfiltersVal);
			$_REQUEST[$name] = array_combine($_REQUEST[$name], $_REQUEST[$name]);
		}
	}
}

$filter_groupids = getSubGroups(getRequest('filter_groupids', []));
$filter_hostids = getRequest('filter_hostids');
if (!hasRequest('form') && $filter_hostids) {
	if (!isset($host)) {
		$host = API::Host()->get([
			'output' => ['hostid'],
			'hostids' => $filter_hostids
		]);
		if (!$host) {
			$host = API::Template()->get([
				'output' => ['templateid'],
				'templateids' => $filter_hostids
			]);
		}
		$host = reset($host);
	}
	if ($host) {
		$_REQUEST['hostid'] = isset($host['hostid']) ? $host['hostid'] : $host['templateid'];
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
$result = false;
if (isset($_REQUEST['delete']) && isset($_REQUEST['itemid'])) {
	$result = false;
	if ($item = get_item_by_itemid($_REQUEST['itemid'])) {
		$result = API::Item()->delete([getRequest('itemid')]);
	}

	if ($result) {
		uncheckTableRows(getRequest('checkbox_hash'));
	}
	unset($_REQUEST['itemid'], $_REQUEST['form']);
	show_messages($result, _('Item deleted'), _('Cannot delete item'));
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

	DBstart();
	$result = true;

	if (!zbx_empty($_REQUEST['new_application'])) {
		$new_appid = API::Application()->create([
			'name' => $_REQUEST['new_application'],
			'hostid' => getRequest('hostid')
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
	if (!in_array($type, [ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP])
			&& hasRequest('delay_flex')) {
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

	if ($result) {
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

		if (hasRequest('add')) {
			$item = [
				'hostid' => getRequest('hostid'),
				'name' => getRequest('name', ''),
				'type' => getRequest('type', ITEM_TYPE_ZABBIX),
				'key_' => getRequest('key', ''),
				'interfaceid' => getRequest('interfaceid', 0),
				'snmp_oid' => getRequest('snmp_oid', ''),
				'snmp_community' => getRequest('snmp_community', ''),
				'snmpv3_contextname' => getRequest('snmpv3_contextname', ''),
				'snmpv3_securityname' => getRequest('snmpv3_securityname', ''),
				'snmpv3_securitylevel' => getRequest('snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV),
				'snmpv3_authprotocol' => getRequest('snmpv3_authprotocol', ITEM_AUTHPROTOCOL_MD5),
				'snmpv3_authpassphrase' => getRequest('snmpv3_authpassphrase', ''),
				'snmpv3_privprotocol' => getRequest('snmpv3_privprotocol', ITEM_PRIVPROTOCOL_DES),
				'snmpv3_privpassphrase' => getRequest('snmpv3_privpassphrase', ''),
				'port' => getRequest('port', ''),
				'authtype' => getRequest('authtype', ITEM_AUTHTYPE_PASSWORD),
				'username' => getRequest('username', ''),
				'password' => getRequest('password', ''),
				'publickey' => getRequest('publickey', ''),
				'privatekey' => getRequest('privatekey', ''),
				'params' => getRequest('params', ''),
				'ipmi_sensor' => getRequest('ipmi_sensor', ''),
				'value_type' => getRequest('value_type', ITEM_VALUE_TYPE_FLOAT),
				'units' => getRequest('units', ''),
				'delay' => $delay,
				'history' => (getRequest('history_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF)
					? ITEM_NO_STORAGE_VALUE
					: getRequest('history', DB::getDefault('items', 'history')),
				'trends' => (getRequest('trends_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF)
					? ITEM_NO_STORAGE_VALUE
					: getRequest('trends', DB::getDefault('items', 'trends')),
				'valuemapid' => getRequest('valuemapid', 0),
				'logtimefmt' => getRequest('logtimefmt', ''),
				'trapper_hosts' => getRequest('trapper_hosts', ''),
				'applications' => $applications,
				'inventory_link' => getRequest('inventory_link', 0),
				'description' => getRequest('description', ''),
				'status' => getRequest('status', ITEM_STATUS_DISABLED),
			];

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

			if ($item['type'] == ITEM_TYPE_JMX) {
				$item['jmx_endpoint'] = getRequest('jmx_endpoint', '');
			}

			if ($preprocessing) {
				$item['preprocessing'] = $preprocessing;
			}

			if ($item['type'] == ITEM_TYPE_DEPENDENT) {
				$item['master_itemid'] = getRequest('master_itemid');
			}

			$result = (bool) API::Item()->create($item);
		}
		else {
			$db_items = API::Item()->get([
				'output' => ['name', 'type', 'key_', 'interfaceid', 'snmp_oid', 'snmp_community', 'snmpv3_contextname',
					'snmpv3_securityname', 'snmpv3_securitylevel', 'snmpv3_authprotocol', 'snmpv3_authpassphrase',
					'snmpv3_privprotocol', 'snmpv3_privpassphrase', 'port', 'authtype', 'username', 'password',
					'publickey', 'privatekey', 'params', 'ipmi_sensor', 'value_type', 'units', 'delay', 'history',
					'trends', 'valuemapid', 'logtimefmt', 'trapper_hosts', 'inventory_link', 'description', 'status',
					'templateid', 'flags', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'posts',
					'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode',
					'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password',
					'verify_peer', 'verify_host', 'allow_traps'
				],
				'selectApplications' => ['applicationid'],
				'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
				'itemids' => getRequest('itemid')
			]);
			$db_item = reset($db_items);

			$item = [];

			if ($db_item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
				if ($db_item['templateid'] == 0) {
					if ($db_item['name'] !== getRequest('name', '')) {
						$item['name'] = getRequest('name', '');
					}
					if ($db_item['type'] != getRequest('type', ITEM_TYPE_ZABBIX)) {
						$item['type'] = getRequest('type', ITEM_TYPE_ZABBIX);
					}
					if ($db_item['key_'] !== getRequest('key', '')) {
						$item['key_'] = getRequest('key', '');
					}
					if ($db_item['snmp_oid'] !== getRequest('snmp_oid', '')) {
						$item['snmp_oid'] = getRequest('snmp_oid', '');
					}
					if ($db_item['ipmi_sensor'] !== getRequest('ipmi_sensor', '')) {
						$item['ipmi_sensor'] = getRequest('ipmi_sensor', '');
					}
					if ($db_item['value_type'] != getRequest('value_type', ITEM_VALUE_TYPE_FLOAT)) {
						$item['value_type'] = getRequest('value_type', ITEM_VALUE_TYPE_FLOAT);
					}
					if ($db_item['units'] !== getRequest('units', '')) {
						$item['units'] = getRequest('units', '');
					}
					if (bccomp($db_item['valuemapid'], getRequest('valuemapid', 0)) != 0) {
						$item['valuemapid'] = getRequest('valuemapid', 0);
					}
					if ($db_item['logtimefmt'] !== getRequest('logtimefmt', '')) {
						$item['logtimefmt'] = getRequest('logtimefmt', '');
					}
				}

				if (bccomp($db_item['interfaceid'], getRequest('interfaceid', 0)) != 0) {
					$item['interfaceid'] = getRequest('interfaceid', 0);
				}
				if ($db_item['snmp_community'] !== getRequest('snmp_community', '')) {
					$item['snmp_community'] = getRequest('snmp_community', '');
				}
				if ($db_item['snmpv3_contextname'] !== getRequest('snmpv3_contextname', '')) {
					$item['snmpv3_contextname'] = getRequest('snmpv3_contextname', '');
				}
				if ($db_item['snmpv3_securityname'] !== getRequest('snmpv3_securityname', '')) {
					$item['snmpv3_securityname'] = getRequest('snmpv3_securityname', '');
				}
				$snmpv3_securitylevel = getRequest('snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
				if ($db_item['snmpv3_securitylevel'] != $snmpv3_securitylevel) {
					$item['snmpv3_securitylevel'] = $snmpv3_securitylevel;
				}
				$snmpv3_authprotocol = ($snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV)
					? ITEM_AUTHPROTOCOL_MD5
					: getRequest('snmpv3_authprotocol', ITEM_AUTHPROTOCOL_MD5);
				if ($db_item['snmpv3_authprotocol'] != $snmpv3_authprotocol) {
					$item['snmpv3_authprotocol'] = $snmpv3_authprotocol;
				}
				if ($db_item['snmpv3_authpassphrase'] !== getRequest('snmpv3_authpassphrase', '')) {
					$item['snmpv3_authpassphrase'] = getRequest('snmpv3_authpassphrase', '');
				}
				$snmpv3_privprotocol = ($snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV)
					? getRequest('snmpv3_privprotocol', ITEM_PRIVPROTOCOL_DES)
					: ITEM_AUTHPROTOCOL_MD5;
				if ($db_item['snmpv3_privprotocol'] != $snmpv3_privprotocol) {
					$item['snmpv3_privprotocol'] = $snmpv3_privprotocol;
				}
				if ($db_item['snmpv3_privpassphrase'] !== getRequest('snmpv3_privpassphrase', '')) {
					$item['snmpv3_privpassphrase'] = getRequest('snmpv3_privpassphrase', '');
				}
				if ($db_item['port'] !== getRequest('port', '')) {
					$item['port'] = getRequest('port', '');
				}
				if ($db_item['authtype'] != getRequest('authtype', ITEM_AUTHTYPE_PASSWORD)) {
					$item['authtype'] = getRequest('authtype', ITEM_AUTHTYPE_PASSWORD);
				}
				if ($db_item['username'] !== getRequest('username', '')) {
					$item['username'] = getRequest('username', '');
				}
				if ($db_item['password'] !== getRequest('password', '')) {
					$item['password'] = getRequest('password', '');
				}
				if ($db_item['publickey'] !== getRequest('publickey', '')) {
					$item['publickey'] = getRequest('publickey', '');
				}
				if ($db_item['privatekey'] !== getRequest('privatekey', '')) {
					$item['privatekey'] = getRequest('privatekey', '');
				}
				if ($db_item['params'] !== getRequest('params', '')) {
					$item['params'] = getRequest('params', '');
				}
				if ($db_item['delay'] != $delay) {
					$item['delay'] = $delay;
				}
				$def_item_history = (getRequest('history_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF)
					? ITEM_NO_STORAGE_VALUE
					: DB::getDefault('items', 'history');
				if ((string) $db_item['history'] !== (string) getRequest('history', $def_item_history)) {
					$item['history'] = getRequest('history', $def_item_history);
				}
				$def_item_trends = (getRequest('trends_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF)
					? ITEM_NO_STORAGE_VALUE
					: DB::getDefault('items', 'trends');
				if ((string) $db_item['trends'] !== (string) getRequest('trends', $def_item_trends)) {
					$item['trends'] = getRequest('trends', $def_item_trends);
				}
				if ($db_item['trapper_hosts'] !== getRequest('trapper_hosts', '')) {
					$item['trapper_hosts'] = getRequest('trapper_hosts', '');
				}
				if ($db_item['jmx_endpoint'] !== getRequest('jmx_endpoint', '')) {
					$item['jmx_endpoint'] = getRequest('jmx_endpoint', '');
				}
				$db_applications = zbx_objectValues($db_item['applications'], 'applicationid');
				natsort($db_applications);
				natsort($applications);
				if (array_values($db_applications) !== array_values($applications)) {
					$item['applications'] = $applications;
				}
				if ($db_item['inventory_link'] != getRequest('inventory_link', 0)) {
					$item['inventory_link'] = getRequest('inventory_link', 0);
				}
				if ($db_item['description'] !== getRequest('description', '')) {
					$item['description'] = getRequest('description', '');
				}
				if ($db_item['preprocessing'] !== $preprocessing) {
					$item['preprocessing'] = $preprocessing;
				}
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
			}

			if ($db_item['status'] != getRequest('status', ITEM_STATUS_DISABLED)) {
				$item['status'] = getRequest('status', ITEM_STATUS_DISABLED);
			}

			if (getRequest('type') == ITEM_TYPE_DEPENDENT && hasRequest('master_itemid')
					&& bccomp($db_item['master_itemid'], getRequest('master_itemid')) != 0) {
				$item['master_itemid'] = getRequest('master_itemid');
			}

			if ($item) {
				$item['itemid'] = getRequest('itemid');

				$result = (bool) API::Item()->update($item);
			}
			else {
				$result = true;
			}
		}
	}

	$result = DBend($result);

	if (hasRequest('add')) {
		show_messages($result, _('Item added'), _('Cannot add item'));
	}
	else {
		show_messages($result, _('Item updated'), _('Cannot update item'));
	}

	if ($result) {
		unset($_REQUEST['itemid'], $_REQUEST['form']);
		uncheckTableRows(getRequest('checkbox_hash'));
	}
}
elseif (hasRequest('check_now') && hasRequest('itemid')) {
	$result = (bool) API::Task()->create([
		'type' => ZBX_TM_TASK_CHECK_NOW,
		'itemids' => getRequest('itemid')
	]);

	show_messages($result, _('Request sent successfully'), _('Cannot send request'));
}
// cleaning history for one item
elseif (hasRequest('del_history') && hasRequest('itemid')) {
	$result = false;

	$itemId = getRequest('itemid');

	$items = API::Item()->get([
		'output' => ['key_'],
		'itemids' => [$itemId],
		'selectHosts' => ['name'],
		'editable' => true
	]);

	if ($items) {
		DBstart();

		$result = Manager::History()->deleteHistory([$itemId]);

		if ($result) {
			$item = reset($items);
			$host = reset($item['hosts']);

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM, _('Item').' ['.$item['key_'].'] ['.$itemId.'] '.
				_('Host').' ['.$host['name'].'] '._('History cleared')
			);
		}

		$result = DBend($result);
	}

	show_messages($result, _('History cleared'), _('Cannot clear history'));
}
// mass update
elseif ($valid_input && hasRequest('massupdate') && hasRequest('group_itemid')) {
	$visible = getRequest('visible', []);
	$itemids = getRequest('group_itemid');

	$result = true;

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

	$applications = getRequest('applications', []);
	$applicationids = [];

	if ($result) {
		try {
			DBstart();

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

			$items = API::Item()->get([
				'output' => ['itemid', 'flags', 'type'],
				'selectApplications' => ['applicationid'],
				'itemids' => $itemids,
				'preservekeys' => true
			]);

			$items_to_update = [];

			if ($items) {
				$item = [
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
					'ipmi_sensor' => getRequest('ipmi_sensor'),
					'applications' => [],
					'status' => getRequest('status'),
					'master_itemid' => getRequest('master_itemid'),
					'url' =>  getRequest('url'),
					'post_type' => getRequest('post_type'),
					'posts' => getRequest('posts'),
					'headers' => getRequest('headers', []),
					'allow_traps' => getRequest('allow_traps', HTTPCHECK_ALLOW_TRAPS_OFF),
					'preprocessing' => []
				];

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

					$item['preprocessing'] = $preprocessing;
				}

				$item = array_intersect_key($item, $visible);

				$discovered_item = [];
				if (hasRequest('status')) {
					$discovered_item['status'] = getRequest('status');
				}

				foreach ($itemids as $itemid) {
					if (array_key_exists($itemid, $items)) {
						if ($items[$itemid]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
							if ($item) {
								if (array_key_exists('applications', $visible)) {
									if ($applicationids) {
										$db_applicationids = zbx_objectValues($items[$itemid]['applications'],
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

										$item['applications'] = array_keys(array_flip($upd_applicationids));
									}
									else {
										if ($massupdate_app_action == ZBX_ACTION_ADD
												|| $massupdate_app_action == ZBX_ACTION_REMOVE) {
											unset($item['applications']);
										}
									}
								}

								$items_to_update[] = ['itemid' => $itemid] + $item;
							}
						}
						else {
							if ($discovered_item) {
								$items_to_update[] = ['itemid' => $itemid] + $discovered_item;
							}
						}
					}
				}
			}

			if ($items_to_update) {
				foreach ($items_to_update as &$update_item) {
					$type = array_key_exists('type', $update_item)
						? $update_item['type']
						: $items[$update_item['itemid']]['type'];

					if ($type != ITEM_TYPE_JMX) {
						unset($update_item['jmx_endpoint']);
					}
				}
				unset($update_item);

				$result = API::Item()->update($items_to_update);
			}
		}
		catch (Exception $e) {
			$result = false;
		}

		$result = DBend($result);
	}

	if ($result) {
		unset($_REQUEST['group_itemid'], $_REQUEST['massupdate'], $_REQUEST['form']);
		uncheckTableRows(getRequest('checkbox_hash'));
	}
	show_messages($result, _('Items updated'), _('Cannot update items'));
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['item.massenable', 'item.massdisable']) && hasRequest('group_itemid')) {
	$itemids = getRequest('group_itemid');
	$status = (getRequest('action') == 'item.massenable') ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;

	$items = [];
	foreach ($itemids as $itemid) {
		$items[] = ['itemid' => $itemid, 'status' => $status];
	}

	$result = (bool) API::Item()->update($items);

	if ($result) {
		uncheckTableRows(getRequest('checkbox_hash'));
	}

	$updated = count($itemids);

	$messageSuccess = ($status == ITEM_STATUS_ACTIVE)
		? _n('Item enabled', 'Items enabled', $updated)
		: _n('Item disabled', 'Items disabled', $updated);
	$messageFailed = ($status == ITEM_STATUS_ACTIVE)
		? _n('Cannot enable item', 'Cannot enable items', $updated)
		: _n('Cannot disable item', 'Cannot disable items', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') === 'item.masscopyto' && hasRequest('copy')
		&& hasRequest('group_itemid')) {
	if (getRequest('copy_targetids', []) && hasRequest('copy_type')) {
		// hosts or templates
		if (getRequest('copy_type') == COPY_TYPE_TO_HOST || getRequest('copy_type') == COPY_TYPE_TO_TEMPLATE) {
			$hosts_ids = getRequest('copy_targetids');
		}
		// host groups
		else {
			$hosts_ids = [];
			$group_ids = getRequest('copy_targetids');

			$db_hosts = DBselect(
				'SELECT DISTINCT h.hostid'.
				' FROM hosts h,hosts_groups hg'.
				' WHERE h.hostid=hg.hostid'.
					' AND '.dbConditionInt('hg.groupid', $group_ids)
			);
			while ($db_host = DBfetch($db_hosts)) {
				$hosts_ids[] = $db_host['hostid'];
			}
		}

		DBstart();

		$result = copyItemsToHosts(getRequest('group_itemid'), $hosts_ids);
		$result = DBend($result);

		$items_count = count(getRequest('group_itemid'));

		if ($result) {
			uncheckTableRows(getRequest('checkbox_hash'));
			unset($_REQUEST['group_itemid']);
		}
		show_messages($result,
			_n('Item copied', 'Items copied', $items_count),
			_n('Cannot copy item', 'Cannot copy items', $items_count)
		);
	}
	else {
		show_error_message(_('No target selected.'));
	}
}
// clean history for selected items
elseif (hasRequest('action') && getRequest('action') === 'item.massclearhistory'
		&& hasRequest('group_itemid') && is_array(getRequest('group_itemid'))) {
	$result = false;

	$itemIds = getRequest('group_itemid');

	$items = API::Item()->get([
		'output' => ['itemid', 'key_'],
		'itemids' => $itemIds,
		'selectHosts' => ['name'],
		'editable' => true
	]);

	if ($items) {
		DBstart();

		$result = Manager::History()->deleteHistory($itemIds);

		if ($result) {
			foreach ($items as $item) {
				$host = reset($item['hosts']);

				add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,
					_('Item').' ['.$item['key_'].'] ['.$item['itemid'].'] '. _('Host').' ['.$host['name'].'] '.
						_('History cleared')
				);
			}
		}

		$result = DBend($result);

		if ($result) {
			uncheckTableRows(getRequest('checkbox_hash'));
		}
	}

	show_messages($result, _('History cleared'), _('Cannot clear history'));
}
elseif (hasRequest('action') && getRequest('action') === 'item.massdelete' && hasRequest('group_itemid')) {
	$group_itemid = getRequest('group_itemid');

	$result = API::Item()->delete($group_itemid);

	if ($result) {
		uncheckTableRows(getRequest('checkbox_hash'));
	}
	show_messages($result, _('Items deleted'), _('Cannot delete items'));
}
elseif (hasRequest('action') && getRequest('action') === 'item.masscheck_now' && hasRequest('group_itemid')) {
	$result = (bool) API::Task()->create([
		'type' => ZBX_TM_TASK_CHECK_NOW,
		'itemids' => getRequest('group_itemid')
	]);

	if ($result) {
		uncheckTableRows(getRequest('checkbox_hash'));
	}

	show_messages($result, _('Request sent successfully'), _('Cannot send request'));
}

if (hasRequest('action') && hasRequest('group_itemid') && !$result) {
	$itemids = API::Item()->get([
		'output' => [],
		'itemids' => getRequest('group_itemid'),
		'editable' => true
	]);
	uncheckTableRows(getRequest('checkbox_hash'), zbx_objectValues($itemids, 'itemid'));
}

/*
 * Display
 */
if (isset($_REQUEST['form']) && str_in_array($_REQUEST['form'], ['create', 'update', 'clone'])) {
	$master_item_options = [];
	$has_errors = false;

	if (hasRequest('itemid')) {
		$items = API::Item()->get([
			'output' => ['itemid', 'type', 'snmp_community', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history',
				'trends', 'status', 'value_type', 'trapper_hosts', 'units', 'snmpv3_securityname',
				'snmpv3_securitylevel',	'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'logtimefmt', 'templateid',
				'valuemapid', 'params', 'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'flags', 'interfaceid', 'port', 'description', 'inventory_link', 'lifetime', 'snmpv3_authprotocol',
				'snmpv3_privprotocol', 'snmpv3_contextname', 'jmx_endpoint', 'master_itemid', 'url', 'query_fields',
				'timeout', 'posts', 'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers',
				'retrieve_mode', 'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password',
				'verify_peer', 'verify_host', 'allow_traps'
			],
			'selectHosts' => ['status', 'name'],
			'selectDiscoveryRule' => ['itemid', 'name'],
			'selectItemDiscovery' => ['parent_itemid'],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'itemids' => getRequest('itemid')
		]);
		$item = $items[0];
		$host = $item['hosts'][0];
		unset($item['hosts']);

		foreach ($item['preprocessing'] as &$step) {
			if ($step['type'] == ZBX_PREPROC_SCRIPT) {
				$step['params'] = [$step['params'], ''];
			}
			else {
				$step['params'] = explode("\n", $step['params']);
			}
		}
		unset($step);

		if ($item['type'] != ITEM_TYPE_JMX) {
			$item['jmx_endpoint'] = ZBX_DEFAULT_JMX_ENDPOINT;
		}

		if (getRequest('type', $item['type']) == ITEM_TYPE_DEPENDENT) {
			// Unset master item if submitted form has no master_itemid set.
			if (hasRequest('form_refresh') && !hasRequest('master_itemid')) {
				$item['master_itemid'] = 0;
			}
			else {
				$master_item_options = [
					'output' => ['itemid', 'type', 'hostid', 'name', 'key_'],
					'itemids' => getRequest('master_itemid', $item['master_itemid']),
					'webitems' => true
				];
			}
		}
	}
	else {
		$hosts = API::Host()->get([
			'output' => ['hostid', 'name', 'status'],
			'hostids' => getRequest('hostid'),
			'templated_hosts' => true
		]);
		$item = [];
		$host = $hosts[0];

		if (getRequest('master_itemid')) {
			$master_item_options = [
				'output' => ['itemid', 'type', 'hostid', 'name', 'key_'],
				'itemids' => getRequest('master_itemid'),
				'hostids' => $host['hostid'],
				'webitems' => true
			];
		}
	}

	if ($master_item_options) {
		$master_items = API::Item()->get($master_item_options);
		if ($master_items) {
			$item['master_item'] = reset($master_items);
		}
		else {
			show_messages(false, '', _('No permissions to referred object or it does not exist!'));
			$has_errors = true;
		}
	}

	$data = getItemFormData($item);
	$data['inventory_link'] = getRequest('inventory_link');
	$data['config'] = select_config();
	$data['host'] = $host;
	$data['preprocessing_test_type'] = CControllerPopupPreprocTestEdit::ZBX_TEST_TYPE_ITEM;
	$data['preprocessing_types'] = CItem::$supported_preprocessing_types;
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

	if (hasRequest('itemid') && !getRequest('form_refresh')) {
		$data['inventory_link'] = $item['inventory_link'];
	}

	// render view
	if (!$has_errors) {
		$itemView = new CView('configuration.item.edit', $data);
		$itemView->render();
		$itemView->show();
	}
}
elseif (((hasRequest('action') && getRequest('action') === 'item.massupdateform') || hasRequest('massupdate'))
		&& hasRequest('group_itemid')) {
	$data = [
		'form' => getRequest('form'),
		'action' => 'item.massupdateform',
		'hostid' => getRequest('hostid', 0),
		'itemids' => getRequest('group_itemid', []),
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
		'applications' => getRequest('applications', []),
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
		'preprocessing_test_type' => CControllerPopupPreprocTestEdit::ZBX_TEST_TYPE_ITEM,
		'preprocessing_types' => CItem::$supported_preprocessing_types,
		'preprocessing_script_maxlength' => DB::getFieldLength('item_preproc', 'params')
	];

	foreach ($data['preprocessing'] as &$step) {
		$step += [
			'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
			'error_handler_params' => ''
		];
	}
	unset($step);

	$data['displayApplications'] = true;
	$data['displayInterfaces'] = true;
	$data['displayMasteritems'] = true;

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
		'itemids' => $data['itemids'],
		'selectInterfaces' => API_OUTPUT_EXTEND
	]);
	$hostCount = count($data['hosts']);

	if ($hostCount > 1) {
		$data['displayApplications'] = false;
		$data['displayInterfaces'] = false;
		$data['displayMasteritems'] = false;
	}
	else {
		// get template count to display applications multiselect only for single template
		$templates = API::Template()->get([
			'output' => ['templateid'],
			'itemids' => $data['itemids']
		]);
		$templateCount = count($templates);

		if ($templateCount != 0) {
			$data['displayInterfaces'] = false;

			if ($templateCount == 1 && $data['hostid'] == 0) {
				// If selected from filter without 'hostid'.
				$templates = reset($templates);
				$data['hostid'] = $templates['templateid'];
			}

			/*
			 * If items belong to single template and some belong to single host, don't display application multiselect
			 * and don't display application multiselect for multiple templates.
			 */
			if ($hostCount == 1 && $templateCount == 1 || $templateCount > 1) {
				$data['displayApplications'] = false;
				$data['displayMasteritems'] = false;
			}
		}

		if ($hostCount == 1 && $data['displayInterfaces']) {
			$data['hosts'] = reset($data['hosts']);

			// Sort interfaces to be listed starting with one selected as 'main'.
			CArrayHelper::sort($data['hosts']['interfaces'], [
				['field' => 'main', 'order' => ZBX_SORT_DOWN]
			]);

			// If selected from filter without 'hostid'.
			if ($data['hostid'] == 0) {
				$data['hostid'] = $data['hosts']['hostid'];
			}

			// set the initial chosen interface to one of the interfaces the items use
			$items = API::Item()->get([
				'itemids' => $data['itemids'],
				'output' => ['itemid', 'type', 'name']
			]);
			$usedInterfacesTypes = [];
			$items_names = [];
			foreach ($items as $item) {
				$usedInterfacesTypes[$item['type']] = itemTypeInterface($item['type']);
				$items_names[$item['itemid']] = $item['name'];
			}
			$initialItemType = min(array_keys($usedInterfacesTypes));
			$data['type'] = (getRequest('type') !== null) ? ($data['type']) : $initialItemType;
			$data['initial_item_type'] = $initialItemType;
			$data['multiple_interface_types'] = (count(array_unique($usedInterfacesTypes)) > 1);
			$data['items_names'] = $items_names;
		}
	}

	if ($data['master_itemid'] != 0 && $data['displayMasteritems']) {
		$master_items = API::Item()->get([
			'output' => ['itemid', 'name'],
			'selectHosts' => ['name'],
			'itemids' => $data['master_itemid'],
			'hostids' => $data['hostid'],
			'webitems' => true
		]);

		if ($master_items) {
			$data['master_itemname'] = $master_items[0]['name'];
			$data['master_hostname'] = $master_items[0]['hosts'][0]['name'];
		}
		else {
			$data['master_itemid'] = 0;
			show_messages(false, '', _('No permissions to referred object or it does not exist!'));
			$has_errors = true;
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

	// render view
	$itemView = new CView('configuration.item.massupdate', $data);
	$itemView->render();
	$itemView->show();
}
elseif (hasRequest('action') && getRequest('action') === 'item.masscopyto' && hasRequest('group_itemid')) {
	$data = getCopyElementsFormData('group_itemid', _('Items'));
	$data['action'] = 'item.masscopyto';

	// render view
	$itemView = new CView('configuration.copy.elements', $data);
	$itemView->render();
	$itemView->show();
}
// list of items
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	if (count($filter_hostids) == 1) {
		$hostid = reset($filter_hostids);
	}
	else {
		$hostid = null;
	}

	$data = [
		'form' => getRequest('form'),
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'config' => select_config(),
		'hostid' => $hostid
	];

	// items
	$options = [
		'search' => [],
		'output' => [
			'itemid', 'type', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status', 'value_type', 'error',
			'templateid', 'flags', 'state', 'master_itemid'
		],
		'editable' => true,
		'selectHosts' => API_OUTPUT_EXTEND,
		'selectTriggers' => ['triggerid'],
		'selectApplications' => API_OUTPUT_EXTEND,
		'selectDiscoveryRule' => API_OUTPUT_EXTEND,
		'selectItemDiscovery' => ['ts_delete'],
		'sortfield' => $sortField,
		'limit' => $data['config']['search_limit'] + 1
	];
	$preFilter = count($options, COUNT_RECURSIVE);

	if ($filter_hostids) {
		$options['hostids'] = $filter_hostids;
	}
	if ($filter_groupids) {
		$options['groupids'] = $filter_groupids;
	}

	if (isset($_REQUEST['filter_application']) && !zbx_empty($_REQUEST['filter_application'])) {
		$options['applicationids'] = array_keys(API::Application()->get([
			'output' => [],
			'groupids' => array_key_exists('groupids', $options) ? $options['groupids'] : null,
			'hostids' => array_key_exists('hostids', $options) ? $options['hostids'] : null,
			'search' => ['name' => getRequest('filter_application')],
			'preservekeys' => true
		]));
	}
	if (isset($_REQUEST['filter_name']) && !zbx_empty($_REQUEST['filter_name'])) {
		$options['search']['name'] = $_REQUEST['filter_name'];
	}
	if (isset($_REQUEST['filter_type']) && !zbx_empty($_REQUEST['filter_type']) && $_REQUEST['filter_type'] != -1) {
		$options['filter']['type'] = $_REQUEST['filter_type'];
	}
	if (isset($_REQUEST['filter_key']) && !zbx_empty($_REQUEST['filter_key'])) {
		$options['search']['key_'] = $_REQUEST['filter_key'];
	}
	if (isset($_REQUEST['filter_snmp_community']) && !zbx_empty($_REQUEST['filter_snmp_community'])) {
		$options['filter']['snmp_community'] = $_REQUEST['filter_snmp_community'];
	}
	if (isset($_REQUEST['filter_snmpv3_securityname']) && !zbx_empty($_REQUEST['filter_snmpv3_securityname'])) {
		$options['filter']['snmpv3_securityname'] = $_REQUEST['filter_snmpv3_securityname'];
	}
	if (isset($_REQUEST['filter_snmp_oid']) && !zbx_empty($_REQUEST['filter_snmp_oid'])) {
		$options['filter']['snmp_oid'] = $_REQUEST['filter_snmp_oid'];
	}
	if (isset($_REQUEST['filter_port']) && !zbx_empty($_REQUEST['filter_port'])) {
		$options['filter']['port'] = $_REQUEST['filter_port'];
	}
	if (isset($_REQUEST['filter_value_type']) && !zbx_empty($_REQUEST['filter_value_type'])
			&& $_REQUEST['filter_value_type'] != -1) {
		$options['filter']['value_type'] = $_REQUEST['filter_value_type'];
	}

	/*
	 * Trapper and SNMP trap items contain zeros in "delay" field and, if no specific type is set, look in item types
	 * other than trapper and SNMP trap that allow zeros. For example, when a flexible interval is used. Since trapper
	 * and SNMP trap items contain zeros, but those zeros should not be displayed, they cannot be filtered by entering
	 * either zero or any other number in filter field.
	 */
	if (hasRequest('filter_delay')) {
		$filter_delay = getRequest('filter_delay');
		$filter_type = getRequest('filter_type');

		if ($filter_delay !== '') {
			if ($filter_type == -1 && $filter_delay == 0) {
				$options['filter']['type'] = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_SIMPLE,
					ITEM_TYPE_SNMPV2C, ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3, ITEM_TYPE_ZABBIX_ACTIVE,
					ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH,
					ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED, ITEM_TYPE_JMX
				];

				$options['filter']['delay'] = $filter_delay;
			}
			elseif ($filter_type == ITEM_TYPE_TRAPPER || $filter_type == ITEM_TYPE_SNMPTRAP
					|| $filter_type == ITEM_TYPE_DEPENDENT) {
				$options['filter']['delay'] = -1;
			}
			else {
				$options['filter']['delay'] = $filter_delay;
			}
		}
	}

	if (isset($_REQUEST['filter_history']) && !zbx_empty($_REQUEST['filter_history'])) {
		$options['filter']['history'] = $_REQUEST['filter_history'];
	}

	// If no specific value type is set, set a numeric value type when filtering by trends.
	if (hasRequest('filter_trends')) {
		$filter_trends = getRequest('filter_trends');

		if ($filter_trends !== '') {
			$options['filter']['trends'] = $filter_trends;

			if (getRequest('filter_value_type') == -1) {
				$options['filter']['value_type'] = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64];
			}
		}
	}

	if (isset($_REQUEST['filter_status']) && !zbx_empty($_REQUEST['filter_status']) && $_REQUEST['filter_status'] != -1) {
		$options['filter']['status'] = $_REQUEST['filter_status'];
	}
	if (isset($_REQUEST['filter_state']) && !zbx_empty($_REQUEST['filter_state']) && $_REQUEST['filter_state'] != -1) {
		$options['filter']['status'] = ITEM_STATUS_ACTIVE;
		$options['filter']['state'] = $_REQUEST['filter_state'];
	}
	if (isset($_REQUEST['filter_templated_items']) && !zbx_empty($_REQUEST['filter_templated_items'])
			&& $_REQUEST['filter_templated_items'] != -1) {
		$options['inherited'] = $_REQUEST['filter_templated_items'];
	}
	if (isset($_REQUEST['filter_discovery']) && !zbx_empty($_REQUEST['filter_discovery'])
			&& $_REQUEST['filter_discovery'] != -1) {
		$options['filter']['flags'] = $_REQUEST['filter_discovery'];
	}
	if (isset($_REQUEST['filter_with_triggers']) && !zbx_empty($_REQUEST['filter_with_triggers'])
			&& $_REQUEST['filter_with_triggers'] != -1) {
		$options['with_triggers'] = $_REQUEST['filter_with_triggers'];
	}
	if (isset($_REQUEST['filter_ipmi_sensor']) && !zbx_empty($_REQUEST['filter_ipmi_sensor'])) {
		$options['filter']['ipmi_sensor'] = $_REQUEST['filter_ipmi_sensor'];
	}

	$data['items'] = API::Item()->get($options);
	$data['parent_templates'] = [];

	// Set values for subfilters, if any of subfilters = false then item shouldn't be shown.
	if ($data['items']) {
		// resolve name macros
		$data['items'] = expandItemNamesWithMasterItems($data['items'], 'items');

		$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);

		foreach ($data['items'] as &$item) {
			$item['hostids'] = zbx_objectValues($item['hosts'], 'hostid');

			if ($data['hostid'] == 0) {
				$host = reset($item['hosts']);
				$item['host'] = $host['name'];
			}

			// Use temporary variable for delay, because the original will be used for sorting later.
			$delay = $item['delay'];

			if ($item['type'] == ITEM_TYPE_TRAPPER || $item['type'] == ITEM_TYPE_SNMPTRAP
					|| $item['type'] == ITEM_TYPE_DEPENDENT) {
				$delay = '';
			}
			else {
				if ($update_interval_parser->parse($delay) == CParser::PARSE_SUCCESS) {
					$delay = $update_interval_parser->getDelay();

					$delay = ($delay[0] !== '{') ? convertUnitsS(timeUnitToSeconds($delay)) : $delay;
				}
			}

			$history = $item['history'];
			$history = ($history[0] !== '{') ? convertUnitsS(timeUnitToSeconds($history)) : $history;

			// Hide trend (zero values) for non-numeric item types.
			$trends = in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
				? $item['trends']
				: '';
			$trends = ($trends !== '' && $trends[0] !== '{')
				? convertUnitsS(timeUnitToSeconds($trends))
				: $trends;

			$item['subfilters'] = [
				'subfilter_hosts' => empty($_REQUEST['subfilter_hosts'])
					|| (boolean) array_intersect($_REQUEST['subfilter_hosts'], $item['hostids']),
				'subfilter_types' => empty($_REQUEST['subfilter_types'])
					|| uint_in_array($item['type'], $_REQUEST['subfilter_types']),
				'subfilter_value_types' => empty($_REQUEST['subfilter_value_types'])
					|| uint_in_array($item['value_type'], $_REQUEST['subfilter_value_types']),
				'subfilter_status' => empty($_REQUEST['subfilter_status'])
					|| uint_in_array($item['status'], $_REQUEST['subfilter_status']),
				'subfilter_state' => empty($_REQUEST['subfilter_state'])
					|| uint_in_array($item['state'], $_REQUEST['subfilter_state']),
				'subfilter_templated_items' => empty($_REQUEST['subfilter_templated_items'])
					|| ($item['templateid'] == 0 && uint_in_array(0, $_REQUEST['subfilter_templated_items'])
					|| ($item['templateid'] > 0 && uint_in_array(1, $_REQUEST['subfilter_templated_items']))),
				'subfilter_discovery' => empty($_REQUEST['subfilter_discovery'])
					|| ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL && uint_in_array(0, $_REQUEST['subfilter_discovery'])
					|| ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED && uint_in_array(1, $_REQUEST['subfilter_discovery']))),
				'subfilter_with_triggers' => empty($_REQUEST['subfilter_with_triggers'])
					|| (count($item['triggers']) == 0 && uint_in_array(0, $_REQUEST['subfilter_with_triggers']))
					|| (count($item['triggers']) > 0 && uint_in_array(1, $_REQUEST['subfilter_with_triggers'])),
				'subfilter_history' => (!getRequest('subfilter_history')
					|| (in_array($history, getRequest('subfilter_history')))),
				'subfilter_trends' => (!getRequest('subfilter_trends')
					|| ($trends !== '' && in_array($trends, getRequest('subfilter_trends')))),
				'subfilter_interval' => (!getRequest('subfilter_interval')
					|| ($delay !== '' && in_array($delay, getRequest('subfilter_interval')))),
				'subfilter_apps' => empty($_REQUEST['subfilter_apps'])
			];

			if (!empty($_REQUEST['subfilter_apps'])) {
				foreach ($item['applications'] as $application) {
					if (str_in_array($application['name'], $_REQUEST['subfilter_apps'])) {
						$item['subfilters']['subfilter_apps'] = true;
						break;
					}
				}
			}

			if (!empty($item['applications'])) {
				order_result($item['applications'], 'name');

				$applications = [];
				foreach ($item['applications'] as $application) {
					$applications[] = $application['name'];
				}
				$item['applications_list'] = implode(', ', $applications);
			}
			else {
				$item['applications_list'] = '';
			}
		}
		unset($item);

		// disable subfilters if list is empty
		foreach ($data['items'] as $item) {
			$atLeastOne = true;
			foreach ($item['subfilters'] as $value) {
				if (!$value) {
					$atLeastOne = false;
					break;
				}
			}
			if ($atLeastOne) {
				break;
			}
		}
		if (!$atLeastOne) {
			foreach ($subfiltersList as $name) {
				$_REQUEST[$name] = [];
				CProfile::update('web.items.'.$name, '', PROFILE_TYPE_STR);
				foreach ($data['items'] as &$item) {
					$item['subfilters'][$name] = true;
				}
				unset($item);
			}
		}
	}

	$data['main_filter'] = getItemFilterForm($data['items']);

	// Remove subfiltered items.
	foreach ($data['items'] as $number => $item) {
		foreach ($item['subfilters'] as $value) {
			if (!$value) {
				unset($data['items'][$number]);
				break;
			}
		}
	}

	switch ($sortField) {
		case 'delay':
			orderItemsByDelay($data['items'], $sortOrder, ['usermacros' => true]);
			break;

		case 'history':
			orderItemsByHistory($data['items'], $sortOrder);
			break;

		case 'trends':
			orderItemsByTrends($data['items'], $sortOrder);
			break;

		case 'status':
			orderItemsByStatus($data['items'], $sortOrder);
			break;

		default:
			order_result($data['items'], $sortField, $sortOrder);
	}

	$data['paging'] = getPagingLine($data['items'], $sortOrder, new CUrl('items.php'));
	$data['parent_templates'] = getItemParentTemplates($data['items'], ZBX_FLAG_DISCOVERY_NORMAL);

	$itemTriggerIds = [];
	foreach ($data['items'] as $item) {
		$itemTriggerIds = array_merge($itemTriggerIds, zbx_objectValues($item['triggers'], 'triggerid'));
	}
	$data['itemTriggers'] = API::Trigger()->get([
		'triggerids' => $itemTriggerIds,
		'output' => ['triggerid', 'description', 'expression', 'recovery_mode', 'recovery_expression', 'priority',
			'status', 'state', 'error', 'templateid', 'flags'
		],
		'selectHosts' => ['hostid', 'name', 'host'],
		'preservekeys' => true
	]);

	$data['trigger_parent_templates'] = getTriggerParentTemplates($data['itemTriggers'], ZBX_FLAG_DISCOVERY_NORMAL);

	sort($filter_hostids);
	$data['checkbox_hash'] = crc32(implode('', $filter_hostids));

	// render view
	$itemView = new CView('configuration.item.list', $data);
	$itemView->render();
	$itemView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
