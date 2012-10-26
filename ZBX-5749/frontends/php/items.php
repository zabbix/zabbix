<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
$page['scripts'] = array('class.cviewswitcher.js');
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

$paramsFieldName = getParamFieldNameByType(get_request('type', 0));

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'description_visible' =>	array(T_ZBX_STR, O_OPT, null,	null,		null),
	'type_visible' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'interface_visible' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'community_visible' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'securityname_visible' =>	array(T_ZBX_STR, O_OPT, null,	null,		null),
	'securitylevel_visible' =>	array(T_ZBX_STR, O_OPT, null,	null,		null),
	'authpassphrase_visible' =>	array(T_ZBX_STR, O_OPT, null,	null,		null),
	'privpassphras_visible' =>	array(T_ZBX_STR, O_OPT, null,	null,		null),
	'port_visible' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'authtype_visible' =>	    array(T_ZBX_STR, O_OPT, null,	null,		null),
	'username_visible' =>	    array(T_ZBX_STR, O_OPT, null,	null,		null),
	'publickey_visible' =>	    array(T_ZBX_STR, O_OPT, null,	null,		null),
	'privatekey_visible' =>	    array(T_ZBX_STR, O_OPT, null,	null,		null),
	'password_visible' =>	    array(T_ZBX_STR, O_OPT, null,	null,		null),
	'value_type_visible' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'data_type_visible' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'units_visible' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'formula_visible' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'delay_visible' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'delay_flex_visible' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'history_visible' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'trends_visible' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'status_visible' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'logtimefmt_visible' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'delta_visible' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'valuemapid_visible' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'trapper_hosts_visible' =>	array(T_ZBX_STR, O_OPT, null,	null,		null),
	'applications_visible' =>	array(T_ZBX_STR, O_OPT, null,	null,		null),
	'groupid' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'hostid' =>					array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'form_hostid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID.NOT_ZERO, 'isset({save})', _('Host')),
	'interfaceid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null, _('Interface')),
	'copy_type' =>				array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	'isset({copy})'),
	'copy_mode' =>				array(T_ZBX_INT, O_OPT, P_SYS,	IN('0'),	null),
	'itemid' =>					array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'(isset({form})&&({form}=="update"))'),
	'name' => array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})', _('Name')),
	'description' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'key' => array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})', _('Key')),
	'delay' => array(T_ZBX_INT, O_OPT, null, BETWEEN(0, SEC_PER_DAY),
		'isset({save})&&(isset({type})&&({type}!='.ITEM_TYPE_TRAPPER.'&&{type}!='.ITEM_TYPE_SNMPTRAP.'))',
		_('Update interval (in sec)')),
	'new_delay_flex' => array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({add_delay_flex})&&(isset({type})&&({type}!=2))',
		_('New flexible interval')),
	'delay_flex' =>				array(T_ZBX_STR, O_OPT, null,	'',			null),
	'history' => array(T_ZBX_INT, O_OPT, null, BETWEEN(0, 65535), 'isset({save})', _('Keep history (in days)')),
	'status' =>					array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), 'isset({save})'),
	'type' =>					array(T_ZBX_INT, O_OPT, null,
		IN(array(-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV2C,
			ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL,
			ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED, ITEM_TYPE_SNMPTRAP)), 'isset({save})'),
	'trends' => array(T_ZBX_INT, O_OPT, null, BETWEEN(0, 65535), 'isset({save})&&isset({value_type})&&'.
		IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type'), _('Keep trends (in days)')),
	'value_type' =>				array(T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4'), 'isset({save})'),
	'data_type' =>				array(T_ZBX_INT, O_OPT, null,
		IN(ITEM_DATA_TYPE_DECIMAL.','.ITEM_DATA_TYPE_OCTAL.','.ITEM_DATA_TYPE_HEXADECIMAL.','.ITEM_DATA_TYPE_BOOLEAN),
		'isset({save})&&(isset({value_type})&&({value_type}=='.ITEM_VALUE_TYPE_UINT64.'))'),
	'valuemapid' =>				array(T_ZBX_INT, O_OPT, null,	DB_ID,		'isset({save})&&isset({value_type})&&'.
		IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type')),
	'authtype' =>				array(T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHTYPE_PASSWORD.','.ITEM_AUTHTYPE_PUBLICKEY),
		'isset({save})&&isset({type})&&({type}=='.ITEM_TYPE_SSH.')'),
	'username' =>				array(T_ZBX_STR, O_OPT, null,	'{type}=='.ITEM_TYPE_JMX.'||'.NOT_EMPTY,
		'isset({save})&&isset({type})&&'.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET.','.ITEM_TYPE_JMX, 'type')),
	'password' =>				array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&isset({type})&&'.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET.','.ITEM_TYPE_JMX, 'type')),
	'publickey' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'isset({save})&&isset({type})&&({type})=='.ITEM_TYPE_SSH.'&&({authtype})=='.ITEM_AUTHTYPE_PUBLICKEY),
	'privatekey' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'isset({save})&&isset({type})&&({type})=='.ITEM_TYPE_SSH.'&&({authtype})=='.ITEM_AUTHTYPE_PUBLICKEY),
	$paramsFieldName =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})&&isset({type})&&'.
		IN(ITEM_TYPE_SSH.','.ITEM_TYPE_DB_MONITOR.','.ITEM_TYPE_TELNET.','.ITEM_TYPE_CALCULATED, 'type'),
		getParamFieldLabelByType(get_request('type', 0))),
	'inventory_link' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), 'isset({save})&&{value_type}!='.ITEM_VALUE_TYPE_LOG),
	'snmp_community' => array(T_ZBX_STR, O_OPT, null, NOT_EMPTY,
		'isset({save})&&isset({type})&&'.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C,'type'), _('SNMP community')),
	'snmp_oid' => array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({save})&&isset({type})&&'.IN(
		ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3,'type'), _('SNMP OID')),
	'port' => array(T_ZBX_STR, O_OPT, null,	BETWEEN(0, 65535), 'isset({save})&&isset({type})&&'.IN(
		ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3,'type'), _('Port')),
	'snmpv3_securitylevel' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1,2'),
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
	'snmpv3_securityname' =>	array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
	'snmpv3_authpassphrase' =>	array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&(isset({type})&&({type}=='.
		ITEM_TYPE_SNMPV3.')&&({snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'||{snmpv3_securitylevel}=='.
		ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.'))'),
	'snmpv3_privpassphrase' =>	array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&(isset({type})&&({type}=='.
		ITEM_TYPE_SNMPV3.')&&({snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'))'),
	'ipmi_sensor' =>			array(T_ZBX_STR, O_OPT, NO_TRIM, NOT_EMPTY,
		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_IPMI.'))', _('IPMI sensor')),
	'trapper_hosts' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&isset({type})&&({type}==2)'),
	'units' =>					array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})&&isset({value_type})&&'.
		IN('0,3','value_type').'(isset({data_type})&&({data_type}!='.ITEM_DATA_TYPE_BOOLEAN.'))'),
	'multiplier' =>				array(T_ZBX_INT, O_OPT, null,	null,		null),
	'delta' =>					array(T_ZBX_INT, O_OPT, null,	IN('0,1,2'), 'isset({save})&&isset({value_type})&&'.
		IN('0,3','value_type').'(isset({data_type})&&({data_type}!='.ITEM_DATA_TYPE_BOOLEAN.'))'),
	'formula' =>				array(T_ZBX_DBL, O_OPT, P_UNSET_EMPTY,		'({value_type}==0&&{}!=0)||({value_type}==3&&{}>0)',
		'isset({save})&&isset({multiplier})&&({multiplier}==1)', _('Custom multiplier')),
	'logtimefmt' =>				array(T_ZBX_STR, O_OPT, null,	null,
		'isset({save})&&(isset({value_type})&&({value_type}==2))'),
	'group_itemid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'copy_targetid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'copy_groupid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({copy})&&(isset({copy_type})&&({copy_type}==0))'),
	'new_application' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'applications' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
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
	'filter_set' =>				array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'filter_group' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'filter_hostname' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
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
	'filter_port' => array(T_ZBX_INT, O_OPT, P_UNSET_EMPTY, BETWEEN(0, 65535), null, _('Port')),
	'filter_value_type' =>		array(T_ZBX_INT, O_OPT, null,	IN('-1,0,1,2,3,4'), null),
	'filter_data_type' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(-1, ITEM_DATA_TYPE_BOOLEAN), null),
	'filter_delay' => array(T_ZBX_INT, O_OPT, P_UNSET_EMPTY, BETWEEN(0, SEC_PER_DAY), null, _('Update interval')),
	'filter_history' => array(T_ZBX_INT, O_OPT, P_UNSET_EMPTY, BETWEEN(0, 65535), null, _('Keep history (in days)')),
	'filter_trends' => array(T_ZBX_INT, O_OPT, P_UNSET_EMPTY, BETWEEN(0, 65535), null, _('Keep trends (in days)')),
	'filter_status' =>			array(T_ZBX_INT, O_OPT, null,	IN('-1,0,1,3'), null),
	'filter_templated_items' => array(T_ZBX_INT, O_OPT, null,	IN('-1,0,1'), null),
	'filter_with_triggers' =>	array(T_ZBX_INT, O_OPT, null,	IN('-1,0,1'), null),
	'filter_ipmi_sensor' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	// subfilters
	'subfilter_apps' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'subfilter_types' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_value_types' =>	array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_status' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_templated_items' => array(T_ZBX_INT, O_OPT, null, null,		null),
	'subfilter_with_triggers' => array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_hosts' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_interval' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_history' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'subfilter_trends' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	// ajax
	'favobj' =>					array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>					array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>				array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})&&("filter"=={favobj})')
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);
$_REQUEST['go'] = get_request('go', 'none');
$_REQUEST['params'] = get_request($paramsFieldName, '');
unset($_REQUEST[$paramsFieldName]);

// permissions
if (get_request('itemid', false)) {
	$item = API::Item()->get(array(
		'itemids' => $_REQUEST['itemid'],
		'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL)),
		'output' => API_OUTPUT_SHORTEN,
		'editable' => true,
		'preservekeys' => true
	));
	if (empty($item)) {
		access_deny();
	}
}
elseif (get_request('hostid', 0) > 0) {
	$hosts = API::Host()->get(array(
		'hostids' => $_REQUEST['hostid'],
		'output' => API_OUTPUT_EXTEND,
		'templated_hosts' => true,
		'editable' => true
	));
	if (empty($hosts)) {
		access_deny();
	}
}

// ajax
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
	$_REQUEST['filter_hostname'] = reset($hosts);
	$_REQUEST['filter_hostname'] = $_REQUEST['filter_hostname']['name'];
}

// filter
if (isset($_REQUEST['filter_set'])) {
	$_REQUEST['filter_groupid'] = get_request('filter_groupid');
	$_REQUEST['filter_group'] = get_request('filter_group');
	$_REQUEST['filter_hostname'] = get_request('filter_hostname');
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
	$_REQUEST['filter_templated_items'] = get_request('filter_templated_items', -1);
	$_REQUEST['filter_with_triggers'] = get_request('filter_with_triggers', -1);
	$_REQUEST['filter_ipmi_sensor'] = get_request('filter_ipmi_sensor');

	CProfile::update('web.items.filter_groupid', $_REQUEST['filter_groupid'], PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_group', $_REQUEST['filter_group'], PROFILE_TYPE_STR);
	CProfile::update('web.items.filter_hostname', $_REQUEST['filter_hostname'], PROFILE_TYPE_STR);
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
	CProfile::update('web.items.filter_templated_items', $_REQUEST['filter_templated_items'], PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_with_triggers', $_REQUEST['filter_with_triggers'], PROFILE_TYPE_INT);
	CProfile::update('web.items.filter_ipmi_sensor', $_REQUEST['filter_ipmi_sensor'], PROFILE_TYPE_STR);
}
else {
	$_REQUEST['filter_groupid'] = CProfile::get('web.items.filter_groupid');
	$_REQUEST['filter_group'] = CProfile::get('web.items.filter_group');
	$_REQUEST['filter_hostname'] = CProfile::get('web.items.filter_hostname');
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
	$_REQUEST['filter_templated_items'] = CProfile::get('web.items.filter_templated_items', -1);
	$_REQUEST['filter_with_triggers'] = CProfile::get('web.items.filter_with_triggers', -1);
	$_REQUEST['filter_ipmi_sensor'] = CProfile::get('web.items.filter_ipmi_sensor');
}

if (isset($_REQUEST['filter_hostname']) && !zbx_empty($_REQUEST['filter_hostname'])) {
	$hostid = API::Host()->getObjects(array('name' => $_REQUEST['filter_hostname']));
	if (empty($hostid)) {
		$hostid = API::Template()->getObjects(array('name' => $_REQUEST['filter_hostname']));
	}
	$hostid = reset($hostid);
	if ($hostid) {
		$hostid = isset($hostid['hostid']) ? $hostid['hostid'] : $hostid['templateid'];
	}
	else {
		$hostid = 0;
	}
}

// subfilters
foreach (array('subfilter_apps', 'subfilter_types', 'subfilter_value_types', 'subfilter_status', 'subfilter_templated_items',
	'subfilter_with_triggers', 'subfilter_hosts', 'subfilter_interval', 'subfilter_history', 'subfilter_trends') as $name) {
	$_REQUEST[$name] = isset($_REQUEST['filter_set']) ? array() : get_request($name, array());
}

/*
 * Actions
 */
$result = false;
if (isset($_REQUEST['add_delay_flex']) && isset($_REQUEST['new_delay_flex'])) {
	$timePeriodValidator = new CTimePeriodValidator(array('allow_multiple' => false));
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
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['itemid'])) {
	unset($_REQUEST['itemid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save']) && $_REQUEST['form_hostid'] > 0) {
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
			'hostid' => $_REQUEST['form_hostid']
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
			'hostid' => get_request('form_hostid'),
			'interfaceid' => get_request('interfaceid', 0),
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
			'multiplier' => get_request('multiplier', 0),
			'delta' => get_request('delta'),
			'snmpv3_securityname' => get_request('snmpv3_securityname'),
			'snmpv3_securitylevel' => get_request('snmpv3_securitylevel'),
			'snmpv3_authpassphrase' => get_request('snmpv3_authpassphrase'),
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

		if (isset($_REQUEST['itemid'])) {
			$db_item = get_item_by_itemid_limited($_REQUEST['itemid']);
			$db_item['applications'] = get_applications_by_itemid($_REQUEST['itemid']);

			foreach ($item as $field => $value) {
				if ($item[$field] == $db_item[$field]) {
					unset($item[$field]);
				}
			}
			$item['itemid'] = $_REQUEST['itemid'];
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
		DBexecute('UPDATE items SET lastvalue=null,lastclock=null,prevvalue=null WHERE itemid='.$_REQUEST['itemid']);
		$host = get_host_by_hostid($item['hostid']);
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM, _('Item').' ['.$item['key_'].'] ['.$_REQUEST['itemid'].'] '.
			_('Host').' ['.$host['name'].'] '._('History cleared'));
	}
	$result = DBend($result);
	show_messages($result, _('History cleared'), _('Cannot clear history'));
}
elseif (isset($_REQUEST['update']) && isset($_REQUEST['massupdate']) && isset($_REQUEST['group_itemid'])) {

	if (get_request('delay_flex_visible')) {
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
		'snmpv3_securityname' => get_request('snmpv3_securityname'),
		'snmpv3_securitylevel' => get_request('snmpv3_securitylevel'),
		'snmpv3_authpassphrase' => get_request('snmpv3_authpassphrase'),
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
	foreach ($item as $number => $field) {
		if (is_null($field)) {
			unset($item[$number]);
		}
	}

	DBstart();
	foreach ($_REQUEST['group_itemid'] as $id) {
		$item['itemid'] = $id;
		$result = API::Item()->update($item);
		if (!$result) {
			break;
		}
	}
	$result = DBend($result);
	show_messages($result, _('Items updated'), _('Cannot update items'));

	if ($result) {
		unset($_REQUEST['group_itemid'], $_REQUEST['massupdate'], $_REQUEST['update'], $_REQUEST['form']);
	}
}
elseif ($_REQUEST['go'] == 'activate' && isset($_REQUEST['group_itemid'])) {
	$group_itemid = $_REQUEST['group_itemid'];

	DBstart();
	$go_result = activate_item($group_itemid);
	$go_result = DBend($go_result);
	show_messages($go_result, _('Items activated'), null);
}
elseif ($_REQUEST['go'] == 'disable' && isset($_REQUEST['group_itemid'])) {
	$group_itemid = $_REQUEST['group_itemid'];

	DBstart();
	$go_result = disable_item($group_itemid);
	$go_result = DBend($go_result);
	show_messages($go_result, _('Items disabled'), null);
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
					' AND '.DBcondition('hg.groupid', $group_ids)
			);
			while ($db_host = DBfetch($db_hosts)) {
				$hosts_ids[] = $db_host['hostid'];
			}
		}

		DBstart();
		$go_result = copyItemsToHosts($_REQUEST['group_itemid'], $hosts_ids);
		$go_result = DBend($go_result);

		show_messages($go_result, _('Items copied'), _('Cannot copy items'));
		$_REQUEST['go'] = 'none2';
	}
	else {
		show_error_message(_('No target selected.'));
	}
}
// clean history for selected items
elseif ($_REQUEST['go'] == 'clean_history' && isset($_REQUEST['group_itemid'])) {
	DBstart();
	$go_result = delete_history_by_itemid($_REQUEST['group_itemid']);
	DBexecute('UPDATE items SET lastvalue=null,lastclock=null,prevvalue=null WHERE '.DBcondition('itemid', $_REQUEST['group_itemid']));

	foreach ($_REQUEST['group_itemid'] as $id) {
		if (!$item = get_item_by_itemid($id)) {
			continue;
		}
		$host = get_host_by_hostid($item['hostid']);
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,
			_('Item').' ['.$item['key_'].'] ['.$id.'] '._('Host').' ['.$host['host'].'] '._('History cleared')
		);
	}
	$go_result = DBend($go_result);
	show_messages($go_result, _('History cleared'), $go_result);
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['group_itemid'])) {
	$group_itemid = $_REQUEST['group_itemid'];

	$itemsToDelete = API::Item()->get(array(
		'output' => array('key_', 'itemid'),
		'selectHosts' => array('name'),
		'itemids' => $group_itemid,
		'preservekeys' => true
	));

	$go_result = API::Item()->delete($group_itemid);

	if ($go_result) {
		foreach ($itemsToDelete as $item) {
			$host = reset($item['hosts']);

			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_ITEM, _('Item').' ['.$item['key_'].'] ['.$item['itemid'].'] '.
				_('Host').' ['.$host['name'].']');
		}

		show_messages(true, _('Items deleted'));
	}
	else {
		show_messages(false, null, _('Cannot delete items'));
	}
}
if ($_REQUEST['go'] != 'none' && !empty($go_result)) {
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray("'.$path.'")');
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
		'form' => get_request('form', null),
		'hostid' => get_request('hostid', null),
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
		'snmpv3_securityname' => get_request('snmpv3_securityname', ''),
		'snmpv3_securitylevel' => get_request('snmpv3_securitylevel', 0),
		'snmpv3_authpassphrase' => get_request('snmpv3_authpassphrase', ''),
		'snmpv3_privpassphrase' => get_request('snmpv3_privpassphrase', ''),
		'formula' => get_request('formula', '1'),
		'logtimefmt' => get_request('logtimefmt', ''),
		'initial_item_type' => null,
		'multiple_interface_types' => false
	);

	// hosts
	$data['hosts'] = API::Host()->get(array(
		'itemids' => $data['itemids'],
		'selectInterfaces' => API_OUTPUT_EXTEND
	));
	$data['is_multiple_hosts'] = count($data['hosts']) > 1;
	if (!$data['is_multiple_hosts']) {
		$data['hosts'] = reset($data['hosts']);

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

	// application
	if (count($data['applications']) == 0) {
		array_push($data['applications'], 0);
	}
	if (!empty($data['hostid'])) {
		$data['db_applications'] = DBfetchArray(DBselect(
			'SELECT a.applicationid,a.name'.
			' FROM applications a'.
			' WHERE a.hostid='.$data['hostid'].
			' ORDER BY a.name'
		));
	}

	// item types
	$data['itemTypes'] = item_type2str();
	unset($data['itemTypes'][ITEM_TYPE_HTTPTEST]);

	// valuemap
	$data['valuemaps'] = DBfetchArray(DBselect('SELECT v.valuemapid,v.name FROM valuemaps v WHERE '.DBin_node('v.valuemapid')));
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
	$data = array(
		'form' => get_request('form', null),
		'sortfield' => getPageSortField('name')
	);

	if (isset($hostid)) {
		$data['form_hostid'] = get_request('form_hostid', $hostid);
		$data['hostid'] = $hostid;
	}

	// items
	$options = array(
		'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)),
		'search' => array(),
		'output' => API_OUTPUT_EXTEND,
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
	if (!empty($data['hostid'])) {
		$options['hostids'] = $hostid;
	}
	if (isset($_REQUEST['filter_group']) && !zbx_empty($_REQUEST['filter_group'])) {
		$options['group'] = $_REQUEST['filter_group'];
	}
	if (isset($_REQUEST['filter_hostname']) && !zbx_empty($_REQUEST['filter_hostname'])) {
		$options['name'] = $_REQUEST['filter_hostname'];
		$data['filter_hostname'] = $_REQUEST['filter_hostname'];
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
	if ($preFilter == $afterFilter) {
		$data['items'] = array();
	}
	else {
		$data['items'] = API::Item()->get($options);
	}

	// set values for subfilters, if any of subfilters = false then item shouldnt be shown
	if (!empty($data['items'])) {
		// fill template host
		fillItemsWithChildTemplates($data['items']);
		$dbHostItems = DBselect(
				'SELECT i.itemid,h.name,h.hostid'.
				' FROM hosts h,items i'.
				' WHERE i.hostid=h.hostid'.
					' AND '.DBcondition('i.itemid', zbx_objectValues($data['items'], 'templateid'))
		);
		while ($dbHostItem = DBfetch($dbHostItems)) {
			foreach ($data['items'] as $itemid => $item) {
				if ($item['templateid'] == $dbHostItem['itemid']) {
					$data['items'][$itemid]['template_host'] = $dbHostItem;
				}
			}
		}

		foreach ($data['items'] as &$item) {
			$item['hostids'] = zbx_objectValues($item['hosts'], 'hostid');
			$item['name_expanded'] = itemName($item);

			if (empty($data['filter_hostname'])) {
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
	}

	$data['flicker'] = getItemFilterForm($data['items']);

	// remove subfiltered items
	if (!empty($data['items'])) {
		foreach ($data['items'] as $number => $item) {
			foreach ($item['subfilters'] as $subfilter => $value) {
				if (!$value) {
					unset($data['items'][$number]);
					break;
				}
			}
		}
	}

	order_result($data['items'], $data['sortfield'], getPageSortOrder());
	$data['paging'] = getPagingLine($data['items']);

	$itemTriggerIds = array();
	foreach ($data['items'] as $item) {
		$itemTriggerIds = array_merge($itemTriggerIds, zbx_objectValues($item['triggers'], 'triggerid'));
	}
	$data['itemTriggers'] = API::Trigger()->get(array(
		'triggerids' => $itemTriggerIds,
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => array('hostid', 'name', 'host'),
		'selectFunctions' => API_OUTPUT_EXTEND,
		'selectItems' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	));
	$data['triggerRealHosts'] = getParentHostsByTriggers($data['itemTriggers']);

	// render view
	$itemView = new CView('configuration.item.list', $data);
	$itemView->render();
	$itemView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
