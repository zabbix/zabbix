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

$page['title'] = _('Configuration of items');
$page['file'] = 'items.php';
$page['scripts'] = ['multilineinput.js', 'items.js', 'class.tagfilteritem.js'];

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
											' && ({type} != '.ITEM_TYPE_TRAPPER.' && {type} != '.ITEM_TYPE_SNMPTRAP.
											' && {type} != '.ITEM_TYPE_DEPENDENT.
											' && !({type} == '.ITEM_TYPE_ZABBIX_ACTIVE.
												' && isset({key}) && strncmp({key}, "mqtt.get", 8) === 0))',
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
										IN([-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE,
											ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL,
											ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH,
											ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED, ITEM_TYPE_SNMPTRAP,
											ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
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
												ITEM_TYPE_CALCULATED.','.ITEM_TYPE_SCRIPT, 'type'
											),
										getParamFieldLabelByType(getRequest('type', 0))
									],
	'inventory_link' =>				[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535),
										'(isset({add}) || isset({update})) && {value_type} != '.ITEM_VALUE_TYPE_LOG
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
	'visible' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'del_history' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'jmx_endpoint' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
										'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_JMX
									],
	'timeout' =>					[T_ZBX_TU, O_OPT, P_ALLOW_USER_MACRO,	null,
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
	'context' =>					[T_ZBX_STR, O_MAND, P_SYS,		IN('"host", "template"'),	null],
	// actions
	'action' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
										IN('"item.massclearhistory","item.masscopyto","item.massdelete",'.
											'"item.massdisable","item.massenable","item.masscheck_now"'
										),
										null
									],
	'add' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'copy' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>						[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'check_now' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'form' =>						[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>				[T_ZBX_INT, O_OPT, null,	null,		null],
	'tags' =>						[T_ZBX_STR, O_OPT, null,	null,		null],
	'show_inherited_tags' =>		[T_ZBX_INT, O_OPT, null,	IN([0,1]),	null],
	// filter
	'filter_set' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_rst' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_groupids' =>			[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'filter_hostids' =>				[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'filter_name' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_type' =>				[T_ZBX_INT, O_OPT, null,
										IN([-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE,
											ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE,
											ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH,
											ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED, ITEM_TYPE_SNMPTRAP,
											ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
										]),
										null
									],
	'filter_key' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_snmp_oid' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
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
	'filter_inherited' =>			[T_ZBX_INT, O_OPT, null,	IN('-1,0,1'), null],
	'filter_with_triggers' =>		[T_ZBX_INT, O_OPT, null,	IN('-1,0,1'), null],
	'filter_discovered' =>			[T_ZBX_INT, O_OPT, null,
										IN([-1, ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]),
										null
									],
	'filter_evaltype' =>			[T_ZBX_INT, O_OPT, null,	IN([TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]), null],
	'filter_tags' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_valuemapids' =>			[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	// subfilters
	'subfilter_set' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'subfilter_types' =>			[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_value_types' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_status' =>			[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_state' =>			[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_inherited' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_with_triggers' =>	[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_discovered' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_hosts' =>			[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_interval' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'subfilter_history' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'subfilter_trends' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'subfilter_tags' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'checkbox_hash' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	// sort and sortorder
	'sort' =>						[T_ZBX_STR, O_OPT, P_SYS,
										IN('"delay","history","key_","name","status","trends","type"'),
										null
									],
	'sortorder' =>					[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'), null]
];

if (getRequest('type') == ITEM_TYPE_HTTPAGENT && getRequest('interfaceid') == INTERFACE_TYPE_OPT) {
	unset($fields['interfaceid']);
	unset($_REQUEST['interfaceid']);
}

$valid_input = check_fields($fields);

$_REQUEST['params'] = getRequest($paramsFieldName, '');
unset($_REQUEST[$paramsFieldName]);

$subfiltersList = ['subfilter_types', 'subfilter_value_types', 'subfilter_status', 'subfilter_state',
	'subfilter_inherited', 'subfilter_with_triggers', 'subfilter_hosts', 'subfilter_interval', 'subfilter_history',
	'subfilter_trends', 'subfilter_discovered'
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

$prefix = (getRequest('context') === 'host') ? 'web.hosts.' : 'web.templates.';

/*
 * Filter
 */
if (hasRequest('filter_set')) {
	CProfile::updateArray($prefix.'items.filter_groupids', getRequest('filter_groupids', []), PROFILE_TYPE_ID);
	CProfile::updateArray($prefix.'items.filter_hostids', getRequest('filter_hostids', []), PROFILE_TYPE_ID);
	CProfile::updateArray($prefix.'items.filter_valuemapids', getRequest('filter_valuemapids', []), PROFILE_TYPE_ID);
	CProfile::update($prefix.'items.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
	CProfile::update($prefix.'items.filter_type', getRequest('filter_type', -1), PROFILE_TYPE_INT);
	CProfile::update($prefix.'items.filter_key', getRequest('filter_key', ''), PROFILE_TYPE_STR);
	CProfile::update($prefix.'items.filter_snmp_oid', getRequest('filter_snmp_oid', ''), PROFILE_TYPE_STR);
	CProfile::update($prefix.'items.filter_value_type', getRequest('filter_value_type', -1), PROFILE_TYPE_INT);
	CProfile::update($prefix.'items.filter_delay', getRequest('filter_delay', ''), PROFILE_TYPE_STR);
	CProfile::update($prefix.'items.filter_history', getRequest('filter_history', ''), PROFILE_TYPE_STR);
	CProfile::update($prefix.'items.filter_trends', getRequest('filter_trends', ''), PROFILE_TYPE_STR);
	CProfile::update($prefix.'items.filter_status', getRequest('filter_status', -1), PROFILE_TYPE_INT);
	CProfile::update($prefix.'items.filter_state', getRequest('filter_state', -1), PROFILE_TYPE_INT);
	CProfile::update($prefix.'items.filter_inherited', getRequest('filter_inherited', -1), PROFILE_TYPE_INT);
	CProfile::update($prefix.'items.filter_with_triggers', getRequest('filter_with_triggers', -1), PROFILE_TYPE_INT);
	CProfile::update($prefix.'items.filter_discovered', getRequest('filter_discovered', -1), PROFILE_TYPE_INT);

	// tags
	$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];
	foreach (getRequest('filter_tags', []) as $tag) {
		if ($tag['tag'] === '' && $tag['value'] === '') {
			continue;
		}
		$filter_tags['tags'][] = $tag['tag'];
		$filter_tags['values'][] = $tag['value'];
		$filter_tags['operators'][] = $tag['operator'];
	}
	CProfile::update($prefix.'items.filter.evaltype', getRequest('filter_evaltype', TAG_EVAL_TYPE_AND_OR), PROFILE_TYPE_INT);
	CProfile::updateArray($prefix.'items.filter.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
	CProfile::updateArray($prefix.'items.filter.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
	CProfile::updateArray($prefix.'items.filter.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);
	unset($filter_tags);

	// subfilters
	foreach ($subfiltersList as $name) {
		$_REQUEST[$name] = [];
		CProfile::update($prefix.'items.'.$name, '', PROFILE_TYPE_STR);
	}

	// Subfilter tags.
	CProfile::updateArray($prefix.'items.subfilter_tags.tag', [], PROFILE_TYPE_STR);
	CProfile::updateArray($prefix.'items.subfilter_tags.value', [], PROFILE_TYPE_STR);
}
elseif (hasRequest('filter_rst')) {
	DBStart();
	if (count(CProfile::getArray($prefix.'items.filter_hostids', [])) != 1) {
		CProfile::deleteIdx($prefix.'items.filter_hostids');
	}
	CProfile::deleteIdx($prefix.'items.filter_groupids');
	CProfile::deleteIdx($prefix.'items.filter_name');
	CProfile::deleteIdx($prefix.'items.filter_type');
	CProfile::deleteIdx($prefix.'items.filter_key');
	CProfile::deleteIdx($prefix.'items.filter_snmp_oid');
	CProfile::deleteIdx($prefix.'items.filter_value_type');
	CProfile::deleteIdx($prefix.'items.filter_delay');
	CProfile::deleteIdx($prefix.'items.filter_history');
	CProfile::deleteIdx($prefix.'items.filter_trends');
	CProfile::deleteIdx($prefix.'items.filter_status');
	CProfile::deleteIdx($prefix.'items.filter_state');
	CProfile::deleteIdx($prefix.'items.filter_inherited');
	CProfile::deleteIdx($prefix.'items.filter_with_triggers');
	CProfile::deleteIdx($prefix.'items.filter_discovered');
	CProfile::deleteIdx($prefix.'items.filter.tags.tag');
	CProfile::deleteIdx($prefix.'items.filter.tags.value');
	CProfile::deleteIdx($prefix.'items.filter.tags.operator');
	CProfile::deleteIdx($prefix.'items.filter.evaltype');
	CProfile::deleteIdx($prefix.'items.filter_valuemapids');
	DBend();
}

$_REQUEST['filter_groupids'] = CProfile::getArray($prefix.'items.filter_groupids', []);
$_REQUEST['filter_hostids'] = CProfile::getArray($prefix.'items.filter_hostids', []);
$_REQUEST['filter_name'] = CProfile::get($prefix.'items.filter_name', '');
$_REQUEST['filter_type'] = CProfile::get($prefix.'items.filter_type', -1);
$_REQUEST['filter_key'] = CProfile::get($prefix.'items.filter_key', '');
$_REQUEST['filter_snmp_oid'] = CProfile::get($prefix.'items.filter_snmp_oid', '');
$_REQUEST['filter_value_type'] = CProfile::get($prefix.'items.filter_value_type', -1);
$_REQUEST['filter_delay'] = CProfile::get($prefix.'items.filter_delay', '');
$_REQUEST['filter_history'] = CProfile::get($prefix.'items.filter_history', '');
$_REQUEST['filter_trends'] = CProfile::get($prefix.'items.filter_trends', '');
$_REQUEST['filter_status'] = CProfile::get($prefix.'items.filter_status', -1);
$_REQUEST['filter_state'] = CProfile::get($prefix.'items.filter_state', -1);
$_REQUEST['filter_inherited'] = CProfile::get($prefix.'items.filter_inherited', -1);
$_REQUEST['filter_discovered'] = CProfile::get($prefix.'items.filter_discovered', -1);
$_REQUEST['filter_with_triggers'] = CProfile::get($prefix.'items.filter_with_triggers', -1);
$_REQUEST['filter_valuemapids'] = CProfile::getArray($prefix.'items.filter_valuemapids', []);

// subfilters
if (hasRequest('subfilter_set')) {
	foreach ($subfiltersList as $name) {
		$_REQUEST[$name] = getRequest($name, []);
		CProfile::update($prefix.'items.'.$name, implode(';', $_REQUEST[$name]), PROFILE_TYPE_STR);
	}

	$subf_tags = [];
	if (hasRequest('subfilter_tags')) {
		foreach (getRequest('subfilter_tags', []) as $tag) {
			if ($tag['tag'] !== null) {
				$subf_tags[json_encode([$tag['tag'], $tag['value']])] = [
					'tag' => $tag['tag'],
					'value' => $tag['value'] ? $tag['value'] : ''
				];
			}
		}
	}
	CProfile::updateArray($prefix.'items.subfilter_tags.tag', array_column($subf_tags, 'tag'), PROFILE_TYPE_STR);
	CProfile::updateArray($prefix.'items.subfilter_tags.value', array_column($subf_tags, 'value'), PROFILE_TYPE_STR);
}
else {
	foreach ($subfiltersList as $name) {
		$_REQUEST[$name] = [];
		$subfilters_value = CProfile::get($prefix.'items.'.$name);
		if (!zbx_empty($subfilters_value)) {
			$_REQUEST[$name] = explode(';', $subfilters_value);
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
elseif (hasRequest('add') || hasRequest('update')) {
	DBstart();
	$result = true;

	$delay = getRequest('delay', DB::getDefault('items', 'delay'));
	$type = getRequest('type', ITEM_TYPE_ZABBIX);

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
	 * "delay_flex" is a temporary field that collects flexible and scheduling intervals separated by a semicolon.
	 * In the end, custom intervals together with "delay" are stored in the "delay" variable.
	 */
	if ($type != ITEM_TYPE_TRAPPER && $type != ITEM_TYPE_SNMPTRAP
			&& ($type != ITEM_TYPE_ZABBIX_ACTIVE || strncmp(getRequest('key'), 'mqtt.get', 8) !== 0)
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

		if ($type == ITEM_TYPE_HTTPAGENT) {
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
		}

		if (hasRequest('add')) {
			$item = [
				'hostid' => getRequest('hostid'),
				'name' => getRequest('name', ''),
				'type' => getRequest('type', ITEM_TYPE_ZABBIX),
				'key_' => getRequest('key', ''),
				'interfaceid' => getRequest('interfaceid', 0),
				'snmp_oid' => getRequest('snmp_oid', ''),
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
				'inventory_link' => getRequest('inventory_link', 0),
				'description' => getRequest('description', ''),
				'status' => getRequest('status', ITEM_STATUS_DISABLED),
				'tags' => $tags
			];

			if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
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

			if ($item['type'] == ITEM_TYPE_SCRIPT) {
				$script_item = [
					'parameters' => getRequest('parameters', []),
					'timeout' => getRequest('timeout', DB::getDefault('items', 'timeout'))
				];

				$item = prepareScriptItemFormData($script_item) + $item;
			}

			if ($item['value_type'] == ITEM_VALUE_TYPE_LOG || $item['value_type'] == ITEM_VALUE_TYPE_TEXT) {
				unset($item['valuemapid']);
			}

			$result = (bool) API::Item()->create($item);
		}
		// Update
		else {
			$db_items = API::Item()->get([
				'output' => ['name', 'type', 'key_', 'interfaceid', 'snmp_oid', 'authtype', 'username', 'password',
					'publickey', 'privatekey', 'params', 'ipmi_sensor', 'value_type', 'units', 'delay', 'history',
					'trends', 'valuemapid', 'logtimefmt', 'trapper_hosts', 'inventory_link', 'description', 'status',
					'templateid', 'flags', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'posts',
					'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode',
					'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password',
					'verify_peer', 'verify_host', 'allow_traps', 'parameters'
				],
				'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
				'selectTags' => ['tag', 'value'],
				'itemids' => getRequest('itemid')
			]);
			$db_item = reset($db_items);

			$item = [];

			if ($db_item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
				if ($db_item['templateid'] == 0) {
					$value_type = getRequest('value_type', ITEM_VALUE_TYPE_FLOAT);

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
					if ($db_item['value_type'] != $value_type) {
						$item['value_type'] = $value_type;
					}
					if ($db_item['units'] !== getRequest('units', '')) {
						$item['units'] = getRequest('units', '');
					}
					if ($value_type != ITEM_VALUE_TYPE_LOG && $value_type != ITEM_VALUE_TYPE_TEXT
							&& bccomp($db_item['valuemapid'], getRequest('valuemapid', 0)) != 0) {
						$item['valuemapid'] = getRequest('valuemapid', 0);
					}
					if ($db_item['logtimefmt'] !== getRequest('logtimefmt', '')) {
						$item['logtimefmt'] = getRequest('logtimefmt', '');
					}
					if (bccomp($db_item['interfaceid'], getRequest('interfaceid', 0)) != 0) {
						$item['interfaceid'] = getRequest('interfaceid', 0);
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
					if ($db_item['preprocessing'] !== $preprocessing) {
						$item['preprocessing'] = $preprocessing;
					}
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
				if ($db_item['inventory_link'] != getRequest('inventory_link', 0)) {
					$item['inventory_link'] = getRequest('inventory_link', 0);
				}
				if ($db_item['description'] !== getRequest('description', '')) {
					$item['description'] = getRequest('description', '');
				}

				if ($db_item['templateid'] == 0 && $type == ITEM_TYPE_HTTPAGENT) {
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

			if (getRequest('type') == ITEM_TYPE_SCRIPT) {
				$script_item = [
					'parameters' => getRequest('parameters', []),
					'timeout' => getRequest('timeout', DB::getDefault('items', 'timeout'))
				];

				$item = prepareScriptItemFormData($script_item) + $item;
				if ($db_item['type'] == getRequest('type')) {
					$compare = function($arr, $arr2) {
						return (array_combine(array_column($arr, 'name'), array_column($arr, 'value')) ==
							array_combine(array_column($arr2, 'name'), array_column($arr2, 'value'))
						);
					};

					if ($compare($db_item['parameters'], $item['parameters'])) {
						unset($item['parameters']);
					}
					if ($db_item['timeout'] === $item['timeout']) {
						unset($item['timeout']);
					}

					if ($db_item['params'] !== getRequest('params', '')) {
						$item['params'] = getRequest('params', '');
					}
				}
				else {
					// If type is changed, even if value stays the same, it must be set. It is required by API.
					$item['params'] = getRequest('params', '');
				}
			}
			else {
				if ($db_item['params'] !== getRequest('params', '')) {
					$item['params'] = getRequest('params', '');
				}
			}

			CArrayHelper::sort($db_item['tags'], ['tag', 'value']);
			CArrayHelper::sort($tags, ['tag', 'value']);

			if (array_values($db_item['tags']) !== array_values($tags)) {
				$item['tags'] = $tags;
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
		'type' => ZBX_TM_DATA_TYPE_CHECK_NOW,
		'request' => [
			'itemid' => getRequest('itemid')
		]
	]);

	show_messages($result, _('Request sent successfully'), _('Cannot send request'));
}
// cleaning history for one item
elseif (hasRequest('del_history') && hasRequest('itemid')) {
	$result = (bool) API::History()->clear([getRequest('itemid')]);

	show_messages($result, _('History cleared'), _('Cannot clear history'));
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
	$result = (bool) API::History()->clear(getRequest('group_itemid'));

	if ($result) {
		uncheckTableRows(getRequest('checkbox_hash'));
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
	$tasks = [];

	foreach (getRequest('group_itemid') as $itemid) {
		$tasks[] = [
			'type' => ZBX_TM_DATA_TYPE_CHECK_NOW,
			'request' => [
				'itemid' => $itemid
			]
		];
	}

	$result = (bool) API::Task()->create($tasks);

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
if (getRequest('form') === 'create' || getRequest('form') === 'update'
		|| (hasRequest('clone') && getRequest('itemid') != 0)) {
	$master_item_options = [];
	$has_errors = false;

	if (hasRequest('itemid') && !hasRequest('clone')) {
		$items = API::Item()->get([
			'output' => ['itemid', 'type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
				'value_type', 'trapper_hosts', 'units', 'logtimefmt', 'templateid', 'valuemapid', 'params',
				'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'flags', 'interfaceid',
				'description', 'inventory_link', 'lifetime', 'jmx_endpoint', 'master_itemid', 'url', 'query_fields',
				'parameters', 'timeout', 'posts', 'status_codes', 'follow_redirects', 'post_type', 'http_proxy',
				'headers', 'retrieve_mode', 'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file',
				'ssl_key_password', 'verify_peer', 'verify_host', 'allow_traps'
			],
			'selectHosts' => ['status', 'name', 'flags'],
			'selectDiscoveryRule' => ['itemid', 'name', 'templateid'],
			'selectItemDiscovery' => ['parent_itemid'],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectTags' => ['tag', 'value'],
			'itemids' => getRequest('itemid')
		]);
		$item = $items[0];
		$host = $item['hosts'][0];
		unset($item['hosts']);

		$i = 0;
		foreach ($item['preprocessing'] as &$step) {
			if ($step['type'] == ZBX_PREPROC_SCRIPT) {
				$step['params'] = [$step['params'], ''];
			}
			else {
				$step['params'] = explode("\n", $step['params']);
			}
			$step['sortorder'] = $i++;
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
			'output' => ['hostid', 'name', 'status', 'flags'],
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

	$form_action = (hasRequest('clone') && getRequest('itemid') != 0) ? 'clone' : getRequest('form');
	$data = getItemFormData($item, ['form' => $form_action]);
	CArrayHelper::sort($data['preprocessing'], ['sortorder']);
	$data['inventory_link'] = getRequest('inventory_link');
	$data['host'] = $host;
	$data['preprocessing_test_type'] = CControllerPopupItemTestEdit::ZBX_TEST_TYPE_ITEM;
	$data['preprocessing_types'] = CItem::SUPPORTED_PREPROCESSING_TYPES;
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

	$data['display_interfaces'] = ($data['host']['status'] == HOST_STATUS_MONITORED
			|| $data['host']['status'] == HOST_STATUS_NOT_MONITORED);

	if (hasRequest('itemid') && !getRequest('form_refresh')) {
		$data['inventory_link'] = $item['inventory_link'];
	}

	$data['config'] = [
		'compression_status' => CHousekeepingHelper::get(CHousekeepingHelper::COMPRESSION_STATUS),
		'hk_history_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL),
		'hk_history' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY),
		'hk_trends_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL),
		'hk_trends' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS)
	];

	// render view
	if (!$has_errors) {
		echo (new CView('configuration.item.edit', $data))->getOutput();
	}
}
elseif (hasRequest('action') && getRequest('action') === 'item.masscopyto' && hasRequest('group_itemid')) {
	$data = getCopyElementsFormData('group_itemid', _('Items'));
	$data['action'] = 'item.masscopyto';

	// render view
	echo (new CView('configuration.copy.elements', $data))->getOutput();
}
// list of items
else {
	$sortField = getRequest('sort', CProfile::get($prefix.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get($prefix.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update($prefix.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update($prefix.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	// Filter and subfilter tags.
	$filter_evaltype = CProfile::get($prefix.'items.filter.evaltype', TAG_EVAL_TYPE_AND_OR);
	$filter_tags = [];
	foreach (CProfile::getArray($prefix.'items.filter.tags.tag', []) as $i => $tag) {
		$filter_tags[] = [
			'tag' => $tag,
			'value' => CProfile::get($prefix.'items.filter.tags.value', null, $i),
			'operator' => CProfile::get($prefix.'items.filter.tags.operator', null, $i)
		];
	}
	$subfilter_tags = [];
	foreach (CProfile::getArray($prefix.'items.subfilter_tags.tag', []) as $i => $tag) {
		$val = CProfile::get($prefix.'items.subfilter_tags.value', '', $i);
		$subfilter_tags[json_encode([$tag, $val])] = [
			'tag' => $tag,
			'value' => $val
		];
	}

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
		'hostid' => $hostid,
		'is_template' => true,
		'context' => getRequest('context')
	];

	// items
	$options = [
		'search' => [],
		'output' => [
			'itemid', 'type', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status', 'value_type', 'error',
			'templateid', 'flags', 'state', 'master_itemid'
		],
		'templated' => ($data['context'] === 'template'),
		'editable' => true,
		'selectHosts' => API_OUTPUT_EXTEND,
		'selectTriggers' => ['triggerid'],
		'selectDiscoveryRule' => API_OUTPUT_EXTEND,
		'selectItemDiscovery' => ['ts_delete'],
		'selectTags' => ['tag', 'value'],
		'sortfield' => $sortField,
		'evaltype' => $filter_evaltype,
		'tags' => $filter_tags,
		'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
	];
	$preFilter = count($options, COUNT_RECURSIVE);

	if ($filter_hostids) {
		$options['hostids'] = $filter_hostids;
	}
	if ($filter_groupids) {
		$options['groupids'] = $filter_groupids;
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
	if (isset($_REQUEST['filter_snmp_oid']) && !zbx_empty($_REQUEST['filter_snmp_oid'])) {
		$options['filter']['snmp_oid'] = $_REQUEST['filter_snmp_oid'];
	}
	if (isset($_REQUEST['filter_value_type']) && !zbx_empty($_REQUEST['filter_value_type'])
			&& $_REQUEST['filter_value_type'] != -1) {
		$options['filter']['value_type'] = $_REQUEST['filter_value_type'];
	}
	if (array_key_exists('hostids', $options) && $_REQUEST['filter_valuemapids']) {
		$hostids = CTemplateHelper::getParentTemplatesRecursive($filter_hostids, $data['context']);

		$valuemap_names = array_unique(array_column(API::ValueMap()->get([
			'output' => ['name'],
			'valuemapids' => $_REQUEST['filter_valuemapids']
		]), 'name'));

		$options['filter']['valuemapid'] = array_column(API::ValueMap()->get([
			'output' => ['valuemapid'],
			'hostids' => $hostids,
			'filter' => ['name' => $valuemap_names]
		]), 'valuemapid');
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
		$filter_key = getRequest('filter_key');
		if ($filter_delay !== '') {
			if ($filter_type == -1 && $filter_delay == 0) {
				$options['filter']['type'] = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE,  ITEM_TYPE_INTERNAL,
					ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI,
					ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED, ITEM_TYPE_JMX
				];

				$options['filter']['delay'] = $filter_delay;
			}
			elseif ($filter_type == ITEM_TYPE_TRAPPER || $filter_type == ITEM_TYPE_SNMPTRAP
					|| $filter_type == ITEM_TYPE_DEPENDENT
					|| ($filter_type == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($filter_key, 'mqtt.get', 8) === 0)) {
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

	if (getRequest('filter_inherited', -1) != -1) {
		$options['inherited'] = getRequest('filter_inherited');
	}
	if (getRequest('filter_discovered', -1) != -1) {
		$options['filter']['flags'] = getRequest('filter_discovered');
	}
	if (isset($_REQUEST['filter_with_triggers']) && !zbx_empty($_REQUEST['filter_with_triggers'])
			&& $_REQUEST['filter_with_triggers'] != -1) {
		$options['with_triggers'] = $_REQUEST['filter_with_triggers'];
	}

	$data['items'] = API::Item()->get($options);
	$data['parent_templates'] = [];

	// Unset unexisting subfilter tags (subfilter tags stored in profiles may contain tags already deleted).
	if ($subfilter_tags) {
		$item_tags = [];
		foreach ($data['items'] as $item) {
			foreach ($item['tags'] as $tag) {
				$item_tags[json_encode([$tag['tag'], $tag['value']])] = true;
			}
		}
		$subfilter_tags = array_intersect_key($subfilter_tags, $item_tags);
	}

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
					|| $item['type'] == ITEM_TYPE_DEPENDENT
					|| ($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($item['key_'], 'mqtt.get', 8) === 0)) {
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
				'subfilter_inherited' =>  !getRequest('subfilter_inherited')
					|| ($item['templateid'] == 0 && uint_in_array(0, getRequest('subfilter_inherited'))
					|| ($item['templateid'] > 0 && uint_in_array(1, getRequest('subfilter_inherited')))),
				'subfilter_discovered' => !getRequest('subfilter_discovered')
					|| ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL
						&& uint_in_array(0, getRequest('subfilter_discovered'))
						|| ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED
							&& uint_in_array(1, getRequest('subfilter_discovered'))
						)
					),
				'subfilter_with_triggers' => empty($_REQUEST['subfilter_with_triggers'])
					|| (count($item['triggers']) == 0 && uint_in_array(0, $_REQUEST['subfilter_with_triggers']))
					|| (count($item['triggers']) > 0 && uint_in_array(1, $_REQUEST['subfilter_with_triggers'])),
				'subfilter_history' => (!getRequest('subfilter_history')
					|| (in_array($history, getRequest('subfilter_history')))),
				'subfilter_trends' => (!getRequest('subfilter_trends')
					|| ($trends !== '' && in_array($trends, getRequest('subfilter_trends')))),
				'subfilter_interval' => (!getRequest('subfilter_interval')
					|| ($delay !== '' && in_array($delay, getRequest('subfilter_interval')))),
				'subfilter_tags' => (!$subfilter_tags
					|| (bool) array_intersect_key($subfilter_tags, array_flip(array_map(function ($tag) {
							return json_encode([$tag['tag'], $tag['value']]);
						}, $item['tags']))))
			];
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
				CProfile::update($prefix.'items.'.$name, '', PROFILE_TYPE_STR);
				foreach ($data['items'] as &$item) {
					$item['subfilters'][$name] = true;
				}
				unset($item);
			}
		}
	}

	if ($data['context'] === 'host') {
		$host_template_filter = $filter_hostids
			? CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $filter_hostids,
				'editable' => true
			]), ['hostid' => 'id'])
			: [];
	}
	else {
		$host_template_filter = $filter_hostids
			? CArrayHelper::renameObjectsKeys(API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => $filter_hostids,
				'editable' => true
			]), ['templateid' => 'id'])
			: [];
	}

	$data['filter_data'] = [
		'groups' => hasRequest('filter_groupids')
			? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => getRequest('filter_groupids'),
				'editable' => true
			]), ['groupid' => 'id'])
			: [],
		'hosts' => $host_template_filter,
		'filter_name' => getRequest('filter_name'),
		'filter_key' => getRequest('filter_key'),
		'filter_type' => getRequest('filter_type'),
		'filter_snmp_oid' => getRequest('filter_snmp_oid'),
		'filter_value_type' => getRequest('filter_value_type'),
		'filter_delay' => getRequest('filter_delay'),
		'filter_history' => getRequest('filter_history'),
		'filter_trends' => getRequest('filter_trends'),
		'filter_status' => getRequest('filter_status'),
		'filter_inherited' => getRequest('filter_inherited'),
		'filter_with_triggers' => getRequest('filter_with_triggers'),
		'filter_valuemapids' => getRequest('filter_valuemapids'),
		'filter_evaltype' => $filter_evaltype,
		'filter_tags' => $filter_tags,
		'subfilter_hosts' => getRequest('subfilter_hosts'),
		'subfilter_types' => getRequest('subfilter_types'),
		'subfilter_status' => getRequest('subfilter_status'),
		'subfilter_value_types' => getRequest('subfilter_value_types'),
		'subfilter_inherited' => getRequest('subfilter_inherited'),
		'subfilter_with_triggers' => getRequest('subfilter_with_triggers'),
		'subfilter_history' => getRequest('subfilter_history'),
		'subfilter_trends' => getRequest('subfilter_trends'),
		'subfilter_interval' => getRequest('subfilter_interval'),
		'subfilter_tags' => $subfilter_tags
	];
	if ($data['context'] === 'host') {
		$data['filter_data'] += [
			'filter_state' => getRequest('filter_state'),
			'filter_discovered' => getRequest('filter_discovered'),
			'subfilter_state' => getRequest('subfilter_state'),
			'subfilter_discovered' => getRequest('subfilter_discovered')
		];
	}
	if ($host_template_filter) {
		$data['filter_data']['filter_valuemapids'] = $data['filter_data']['filter_valuemapids']
			? CArrayHelper::renameObjectsKeys(API::ValueMap()->get([
				'output' => ['valuemapid', 'name'],
				'valuemapids' => $data['filter_data']['filter_valuemapids']
			]), ['valuemapid' => 'id'])
			: [];
	}

	$data['subfilter'] = makeItemSubfilter($data['filter_data'], $data['items'], $data['context']);

	if (!$data['filter_data']['filter_tags']) {
		$data['filter_data']['filter_tags'] = [[
			'tag' => '',
			'value' => '',
			'operator' => TAG_OPERATOR_LIKE
		]];
	}

	// Replace hash keys by numeric index used in subfilter.
	foreach ($data['filter_data']['subfilter_tags'] as $hash => $tag) {
		$data['filter_data']['subfilter_tags'][$tag['num']] = [
			'tag' => $tag['tag'],
			'value' => $tag['value']
		];
		unset($data['filter_data']['subfilter_tags'][$hash]);
	}

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

	// Set is_template false, when one of hosts is not template.
	if ($data['items']) {
		$hosts_status = [];
		foreach ($data['items'] as $item) {
			$hosts_status[$item['hosts'][0]['status']] = true;
		}
		foreach ($hosts_status as $key => $value) {
			if ($key != HOST_STATUS_TEMPLATE) {
				$data['is_template'] = false;
				break;
			}
		}
	}

	CPagerHelper::savePage($page['file'], $page_num);

	$data['paging'] = CPagerHelper::paginate($page_num, $data['items'], $sortOrder,
		(new CUrl('items.php'))->setArgument('context', $data['context'])
	);

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

	$data['config'] = [
		'compression_status' => CHousekeepingHelper::get(CHousekeepingHelper::COMPRESSION_STATUS)
	];

	$data['allowed_ui_conf_templates'] = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);

	$data['tags'] = makeTags($data['items'], true, 'itemid', ZBX_TAG_COUNT_DEFAULT, $filter_tags);

	// render view
	echo (new CView('configuration.item.list', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';
