<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once('include/config.inc.php');
require_once('include/hosts.inc.php');
require_once('include/items.inc.php');
require_once('include/forms.inc.php');

$page['title'] = 'S_CONFIGURATION_OF_ITEMS';
$page['file'] = 'items.php';
$page['scripts'] = array('class.cviewswitcher.js');
$page['hist_arg'] = array();

include_once('include/page_header.php');
?>
<?php
// needed type to know which field name to use
$itemType = get_request('type', 0);
switch($itemType) {
	case ITEM_TYPE_SSH: case ITEM_TYPE_TELNET: $paramsFieldName = S_EXECUTED_SCRIPT; break;
	case ITEM_TYPE_DB_MONITOR: $paramsFieldName = S_PARAMS; break;
	case ITEM_TYPE_CALCULATED: $paramsFieldName = S_FORMULA; break;
	default: $paramsFieldName = 'params';
}
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'type_visible'=>			array(T_ZBX_STR, O_OPT,  null, null,           null),
		'community_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'securityname_visible'=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		'securitylevel_visible'=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		'authpassphrase_visible'=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		'privpassphras_visible'=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		'port_visible'=>			array(T_ZBX_STR, O_OPT,  null, null,           null),
		'value_type_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'data_type_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'units_visible'=>			array(T_ZBX_STR, O_OPT,  null, null,           null),
		'formula_visible'=>			array(T_ZBX_STR, O_OPT,  null, null,           null),
		'delay_visible'=>			array(T_ZBX_STR, O_OPT,  null, null,           null),
		'delay_flex_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'history_visible'=>			array(T_ZBX_STR, O_OPT,  null, null,           null),
		'trends_visible'=>			array(T_ZBX_STR, O_OPT,  null, null,           null),
		'status_visible'=>			array(T_ZBX_STR, O_OPT,  null, null,           null),
		'logtimefmt_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'delta_visible'=>			array(T_ZBX_STR, O_OPT,  null, null,           null),
		'valuemapid_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'trapper_hosts_visible'=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		'applications_visible'=>	array(T_ZBX_STR, O_OPT,  null, null,           null),

		'groupid'=>			array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,			null),
		'hostid'=>			array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,			null),
		'form_hostid'=>			array(T_ZBX_INT, O_OPT,  null,	DB_ID,		'isset({save})', S_HOST),


		'add_groupid'=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,			'(isset({register})&&({register}=="go"))'),
		'action'=>			array(T_ZBX_STR, O_OPT,	 P_SYS,	NOT_EMPTY,		'(isset({register})&&({register}=="go"))'),

		'copy_type'=>			array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0,1'),	'isset({copy})'),
		'copy_mode'=>			array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0'),	null),

		'itemid'=>			array(T_ZBX_INT, O_NO,	 P_SYS,	DB_ID,			'(isset({form})&&({form}=="update"))'),
		'description'=>		array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,		'isset({save})'),
		'key'=>				array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,		'isset({save})'),
		'delay'=>			array(T_ZBX_INT, O_OPT,  null,  '(('.BETWEEN(1,86400).
				'(!isset({delay_flex}) || !({delay_flex}) || is_array({delay_flex}) && !count({delay_flex}))) ||'.
				'('.BETWEEN(0,86400).'isset({delay_flex})&&is_array({delay_flex})&&count({delay_flex})>0))&&',
				'isset({save})&&(isset({type})&&({type}!='.ITEM_TYPE_TRAPPER.'))'),
		'new_delay_flex'=>		array(T_ZBX_STR, O_OPT,  NOT_EMPTY,  '',	'isset({add_delay_flex})&&(isset({type})&&({type}!=2))'),
		'rem_delay_flex'=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,86400),null),
		'delay_flex'=>		array(T_ZBX_STR, O_OPT,  null,  '',null),
		'history'=>			array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),'isset({save})'),
		'status'=>			array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),'isset({save})'),
		'type'=>			array(T_ZBX_INT, O_OPT,  null,
				IN(array(-1,ITEM_TYPE_ZABBIX,ITEM_TYPE_SNMPV1,ITEM_TYPE_TRAPPER,ITEM_TYPE_SIMPLE,
					ITEM_TYPE_SNMPV2C,ITEM_TYPE_INTERNAL,ITEM_TYPE_SNMPV3,ITEM_TYPE_ZABBIX_ACTIVE,
					ITEM_TYPE_AGGREGATE,ITEM_TYPE_EXTERNAL,ITEM_TYPE_DB_MONITOR,
					ITEM_TYPE_IPMI,ITEM_TYPE_SSH,ITEM_TYPE_TELNET,ITEM_TYPE_CALCULATED)),'isset({save})'),
		'trends'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),	'isset({save})&&isset({value_type})&&'.IN(
												ITEM_VALUE_TYPE_FLOAT.','.
												ITEM_VALUE_TYPE_UINT64, 'value_type')),
		'value_type'=>		array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2,3,4'),	'isset({save})'),
		'data_type'=>		array(T_ZBX_INT, O_OPT,  null,  IN(ITEM_DATA_TYPE_DECIMAL.','.ITEM_DATA_TYPE_OCTAL.','.ITEM_DATA_TYPE_HEXADECIMAL),
					'isset({save})&&(isset({value_type})&&({value_type}=='.ITEM_VALUE_TYPE_UINT64.'))'),
		'valuemapid'=>		array(T_ZBX_INT, O_OPT,	 null,	DB_ID,		'isset({save})&&isset({value_type})&&'.IN(
												ITEM_VALUE_TYPE_FLOAT.','.
												ITEM_VALUE_TYPE_UINT64, 'value_type')),
		'authtype'=>		array(T_ZBX_INT, O_OPT,  NULL,	IN(ITEM_AUTHTYPE_PASSWORD.','.ITEM_AUTHTYPE_PUBLICKEY),
											'isset({save})&&isset({type})&&({type}=='.ITEM_TYPE_SSH.')'),
		'username'=>		array(T_ZBX_STR, O_OPT,  NULL,	NULL,		'isset({save})&&isset({type})&&'.IN(
												ITEM_TYPE_SSH.','.
												ITEM_TYPE_TELNET, 'type')),
		'password'=>		array(T_ZBX_STR, O_OPT,  NULL,	NULL,		'isset({save})&&isset({type})&&'.IN(
												ITEM_TYPE_SSH.','.
												ITEM_TYPE_TELNET, 'type')),
		'publickey'=>		array(T_ZBX_STR, O_OPT,  NULL,	NULL,		'isset({save})&&isset({type})&&({type})=='.ITEM_TYPE_SSH.'&&({authtype})=='.ITEM_AUTHTYPE_PUBLICKEY),
		'privatekey'=>		array(T_ZBX_STR, O_OPT,  NULL,	NULL,		'isset({save})&&isset({type})&&({type})=='.ITEM_TYPE_SSH.'&&({authtype})=='.ITEM_AUTHTYPE_PUBLICKEY),
		'params'=>		array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,	'isset({save})&&isset({type})&&'.IN(
												ITEM_TYPE_SSH.','.
												ITEM_TYPE_DB_MONITOR.','.
												ITEM_TYPE_TELNET.','.
												ITEM_TYPE_CALCULATED,'type'), $paramsFieldName),
		//hidden fields for better gui
		'params_script'=>	array(T_ZBX_STR, O_OPT, NULL, NULL, NULL),
		'params_dbmonitor'=>	array(T_ZBX_STR, O_OPT, NULL, NULL, NULL),
		'params_calculted'=>	array(T_ZBX_STR, O_OPT, NULL, NULL, NULL),

		'snmp_community'=>	array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,		'isset({save})&&isset({type})&&'.IN(
													ITEM_TYPE_SNMPV1.','.
													ITEM_TYPE_SNMPV2C,'type')),
		'snmp_oid'=>		array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,		'isset({save})&&isset({type})&&'.IN(
													ITEM_TYPE_SNMPV1.','.
													ITEM_TYPE_SNMPV2C.','.
													ITEM_TYPE_SNMPV3,'type')),
		'snmp_port'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),	'isset({save})&&isset({type})&&'.IN(
													ITEM_TYPE_SNMPV1.','.
													ITEM_TYPE_SNMPV2C.','.
													ITEM_TYPE_SNMPV3,'type')),

		'snmpv3_securitylevel'=>array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2'),	'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
		'snmpv3_securityname'=>	array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
		'snmpv3_authpassphrase'=>array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.')&&({snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'||{snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.'))'),
		'snmpv3_privpassphrase'=>array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.')&&({snmpv3_securitylevel}=='.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'))'),

		'ipmi_sensor'=>		array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,	'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_IPMI.'))', S_IPMI_SENSOR),

		'trapper_hosts'=>	array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})&&isset({type})&&({type}==2)'),
		'units'=>		array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&isset({value_type})&&'.IN('0,3','value_type')),
		'multiplier'=>		array(T_ZBX_INT, O_OPT,  null,  null,		null),
		'delta'=>		array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2'),	'isset({save})&&isset({value_type})&&'.IN('0,3','value_type')),

		'formula'=>		array(T_ZBX_DBL, O_OPT,  P_UNSET_EMPTY,  '({value_type}==0&&{}!=0)||({value_type}==3&&{}>0)',	'isset({save})&&isset({multiplier})&&({multiplier}==1)', S_CUSTOM_MULTIPLIER),
		'logtimefmt'=>		array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&(isset({value_type})&&({value_type}==2))'),

		'group_itemid'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),
		'copy_targetid'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),
		'filter_groupid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	'isset({copy})&&(isset({copy_type})&&({copy_type}==0))'),
		'new_application'=>	array(T_ZBX_STR, O_OPT, null,	null,	'isset({save})'),
		'applications'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),

		'del_history'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'add_delay_flex'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_delay_flex'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
// Actions
		'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
// form
		'register'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'clone'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'update'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'copy'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'select'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'massupdate'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	null,	null,	null),
// filter
		'filter_set' =>		array(T_ZBX_STR, O_OPT,	P_ACT,	null,	null),

		'filter_group'=>			array(T_ZBX_STR, O_OPT,  null,	null,		null),
		'filter_host'=>				array(T_ZBX_STR, O_OPT,  null,	null,		null),
		'filter_hostid'=>			array(T_ZBX_INT, O_OPT,  null,	DB_ID,		null),
		'filter_application'=>		array(T_ZBX_STR, O_OPT,  null,	null,		null),
		'filter_description'=>		array(T_ZBX_STR, O_OPT,  null,	null,		null),
		'filter_type'=>				array(T_ZBX_INT, O_OPT,  null,
				IN(array(-1,ITEM_TYPE_ZABBIX,ITEM_TYPE_SNMPV1,ITEM_TYPE_TRAPPER,ITEM_TYPE_SIMPLE,
				ITEM_TYPE_SNMPV2C,ITEM_TYPE_INTERNAL,ITEM_TYPE_SNMPV3,ITEM_TYPE_ZABBIX_ACTIVE,
				ITEM_TYPE_AGGREGATE,ITEM_TYPE_EXTERNAL,ITEM_TYPE_DB_MONITOR,
				ITEM_TYPE_IPMI,ITEM_TYPE_SSH,ITEM_TYPE_TELNET,ITEM_TYPE_CALCULATED)),null),
		'filter_key'=>				array(T_ZBX_STR, O_OPT,  null,  null,		null),
		'filter_snmp_community'=>array(T_ZBX_STR, O_OPT,  null,  null,	null),
		'filter_snmpv3_securityname'=>array(T_ZBX_STR, O_OPT,  null,  null,  null),
		'filter_snmp_oid'=>			array(T_ZBX_STR, O_OPT,  null,  null,	null),
		'filter_snmp_port'=>		array(T_ZBX_INT, O_OPT,  P_UNSET_EMPTY,  BETWEEN(0,65535),	null),
		'filter_value_type'=>		array(T_ZBX_INT, O_OPT,  null,  IN('-1,0,1,2,3,4'),null),
		'filter_data_type'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(-1,ITEM_DATA_TYPE_HEXADECIMAL),null),
		'filter_delay'=>			array(T_ZBX_INT, O_OPT,  P_UNSET_EMPTY,  BETWEEN(0,86400),null),
		'filter_history'=>			array(T_ZBX_INT, O_OPT,  P_UNSET_EMPTY,  BETWEEN(0,65535),null),
		'filter_trends'=>			array(T_ZBX_INT, O_OPT,  P_UNSET_EMPTY,  BETWEEN(0,65535),null),
		'filter_status'=>			array(T_ZBX_INT, O_OPT,  null,  IN('-1,0,1,3'),null),
		'filter_templated_items'=>array(T_ZBX_INT, O_OPT,  null,  IN('-1,0,1'),null),
		'filter_with_triggers'=>array(T_ZBX_INT, O_OPT,  null,  IN('-1,0,1'),null),
		'filter_ipmi_sensor' =>		array(T_ZBX_STR, O_OPT,  null,  null,	null),

// subfilters
		'subfilter_apps'=>				array(T_ZBX_STR, O_OPT,	 null,	null, null),
		'subfilter_types'=>				array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'subfilter_value_types'=>array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'subfilter_status'=>			array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'subfilter_templated_items'=>array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'subfilter_with_triggers'=>array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'subfilter_hosts'=>				array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'subfilter_interval'=>				array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'subfilter_history'=>				array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'subfilter_trends'=>				array(T_ZBX_INT, O_OPT,	 null,	null, null),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})')
	);

	check_fields($fields);
	validate_sort_and_sortorder('description', ZBX_SORT_UP);
	$_REQUEST['go'] = get_request('go', 'none');

// PERMISSIONS
	if(get_request('itemid', false)){
		$options = array(
			'itemids' => $_REQUEST['itemid'],
			'output' => API_OUTPUT_SHORTEN,
			'editable' => 1
		);
		$item = CItem::get($options);
		if(empty($item)) access_deny();
	}
	else if(get_request('hostid', 0) > 0){
		$options = array(
			'hostids' => $_REQUEST['hostid'],
			'extendoutput' => 1,
			'templated_hosts' => 1,
			'editable' => 1
		);
		$hosts = CHost::get($options);
		if(empty($hosts)) access_deny();
	}
?>
<?php
/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.items.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
//--------

	$hostid = get_request('hostid', 0);
	if($hostid > 0){
		$_REQUEST['filter_host'] = reset($hosts);
		$_REQUEST['filter_host'] = $_REQUEST['filter_host']['host'];
		$_REQUEST['filter_set'] = 1;
	}

/* FILTER */
	if(isset($_REQUEST['filter_set'])){
		$_REQUEST['filter_group'] = get_request('filter_group');
		$_REQUEST['filter_host'] = get_request('filter_host');
		$_REQUEST['filter_application'] = get_request('filter_application');
		$_REQUEST['filter_description'] = get_request('filter_description');
		$_REQUEST['filter_type'] = get_request('filter_type', -1);
		$_REQUEST['filter_key'] = get_request('filter_key');
		$_REQUEST['filter_snmp_community'] = get_request('filter_snmp_community');
		$_REQUEST['filter_snmpv3_securityname'] = get_request('filter_snmpv3_securityname');
		$_REQUEST['filter_snmp_oid'] = get_request('filter_snmp_oid');
		$_REQUEST['filter_snmp_port'] = get_request('filter_snmp_port');
		$_REQUEST['filter_value_type'] = get_request('filter_value_type', -1);
		$_REQUEST['filter_data_type'] = get_request('filter_data_type', -1);
		$_REQUEST['filter_delay'] = get_request('filter_delay');
		$_REQUEST['filter_history'] = get_request('filter_history');
		$_REQUEST['filter_trends'] = get_request('filter_trends');
		$_REQUEST['filter_status'] = get_request('filter_status');
		$_REQUEST['filter_templated_items'] = get_request('filter_templated_items', -1);
		$_REQUEST['filter_with_triggers'] = get_request('filter_with_triggers', -1);
		$_REQUEST['filter_ipmi_sensor'] = get_request('filter_ipmi_sensor');

		CProfile::update('web.items.filter_group', $_REQUEST['filter_group'], PROFILE_TYPE_STR);
		CProfile::update('web.items.filter_host', $_REQUEST['filter_host'], PROFILE_TYPE_STR);
		CProfile::update('web.items.filter_application', $_REQUEST['filter_application'], PROFILE_TYPE_STR);
		CProfile::update('web.items.filter_description', $_REQUEST['filter_description'], PROFILE_TYPE_STR);
		CProfile::update('web.items.filter_type', $_REQUEST['filter_type'], PROFILE_TYPE_INT);
		CProfile::update('web.items.filter_key', $_REQUEST['filter_key'], PROFILE_TYPE_STR);
		CProfile::update('web.items.filter_snmp_community', $_REQUEST['filter_snmp_community'], PROFILE_TYPE_STR);
		CProfile::update('web.items.filter_snmpv3_securityname', $_REQUEST['filter_snmpv3_securityname'], PROFILE_TYPE_STR);
		CProfile::update('web.items.filter_snmp_oid', $_REQUEST['filter_snmp_oid'], PROFILE_TYPE_STR);
		CProfile::update('web.items.filter_snmp_port', $_REQUEST['filter_snmp_port'], PROFILE_TYPE_STR);
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
	else{
		$_REQUEST['filter_group'] = CProfile::get('web.items.filter_group');
		$_REQUEST['filter_host'] = CProfile::get('web.items.filter_host');
		$_REQUEST['filter_application'] = CProfile::get('web.items.filter_application');
		$_REQUEST['filter_description'] = CProfile::get('web.items.filter_description');
		$_REQUEST['filter_type'] = CProfile::get('web.items.filter_type', -1);
		$_REQUEST['filter_key'] = CProfile::get('web.items.filter_key');
		$_REQUEST['filter_snmp_community'] = CProfile::get('web.items.filter_snmp_community');
		$_REQUEST['filter_snmpv3_securityname'] = CProfile::get('web.items.filter_snmpv3_securityname');
		$_REQUEST['filter_snmp_oid'] = CProfile::get('web.items.filter_snmp_oid');
		$_REQUEST['filter_snmp_port'] = CProfile::get('web.items.filter_snmp_port');
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

	if(isset($_REQUEST['filter_host']) && !zbx_empty($_REQUEST['filter_host'])){
		$hostid = CHost::getObjects(array('host' => $_REQUEST['filter_host']));
		if(empty($hostid))
			$hostid = CTemplate::getObjects(array('host' => $_REQUEST['filter_host']));

		$hostid = reset($hostid);

		$hostid = $hostid ? $hostid['hostid'] : 0;
	}

// SUBFILTERS {
	$subfilters = array('subfilter_apps', 'subfilter_types', 'subfilter_value_types', 'subfilter_status',
		'subfilter_templated_items', 'subfilter_with_triggers', 'subfilter_hosts', 'subfilter_interval', 'subfilter_history', 'subfilter_trends');

	foreach($subfilters as $name){
		if(isset($_REQUEST['filter_set'])){
			$_REQUEST[$name] = array();
		}
		else{
			$_REQUEST[$name] = get_request($name, array());
		}
	}
// } SUBFILTERS

?>
<?php
	$result = 0;
	if(isset($_REQUEST['del_delay_flex']) && isset($_REQUEST['rem_delay_flex'])){
		$_REQUEST['delay_flex'] = get_request('delay_flex',array());
		foreach($_REQUEST['rem_delay_flex'] as $val){
			unset($_REQUEST['delay_flex'][$val]);
		}
	}
	else if(isset($_REQUEST['add_delay_flex'])&&isset($_REQUEST['new_delay_flex'])){
		$_REQUEST['delay_flex'] = get_request('delay_flex', array());
		array_push($_REQUEST['delay_flex'],$_REQUEST['new_delay_flex']);
	}
	else if(isset($_REQUEST['delete'])&&isset($_REQUEST['itemid'])){
		$result = false;
		if($item = get_item_by_itemid($_REQUEST['itemid'])){
			$result = CItem::delete($_REQUEST['itemid']);
		}

		show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);

		unset($_REQUEST['itemid']);
		unset($_REQUEST['form']);
	}
	else if(isset($_REQUEST['clone']) && isset($_REQUEST['itemid'])){
		unset($_REQUEST['itemid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['save']) && ($_REQUEST['form_hostid'] > 0)){
		$applications = get_request('applications',array());
		$delay_flex = get_request('delay_flex',array());
		$db_delay_flex = '';

		foreach($delay_flex as $num => $val){
			$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
		}

		$db_delay_flex = trim($db_delay_flex,';');

		$item = array(
				'description'	=> get_request('description'),
				'key_'			=> get_request('key'),
				'hostid'		=> get_request('form_hostid'),
				'delay'			=> get_request('delay'),
				'history'		=> get_request('history'),
				'status'		=> get_request('status'),
				'type'			=> get_request('type'),
				'snmp_community'=> get_request('snmp_community'),
				'snmp_oid'		=> get_request('snmp_oid'),
				'value_type'	=> get_request('value_type'),
				'trapper_hosts'	=> get_request('trapper_hosts'),
				'snmp_port'		=> get_request('snmp_port'),
				'units'			=> get_request('units'),
			'multiplier'	=> get_request('multiplier', 0),
				'delta'			=> get_request('delta'),
				'snmpv3_securityname'	=> get_request('snmpv3_securityname'),
				'snmpv3_securitylevel'	=> get_request('snmpv3_securitylevel'),
				'snmpv3_authpassphrase'	=> get_request('snmpv3_authpassphrase'),
				'snmpv3_privpassphrase'	=> get_request('snmpv3_privpassphrase'),
				'formula'			=> get_request('formula'),
				'trends'			=> get_request('trends'),
				'logtimefmt'		=> get_request('logtimefmt'),
				'valuemapid'		=> get_request('valuemapid'),
				'delay_flex'		=> $db_delay_flex,
				'authtype'		=> get_request('authtype'),
				'username'		=> get_request('username'),
				'password'		=> get_request('password'),
				'publickey'		=> get_request('publickey'),
				'privatekey'		=> get_request('privatekey'),
				'params'			=> get_request('params'),
				'ipmi_sensor'		=> get_request('ipmi_sensor'),
			'data_type'		=> get_request('data_type')
		);

		if(isset($_REQUEST['itemid'])){
			DBstart();

			$new_appid = true;
			$result = false;

			if(!zbx_empty($_REQUEST['new_application'])){
				if($new_appid = add_application($_REQUEST['new_application'],$_REQUEST['form_hostid']))
					$applications[$new_appid] = $new_appid;
			}

			if((count($applications) == 1) && in_array(0, $applications))
				$applications = array();
			$item['applications'] = $applications;

			$db_item = get_item_by_itemid_limited($_REQUEST['itemid']);
			$db_item['applications'] = get_applications_by_itemid($_REQUEST['itemid']);

			foreach($item as $field => $value){
				if($item[$field] == $db_item[$field]) $item[$field] = null;
			}

			if($new_appid){
				$result = smart_update_item($_REQUEST['itemid'],$item);
			}

			$result = DBend($result);

			$itemid = $_REQUEST['itemid'];
/*			$action = AUDIT_ACTION_UPDATE;*/

			show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
		}
		else {
			DBstart();

			// when cloning an item, replace the old applications with the same applications from the new host
			if ($_REQUEST['form'] == 'clone') {
				$applications = get_same_applications_for_host($applications, $item['hostid']);
			}

			$new_appid = true;
			$itemid = false;
			if (!zbx_empty($_REQUEST['new_application'])) {
				if ($new_appid = add_application($_REQUEST['new_application'], $_REQUEST['form_hostid'])) {
					$applications[$new_appid] = $new_appid;
				}
			}

			$item['applications'] = $applications;

			if ($new_appid) {
				$itemid = add_item($item);
			}

			$result = DBend($itemid);

			show_messages($result, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
		}

		if ($result) {
			unset($_REQUEST['itemid']);
			unset($_REQUEST['form']);
		}
	}
	else if(isset($_REQUEST['del_history'])&&isset($_REQUEST['itemid'])){
		// cleaning history for one item
		$result = false;
		DBstart();
		if($item = get_item_by_itemid($_REQUEST['itemid'])){
			$result = delete_history_by_itemid($_REQUEST['itemid']);
		}
		if($result){
			DBexecute('UPDATE items SET lastvalue=null,lastclock=null,prevvalue=null'.
				' WHERE itemid='.$_REQUEST['itemid']);
			$host = get_host_by_hostid($item['hostid']);
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,
				S_ITEM.' ['.$item['key_'].'] ['.$_REQUEST['itemid'].'] '.S_HOST.' ['.$host['host'].'] '.S_HISTORY_CLEARED);
		}
		$result = DBend($result);
		show_messages($result, S_HISTORY_CLEARED, S_CANNOT_CLEAR_HISTORY);

	}
	else if(isset($_REQUEST['update']) && isset($_REQUEST['massupdate']) && isset($_REQUEST['group_itemid'])){
		$delay_flex = get_request('delay_flex',array());
		$db_delay_flex = '';
		foreach($delay_flex as $val)
			$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
		$db_delay_flex = trim($db_delay_flex,';');

		if(!is_null(get_request('formula',null))) $_REQUEST['multiplier']=1;
		if('0' === get_request('formula',null)) $_REQUEST['multiplier']=0;

		$group_itemid = $_REQUEST['group_itemid'];
		$result = false;

		$item = array(
				'description'	=> null,
				'key_'			=> null,
				'hostid'		=> null,
				'delay'			=> get_request('delay'),
				'history'		=> get_request('history'),
				'status'		=> get_request('status'),
				'type'			=> get_request('type'),
				'snmp_community'	=> get_request('snmp_community'),
				'snmp_oid'		=> get_request('snmp_oid'),
				'value_type'	=> get_request('value_type'),
				'trapper_hosts'	=> get_request('trapper_hosts'),
				'snmp_port'		=> get_request('snmp_port'),
				'units'			=> get_request('units'),
				'multiplier'	=> get_request('multiplier'),
				'delta'			=> get_request('delta'),
				'snmpv3_securityname'	=> get_request('snmpv3_securityname'),
				'snmpv3_securitylevel'	=> get_request('snmpv3_securitylevel'),
				'snmpv3_authpassphrase'	=> get_request('snmpv3_authpassphrase'),
				'snmpv3_privpassphrase'	=> get_request('snmpv3_privpassphrase'),
				'formula'			=> get_request('formula'),
				'trends'			=> get_request('trends'),
				'logtimefmt'		=> get_request('logtimefmt'),
				'valuemapid'		=> get_request('valuemapid'),
				'delay_flex'		=> $db_delay_flex,
				'authtype'		=> get_request('authtype'),
				'username'		=> get_request('username'),
				'password'		=> get_request('password'),
				'publickey'		=> get_request('publickey'),
				'privatekey'		=> get_request('privatekey'),
				'params'			=> null,
				'ipmi_sensor'		=> get_request('ipmi_sensor'),
				'applications'		=> get_request('applications',null),
				'data_type'		=> get_request('data_type'));

		DBstart();
		foreach($group_itemid as $id){
			$result |= smart_update_item($id,$item);
		}
		$result = DBend($result);

		show_messages($result, S_ITEMS_UPDATED);
		unset($_REQUEST['group_itemid'], $_REQUEST['massupdate'], $_REQUEST['update'], $_REQUEST['form']);
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}
	// if button "Do" is pressed
	else if(isset($_REQUEST['register'])){
		// getting data about how item should look after update or creation
		$item = array(
				'description'	=> get_request('description'),
				'key_'			=> get_request('key'),
				'hostid'		=> get_request('hostid'),
				'delay'			=> get_request('delay'),
				'history'		=> get_request('history'),
				'status'		=> get_request('status'),
				'type'			=> get_request('type'),
				'snmp_community'=> get_request('snmp_community'),
				'snmp_oid'		=> get_request('snmp_oid'),
				'value_type'	=> get_request('value_type'),
				'trapper_hosts'	=> get_request('trapper_hosts'),
				'snmp_port'		=> get_request('snmp_port'),
				'units'			=> get_request('units'),
				'multiplier'	=> get_request('multiplier'),
				'delta'			=> get_request('delta'),
				'snmpv3_securityname'	=> get_request('snmpv3_securityname'),
				'snmpv3_securitylevel'	=> get_request('snmpv3_securitylevel'),
				'snmpv3_authpassphrase'	=> get_request('snmpv3_authpassphrase'),
				'snmpv3_privpassphrase'	=> get_request('snmpv3_privpassphrase'),
				'formula'			=> get_request('formula'),
				'trends'			=> get_request('trends'),
				'logtimefmt'		=> get_request('logtimefmt'),
				'valuemapid'		=> get_request('valuemapid'),
				'authtype'		=> get_request('authtype'),
				'username'		=> get_request('username'),
				'password'		=> get_request('password'),
				'publickey'		=> get_request('publickey'),
				'privatekey'		=> get_request('privatekey'),
				'params'			=> get_request('params'),
				'ipmi_sensor'		=> get_request('ipmi_sensor'),
				'data_type'		=> get_request('data_type'),
				'applications'  => get_request('applications',array())
			);
		$delay_flex = get_request('delay_flex',array());
		$db_delay_flex = '';
		foreach($delay_flex as $val){
			$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
		}
		$db_delay_flex = trim($db_delay_flex, ';');
		$item['delay_flex'] = $db_delay_flex;

		// what exactly user wants us to do?
		switch($_REQUEST['action']){
			// create item on all hosts inside selected group
			case 'add to group':
				DBstart();
				$result = add_item_to_group($_REQUEST['add_groupid'], $item);
				$result = DBend($result);
				show_messages($result, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
				if($result){
					unset($_REQUEST['form']);
					unset($_REQUEST['itemid']);
				}
			break;
			// update item on all hosts inside selected group
			case 'update in group':
				DBstart();
				$result = update_item_in_group($_REQUEST['add_groupid'], $_REQUEST['itemid'], $item);
				$result = DBend($result);
				show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
				if($result){
					unset($_REQUEST['form']);
					unset($_REQUEST['itemid']);
				}
			break;
			// delete item from all hosts inside selected group
			case 'delete from group':
				DBstart();
				$result = delete_item_from_group($_REQUEST['add_groupid'], $_REQUEST['itemid']);
				$result = DBend($result);
				show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
				if($result){
					unset($_REQUEST['form']);
					unset($_REQUEST['itemid']);
				}
			break;
		}
	}
// ----- GO -----
	else if(($_REQUEST['go'] == 'activate') && isset($_REQUEST['group_itemid'])){
		global $USER_DETAILS;

		$group_itemid = $_REQUEST['group_itemid'];

		DBstart();
		$go_result = activate_item($group_itemid);
		$go_result = DBend($go_result);
		show_messages($go_result, S_ITEMS_ACTIVATED, null);
	}
	else if(($_REQUEST['go'] == 'disable') && isset($_REQUEST['group_itemid'])){
		global $USER_DETAILS;

		$group_itemid = $_REQUEST['group_itemid'];

		DBstart();
		$go_result = disable_item($group_itemid);
		$go_result = DBend($go_result);
		show_messages($go_result, S_ITEMS_DISABLED, null);
	}
	else if(isset($_REQUEST['copy']) && isset($_REQUEST['group_itemid']) && ($_REQUEST['go'] == 'copy_to')){
		if(isset($_REQUEST['copy_targetid']) && $_REQUEST['copy_targetid'] > 0 && isset($_REQUEST['copy_type'])){
			if(0 == $_REQUEST['copy_type']){ /* hosts */
				$hosts_ids = $_REQUEST['copy_targetid'];
			}
			else{ /* groups */
				$hosts_ids = array();
				$group_ids = $_REQUEST['copy_targetid'];

				$db_hosts = DBselect('SELECT DISTINCT h.hostid '.
					' FROM hosts h, hosts_groups hg'.
					' WHERE h.hostid=hg.hostid '.
						' AND '.DBcondition('hg.groupid',$group_ids));
				while($db_host = DBfetch($db_hosts)){
					array_push($hosts_ids, $db_host['hostid']);
				}
			}

			$go_result = false;
			DBstart();
			foreach($_REQUEST['group_itemid'] as $item_id)
				foreach($hosts_ids as $host_id){
					$go_result |= copy_item_to_host($item_id, $host_id, true);
				}

			$go_result = DBend($go_result);
			show_messages($go_result, S_ITEMS_COPIED, S_CANNOT_COPY_ITEMS);
			$_REQUEST['go'] = 'none2';
		}
		else{
			error(S_NO_TARGET_SELECTED);
		}
		show_messages();
	}
	else if(($_REQUEST['go'] == 'clean_history') && isset($_REQUEST['group_itemid'])){
		// clean history for selected items
		DBstart();
		$go_result = delete_history_by_itemid($_REQUEST['group_itemid']);
		DBexecute('UPDATE items SET lastvalue=null,lastclock=null,prevvalue=null'.
					' WHERE '.DBcondition('itemid', $_REQUEST['group_itemid']));
		foreach($_REQUEST['group_itemid'] as $id){
			if(!$item = get_item_by_itemid($id)){
				continue;
			}
			$host = get_host_by_hostid($item['hostid']);
			add_audit(
				AUDIT_ACTION_UPDATE,
				AUDIT_RESOURCE_ITEM,
				S_ITEM.' ['.$item['key_'].'] ['.$id.'] '.S_HOST.' ['.$host['host'].'] '.S_HISTORY_CLEARED
			);
		}
		$go_result = DBend($go_result);
		show_messages($go_result, S_HISTORY_CLEARED, $go_result);
	}
	else if(($_REQUEST['go'] == 'delete') && isset($_REQUEST['group_itemid'])){
		global $USER_DETAILS;

		$go_result = true;
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_WRITE);

		$group_itemid = $_REQUEST['group_itemid'];

		$sql = 'SELECT h.host, i.itemid, i.description, i.key_, i.templateid, i.type'.
				' FROM items i, hosts h '.
				' WHERE '.DBcondition('i.itemid',$group_itemid).
					' AND h.hostid=i.hostid'.
					' AND '.DBcondition('h.hostid',$available_hosts);
		$db_items = DBselect($sql);
		while($item = DBfetch($db_items)) {
			if($item['templateid'] != ITEM_TYPE_ZABBIX) {
				unset($group_itemid[$item['itemid']]);
				error(S_ITEM.SPACE."'".$item['host'].':'.item_description($item)."'".SPACE.S_CANNOT_DELETE_ITEM.SPACE.'('.S_TEMPLATED_ITEM.')');
				continue;
			}
			else if($item['type'] == ITEM_TYPE_HTTPTEST) {
				unset($group_itemid[$item['itemid']]);
				error(S_ITEM.SPACE."'".$item['host'].':'.item_description($item)."'".SPACE.S_CANNOT_DELETE_ITEM.SPACE.'('.S_WEB_ITEM.')');
				continue;
			}

			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_ITEM,S_ITEM.' ['.$item['key_'].'] ['.$item['itemid'].'] '.S_HOST.' ['.$item['host'].']');
		}

		$go_result &= !empty($group_itemid);
		if($go_result) {
			$go_result = CItem::delete($group_itemid);
		}
		show_messages($go_result, S_ITEMS_DELETED, S_CANNOT_DELETE_ITEMS);
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}
?>
<?php
	$items_wdgt = new CWidget();

	$form = new CForm(null, 'get');
	$form->setName('hdrform');
	if(!isset($_REQUEST['form']))
		$form->addVar('form_hostid', $hostid);

// Config
	$form->addItem(array(SPACE, new CButton('form', S_CREATE_ITEM)));

	$items_wdgt->addPageHeader(S_CONFIGURATION_OF_ITEMS_BIG, $form);
//	show_table_header(S_CONFIGURATION_OF_ITEMS_BIG, $form);

	if(isset($_REQUEST['form']) && str_in_array($_REQUEST['form'], array(S_CREATE_ITEM, 'update', 'clone'))){
		$items_wdgt->addItem(insert_item_form());
	}
	else if((($_REQUEST['go'] == 'massupdate') || isset($_REQUEST['massupdate'])) && isset($_REQUEST['group_itemid'])){
		$items_wdgt->addItem(insert_mass_update_item_form());
	}
	else if(($_REQUEST['go'] == 'copy_to') && isset($_REQUEST['group_itemid'])){
		$items_wdgt->addItem(insert_copy_elements_to_forms('group_itemid'));
	}
	else{
		$logtype['log']=0;
		$logtype['logrt']=1;
		$logtype['eventlog']=2;
		$logtype['snmptraps']=3;
		$dbkey[0]='log[%';
		$dbkey[1]='logrt[%';
		$dbkey[2]='eventlog[%';
		$dbkey[3]='snmptraps';

		$show_host = true;

// Items Header
		$numrows = new CDiv();
		$numrows->setAttribute('name', 'numrows');

		$items_wdgt->addHeader(S_ITEMS_BIG, SPACE);
		$items_wdgt->addHeader($numrows, SPACE);
// ----------------

// Items Filter{
		$sortfield = getPageSortField('description');
		$sortorder = getPageSortOrder();
		$options = array(
			'filter' => array(),
			'search' => array(),
			'output' => API_OUTPUT_EXTEND,
			'editable' => 1,
			'select_hosts' => API_OUTPUT_EXTEND,
			'select_triggers' => API_OUTPUT_REFER,
			'select_applications' => API_OUTPUT_EXTEND,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);

		$preFilter = count($options, COUNT_RECURSIVE);

		if($hostid > 0)
			$options['hostids'] = $hostid;
		if(isset($_REQUEST['filter_group']) && !zbx_empty($_REQUEST['filter_group']))
			$options['group'] = $_REQUEST['filter_group'];

		if(isset($_REQUEST['filter_host']) && !zbx_empty($_REQUEST['filter_host']))
			$options['host'] = $_REQUEST['filter_host'];

		if(isset($_REQUEST['filter_application']) && !zbx_empty($_REQUEST['filter_application']))
			$options['application'] = $_REQUEST['filter_application'];

		if(isset($_REQUEST['filter_description']) && !zbx_empty($_REQUEST['filter_description']))
			$options['search']['description'] = $_REQUEST['filter_description'];

		if(isset($_REQUEST['filter_type']) && !zbx_empty($_REQUEST['filter_type']) && ($_REQUEST['filter_type'] != -1))
			$options['filter']['type'] = $_REQUEST['filter_type'];

		if(isset($_REQUEST['filter_key']) && !zbx_empty($_REQUEST['filter_key']))
			$options['search']['key_'] = $_REQUEST['filter_key'];

		if(isset($_REQUEST['filter_snmp_community']) && !zbx_empty($_REQUEST['filter_snmp_community']))
			$options['filter']['snmp_community'] = $_REQUEST['filter_snmp_community'];

		if(isset($_REQUEST['filter_snmpv3_securityname']) && !zbx_empty($_REQUEST['filter_snmpv3_securityname']))
			$options['filter']['snmpv3_securityname'] = $_REQUEST['filter_snmpv3_securityname'];

		if(isset($_REQUEST['filter_snmp_oid']) && !zbx_empty($_REQUEST['filter_snmp_oid']))
			$options['filter']['snmp_oid'] = $_REQUEST['filter_snmp_oid'];

		if(isset($_REQUEST['filter_snmp_port']) && !zbx_empty($_REQUEST['filter_snmp_port']))
			$options['filter']['snmp_port'] = $_REQUEST['filter_snmp_port'];

		if(isset($_REQUEST['filter_value_type']) && !zbx_empty($_REQUEST['filter_value_type']) && $_REQUEST['filter_value_type'] != -1)
			$options['filter']['value_type'] = $_REQUEST['filter_value_type'];

		if(isset($_REQUEST['filter_data_type']) && !zbx_empty($_REQUEST['filter_data_type']) && $_REQUEST['filter_data_type'] != -1)
			$options['filter']['data_type'] = $_REQUEST['filter_data_type'];

		if(isset($_REQUEST['filter_delay']) && !zbx_empty($_REQUEST['filter_delay']))
			$options['filter']['delay'] = $_REQUEST['filter_delay'];

		if(isset($_REQUEST['filter_history']) && !zbx_empty($_REQUEST['filter_history']))
			$options['filter']['history'] = $_REQUEST['filter_history'];

		if(isset($_REQUEST['filter_trends']) && !zbx_empty($_REQUEST['filter_trends']))
			$options['filter']['trends'] = $_REQUEST['filter_trends'];

		if(isset($_REQUEST['filter_status']) && !zbx_empty($_REQUEST['filter_status']) && $_REQUEST['filter_status'] != -1)
			$options['filter']['status'] = $_REQUEST['filter_status'];

		if(isset($_REQUEST['filter_templated_items']) && !zbx_empty($_REQUEST['filter_templated_items']) && $_REQUEST['filter_templated_items'] != -1)
			$options['inherited'] = $_REQUEST['filter_templated_items'];

		if(isset($_REQUEST['filter_with_triggers']) && !zbx_empty($_REQUEST['filter_with_triggers']) && $_REQUEST['filter_with_triggers'] != -1)
			$options['with_triggers'] = $_REQUEST['filter_with_triggers'];

		if(isset($_REQUEST['filter_ipmi_sensor']) && !zbx_empty($_REQUEST['filter_ipmi_sensor']))
			$options['filter']['ipmi_sensor'] = $_REQUEST['filter_ipmi_sensor'];

		$afterFilter = count($options, COUNT_RECURSIVE);
//} Items Filter

		if($preFilter == $afterFilter)
			$items = array();
		else
			$items = CItem::get($options);

// Header Host
		if($hostid > 0){
			$tbl_header_host = get_header_host_table($hostid, array('triggers', 'applications', 'graphs'));
			$items_wdgt->addItem($tbl_header_host);
			$show_host = false;
		}


		$form = new CForm();
		$form->setName('items');
		$form->addVar('hostid', $hostid);

		$table  = new CTableInfo();
// Table Header //
		$table->setHeader(array(
			new CCheckBox('all_items',null,"checkAll('".$form->GetName()."','all_items','group_itemid');"),
			S_WIZARD,
			$show_host?S_HOST:null,
			make_sorting_header(S_DESCRIPTION,'description'),
			S_TRIGGERS,
			make_sorting_header(S_KEY,'key_'),
			make_sorting_header(S_INTERVAL,'delay'),
			make_sorting_header(S_HISTORY,'history'),
			make_sorting_header(S_TRENDS,'trends'),
			make_sorting_header(S_TYPE,'type'),
			S_APPLICATIONS,
			make_sorting_header(S_STATUS,'status'),
			S_ERROR
		));

// SET VALUES FOR SUBFILTERS {
// if any of subfilters = false then item shouldnt be shown
		foreach($items as $num => $item){
			$item['hostids'] = zbx_objectValues($item['hosts'], 'hostid');
			$item['subfilters'] = array();

			$item['subfilters']['subfilter_hosts'] =
				(empty($_REQUEST['subfilter_hosts']) || (boolean)array_intersect($_REQUEST['subfilter_hosts'], $item['hostids']));

			$item['subfilters']['subfilter_apps'] = false;
			if(empty($_REQUEST['subfilter_apps'])){
				$item['subfilters']['subfilter_apps'] = true;
			}
			else{
				foreach($item['applications'] as $app){
					if(str_in_array($app['name'], $_REQUEST['subfilter_apps'])){
						$item['subfilters']['subfilter_apps'] = true;
						break;
					}
				}
			}

			$item['subfilters']['subfilter_types'] =
				(empty($_REQUEST['subfilter_types']) || uint_in_array($item['type'], $_REQUEST['subfilter_types']));

			$item['subfilters']['subfilter_value_types'] =
				(empty($_REQUEST['subfilter_value_types']) || uint_in_array($item['value_type'], $_REQUEST['subfilter_value_types']));

			$item['subfilters']['subfilter_status'] =
				(empty($_REQUEST['subfilter_status']) || uint_in_array($item['status'], $_REQUEST['subfilter_status']));

			$item['subfilters']['subfilter_templated_items'] =
				(empty($_REQUEST['subfilter_templated_items']) || (($item['templateid'] == 0) && uint_in_array(0, $_REQUEST['subfilter_templated_items']))
				|| (($item['templateid'] > 0) && uint_in_array(1, $_REQUEST['subfilter_templated_items'])));

			$item['subfilters']['subfilter_with_triggers'] =
				(empty($_REQUEST['subfilter_with_triggers']) || ((count($item['triggers']) == 0) && uint_in_array(0, $_REQUEST['subfilter_with_triggers']))
				|| ((count($item['triggers']) > 0) && uint_in_array(1, $_REQUEST['subfilter_with_triggers'])));

			$item['subfilters']['subfilter_history'] =
				(empty($_REQUEST['subfilter_history']) || uint_in_array($item['history'], $_REQUEST['subfilter_history']));

			$item['subfilters']['subfilter_trends'] =
				(empty($_REQUEST['subfilter_trends']) || uint_in_array($item['trends'], $_REQUEST['subfilter_trends']));

			$item['subfilters']['subfilter_interval'] =
				(empty($_REQUEST['subfilter_interval']) || uint_in_array($item['delay'], $_REQUEST['subfilter_interval']));

			$items[$num] = $item;
		}
// } SET VALUES FOR SUBFILTERS

// Add filter form
// !!! $items must contain all selected items with [subfilters] values !!!
		$items_wdgt->addFlicker(get_item_filter_form($items), CProfile::get('web.items.filter.state', 0));

// Subfilter out items
		foreach($items as $num => $item){
			foreach($item['subfilters'] as $subfilter => $value){
				if(!$value) unset($items[$num]);
			}
		}

// sorting && paging
// !!! should go after we subfiltered out items !!!
		order_result($items, $sortfield, $sortorder);
		$paging = getPagingLine($items);
//---------

		$itemTriggerIds = array();
		foreach($items as $num => $item)
			$itemTriggerIds = array_merge($itemTriggerIds, zbx_objectValues($item['triggers'], 'triggerid'));

		$itemTriggers = CTrigger::get(array(
			'triggerids' => $itemTriggerIds,
			'expandDescription' => true,
			'output' => API_OUTPUT_EXTEND,
			'select_hosts' => array('hostid','host','status'),
			'select_functions' => API_OUTPUT_EXTEND,
			'select_items' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		$trigRealHosts = getParentHostsByTriggers($itemTriggers);

		foreach($items as $inum => $item){

			if($show_host){
				$host = array_pop($item['hosts']);
				$host = $host['host'];
			}
			else{
				$host = null;
			}

			$description = array();
			if($item['templateid']){
				$template_host = get_realhost_by_itemid($item['templateid']);
				$description[] = new CLink($template_host['host'],'?hostid='.$template_host['hostid'], 'unknown');
				$description[] = ':';
			}
			$item['description_expanded'] = item_description($item);
			$description[] = new CLink($item['description_expanded'], '?form=update&itemid='.$item['itemid']);

			$status = new CCol(new CLink(item_status2str($item['status']), '?group_itemid='.$item['itemid'].'&go='.
				($item['status']? 'activate':'disable'), item_status2style($item['status'])));


			if(zbx_empty($item['error'])){
				$error = new CDiv(SPACE, 'iconok');
			}
			else{
				$error = new CDiv(SPACE, 'iconerror');
				$error->setHint($item['error'], '', 'on');
			}


			if(empty($item['applications'])){
				$applications = '-';
			}
			else{
				$applications = array();
				foreach($item['applications'] as $anum => $app){
					$applications[] = $app['name'];
				}
				$applications = implode(', ', $applications);
			}


			$trigger_hint = new CTableInfo();
			$trigger_hint->setHeader(array(
				S_SEVERITY,
				S_NAME,
				S_EXPRESSION,
				S_STATUS
			));

// TRIGGERS INFO
			foreach($item['triggers'] as $tnum => &$trigger){
				$triggerid = $trigger['triggerid'];
				$trigger = $itemTriggers[$triggerid];

				$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');
				$trigger['items'] = zbx_toHash($trigger['items'], 'itemid');
				$trigger['functions'] = zbx_toHash($trigger['functions'], 'functionid');

				$tr_description = array();
				if($trigger['templateid'] > 0){
					if(!isset($trigRealHosts[$triggerid])){
						$tr_description[] = new CSpan('HOST','unknown');
						$tr_description[] = ':';
					}
					else{
						$real_hosts = $trigRealHosts[$triggerid];
						$real_host = reset($real_hosts);
						$tr_description[] = new CLink($real_host['host'], 'triggers.php?&hostid='.$real_host['hostid'], 'unknown');
						$tr_description[] = ':';
					}
				}

				$tr_description[] = new CLink($trigger['description'], 'triggers.php?form=update&triggerid='.$triggerid);

				if($trigger['value'] != TRIGGER_VALUE_UNKNOWN) $trigger['error'] = '';

				switch($trigger['priority']){
					case 0: $priority = S_NOT_CLASSIFIED; break;
					case 1: $priority = new CCol(S_INFORMATION, 'information'); break;
					case 2: $priority = new CCol(S_WARNING, 'warning'); break;
					case 3: $priority = new CCol(S_AVERAGE, 'average'); break;
					case 4: $priority = new CCol(S_HIGH, 'high'); break;
					case 5: $priority = new CCol(S_DISASTER, 'disaster'); break;
					default: $priority = $trigger['priority'];
				}

				if($trigger['status'] == TRIGGER_STATUS_DISABLED){
					$tstatus = new CSpan(S_DISABLED, 'disabled');
				}
				else if($trigger['status'] == TRIGGER_STATUS_ENABLED){
					$tstatus = new CSpan(S_ENABLED, 'enabled');
				}

				$trigger_hint->addRow(array(
					$priority,
					$tr_description,
					triggerExpression($trigger,1),
					$tstatus,
				));

				$item['triggers'][$tnum] = $trigger;
			}
			unset($trigger);

			if(!empty($item['triggers'])){
				$trigger_info = new CSpan(S_TRIGGERS,'link_menu');
				$trigger_info->setHint($trigger_hint);
				$trigger_info = array($trigger_info);
				$trigger_info[] = ' ('.count($item['triggers']).')';

				$trigger_hint = array();
			}
			else{
				$trigger_info = SPACE;
			}
//-------
// if item type is 'Log' we must show log menu
			if($item['value_type'] == ITEM_VALUE_TYPE_LOG || $item['value_type'] == ITEM_VALUE_TYPE_STR || $item['value_type'] == ITEM_VALUE_TYPE_TEXT){

				$triggers_flag = false;
				$triggers="Array('".S_EDIT_TRIGGER."',null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']}\n";

				foreach($item['triggers'] as $num => $trigger){
					foreach($trigger['functions'] as $fnum => $function)
						if(!str_in_array($function['function'], array('regexp','iregexp'))) continue 2;

					$triggers .= ',["'.$trigger['description'].'",'.
										zbx_jsvalue("javascript: openWinCentered('tr_logform.php?sform=1&itemid=".$item['itemid'].
																"&triggerid=".$trigger['triggerid'].
																"','TriggerLog',760,540,".
																"'titlebar=no, resizable=yes, scrollbars=yes');").']';
					$triggers_flag = true;
				}

				if($triggers_flag){
					$triggers = rtrim($triggers,',').')';
				}
				else{
					$triggers = 'Array()';
				}

				$menuicon = new CIcon(S_MENU,'iconmenu_b',
						'call_triggerlog_menu(event, '.zbx_jsvalue($item['itemid']).','.
						zbx_jsvalue($item['description_expanded']).','.$triggers.');');
			}
			else{
				$menuicon = SPACE;
			}

			$table->addRow(array(
				new CCheckBox('group_itemid['.$item['itemid'].']',null,null,$item['itemid']),
				$menuicon,
				$host,
				$description,
				$trigger_info,
				$item['key_'],
				($item['type'] == ITEM_TYPE_TRAPPER ? '' : $item['delay']),
				$item['history'],
				(in_array($item['value_type'], array(ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT)) ? '' : $item['trends']),
				item_type2str($item['type']),
				new CCol($applications, 'wraptext'),
				$status,
				$error
			));
		}

// GO{
		$goBox = new CComboBox('go');
		$goOption = new CComboItem('activate',S_ACTIVATE_SELECTED);
		$goOption->setAttribute('confirm',S_ENABLE_SELECTED_ITEMS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('disable',S_DISABLE_SELECTED);
		$goOption->setAttribute('confirm',S_DISABLE_SELECTED_ITEMS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('massupdate',S_MASS_UPDATE);
		//$goOption->setAttribute('confirm',S_MASS_UPDATE_SELECTED_ITEMS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('copy_to',S_COPY_SELECTED_TO);
		//$goOption->setAttribute('confirm',S_COPY_SELECTED_ITEMS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('clean_history',S_CLEAR_HISTORY_FOR_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_HISTORY_SELECTED_ITEMS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_ITEMS_Q);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO);
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "group_itemid";');

		$footer = get_table_header(array($goBox, $goButton));
// }GO

// PAGING FOOTER
		$table = array($paging,$table,$paging,$footer);
//---------

		$form->addItem($table);
		$items_wdgt->addItem($form);
	}

	$items_wdgt->show();


include_once('include/page_footer.php');
?>
