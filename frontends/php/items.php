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
require_once dirname(__FILE__).'/include/hostgroups.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of items');
$page['file'] = 'items.php';
$page['scripts'] = ['class.cviewswitcher.js', 'multiselect.js', 'items.js'];

require_once dirname(__FILE__).'/include/page_header.php';

$paramsFieldName = getParamFieldNameByType(getRequest('type', 0));

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hostid' =>					[T_ZBX_INT, O_OPT, P_SYS,	DB_ID.NOT_ZERO, 'isset({form}) && !isset({itemid})'],
	'interfaceid' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null, _('Interface')],
	'copy_type' =>				[T_ZBX_INT, O_OPT, P_SYS,	IN('0,1,2'), 'isset({copy})'],
	'copy_mode' =>				[T_ZBX_INT, O_OPT, P_SYS,	IN('0'),	null],
	'itemid' =>					[T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'],
	'name' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({add}) || isset({update})', _('Name')],
	'description' =>			[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'key' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({add}) || isset({update})', _('Key')],
	'master_itemid' =>			[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && {type}=='.ITEM_TYPE_DEPENDENT, _('Master item')],
	'master_itemname' =>		[T_ZBX_STR, O_OPT, null,	null,			null],
	'delay' =>					[T_ZBX_TU, O_OPT, P_ALLOW_USER_MACRO, null,
		'(isset({add}) || isset({update})) && isset({type}) && {type}!='.ITEM_TYPE_TRAPPER.
			' && {type}!='.ITEM_TYPE_SNMPTRAP.' && {type}!='.ITEM_TYPE_DEPENDENT,
		_('Update interval')
	],
	'delay_flex' =>				[T_ZBX_STR, O_OPT, null,	null,			null],
	'history' =>				[T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})',
		_('History storage period')
	],
	'status' =>					[T_ZBX_INT, O_OPT, null,	IN([ITEM_STATUS_DISABLED, ITEM_STATUS_ACTIVE]), null],
	'type' =>					[T_ZBX_INT, O_OPT, null,
		IN([-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV2C,
			ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL,
			ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT]), 'isset({add}) || isset({update})'],
	'trends' =>					[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({value_type}) && '.
			IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type'),
		_('Trend storage period')
	],
	'value_type' =>				[T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4'), 'isset({add}) || isset({update})'],
	'valuemapid' =>				[T_ZBX_INT, O_OPT, null,	DB_ID,		'(isset({add}) || isset({update})) && isset({value_type}) && '.
		IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type')],
	'authtype' =>				[T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHTYPE_PASSWORD.','.ITEM_AUTHTYPE_PUBLICKEY),
		'(isset({add}) || isset({update})) && isset({type}) && {type}=='.ITEM_TYPE_SSH],
	'username' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type'), _('User name')],
	'password' =>				[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type')],
	'publickey' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SSH.' && {authtype} == '.ITEM_AUTHTYPE_PUBLICKEY],
	'privatekey' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && ({type}) == '.ITEM_TYPE_SSH.' && ({authtype}) == '.ITEM_AUTHTYPE_PUBLICKEY],
	$paramsFieldName =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({add}) || isset({update})) && isset({type}) && '.
		IN(ITEM_TYPE_SSH.','.ITEM_TYPE_DB_MONITOR.','.ITEM_TYPE_TELNET.','.ITEM_TYPE_CALCULATED, 'type'),
		getParamFieldLabelByType(getRequest('type', 0))],
	'inventory_link' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), '(isset({add}) || isset({update})) && {value_type} != '.ITEM_VALUE_TYPE_LOG],
	'snmp_community' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && '.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C, 'type'), _('SNMP community')],
	'snmp_oid' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY, '(isset({add}) || isset({update})) && isset({type}) && '.IN(
		ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3, 'type'), _('SNMP OID')],
	'port' =>					[T_ZBX_STR, O_OPT, null,	BETWEEN(0, 65535), '(isset({add}) || isset({update})) && isset({type}) && '.IN(
		ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3, 'type'), _('Port')],
	'snmpv3_securitylevel' =>	[T_ZBX_INT, O_OPT, null,	IN('0,1,2'),
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SNMPV3],
	'snmpv3_contextname' =>	[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SNMPV3],
	'snmpv3_securityname' =>	[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SNMPV3],
	'snmpv3_authprotocol' =>	[T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHPROTOCOL_MD5.','.ITEM_AUTHPROTOCOL_SHA),
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SNMPV3.' && ({snmpv3_securitylevel} == '.
		ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.' || {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.')'],
	'snmpv3_authpassphrase' =>	[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SNMPV3.' && ({snmpv3_securitylevel} == '.
		ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.' || {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.')'],
	'snmpv3_privprotocol' =>	[T_ZBX_INT, O_OPT, null,	IN(ITEM_PRIVPROTOCOL_DES.','.ITEM_PRIVPROTOCOL_AES),
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SNMPV3.' && {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV],
	'snmpv3_privpassphrase' =>	[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SNMPV3.' && {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV],
	'ipmi_sensor' =>			[T_ZBX_STR, O_OPT, P_NO_TRIM, NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_IPMI, _('IPMI sensor')],
	'trapper_hosts' =>			[T_ZBX_STR, O_OPT, null,	null,		'(isset({add}) || isset({update})) && isset({type}) && {type} == 2'],
	'units' =>					[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({value_type}) && '.
		IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type')
	],
	'logtimefmt' =>				[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({value_type}) && {value_type} == 2'],
	'preprocessing' =>			[T_ZBX_STR, O_OPT, P_NO_TRIM,	null,	null],
	'group_itemid' =>			[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'copy_targetid' =>		    [T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'copy_groupid' =>		    [T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({copy}) && (isset({copy_type}) && {copy_type} == 0)'],
	'new_application' =>		[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'visible' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'applications' =>			[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'new_applications' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'del_history' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'jmx_endpoint' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_JMX
	],
	// actions
	'action' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
									IN('"item.massclearhistory","item.masscopyto","item.massdelete",'.
										'"item.massdisable","item.massenable","item.massupdateform"'
									),
									null
								],
	'add' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'copy' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'massupdate' =>				[T_ZBX_STR, O_OPT, P_SYS, null,	null],
	'cancel' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>			[T_ZBX_INT, O_OPT, null,	null,		null],
	// filter
	'filter_set' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_rst' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_groupid' =>			[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'filter_hostid' =>			[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'filter_application' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_name' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_type' =>			[T_ZBX_INT, O_OPT, null,
		IN([-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV2C,
		ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR,
		ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED, ITEM_TYPE_SNMPTRAP,
		ITEM_TYPE_DEPENDENT]), null],
	'filter_key' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_snmp_community' =>	[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_snmpv3_securityname' => [T_ZBX_STR, O_OPT, null, null,		null],
	'filter_snmp_oid' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_port' =>			[T_ZBX_INT, O_OPT, P_UNSET_EMPTY, BETWEEN(0, 65535), null, _('Port')],
	'filter_value_type' =>		[T_ZBX_INT, O_OPT, null,	IN('-1,0,1,2,3,4'), null],
	'filter_delay' =>			[T_ZBX_STR, O_OPT, P_UNSET_EMPTY, null, null, _('Update interval')],
	'filter_history' =>			[T_ZBX_STR, O_OPT, P_UNSET_EMPTY, null, null, _('History')],
	'filter_trends' =>			[T_ZBX_STR, O_OPT, P_UNSET_EMPTY, null, null, _('Trends')],
	'filter_status' =>			[T_ZBX_INT, O_OPT, null,	IN([-1, ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]), null],
	'filter_state' =>			[T_ZBX_INT, O_OPT, null,	IN([-1, ITEM_STATE_NORMAL, ITEM_STATE_NOTSUPPORTED]), null],
	'filter_templated_items' => [T_ZBX_INT, O_OPT, null,	IN('-1,0,1'), null],
	'filter_with_triggers' =>	[T_ZBX_INT, O_OPT, null,	IN('-1,0,1'), null],
	'filter_discovery' =>		[T_ZBX_INT, O_OPT, null,	IN([-1, ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]), null],
	'filter_ipmi_sensor' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	// subfilters
	'subfilter_set' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'subfilter_apps' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'subfilter_types' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_value_types' =>	[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_status' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_state' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_templated_items' => [T_ZBX_INT, O_OPT, null, null,		null],
	'subfilter_with_triggers' => [T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_discovery' =>	[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_hosts' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	'subfilter_interval' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'subfilter_history' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'subfilter_trends' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	// sort and sortorder
	'sort' =>					[T_ZBX_STR, O_OPT, P_SYS,
									IN('"delay","history","key_","name","status","trends","type"'),
									null
								],
	'sortorder' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
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

// Set sub-groups of selected group.
$filter_groupids = [];

if (hasRequest('filter_groupid')) {
	$filter_groupids = [getRequest('filter_groupid')];

	$filter_groups = API::HostGroup()->get([
		'output' => ['groupid', 'name'],
		'groupids' => $filter_groupids,
		'preservekeys' => true
	]);

	$filter_groups_names = [];

	foreach ($filter_groups as $group) {
		$filter_groups_names[] = $group['name'].'/';
	}

	if ($filter_groups_names) {
		$child_groups = API::HostGroup()->get([
			'output' => ['groupid'],
			'search' => ['name' => $filter_groups_names],
			'searchByAny' => true,
			'startSearch' => true
		]);

		foreach ($child_groups as $child_group) {
			$filter_groupids[] = $child_group['groupid'];
		}
	}
}

if ($filter_groupids) {
	$count = API::HostGroup()->get([
		'groupids' => $filter_groupids,
		'editable' => true,
		'countOutput' => true
	]);

	if ($count != count($filter_groupids)) {
		access_deny();
	}
}

if (getRequest('filter_hostid') && !isWritableHostTemplates([getRequest('filter_hostid')])) {
	access_deny();
}

if (!empty($hosts)) {
	$host = reset($hosts);
	$_REQUEST['filter_hostid'] = $host['hostid'];
}

/*
 * Filter
 */
if (hasRequest('filter_set')) {
	CProfile::update('web.items.filter_groupid', getRequest('filter_groupid', 0), PROFILE_TYPE_ID);
	CProfile::update('web.items.filter_hostid', getRequest('filter_hostid', 0), PROFILE_TYPE_ID);
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
	CProfile::delete('web.items.filter_groupid');
	CProfile::delete('web.items.filter_application');
	CProfile::delete('web.items.filter_name');
	CProfile::delete('web.items.filter_type');
	CProfile::delete('web.items.filter_key');
	CProfile::delete('web.items.filter_snmp_community');
	CProfile::delete('web.items.filter_snmpv3_securityname');
	CProfile::delete('web.items.filter_snmp_oid');
	CProfile::delete('web.items.filter_port');
	CProfile::delete('web.items.filter_value_type');
	CProfile::delete('web.items.filter_delay');
	CProfile::delete('web.items.filter_history');
	CProfile::delete('web.items.filter_trends');
	CProfile::delete('web.items.filter_status');
	CProfile::delete('web.items.filter_state');
	CProfile::delete('web.items.filter_templated_items');
	CProfile::delete('web.items.filter_with_triggers');
	CProfile::delete('web.items.filter_ipmi_sensor');
	DBend();
}

$_REQUEST['filter_groupid'] = CProfile::get('web.items.filter_groupid', 0);
$_REQUEST['filter_hostid'] = CProfile::get('web.items.filter_hostid', 0);
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

$filterHostId = getRequest('filter_hostid');
if (!hasRequest('form') && $filterHostId) {
	if (!isset($host)) {
		$host = API::Host()->get([
			'output' => ['hostid'],
			'hostids' => $filterHostId
		]);
		if (!$host) {
			$host = API::Template()->get([
				'output' => ['templateid'],
				'templateids' => $filterHostId
			]);
		}
		$host = reset($host);
	}
	if ($host) {
		$_REQUEST['hostid'] = isset($host['hostid']) ? $host['hostid'] : $host['templateid'];
	}
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
		uncheckTableRows(getRequest('hostid'));
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
				'history' => getRequest('history', DB::getDefault('items', 'history')),
				'trends' => getRequest('trends', DB::getDefault('items', 'trends')),
				'valuemapid' => getRequest('valuemapid', 0),
				'logtimefmt' => getRequest('logtimefmt', ''),
				'trapper_hosts' => getRequest('trapper_hosts', ''),
				'applications' => $applications,
				'inventory_link' => getRequest('inventory_link', 0),
				'description' => getRequest('description', ''),
				'status' => getRequest('status', ITEM_STATUS_DISABLED)
			];

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
					'templateid', 'flags', 'jmx_endpoint', 'master_itemid'
				],
				'selectApplications' => ['applicationid'],
				'selectPreprocessing' => ['type', 'params'],
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
				$snmpv3_securitylevel = getRequest('snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);;
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
				if ($db_item['history'] != getRequest('history', DB::getDefault('items', 'history'))) {
					$item['history'] = getRequest('history', DB::getDefault('items', 'history'));
				}
				if ($db_item['trends'] != getRequest('trends', DB::getDefault('items', 'trends'))) {
					$item['trends'] = getRequest('trends', DB::getDefault('items', 'trends'));
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
			}

			if ($db_item['status'] != getRequest('status', ITEM_STATUS_DISABLED)) {
				$item['status'] = getRequest('status', ITEM_STATUS_DISABLED);
			}

			if ($db_item['preprocessing'] !== $preprocessing) {
				$item['preprocessing'] = $preprocessing;
			}

			if (getRequest('type') == ITEM_TYPE_DEPENDENT) {
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
		uncheckTableRows(getRequest('hostid'));
	}
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

	if (isset($visible['update_interval'])) {
		$delay = getRequest('delay', DB::getDefault('items', 'delay'));

		if (hasRequest('delay_flex')) {
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
					elseif (strpos($interval['period'], ';')  !== false) {
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
	}
	else {
		$delay = null;
	}

	$applications = getRequest('applications');
	if (isset($applications[0]) && $applications[0] == '0') {
		$applications = [];
	}

	try {
		DBstart();

		// add new or existing applications
		if (isset($visible['new_applications']) && !empty($_REQUEST['new_applications'])) {
			foreach ($_REQUEST['new_applications'] as $newApplication) {
				if (is_array($newApplication) && isset($newApplication['new'])) {
					$newApplications[] = [
						'name' => $newApplication['new'],
						'hostid' => getRequest('hostid')
					];
				}
				else {
					$existApplication[] = $newApplication;
				}
			}

			if (isset($newApplications)) {
				if (!$createdApplication = API::Application()->create($newApplications)) {
					throw new Exception();
				}
				if (isset($existApplication)) {
					$existApplication = array_merge($existApplication, $createdApplication['applicationids']);
				}
				else {
					$existApplication = $createdApplication['applicationids'];
				}
			}
		}

		if (isset($visible['applications'])) {
			if (isset($_REQUEST['applications'])) {
				if (isset($existApplication)) {
					$applications = array_unique(array_merge($_REQUEST['applications'], $existApplication));
				}
				else {
					$applications = $_REQUEST['applications'];
				}
			}
			else {
				if (isset($existApplication)){
					$applications = $existApplication;
				}
				else {
					$applications = [];
				}
			}
		}

		// add applications
		if (!empty($existApplication) && (!isset($visible['applications']) || !isset($_REQUEST['applications']))) {
			foreach ($existApplication as $linkApp) {
				$linkApplications[] = ['applicationid' => $linkApp];
			}
			foreach ($itemids as $itemid) {
				$link_items[] = ['itemid' => $itemid];
			}
			$linkApp = [
				'applications' => $linkApplications,
				'items' => $link_items
			];
			API::Application()->massAdd($linkApp);
		}

		$items = API::Item()->get([
			'output' => ['itemid', 'flags', 'type'],
			'itemids' => $itemids,
			'preservekeys' => true
		]);
		$items_to_update = [];

		if ($items) {
			$item = [
				'interfaceid' => getRequest('interfaceid'),
				'description' => getRequest('description'),
				'delay' => $delay,
				'history' => getRequest('history'),
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
				'trends' => getRequest('trends'),
				'logtimefmt' => getRequest('logtimefmt'),
				'valuemapid' => getRequest('valuemapid'),
				'authtype' => getRequest('authtype'),
				'jmx_endpoint' => getRequest('jmx_endpoint'),
				'username' => getRequest('username'),
				'password' => getRequest('password'),
				'publickey' => getRequest('publickey'),
				'privatekey' => getRequest('privatekey'),
				'ipmi_sensor' => getRequest('ipmi_sensor'),
				'applications' => $applications,
				'status' => getRequest('status'),
				'master_itemid' => getRequest('master_itemid')
			];
			if (hasRequest('preprocessing')) {
				$preprocessing = getRequest('preprocessing');

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

				$item['preprocessing'] = $preprocessing;
			}
			foreach ($item as $key => $field) {
				if ($field === null) {
					unset($item[$key]);
				}
			}

			$discovered_item = [];
			if (hasRequest('status')) {
				$discovered_item['status'] = getRequest('status');
			}

			foreach ($itemids as $itemid) {
				if (array_key_exists($itemid, $items)) {
					if ($items[$itemid]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
						if ($item) {
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

	if ($result) {
		unset($_REQUEST['group_itemid'], $_REQUEST['massupdate'], $_REQUEST['form']);
		uncheckTableRows(getRequest('hostid'));
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
		uncheckTableRows(getRequest('hostid'));
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
	if (hasRequest('copy_targetid') && getRequest('copy_targetid') > 0 && hasRequest('copy_type')) {
		// hosts or templates
		if (getRequest('copy_type') == COPY_TYPE_TO_HOST || getRequest('copy_type') == COPY_TYPE_TO_TEMPLATE) {
			$hosts_ids = getRequest('copy_targetid');
		}
		// host groups
		else {
			$hosts_ids = [];
			$group_ids = getRequest('copy_targetid');

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
			uncheckTableRows(getRequest('hostid'));
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
			uncheckTableRows(getRequest('hostid'));
		}
	}

	show_messages($result, _('History cleared'), _('Cannot clear history'));
}
elseif (hasRequest('action') && getRequest('action') === 'item.massdelete' && hasRequest('group_itemid')) {
	DBstart();

	$group_itemid = getRequest('group_itemid');

	$itemsToDelete = API::Item()->get([
		'output' => ['key_', 'itemid'],
		'selectHosts' => ['name'],
		'itemids' => $group_itemid,
		'preservekeys' => true
	]);

	$result = API::Item()->delete($group_itemid);

	if ($result) {
		foreach ($itemsToDelete as $item) {
			$host = reset($item['hosts']);
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_ITEM,
				_('Item').' ['.$item['key_'].'] ['.$item['itemid'].'] '._('Host').' ['.$host['name'].']'
			);
		}
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}
	show_messages($result, _('Items deleted'), _('Cannot delete items'));
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
				'snmpv3_privprotocol', 'snmpv3_contextname', 'jmx_endpoint', 'master_itemid'
			],
			'selectHosts' => ['status'],
			'selectDiscoveryRule' => ['itemid', 'name'],
			'selectPreprocessing' => ['type', 'params'],
			'itemids' => getRequest('itemid')
		]);
		$item = $items[0];
		$host = $item['hosts'][0];
		unset($item['hosts']);

		foreach ($item['preprocessing'] as &$step) {
			$step['params'] = explode("\n", $step['params']);
		}
		unset($step);

		if ($item['type'] != ITEM_TYPE_JMX) {
			$item['jmx_endpoint'] = ZBX_DEFAULT_JMX_ENDPOINT;
		}

		if ($item['type'] == ITEM_TYPE_DEPENDENT) {
			$master_item_options = [
				'itemids'	=> $item['master_itemid'],
				'output'	=> ['itemid', 'type', 'hostid', 'name', 'key_']
			];
		}
	}
	else {
		$hosts = API::Host()->get([
			'output'			=> ['status'],
			'hostids'			=> getRequest('hostid'),
			'templated_hosts'	=> true
		]);
		$item = [];
		$host = $hosts[0];

		if ($host && getRequest('master_itemid')) {
			$master_item_options = [
				'itemids' => getRequest('master_itemid'),
				'output' => ['itemid', 'type', 'hostid', 'name', 'key_'],
				'filter' => ['hostid' => $host['hostid']]
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
	$data['trends_default'] = DB::getDefault('items', 'trends');

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
		'hostid' => getRequest('hostid'),
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
		'master_itemname' => getRequest('master_itemname', ''),
		'master_itemid' => getRequest('master_itemid', 0)
	];

	$data['displayApplications'] = true;
	$data['displayInterfaces'] = true;

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

			if ($templateCount == 1 && !$data['hostid']) {
				// if selected from filter without 'hostid'
				$templates = reset($templates);
				$data['hostid'] = $templates['templateid'];
			}

			// if items belong to single template and some belong to single host, don't display application multiselect
			// and don't display application multiselect for multiple templates
			if ($hostCount == 1 && $templateCount == 1 || $templateCount > 1) {
				$data['displayApplications'] = false;
			}
		}

		if ($hostCount == 1 && $data['displayInterfaces']) {
			$data['hosts'] = reset($data['hosts']);

			// Sort interfaces to be listed starting with one selected as 'main'.
			CArrayHelper::sort($data['hosts']['interfaces'], [
				['field' => 'main', 'order' => ZBX_SORT_DOWN]
			]);

			// if selected from filter without 'hostid'
			if (!$data['hostid']) {
				$data['hostid'] = $data['hosts']['hostid'];
			}

			// set the initial chosen interface to one of the interfaces the items use
			$items = API::Item()->get([
				'itemids' => $data['itemids'],
				'output' => ['itemid', 'type']
			]);
			$usedInterfacesTypes = [];
			foreach ($items as $item) {
				$usedInterfacesTypes[$item['type']] = itemTypeInterface($item['type']);
			}
			$initialItemType = min(array_keys($usedInterfacesTypes));
			$data['type'] = (getRequest('type') !== null) ? ($data['type']) : $initialItemType;
			$data['initial_item_type'] = $initialItemType;
			$data['multiple_interface_types'] = (count(array_unique($usedInterfacesTypes)) > 1);
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

	// render view
	$itemView = new CView('configuration.item.massupdate', $data);
	$itemView->render();
	$itemView->show();
}
elseif (hasRequest('action') && getRequest('action') === 'item.masscopyto' && hasRequest('group_itemid')) {
	// render view
	$data = getCopyElementsFormData('group_itemid', _('Items'));
	$data['action'] = 'item.masscopyto';
	$graphView = new CView('configuration.copy.elements', $data);
	$graphView->render();
	$graphView->show();
}
// list of items
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$_REQUEST['hostid'] = empty($_REQUEST['filter_hostid']) ? null : $_REQUEST['filter_hostid'];

	$config = select_config();

	$data = [
		'form' => getRequest('form'),
		'hostid' => getRequest('hostid'),
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'config' => $config
	];

	// items
	$options = [
		'hostids' => $data['hostid'],
		'search' => [],
		'output' => [
			'itemid', 'type', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status', 'value_type', 'error',
			'templateid', 'flags', 'state', 'master_itemid'
		],
		'editable' => true,
		'selectHosts' => API_OUTPUT_EXTEND,
		'selectTriggers' => ['triggerid', 'description'],
		'selectApplications' => API_OUTPUT_EXTEND,
		'selectDiscoveryRule' => API_OUTPUT_EXTEND,
		'selectItemDiscovery' => ['ts_delete'],
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	];
	$preFilter = count($options, COUNT_RECURSIVE);

	$filter_groupid = getRequest('filter_groupid', []);
	if ($filter_groupid) {
		$filter_groupids = [$filter_groupid];
		$filter_groups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $filter_groupids,
			'preservekeys' => true
		]);

		$filter_groups_names = [];
		foreach ($filter_groups as $group) {
			$filter_groups_names[] = $group['name'].'/';
		}

		if ($filter_groups_names) {
			$child_groups = API::HostGroup()->get([
				'output' => ['groupid'],
				'search' => ['name' => $filter_groups_names],
				'searchByAny' => true,
				'startSearch' => true
			]);

			foreach ($child_groups as $child_group) {
				$filter_groupids[] = $child_group['groupid'];
			}
		}

		if ($filter_groupids) {
			$options['groupids'] = $filter_groupids;
		}
	}
	if (isset($_REQUEST['filter_hostid']) && !empty($_REQUEST['filter_hostid'])) {
		$data['filter_hostid'] = $_REQUEST['filter_hostid'];
	}
	if (isset($_REQUEST['filter_application']) && !zbx_empty($_REQUEST['filter_application'])) {
		$options['application'] = $_REQUEST['filter_application'];
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
	 * Trapper and SNMP trap items contain zeroes in "delay" field and, if no specific type is set, look in item types
	 * other than trapper and SNMP trap that allow zeroes. For example, when a flexible interval is used. Since trapper
	 * and SNMP trap items contain zeroes, but those zeroes should not be displayed, they cannot be filtered by entering
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

	$data['filterSet'] = ($options['hostids'] || $preFilter != count($options, COUNT_RECURSIVE));
	if ($data['filterSet']) {
		$data['items'] = API::Item()->get($options);
	}
	else {
		$data['items'] = [];
	}

	// set values for subfilters, if any of subfilters = false then item shouldnt be shown
	if ($data['items']) {
		// fill template host
		fillItemsWithChildTemplates($data['items']);

		$dbHostItems = DBselect(
			'SELECT i.itemid,h.name,h.hostid'.
			' FROM hosts h,items i'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionInt('i.itemid', zbx_objectValues($data['items'], 'templateid'))
		);
		while ($dbHostItem = DBfetch($dbHostItems)) {
			foreach ($data['items'] as &$item) {
				if ($item['templateid'] == $dbHostItem['itemid']) {
					$item['template_host'] = $dbHostItem;
				}
			}
			unset($item);
		}

		// resolve name macros
		$data['items'] = expandItemNamesWithMasterItems($data['items'], API::Item());

		$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);

		foreach ($data['items'] as &$item) {
			$item['hostids'] = zbx_objectValues($item['hosts'], 'hostid');

			if (empty($data['filter_hostid'])) {
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

	// Draw the filter and subfilter.
	$data['flicker'] = getItemFilterForm($data['items']);

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

	$itemTriggerIds = [];
	foreach ($data['items'] as $item) {
		$itemTriggerIds = array_merge($itemTriggerIds, zbx_objectValues($item['triggers'], 'triggerid'));
	}
	$data['itemTriggers'] = API::Trigger()->get([
		'triggerids' => $itemTriggerIds,
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => ['hostid', 'name', 'host'],
		'selectFunctions' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	]);
	$data['triggerRealHosts'] = getParentHostsByTriggers($data['itemTriggers']);

	// Select writable templates IDs.
	$hostids = [];

	foreach ($data['triggerRealHosts'] as $real_host) {
		$hostids = array_merge($hostids, zbx_objectValues($real_host, 'hostid'));
	}

	foreach ($data['items'] as $item) {
		if (array_key_exists('template_host', $item)) {
			$hostids = array_merge($hostids, zbx_objectValues($item['template_host'], 'hostid'));
		}
	}

	$data['writable_templates'] = [];

	if ($hostids) {
		$data['writable_templates'] = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => array_keys(array_flip($hostids)),
			'editable' => true,
			'preservekeys' => true
		]);
	}

	// determine, show or not column of errors
	if (isset($hosts)) {
		$host = reset($hosts);

		$data['showInfoColumn'] = ($host['status'] != HOST_STATUS_TEMPLATE);
	}
	else {
		$data['showInfoColumn'] = true;
	}

	// render view
	$itemView = new CView('configuration.item.list', $data);
	$itemView->render();
	$itemView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
