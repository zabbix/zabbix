<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
$page['scripts'] = array('class.cviewswitcher.js', 'multiselect.js');
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

$paramsFieldName = getParamFieldNameByType(get_request('type', 0));

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupid' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'hostid' =>					array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID.NOT_ZERO, 'isset({form})&&!isset({itemid})'),
	'interfaceid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null, _('Interface')),
	'copy_type' =>				array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	'isset({copy})'),
	'copy_mode' =>				array(T_ZBX_INT, O_OPT, P_SYS,	IN('0'),	null),
	'itemid' =>					array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'isset({form})&&{form}=="update"'),
	'name' =>					array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})', _('Name')),
	'description' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'key' =>					array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})', _('Key')),
	'delay' =>					array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, SEC_PER_DAY),
		'isset({save})&&isset({type})&&{type}!='.ITEM_TYPE_TRAPPER.'&&{type}!='.ITEM_TYPE_SNMPTRAP,
		_('Update interval (in sec)')),
	'new_delay_flex' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({add_delay_flex})&&isset({type})&&{type}!=2',
		_('New flexible interval')),
	'delay_flex' =>				array(T_ZBX_STR, O_OPT, null,	'',			null),
	'history' =>				array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), 'isset({save})',
		_('History storage period')
	),
	'status' =>					array(T_ZBX_INT, O_OPT, null,	IN(array(ITEM_STATUS_DISABLED, ITEM_STATUS_ACTIVE)), null),
	'type' =>					array(T_ZBX_INT, O_OPT, null,
		IN(array(-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV2C,
			ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL,
			ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED, ITEM_TYPE_SNMPTRAP)), 'isset({save})'),
	'trends' =>					array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), 'isset({save})&&isset({value_type})&&'.
		IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type'), _('Trend storage period')
	),
	'value_type' =>				array(T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4'), 'isset({save})'),
	'data_type' =>				array(T_ZBX_INT, O_OPT, null,
		IN(ITEM_DATA_TYPE_DECIMAL.','.ITEM_DATA_TYPE_OCTAL.','.ITEM_DATA_TYPE_HEXADECIMAL.','.ITEM_DATA_TYPE_BOOLEAN),
		'isset({save})&&isset({value_type})&&{value_type}=='.ITEM_VALUE_TYPE_UINT64),
	'valuemapid' =>				array(T_ZBX_INT, O_OPT, null,	DB_ID,		'isset({save})&&isset({value_type})&&'.
		IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type')),
	'authtype' =>				array(T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHTYPE_PASSWORD.','.ITEM_AUTHTYPE_PUBLICKEY),
		'isset({save})&&isset({type})&&{type}=='.ITEM_TYPE_SSH),
	'username' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'isset({save})&&isset({type})&&'.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type'), _('User name')),
	'password' =>				array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&isset({type})&&'.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type')),
	'publickey' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'isset({save})&&isset({type})&&{type}=='.ITEM_TYPE_SSH.'&&{authtype}=='.ITEM_AUTHTYPE_PUBLICKEY),
	'privatekey' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'isset({save})&&isset({type})&&({type})=='.ITEM_TYPE_SSH.'&&({authtype})=='.ITEM_AUTHTYPE_PUBLICKEY),
	$paramsFieldName =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})&&isset({type})&&'.
		IN(ITEM_TYPE_SSH.','.ITEM_TYPE_DB_MONITOR.','.ITEM_TYPE_TELNET.','.ITEM_TYPE_CALCULATED, 'type'),
		getParamFieldLabelByType(get_request('type', 0))),
	'inventory_link' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), 'isset({save})&&{value_type}!='.ITEM_VALUE_TYPE_LOG),
	'snmp_community' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'isset({save})&&isset({type})&&'.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C, 'type'), _('SNMP community')),
	'snmp_oid' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})&&isset({type})&&'.IN(
		ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3, 'type'), _('SNMP OID')),
	'port' =>					array(T_ZBX_STR, O_OPT, null,	BETWEEN(0, 65535), 'isset({save})&&isset({type})&&'.IN(
		ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3, 'type'), _('Port')),
	'snmpv3_securitylevel' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1,2'),
		'isset({save})&&isset({type})&&{type}=='.ITEM_TYPE_SNMPV3),
	'snmpv3_contextname' =>	array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&isset({type})&&{type}=='.ITEM_TYPE_SNMPV3),
	'snmpv3_securityname' =>	array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&isset({type})&&{type}=='.ITEM_TYPE_SNMPV3),
	'snmpv3_authprotocol' =>	array(T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHPROTOCOL_MD5.','.ITEM_AUTHPROTOCOL_SHA),
		'isset({save})&&isset({type})&&{type}=='.ITEM_TYPE_SNMPV3.'&&({snmpv3_securitylevel}=='.
		ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'||{snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.')'),
	'snmpv3_authpassphrase' =>	array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&isset({type})&&{type}=='.ITEM_TYPE_SNMPV3.'&&({snmpv3_securitylevel}=='.
		ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'||{snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.')'),
	'snmpv3_privprotocol' =>	array(T_ZBX_INT, O_OPT, null,	IN(ITEM_PRIVPROTOCOL_DES.','.ITEM_PRIVPROTOCOL_AES),
		'isset({save})&&isset({type})&&{type}=='.ITEM_TYPE_SNMPV3.'&&{snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV),
	'snmpv3_privpassphrase' =>	array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&isset({type})&&{type}=='.ITEM_TYPE_SNMPV3.'&&{snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV),
	'ipmi_sensor' =>			array(T_ZBX_STR, O_OPT, NO_TRIM, NOT_EMPTY,
		'isset({save})&&isset({type})&&{type}=='.ITEM_TYPE_IPMI, _('IPMI sensor')),
	'trapper_hosts' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&isset({type})&&{type}==2'),
	'units' =>					array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&isset({value_type})&&'.
		IN('0,3', 'value_type').'isset({data_type})&&{data_type}!='.ITEM_DATA_TYPE_BOOLEAN),
	'multiplier' =>				array(T_ZBX_INT, O_OPT, null,	null,		null),
	'delta' =>					array(T_ZBX_INT, O_OPT, null,	IN('0,1,2'), 'isset({save})&&isset({value_type})&&'.
		IN('0,3', 'value_type').'isset({data_type})&&{data_type}!='.ITEM_DATA_TYPE_BOOLEAN),
	'formula' =>				array(T_ZBX_DBL_STR, O_OPT, null,		'({value_type}==0&&{}!=0)||({value_type}==3&&{}>0)',
		'isset({save})&&isset({multiplier})&&{multiplier}==1', _('Custom multiplier')),
	'logtimefmt' =>				array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&isset({value_type})&&{value_type}==2'),
	'group_itemid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'copy_targetid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'copy_groupid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({copy})&&isset({copy_type})&&{copy_type}==0'),
	'new_application' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'visible' =>		array(T_ZBX_STR, O_OPT, null,		null,		null),
	'applications' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'new_applications' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'del_history' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'add_delay_flex' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	// actions
	'go' =>						array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'save' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'update' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'copy' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>					array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form' =>					array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'massupdate' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>			array(T_ZBX_INT, O_OPT, null,	null,		null),
	// filter
	'filter_set' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'filter_groupid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'filter_hostid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'filter_application' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'filter_name' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'filter_type' =>			array(T_ZBX_INT, O_OPT, null,
		IN(array(-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV2C,
		ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR,
		ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED, ITEM_TYPE_SNMPTRAP)), null),
	'filter_key' =>				array(T_ZBX_STR, O_OPT, null,	null,		null),
	'filter_snmp_community' =>	array(T_ZBX_STR, O_OPT, null,	null,		null),
	'filter_snmpv3_securityname' => array(T_ZBX_STR, O_OPT, null, null,		null),
	'filter_snmp_oid' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'filter_port' =>			array(T_ZBX_INT, O_OPT, P_UNSET_EMPTY, BETWEEN(0, 65535), null, _('Port')),
	'filter_value_type' =>		array(T_ZBX_INT, O_OPT, null,	IN('-1,0,1,2,3,4'), null),
	'filter_data_type' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(-1, ITEM_DATA_TYPE_BOOLEAN), null),
	'filter_delay' =>			array(T_ZBX_INT, O_OPT, P_UNSET_EMPTY, BETWEEN(0, SEC_PER_DAY), null, _('Update interval')),
	'filter_history' =>			array(T_ZBX_INT, O_OPT, P_UNSET_EMPTY, BETWEEN(0, 65535), null,	_('History')),
	'filter_trends' =>			array(T_ZBX_INT, O_OPT, P_UNSET_EMPTY, BETWEEN(0, 65535), null, _('Trends')),
	'filter_status' =>			array(T_ZBX_INT, O_OPT, null,	IN(array(-1, ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED)), null),
	'filter_state' =>			array(T_ZBX_INT, O_OPT, null,	IN(array(-1, ITEM_STATE_NORMAL, ITEM_STATE_NOTSUPPORTED)), null),
	'filter_templated_items' => array(T_ZBX_INT, O_OPT, null,	IN('-1,0,1'), null),
	'filter_with_triggers' =>	array(T_ZBX_INT, O_OPT, null,	IN('-1,0,1'), null),
	'filter_ipmi_sensor' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	// subfilters
	'subfilter_set' =>			array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'subfilter_apps' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'subfilter_types' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_value_types' =>	array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_status' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_state' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_templated_items' => array(T_ZBX_INT, O_OPT, null, null,		null),
	'subfilter_with_triggers' => array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_hosts' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_interval' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_history' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_trends' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	// ajax
	'favobj' =>					array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>					array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>				array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})&&"filter"=={favobj}')
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);
$_REQUEST['go'] = get_request('go', 'none');
$_REQUEST['params'] = get_request($paramsFieldName, '');
unset($_REQUEST[$paramsFieldName]);

$subfiltersList = array('subfilter_apps', 'subfilter_types', 'subfilter_value_types', 'subfilter_status',
	'subfilter_state', 'subfilter_templated_items', 'subfilter_with_triggers', 'subfilter_hosts', 'subfilter_interval',
	'subfilter_history', 'subfilter_trends'
);

/*
 * Permissions
 */
if (get_request('itemid', false)) {
	$item = API::Item()->get(array(
		'itemids' => $_REQUEST['itemid'],
		'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL)),
		'output' => array('itemid'),
		'selectHosts' => array('status'),
		'editable' => true,
		'preservekeys' => true
	));
	if (empty($item)) {
		access_deny();
	}
	$item = reset($item);
	$hosts = $item['hosts'];
}
elseif (get_request('hostid', 0) > 0) {
	$hosts = API::Host()->get(array(
		'hostids' => $_REQUEST['hostid'],
		'output' => array('status'),
		'templated_hosts' => true,
		'editable' => true
	));
	if (empty($hosts)) {
		access_deny();
	}
}

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'filter') {
		CProfile::update('web.items.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

if (!empty($hosts)) {
	$host = reset($hosts);
	$_REQUEST['filter_hostid'] = $host['hostid'];
}

// filter
if (isset($_REQUEST['filter_set'])) {
	$_REQUEST['filter_groupid'] = get_request('filter_groupid', 0);
	$_REQUEST['filter_hostid'] = get_request('filter_hostid', 0);
	$_REQUEST['filter_application'] = get_request('filter_application');
	$_REQUEST['filter_name'] = get_request('filter_name');
	$_REQUEST['filter_type'] = get_request('filter_type', -1);
	$_REQUEST['filter_key'] = get_request('filter_key');
	$_REQUEST['filter_snmp_community'] = get_request('filter_snmp_community');
	$_REQUEST['filter_snmpv3_securityname'] = get_request('filter_snmpv3_securityname');
	$_REQUEST['filter_snmp_oid'] = get_request('filter_snmp_oid');
	$_REQUEST['filter_port'] = get_request('filter_port');
	$_REQUEST['filter_value_type'] = get_request('filter_value_type', -1);
	$_REQUEST['filter_data_type'] = get_request('filter_data_type', -1);
	$_REQUEST['filter_delay'] = get_request('filter_delay');
	$_REQUEST['filter_history'] = get_request('filter_history');
	$_REQUEST['filter_trends'] = get_request('filter_trends');
	$_REQUEST['filter_status'] = get_request('filter_status', -1);
	$_REQUEST['filter_state'] = get_request('filter_state', -1);
	$_REQUEST['filter_templated_items'] = get_request('filter_templated_items', -1);
	$_REQUEST['filter_with_triggers'] = get_request('filter_with_triggers', -1);
	$_REQUEST['filter_ipmi_sensor'] = get_request('filter_ipmi_sensor');

	CProfile::update('web.items.filter_groupid', $_REQUEST['filter_groupid'], PROFILE_TYPE_ID);
	CProfile::update('web.items.filter_hostid', $_REQUEST['filter_hostid'], PROFILE_TYPE_ID);
	CProfile::update('web.items.filter_application', $_REQUEST['filter_application'], PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_name', $_REQUEST['filter_name'], PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_type', $_REQUEST['filter_type'], PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_key', $_REQUEST['filter_key'], PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_snmp_community', $_REQUEST['filter_snmp_community'], PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_snmpv3_securityname', $_REQUEST['filter_snmpv3_securityname'], PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_snmp_oid', $_REQUEST['filter_snmp_oid'], PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_port', $_REQUEST['filter_port'], PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_value_type', $_REQUEST['filter_value_type'], PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_data_type', $_REQUEST['filter_data_type'], PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_delay', $_REQUEST['filter_delay'], PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_history', $_REQUEST['filter_history'], PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_trends', $_REQUEST['filter_trends'], PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_status', $_REQUEST['filter_status'], PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_state', $_REQUEST['filter_state'], PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_templated_items', $_REQUEST['filter_templated_items'], PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_with_triggers', $_REQUEST['filter_with_triggers'], PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_ipmi_sensor', $_REQUEST['filter_ipmi_sensor'], PROFILE_TYPE_STR);

	// subfilters
	foreach ($subfiltersList as $name) {
		$_REQUEST[$name] = array();
		CProfile::update('web.items.'.$name, '', PROFILE_TYPE_STR);
	}
}
else {
	$_REQUEST['filter_groupid'] = CProfile::get('web.items.filter_groupid');
	$_REQUEST['filter_hostid'] = CProfile::get('web.items.filter_hostid');
	$_REQUEST['filter_application'] = CProfile::get('web.items.filter_application');
	$_REQUEST['filter_name'] = CProfile::get('web.items.filter_name');
	$_REQUEST['filter_type'] = CProfile::get('web.items.filter_type', -1);
	$_REQUEST['filter_key'] = CProfile::get('web.items.filter_key');
	$_REQUEST['filter_snmp_community'] = CProfile::get('web.items.filter_snmp_community');
	$_REQUEST['filter_snmpv3_securityname'] = CProfile::get('web.items.filter_snmpv3_securityname');
	$_REQUEST['filter_snmp_oid'] = CProfile::get('web.items.filter_snmp_oid');
	$_REQUEST['filter_port'] = CProfile::get('web.items.filter_port');
	$_REQUEST['filter_value_type'] = CProfile::get('web.items.filter_value_type', -1);
	$_REQUEST['filter_data_type'] = CProfile::get('web.items.filter_data_type', -1);
	$_REQUEST['filter_delay'] = CProfile::get('web.items.filter_delay');
	$_REQUEST['filter_history'] = CProfile::get('web.items.filter_history');
	$_REQUEST['filter_trends'] = CProfile::get('web.items.filter_trends');
	$_REQUEST['filter_status'] = CProfile::get('web.items.filter_status');
	$_REQUEST['filter_state'] = CProfile::get('web.items.filter_state');
	$_REQUEST['filter_templated_items'] = CProfile::get('web.items.filter_templated_items', -1);
	$_REQUEST['filter_with_triggers'] = CProfile::get('web.items.filter_with_triggers', -1);
	$_REQUEST['filter_ipmi_sensor'] = CProfile::get('web.items.filter_ipmi_sensor');

	// subfilters
	foreach ($subfiltersList as $name) {
		if (isset($_REQUEST['subfilter_set'])) {
			$_REQUEST[$name] = get_request($name, array());
			CProfile::update('web.items.'.$name, implode(';', $_REQUEST[$name]), PROFILE_TYPE_STR);
		}
		else {
			$_REQUEST[$name] = array();
			$subfiltersVal = CProfile::get('web.items.'.$name);
			if (!zbx_empty($subfiltersVal)) {
				$_REQUEST[$name] = explode(';', $subfiltersVal);
				$_REQUEST[$name] = array_combine($_REQUEST[$name], $_REQUEST[$name]);
			}
		}
	}
}

if (!isset($_REQUEST['form']) && isset($_REQUEST['filter_hostid']) && !empty($_REQUEST['filter_hostid'])) {
	if (!isset($host)) {
		$host = API::Host()->getObjects(array('hostid' => $_REQUEST['filter_hostid']));
		if (empty($host)) {
			$host = API::Template()->getObjects(array('hostid' => $_REQUEST['filter_hostid']));
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
if (isset($_REQUEST['add_delay_flex']) && isset($_REQUEST['new_delay_flex'])) {
	$timePeriodValidator = new CTimePeriodValidator(array('allowMultiple' => false));
	$_REQUEST['delay_flex'] = get_request('delay_flex', array());

	if ($timePeriodValidator->validate($_REQUEST['new_delay_flex']['period'])) {
		array_push($_REQUEST['delay_flex'], $_REQUEST['new_delay_flex']);
		unset($_REQUEST['new_delay_flex']);
	}
	else {
		error($timePeriodValidator->getError());
		show_messages(false, null, _('Invalid time period'));
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['itemid'])) {
	$result = false;
	if ($item = get_item_by_itemid($_REQUEST['itemid'])) {
		$result = API::Item()->delete($_REQUEST['itemid']);
	}
	show_messages($result, _('Item deleted'), _('Cannot delete item'));
	unset($_REQUEST['itemid'], $_REQUEST['form']);
	clearCookies($result, get_request('hostid'));
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['itemid'])) {
	unset($_REQUEST['itemid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	$delay_flex = get_request('delay_flex', array());
	$db_delay_flex = '';
	foreach ($delay_flex as $value) {
		$db_delay_flex .= $value['delay'].'/'.$value['period'].';';
	}
	$db_delay_flex = trim($db_delay_flex, ';');

	$applications = get_request('applications', array());
	$application = reset($applications);
	if (empty($application)) {
		array_shift($applications);
	}

	DBstart();
	$result = true;

	if (!zbx_empty($_REQUEST['new_application'])) {
		$new_appid = API::Application()->create(array(
			'name' => $_REQUEST['new_application'],
			'hostid' => get_request('hostid')
		));
		if ($new_appid) {
			$new_appid = reset($new_appid['applicationids']);
			$applications[$new_appid] = $new_appid;
		}
		else {
			$result = false;
		}
	}

	if ($result) {
		$item = array(
			'name' => get_request('name'),
			'description' => get_request('description'),
			'key_' => get_request('key'),
			'hostid' => get_request('hostid'),
			'interfaceid' => get_request('interfaceid', 0),
			'delay' => get_request('delay'),
			'history' => get_request('history'),
			'status' => get_request('status', ITEM_STATUS_DISABLED),
			'type' => get_request('type'),
			'snmp_community' => get_request('snmp_community'),
			'snmp_oid' => get_request('snmp_oid'),
			'value_type' => get_request('value_type'),
			'trapper_hosts' => get_request('trapper_hosts'),
			'port' => get_request('port'),
			'units' => get_request('units'),
			'multiplier' => get_request('multiplier', 0),
			'delta' => get_request('delta'),
			'snmpv3_contextname' => get_request('snmpv3_contextname'),
			'snmpv3_securityname' => get_request('snmpv3_securityname'),
			'snmpv3_securitylevel' => get_request('snmpv3_securitylevel'),
			'snmpv3_authprotocol' => get_request('snmpv3_authprotocol'),
			'snmpv3_authpassphrase' => get_request('snmpv3_authpassphrase'),
			'snmpv3_privprotocol' => get_request('snmpv3_privprotocol'),
			'snmpv3_privpassphrase' => get_request('snmpv3_privpassphrase'),
			'formula' => get_request('formula'),
			'trends' => get_request('trends'),
			'logtimefmt' => get_request('logtimefmt'),
			'valuemapid' => get_request('valuemapid'),
			'delay_flex' => $db_delay_flex,
			'authtype' => get_request('authtype'),
			'username' => get_request('username'),
			'password' => get_request('password'),
			'publickey' => get_request('publickey'),
			'privatekey' => get_request('privatekey'),
			'params' => get_request('params'),
			'ipmi_sensor' => get_request('ipmi_sensor'),
			'data_type' => get_request('data_type'),
			'applications' => $applications,
			'inventory_link' => get_request('inventory_link')
		);

		if (hasRequest('itemid')) {
			$itemId = getRequest('itemid');

			$dbItem = get_item_by_itemid_limited($itemId);
			$dbItem['applications'] = get_applications_by_itemid($itemId);

			// unset snmpv3 fields
			if ($item['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV) {
				$item['snmpv3_authprotocol'] = ITEM_AUTHPROTOCOL_MD5;
				$item['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
			}
			elseif ($item['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
				$item['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
			}

			$item = CArrayHelper::unsetEqualValues($item, $dbItem);
			$item['itemid'] = $itemId;

			$result = API::Item()->update($item);
		}
		else {
			$result = API::Item()->create($item);
		}
	}

	$result = DBend($result);

	if (isset($_REQUEST['itemid'])) {
		show_messages($result, _('Item updated'), _('Cannot update item'));
	}
	else {
		show_messages($result, _('Item added'), _('Cannot add item'));
	}

	if ($result) {
		unset($_REQUEST['itemid'], $_REQUEST['form']);
		clearCookies($result, get_request('hostid'));
	}
}
// cleaning history for one item
elseif (isset($_REQUEST['del_history']) && isset($_REQUEST['itemid'])) {
	$result = false;

	DBstart();

	if ($item = get_item_by_itemid($_REQUEST['itemid'])) {
		$result = delete_history_by_itemid($_REQUEST['itemid']);
	}

	if ($result) {
		$host = get_host_by_hostid($_REQUEST['hostid']);
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM, _('Item').' ['.$item['key_'].'] ['.$_REQUEST['itemid'].'] '.
			_('Host').' ['.$host['name'].'] '._('History cleared'));
	}

	$result = DBend($result);

	show_messages($result, _('History cleared'), _('Cannot clear history'));
	clearCookies($result, get_request('hostid'));
}
// mass update
elseif (isset($_REQUEST['update']) && isset($_REQUEST['massupdate']) && isset($_REQUEST['group_itemid'])) {
	$visible = get_request('visible', array());
	if (isset($visible['delay_flex'])) {
		$delay_flex = get_request('delay_flex');
		if (!is_null($delay_flex)) {
			$db_delay_flex = '';
			foreach ($delay_flex as $val) {
				$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
			}
			$db_delay_flex = trim($db_delay_flex, ';');
		}
		else {
			$db_delay_flex = '';
		}
	}
	else {
		$db_delay_flex = null;
	}

	if (!is_null(get_request('formula', null))) {
		$_REQUEST['multiplier'] = 1;
	}
	if (get_request('formula', null) === '0') {
		$_REQUEST['multiplier'] = 0;
	}

	$applications = get_request('applications', null);
	if (isset($applications[0]) && $applications[0] == '0') {
		$applications = array();
	}

	try {
		DBstart();

		// add new or existing applications
		if (isset($visible['new_applications']) && !empty($_REQUEST['new_applications'])) {
			foreach ($_REQUEST['new_applications'] as $newApplication) {
				if (is_array($newApplication) && isset($newApplication['new'])) {
					$newApplications[] = array(
						'name' => $newApplication['new'],
						'hostid' => get_request('hostid')
					);
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
					$applications = array();
				}
			}
		}

		$item = array(
			'interfaceid' => get_request('interfaceid'),
			'description' => get_request('description'),
			'delay' => get_request('delay'),
			'history' => get_request('history'),
			'status' => get_request('status'),
			'type' => get_request('type'),
			'snmp_community' => get_request('snmp_community'),
			'snmp_oid' => get_request('snmp_oid'),
			'value_type' => get_request('value_type'),
			'trapper_hosts' => get_request('trapper_hosts'),
			'port' => get_request('port'),
			'units' => get_request('units'),
			'multiplier' => get_request('multiplier'),
			'delta' => get_request('delta'),
			'snmpv3_contextname' => get_request('snmpv3_contextname'),
			'snmpv3_securityname' => get_request('snmpv3_securityname'),
			'snmpv3_securitylevel' => get_request('snmpv3_securitylevel'),
			'snmpv3_authprotocol' => get_request('snmpv3_authprotocol'),
			'snmpv3_authpassphrase' => get_request('snmpv3_authpassphrase'),
			'snmpv3_privprotocol' => get_request('snmpv3_privprotocol'),
			'snmpv3_privpassphrase' => get_request('snmpv3_privpassphrase'),
			'formula' => get_request('formula'),
			'trends' => get_request('trends'),
			'logtimefmt' => get_request('logtimefmt'),
			'valuemapid' => get_request('valuemapid'),
			'delay_flex' => $db_delay_flex,
			'authtype' => get_request('authtype'),
			'username' => get_request('username'),
			'password' => get_request('password'),
			'publickey' => get_request('publickey'),
			'privatekey' => get_request('privatekey'),
			'ipmi_sensor' => get_request('ipmi_sensor'),
			'applications' => $applications,
			'data_type' => get_request('data_type')
		);

		// add applications
		if (!empty($existApplication) && (!isset($visible['applications']) || !isset($_REQUEST['applications']))) {
			foreach ($existApplication as $linkApp) {
				$linkApplications[] = array('applicationid' => $linkApp);
			}
			foreach (get_request('group_itemid') as $linkItem) {
				$linkItems[] = array('itemid' => $linkItem);
			}
			$linkApp = array(
				'applications' => $linkApplications,
				'items' => $linkItems
			);
			API::Application()->massAdd($linkApp);
		}

		foreach ($item as $key => $field) {
			if ($field === null) {
				unset($item[$key]);
			}
		}

		foreach ($_REQUEST['group_itemid'] as $id) {
			$item['itemid'] = $id;

			if (!$result = API::Item()->update($item)) {
				break;
			}
		}
	}
	catch (Exception $e) {
		$result = false;
	}

	$result = DBend($result);

	show_messages($result, _('Items updated'), _('Cannot update items'));

	if ($result) {
		unset($_REQUEST['group_itemid'], $_REQUEST['massupdate'], $_REQUEST['update'], $_REQUEST['form']);
		clearCookies($result, get_request('hostid'));
	}
}
elseif (str_in_array(getRequest('go'), array('activate', 'disable')) && hasRequest('group_itemid')) {
	$groupItemId = getRequest('group_itemid');
	$enable = (getRequest('go') == 'activate');

	DBstart();
	$result = $enable ? activate_item($groupItemId) : disable_item($groupItemId);
	$result = DBend($result);

	$updated = count($groupItemId);

	$messageSuccess = $enable
		? _n('Item enabled', 'Items enabled', $updated)
		: _n('Item disabled', 'Items disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable item', 'Cannot enable items', $updated)
		: _n('Cannot disable item', 'Cannot disable items', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
	clearCookies($result, getRequest('hostid'));
}
elseif ($_REQUEST['go'] == 'copy_to' && isset($_REQUEST['copy']) && isset($_REQUEST['group_itemid'])) {
	if (isset($_REQUEST['copy_targetid']) && $_REQUEST['copy_targetid'] > 0 && isset($_REQUEST['copy_type'])) {
		// host
		if ($_REQUEST['copy_type'] == 0) {
			$hosts_ids = $_REQUEST['copy_targetid'];
		}
		// groups
		else {
			$hosts_ids = array();
			$group_ids = $_REQUEST['copy_targetid'];

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

		$goResult = copyItemsToHosts($_REQUEST['group_itemid'], $hosts_ids);
		$goResult = DBend($goResult);

		show_messages($goResult, _('Items copied'), _('Cannot copy items'));
		clearCookies($goResult, get_request('hostid'));

		$_REQUEST['go'] = 'none2';
	}
	else {
		show_error_message(_('No target selected.'));
	}
}
// clean history for selected items
elseif ($_REQUEST['go'] == 'clean_history' && isset($_REQUEST['group_itemid'])) {
	DBstart();

	$goResult = delete_history_by_itemid($_REQUEST['group_itemid']);


	foreach ($_REQUEST['group_itemid'] as $id) {
		if (!$item = get_item_by_itemid($id)) {
			continue;
		}
		$host = get_host_by_hostid($item['hostid']);
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,
			_('Item').' ['.$item['key_'].'] ['.$id.'] '._('Host').' ['.$host['host'].'] '._('History cleared')
		);
	}

	$goResult = DBend($goResult);

	show_messages($goResult, _('History cleared'), $goResult);
	clearCookies($goResult, get_request('hostid'));
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['group_itemid'])) {
	DBstart();

	$group_itemid = $_REQUEST['group_itemid'];

	$itemsToDelete = API::Item()->get(array(
		'output' => array('key_', 'itemid'),
		'selectHosts' => array('name'),
		'itemids' => $group_itemid,
		'preservekeys' => true
	));

	$goResult = API::Item()->delete($group_itemid);

	if ($goResult) {
		foreach ($itemsToDelete as $item) {
			$host = reset($item['hosts']);
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_ITEM, _('Item').' ['.$item['key_'].'] ['.$item['itemid'].'] '.
				_('Host').' ['.$host['name'].']');
		}
	}

	show_messages(DBend($goResult), _('Items deleted'), _('Cannot delete items'));
	clearCookies($goResult, get_request('hostid'));
}

/*
 * Display
 */
if (isset($_REQUEST['form']) && str_in_array($_REQUEST['form'], array(_('Create item'), 'update', 'clone'))) {
	$data = getItemFormData();
	$data['page_header'] = _('CONFIGURATION OF ITEMS');

	// render view
	$itemView = new CView('configuration.item.edit', $data);
	$itemView->render();
	$itemView->show();
}
elseif ($_REQUEST['go'] == 'massupdate' || isset($_REQUEST['massupdate']) && isset($_REQUEST['group_itemid'])) {
	$data = array(
		'form' => get_request('form'),
		'hostid' => get_request('hostid'),
		'itemids' => get_request('group_itemid', array()),
		'description' => get_request('description', ''),
		'delay' => get_request('delay', ZBX_ITEM_DELAY_DEFAULT),
		'delay_flex' => get_request('delay_flex', array()),
		'history' => get_request('history', 90),
		'status' => get_request('status', 0),
		'type' => get_request('type', 0),
		'interfaceid' => get_request('interfaceid', 0),
		'snmp_community' => get_request('snmp_community', 'public'),
		'port' => get_request('port', ''),
		'value_type' => get_request('value_type', ITEM_VALUE_TYPE_UINT64),
		'data_type' => get_request('data_type', ITEM_DATA_TYPE_DECIMAL),
		'trapper_hosts' => get_request('trapper_hosts', ''),
		'units' => get_request('units', ''),
		'authtype' => get_request('authtype', ''),
		'username' => get_request('username', ''),
		'password' => get_request('password', ''),
		'publickey' => get_request('publickey', ''),
		'privatekey' => get_request('privatekey', ''),
		'valuemapid' => get_request('valuemapid', 0),
		'delta' => get_request('delta', 0),
		'trends' => get_request('trends', DAY_IN_YEAR),
		'applications' => get_request('applications', array()),
		'snmpv3_contextname' => get_request('snmpv3_contextname', ''),
		'snmpv3_securityname' => get_request('snmpv3_securityname', ''),
		'snmpv3_securitylevel' => get_request('snmpv3_securitylevel', 0),
		'snmpv3_authprotocol' => get_request('snmpv3_authprotocol', ITEM_AUTHPROTOCOL_MD5),
		'snmpv3_authpassphrase' => get_request('snmpv3_authpassphrase', ''),
		'snmpv3_privprotocol' => get_request('snmpv3_privprotocol', ITEM_PRIVPROTOCOL_DES),
		'snmpv3_privpassphrase' => get_request('snmpv3_privpassphrase', ''),
		'formula' => get_request('formula', '1'),
		'logtimefmt' => get_request('logtimefmt', ''),
		'initial_item_type' => null,
		'multiple_interface_types' => false,
		'visible' => get_request('visible', array())
	);

	$data['displayApplications'] = true;
	$data['displayInterfaces'] = true;

	// hosts
	$data['hosts'] = API::Host()->get(array(
		'itemids' => $data['itemids'],
		'selectInterfaces' => API_OUTPUT_EXTEND
	));
	$hostCount = count($data['hosts']);

	if ($hostCount > 1) {
		$data['displayApplications'] = false;
		$data['displayInterfaces'] = false;
	}
	else {
		// get template count to display applications multiselect only for single template
		$templates = API::Template()->get(array(
			'output' => array('templateid'),
			'itemids' => $data['itemids']
		));
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

			// if selected from filter without 'hostid'
			if (!$data['hostid']) {
				$data['hostid'] = $data['hosts']['hostid'];
			}

			// set the initial chosen interface to one of the interfaces the items use
			$items = API::Item()->get(array(
				'itemids' => zbx_objectValues($data['hosts']['items'], 'itemid'),
				'output' => array('itemid', 'type')
			));
			$usedInterfacesTypes = array();
			foreach ($items as $item) {
				$usedInterfacesTypes[$item['type']] = itemTypeInterface($item['type']);
			}
			$initialItemType = min(array_keys($usedInterfacesTypes));
			$data['type'] = (get_request('type') !== null) ? ($data['type']) : $initialItemType;
			$data['initial_item_type'] = $initialItemType;
			$data['multiple_interface_types'] = (count(array_unique($usedInterfacesTypes)) > 1);
		}
	}

	// item types
	$data['itemTypes'] = item_type2str();
	unset($data['itemTypes'][ITEM_TYPE_HTTPTEST]);

	// valuemap
	$data['valuemaps'] = DBfetchArray(DBselect(
			'SELECT v.valuemapid,v.name'.
			' FROM valuemaps v'.
			whereDbNode('v.valuemapid')
	));
	order_result($data['valuemaps'], 'name');

	// render view
	$itemView = new CView('configuration.item.massupdate', $data);
	$itemView->render();
	$itemView->show();
}
elseif ($_REQUEST['go'] == 'copy_to' && isset($_REQUEST['group_itemid'])) {
	$data = array(
		'group_itemid' => get_request('group_itemid', array()),
		'hostid' => get_request('hostid', 0),
		'copy_type' => get_request('copy_type', 0),
		'copy_groupid' => get_request('copy_groupid', 0),
		'copy_targetid' => get_request('copy_targetid', array())
	);

	if (!is_array($data['group_itemid']) || (is_array($data['group_itemid']) && count($data['group_itemid']) < 1)) {
		error(_('Incorrect list of items.'));
	}
	else {
		// group
		$data['groups'] = API::HostGroup()->get(array(
			'output' => API_OUTPUT_EXTEND
		));
		order_result($data['groups'], 'name');

		// hosts
		if ($data['copy_type'] == 0) {
			if (empty($data['copy_groupid'])) {
				foreach ($data['groups'] as $group) {
					$data['copy_groupid'] = $group['groupid'];
				}
			}

			$data['hosts'] = API::Host()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'groupids' => $data['copy_groupid'],
				'templated_hosts' => true
			));
			order_result($data['hosts'], 'name');
		}
	}

	// render view
	$itemView = new CView('configuration.item.copy', $data);
	$itemView->render();
	$itemView->show();
}
// list of items
else {
	$_REQUEST['hostid'] = empty($_REQUEST['filter_hostid']) ? null : $_REQUEST['filter_hostid'];

	$data = array(
		'form' => get_request('form'),
		'hostid' => get_request('hostid'),
		'sortfield' => getPageSortField('name'),
		'displayNodes' => (is_array(get_current_nodeid()) && empty($_REQUEST['filter_groupid']) && empty($_REQUEST['filter_hostid']))
	);

	// items
	$options = array(
		'hostids' => $data['hostid'],
		'search' => array(),
		'output' => array(
			'itemid', 'type', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status', 'value_type', 'error',
			'templateid', 'flags', 'state'
		),
		'editable' => true,
		'selectHosts' => API_OUTPUT_EXTEND,
		'selectTriggers' => API_OUTPUT_REFER,
		'selectApplications' => API_OUTPUT_EXTEND,
		'selectDiscoveryRule' => API_OUTPUT_EXTEND,
		'selectItemDiscovery' => array('ts_delete'),
		'sortfield' => $data['sortfield'],
		'limit' => $config['search_limit'] + 1
	);
	$preFilter = count($options, COUNT_RECURSIVE);

	if (isset($_REQUEST['filter_groupid']) && !empty($_REQUEST['filter_groupid'])) {
		$options['groupids'] = $_REQUEST['filter_groupid'];
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
	if (isset($_REQUEST['filter_data_type']) && !zbx_empty($_REQUEST['filter_data_type'])
			&& $_REQUEST['filter_data_type'] != -1) {
		$options['filter']['data_type'] = $_REQUEST['filter_data_type'];
	}
	if (isset($_REQUEST['filter_delay']) && !zbx_empty($_REQUEST['filter_delay'])) {
		$options['filter']['delay'] = $_REQUEST['filter_delay'];
	}
	if (isset($_REQUEST['filter_history']) && !zbx_empty($_REQUEST['filter_history'])) {
		$options['filter']['history'] = $_REQUEST['filter_history'];
	}
	if (isset($_REQUEST['filter_trends']) && !zbx_empty($_REQUEST['filter_trends'])) {
		$options['filter']['trends'] = $_REQUEST['filter_trends'];
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
	if (isset($_REQUEST['filter_with_triggers']) && !zbx_empty($_REQUEST['filter_with_triggers'])
			&& $_REQUEST['filter_with_triggers'] != -1) {
		$options['with_triggers'] = $_REQUEST['filter_with_triggers'];
	}
	if (isset($_REQUEST['filter_ipmi_sensor']) && !zbx_empty($_REQUEST['filter_ipmi_sensor'])) {
		$options['filter']['ipmi_sensor'] = $_REQUEST['filter_ipmi_sensor'];
	}

	$afterFilter = count($options, COUNT_RECURSIVE);
	if (empty($options['hostids']) && $preFilter == $afterFilter) {
		$data['items'] = array();
	}
	else {
		$data['items'] = API::Item()->get($options);
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
		$data['items'] = CMacrosResolverHelper::resolveItemNames($data['items']);

		foreach ($data['items'] as &$item) {
			$item['hostids'] = zbx_objectValues($item['hosts'], 'hostid');

			if (empty($data['filter_hostid'])) {
				$host = reset($item['hosts']);
				$item['host'] = $host['name'];
			}

			$item['subfilters'] = array(
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
				'subfilter_with_triggers' => empty($_REQUEST['subfilter_with_triggers'])
					|| (count($item['triggers']) == 0 && uint_in_array(0, $_REQUEST['subfilter_with_triggers']))
					|| (count($item['triggers']) > 0 && uint_in_array(1, $_REQUEST['subfilter_with_triggers'])),
				'subfilter_history' => empty($_REQUEST['subfilter_history'])
					|| uint_in_array($item['history'], $_REQUEST['subfilter_history']),
				'subfilter_trends' => empty($_REQUEST['subfilter_trends'])
					|| uint_in_array($item['trends'], $_REQUEST['subfilter_trends']),
				'subfilter_interval' => empty($_REQUEST['subfilter_interval'])
					|| uint_in_array($item['delay'], $_REQUEST['subfilter_interval']),
				'subfilter_apps' => empty($_REQUEST['subfilter_apps'])
			);

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

				$applications = array();
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
				$_REQUEST[$name] = array();
				CProfile::update('web.items.'.$name, '', PROFILE_TYPE_STR);
				foreach ($data['items'] as &$item) {
					$item['subfilters'][$name] = true;
				}
				unset($item);
			}
		}
	}

	$data['flicker'] = getItemFilterForm($data['items']);

	// remove subfiltered items
	if (!empty($data['items'])) {
		foreach ($data['items'] as $number => $item) {
			foreach ($item['subfilters'] as $value) {
				if (!$value) {
					unset($data['items'][$number]);
					break;
				}
			}
		}
	}

	if ($data['sortfield'] === 'status') {
		orderItemsByStatus($data['items'], getPageSortOrder());
	}
	else {
		order_result($data['items'], $data['sortfield'], getPageSortOrder());
	}

	$data['paging'] = getPagingLine($data['items'], array('itemid'));

	$itemTriggerIds = array();
	foreach ($data['items'] as $item) {
		$itemTriggerIds = array_merge($itemTriggerIds, zbx_objectValues($item['triggers'], 'triggerid'));
	}
	$data['itemTriggers'] = API::Trigger()->get(array(
		'triggerids' => $itemTriggerIds,
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => array('hostid', 'name', 'host'),
		'selectFunctions' => API_OUTPUT_EXTEND,
		'selectItems' => array('itemid', 'hostid', 'key_', 'type', 'flags', 'status'),
		'preservekeys' => true
	));
	$data['triggerRealHosts'] = getParentHostsByTriggers($data['itemTriggers']);

	// nodes
	if ($data['displayNodes']) {
		foreach ($data['items'] as $key => $item) {
			$data['items'][$key]['nodename'] = get_node_name_by_elid($item['itemid'], true);
		}
	}

	// determine, show or not column of errors
	if (isset($hosts)) {
		$host = reset($hosts);
		$data['showErrorColumn'] = ($host['status'] != HOST_STATUS_TEMPLATE);
	}
	else {
		$data['showErrorColumn'] = true;
	}

	// render view
	$itemView = new CView('configuration.item.list', $data);
	$itemView->render();
	$itemView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
