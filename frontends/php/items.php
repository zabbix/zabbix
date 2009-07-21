<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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

	$page['title'] = "S_CONFIGURATION_OF_ITEMS";
	$page['file'] = 'items.php';
	$page['scripts'] = array('scriptaculous.js?load=effects');
	$page['hist_arg'] = array();

include_once('include/page_header.php');

	$_REQUEST['config'] = get_request('config','itemts.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
//  NEW  templates.php; hosts.php; items.php; triggers.php; graphs.php; maintenances.php;
// 	OLD  0 - hosts; 1 - groups; 2 - linkages; 3 - templates; 4 - applications; 5 - Proxies; 6 - maintenance
		'config'=>					array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),

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
		'hostid'=>			array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,			'isset({save})'),

		'add_groupid'=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,			'(isset({register})&&({register}=="go"))'),
		'action'=>			array(T_ZBX_STR, O_OPT,	 P_SYS,	NOT_EMPTY,		'(isset({register})&&({register}=="go"))'),

		'copy_type'	=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0,1'),		'isset({copy})'),
		'copy_mode'	=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0'),		null),

		'itemid'=>			array(T_ZBX_INT, O_NO,	 P_SYS,	DB_ID,			'(isset({form})&&({form}=="update"))'),
		'description'=>		array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,		'isset({save})'),
		'key'=>				array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,		'isset({save})'),
		'delay'=>			array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,86400),'isset({save})&&(isset({type})&&({type}!=2))'),
		'new_delay_flex'=>	array(T_ZBX_STR, O_OPT,  NOT_EMPTY,  '','isset({add_delay_flex})&&(isset({type})&&({type}!=2))'),
		'rem_delay_flex'=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,86400),null),
		'delay_flex'=>		array(T_ZBX_STR, O_OPT,  null,  '',null),
		'history'=>			array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),'isset({save})'),
		'status'=>			array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),'isset({save})'),
		'type'=>			array(T_ZBX_INT, O_OPT,  null,
				IN(array(-1,ITEM_TYPE_ZABBIX,ITEM_TYPE_SNMPV1,ITEM_TYPE_TRAPPER,ITEM_TYPE_SIMPLE,
					ITEM_TYPE_SNMPV2C,ITEM_TYPE_INTERNAL,ITEM_TYPE_SNMPV3,ITEM_TYPE_ZABBIX_ACTIVE,
					ITEM_TYPE_AGGREGATE,ITEM_TYPE_HTTPTEST,ITEM_TYPE_EXTERNAL,ITEM_TYPE_DB_MONITOR,ITEM_TYPE_IPMI)),'isset({save})'),
		'trends'=>			array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),		'isset({save})'),
		'value_type'=>		array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2,3,4'),	'isset({save})'),
		'data_type'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(ITEM_DATA_TYPE_DECIMAL,ITEM_DATA_TYPE_HEXADECIMAL),
					'isset({save})&&(isset({value_type})&&({value_type}=='.ITEM_VALUE_TYPE_UINT64.'))'),
		'valuemapid'=>		array(T_ZBX_INT, O_OPT,	 null,	DB_ID,				'isset({save})'),
		'params'=>			array(T_ZBX_STR, O_OPT,  NULL,	NULL,'isset({save})'),

		'snmp_community'=>	array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,			'isset({save})&&isset({type})&&'.IN('1,4','type')),
		'snmp_oid'=>		array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,			'isset({save})&&isset({type})&&'.IN('1,4,6','type')),
		'snmp_port'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),	'isset({save})&&isset({type})&&'.IN('1,4,6','type')),

		'snmpv3_securitylevel'=>array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2'),	'isset({save})&&(isset({type})&&({type}==6))'),
		'snmpv3_securityname'=>array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})&&(isset({type})&&({type}==6))'),
		'snmpv3_authpassphrase'=>array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})&&(isset({type})&&({type}==6))'),
		'snmpv3_privpassphrase'=>array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})&&(isset({type})&&({type}==6))'),

		'ipmi_sensor'=>array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})&&(isset({type})&&({type}==12))'),

		'trapper_hosts'=>	array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})&&isset({type})&&({type}==2)'),
		'units'=>			array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})&&isset({type})&&'.IN('0,3','type')),
		'multiplier'=>		array(T_ZBX_INT, O_OPT,  null,  IN('0,1'),		'isset({save})&&isset({type})&&'.IN('0,3','type')),
		'delta'=>			array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2'),	'isset({save})&&isset({type})&&'.IN('0,3','type')),

		'formula'=>			array(T_ZBX_DBL, O_OPT,  null,  null,		'isset({save})&&(isset({multiplier})&&({multiplier}==1))'),
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
		"filter_rst"=>				array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		"filter_set"=>				array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),

		'filter_group'=>			array(T_ZBX_STR, O_OPT,  null,	null,		null),
		'filter_host'=>				array(T_ZBX_STR, O_OPT,  null,	null,		null),
		'filter_hostid'=>			array(T_ZBX_INT, O_OPT,  null,	DB_ID,		null),
		'filter_application'=>		array(T_ZBX_STR, O_OPT,  null,	null,		null),
		'filter_description'=>		array(T_ZBX_STR, O_OPT,  null,	null,		null),
		'filter_type'=>				array(T_ZBX_INT, O_OPT,  null,
				IN(array(-1,ITEM_TYPE_ZABBIX,ITEM_TYPE_SNMPV1,ITEM_TYPE_TRAPPER,ITEM_TYPE_SIMPLE,
				ITEM_TYPE_SNMPV2C,ITEM_TYPE_INTERNAL,ITEM_TYPE_SNMPV3,ITEM_TYPE_ZABBIX_ACTIVE,
				ITEM_TYPE_AGGREGATE,ITEM_TYPE_HTTPTEST,ITEM_TYPE_EXTERNAL,ITEM_TYPE_DB_MONITOR,ITEM_TYPE_IPMI)),null),
		'filter_key'=>				array(T_ZBX_STR, O_OPT,  null,  null,		null),
		'filter_snmp_community'=>	array(T_ZBX_STR, O_OPT,  null,  null,	null),
		'filter_snmp_oid'=>			array(T_ZBX_STR, O_OPT,  null,  null,	null),
		'filter_snmp_port'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),	null),
		'filter_value_type'=>		array(T_ZBX_INT, O_OPT,  null,  IN('-1,0,1,2,3,4'),null),
		'filter_data_type'=>			array(T_ZBX_INT, O_OPT,  null,  BETWEEN(-1,ITEM_DATA_TYPE_HEXADECIMAL),null),
		'filter_delay'=>			array(T_ZBX_INT, O_OPT,  -1,  BETWEEN(0,86400),null),
		'filter_history'=>			array(T_ZBX_INT, O_OPT,  -1,  BETWEEN(0,65535),null),
		'filter_trends'=>			array(T_ZBX_INT, O_OPT,  -1,  BETWEEN(0,65535),null),
		'filter_status'=>			array(T_ZBX_INT, O_OPT,  null,  IN('-1,0,1,3'),null),

//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})'),

	);

	check_fields($fields);
	validate_sort_and_sortorder('description',ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go','none');
?>
<?php
/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			update_profile('web.items.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
//--------

/* FILTER */
	$_REQUEST['filter_group']			= get_request('filter_group');
	$_REQUEST['filter_host']			= get_request('filter_host');
	$_REQUEST['filter_application']		= get_request('filter_application');
	$_REQUEST['filter_description']		= get_request('filter_description');
	$_REQUEST['filter_type']			= get_request('filter_type', -1);
	$_REQUEST['filter_key']				= get_request('filter_key');
	$_REQUEST['filter_snmp_community']	= get_request('filter_snmp_community');
	$_REQUEST['filter_snmp_oid']		= get_request('filter_snmp_oid');
	$_REQUEST['filter_snmp_port']		= get_request('filter_snmp_port');
	$_REQUEST['filter_value_type']		= get_request('filter_value_type', -1);
	$_REQUEST['filter_data_type']		= get_request('filter_data_type', -1);
	$_REQUEST['filter_delay']			= get_request('filter_delay');
	$_REQUEST['filter_history']			= get_request('filter_history');
	$_REQUEST['filter_trends']			= get_request('filter_trends');
	$_REQUEST['filter_status']			= get_request('filter_status');

// --------------

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE);

	if(isset($_REQUEST['hostid']) && ($_REQUEST['hostid'] > 0) && !isset($available_hosts[$_REQUEST['hostid']])){
		unset($_REQUEST['hostid']);
	}
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
			DBstart();
				$result = delete_item($_REQUEST['itemid']);
			$result = DBend($result);
		}

		show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);

		unset($_REQUEST['itemid']);
		unset($_REQUEST['form']);
	}
	else if(isset($_REQUEST['clone']) && isset($_REQUEST['itemid'])){
		unset($_REQUEST['itemid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['save'])){
		$applications = get_request('applications',array());
		$delay_flex = get_request('delay_flex',array());
		$db_delay_flex = '';
		foreach($delay_flex as $num => $val)
			$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
		$db_delay_flex = trim($db_delay_flex,';');

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
				'delay_flex'		=> $db_delay_flex,
				'params'			=> get_request('params'),
				'ipmi_sensor'		=> get_request('ipmi_sensor'),
				'data_type'		=> get_request('data_type'));

		if(isset($_REQUEST['itemid'])){
			DBstart();

			$new_appid = true;
			$result = false;

			if(!zbx_empty($_REQUEST['new_application'])){
				if($new_appid = add_application($_REQUEST['new_application'],$_REQUEST['hostid']))
					$applications[$new_appid] = $new_appid;
			}

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
		else{
			DBstart();

			$new_appid = true;
			$itemid = false;
			if(!zbx_empty($_REQUEST['new_application'])){
				if($new_appid = add_application($_REQUEST['new_application'],$_REQUEST['hostid']))
					$applications[$new_appid] = $new_appid;
			}

			$item['applications'] = $applications;

			if($new_appid){
				$itemid=add_item($item);
			}

			$result = DBend($itemid);

/*			$action = AUDIT_ACTION_ADD;*/
			show_messages($result, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
		}

		if($result){
/*			$host = get_host_by_hostid($_REQUEST['hostid']);

			add_audit($action, AUDIT_RESOURCE_ITEM, S_ITEM.' ['.$_REQUEST['key'].'] ['.$itemid.'] '.S_HOST.' ['.$host['host'].']');*/

			unset($_REQUEST['itemid']);
			unset($_REQUEST['form']);
		}
	}
	else if(isset($_REQUEST['del_history'])&&isset($_REQUEST['itemid'])){
		$result = false;
		if($item = get_item_by_itemid($_REQUEST['itemid'])){
			DBstart();
				$result = delete_history_by_itemid($_REQUEST['itemid']);
			$result = DBend($result);
		}

		if($result){
			DBexecute('UPDATE items SET nextcheck=0,lastvalue=null,lastclock=null,prevvalue=null '.
				' WHERE itemid='.$_REQUEST['itemid']);

			$host = get_host_by_hostid($_REQUEST['hostid']);

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,
				S_ITEM.' ['.$item['key_'].'] ['.$_REQUEST['itemid'].'] '.S_HOST.' ['.$host['host'].'] '.S_HISTORY_CLEANED);
		}
		show_messages($result, S_HISTORY_CLEANED, S_CANNOT_CLEAN_HISTORY);

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
				'delay_flex'		=> $db_delay_flex,
				'params'			=> null,
				'ipmi_sensor'		=> get_request('ipmi_sensor'),
				'applications'		=> get_request('applications',array()),
				'data_type'		=> get_request('data_type'));

		DBstart();
		foreach($group_itemid as $id){
			$result |= smart_update_item($id,$item);
		}
		$result = DBend($result);

		show_messages($result, S_ITEMS_UPDATED);
		unset($_REQUEST['group_itemid'], $_REQUEST['massupdate'], $_REQUEST['update']);
	}
	else if(isset($_REQUEST['register'])){

		if($_REQUEST['register']=='do'){
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
//					'delay_flex'		=> $db_delay_flex,
					'params'			=> get_request('params'),
					'ipmi_sensor'		=> get_request('ipmi_sensor'),
//					'applications'		=> $applications
					'data_type'		=> get_request('data_type'),
				);

			if($_REQUEST['action']=='add to group'){
				$applications = get_request('applications',array());
				$delay_flex = get_request('delay_flex',array());
				$db_delay_flex = '';

				foreach($delay_flex as $val){
					$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
				}

				$db_delay_flex = trim($db_delay_flex,';');

				$item['delay_flex'] = $db_delay_flex;
				$item['applications'] = $applications;

				DBstart();
				$itemid=add_item_to_group($_REQUEST['add_groupid'],$item);

				$result = DBend($itemid);
				show_messages($result, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
				if($result){
					unset($_REQUEST['form']);
					unset($_REQUEST['itemid']);
					unset($itemid);
				}
			}

			if($_REQUEST['action']=='update in group'){

				$applications = get_request('applications',array());
				$delay_flex = get_request('delay_flex',array());
				$db_delay_flex = '';

				foreach($delay_flex as $val){
					$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
				}

				$db_delay_flex = trim($db_delay_flex,';');

				$item['delay_flex'] = $db_delay_flex;
				$item['applications'] = $applications;

				DBstart();
					$result = update_item_in_group($_REQUEST['add_groupid'],$_REQUEST['itemid'],$item);
				$result = DBend($result);

				show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
				if($result){
					unset($_REQUEST['form']);
					unset($_REQUEST['itemid']);
				}
			}

			if($_REQUEST['action']=='delete from group'){

				DBstart();
					$result = delete_item_from_group($_REQUEST['add_groupid'],$_REQUEST['itemid']);
				$result = DBend($result);

				show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
				if($result){
					unset($_REQUEST['form']);
					unset($_REQUEST['itemid']);
				}
			}
		}
	}
// ----- GO -----
	else if(($_REQUEST['go'] == 'activate') && isset($_REQUEST['group_itemid'])){
		global $USER_DETAILS;
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_WRITE);

		$group_itemid = $_REQUEST['group_itemid'];

		DBstart();
		$result = activate_item($group_itemid);
		$result = DBend($result);
		show_messages($result, S_ITEMS_ACTIVATED, null);
	}
	else if(($_REQUEST['go'] == 'disable') && isset($_REQUEST['group_itemid'])){
		global $USER_DETAILS;
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_WRITE);

		$group_itemid = $_REQUEST['group_itemid'];

		DBstart();
		$result = disable_item($group_itemid);
		$result = DBend($result);
		show_messages($result, S_ITEMS_DISABLED, null);
	}
	else if(isset($_REQUEST['copy'])&&isset($_REQUEST['group_itemid']) && ($_REQUEST['go'] == 'copy_to')){
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

			$result = false;
			DBstart();
			foreach($_REQUEST['group_itemid'] as $item_id)
				foreach($hosts_ids as $host_id){
					$result |= copy_item_to_host($item_id, $host_id, true);
				}

			$result = DBend($result);
			show_messages($result, S_ITEMS_COPIED, S_CANNOT_COPY_ITEMS);
			$_REQUEST['go'] = 'none';
		}
		else{
			error('No target selection.');
		}
		show_messages();
	}
	else if(($_REQUEST['go'] == 'clean_history') && isset($_REQUEST['group_itemid'])){
		$result = false;
		$group_itemid = $_REQUEST['group_itemid'];

		DBstart();
		foreach($group_itemid as $id){
			if(!$item = get_item_by_itemid($id))	continue;

			$cur_result = delete_history_by_itemid($id);
			$result |= $cur_result;

			if($cur_result){
				DBexecute('update items set nextcheck=0,lastvalue=null,lastclock=null,prevvalue=null where itemid='.$id);

				$host = get_host_by_hostid($item['hostid']);
				add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,
					S_ITEM.' ['.$item['key_'].'] ['.$id.'] '.S_HOST.' ['.$host['host'].'] '.S_HISTORY_CLEANED);
			}
		}
		$result = DBend($result);
		show_messages($result, S_HISTORY_CLEANED, $result);
	}
	else if(($_REQUEST['go'] == 'delete') && isset($_REQUEST['group_itemid'])){
		global $USER_DETAILS;

		$result = true;
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

		$result &= !empty($group_itemid);
		if($result) {
			DBstart();
			$result = delete_item($group_itemid);
			$result = DBend($result);
		}
		show_messages($result, S_ITEMS_DELETED, S_CANNOT_DELETE_ITEMS);

	}
?>
<?php
	$show_host = true;
	$hostid = get_request('hostid', 0);
	
	if(!zbx_empty($_REQUEST['filter_host'])){
		$hostid = CHost::getId(array('host' => $_REQUEST['filter_host']));
	}
	
	if($hostid > 0){
		$show_host = false;
		$header_host_opt = array(
			'hostids' => $hostid,
			'extendoutput' => 1,
			'select_groups' => 1,
			'select_triggers' => 1,
			'select_graphs' => 1
		);
					
		$header_host = CHost::get($header_host_opt);
		$header_host = array_pop($header_host);
		
		$_REQUEST['filter_host'] = $header_host['host'];
		if(empty($header_host['groups'])){
			$_REQUEST['filter_group'] = '';
		}
		else{
			$_REQUEST['filter_group'] = array_pop($header_host['groups']);	
			$_REQUEST['filter_group'] = $_REQUEST['filter_group']['name'];
		}
	}
?>
<?php
	$form = new CForm();
	$form->setMethod('get');
	$form->setName('hdrform');

	$form->addVar('hostid',$hostid);
	
// Config
	$cmbConf = new CComboBox('config','items.php','javascript: submit()');
	$cmbConf->setAttribute('onchange','javascript: redirect(this.options[this.selectedIndex].value);');	
		$cmbConf->addItem('templates.php',S_TEMPLATES);
		$cmbConf->addItem('hosts.php',S_HOSTS);
		$cmbConf->addItem('items.php',S_ITEMS);
		$cmbConf->addItem('triggers.php',S_TRIGGERS);
		$cmbConf->addItem('graphs.php',S_GRAPHS);
		$cmbConf->addItem('applications.php',S_APPLICATIONS);
		
	$form->addItem($cmbConf);
	$form->addItem(array(SPACE, new CButton('form',S_CREATE_ITEM)));

	show_table_header(S_CONFIGURATION_OF_ITEMS_BIG, $form);
	
echo SBR;

	if(isset($_REQUEST['form'])){
// FORM
		if(str_in_array($_REQUEST['form'],array(S_CREATE_ITEM,'update','clone')) ||
			(($_REQUEST['form']=='mass_update') && isset($_REQUEST['group_itemid'])))
		{
			insert_item_form();
		}
	}

	if(($_REQUEST['go'] == 'massupdate') && isset($_REQUEST['group_itemid'])){
		insert_mass_update_item_form('group_itemid');
	}
	else if(($_REQUEST['go'] == 'copy_to') && isset($_REQUEST['group_itemid'])){
		insert_copy_elements_to_forms('group_itemid');
	}
	else if (!isset($_REQUEST['form']) || !str_in_array($_REQUEST['form'],array(S_CREATE_ITEM,'update','clone'))) {

		$items_wdgt = new CWidget();

// Items Header
		$row_count = 0;
		$numrows = new CSpan(null,'info');
		$numrows->setAttribute('name','numrows');
		$header = array(S_ITEMS_BIG,new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),S_FOUND.': ',$numrows);

		$items_wdgt->addHeader($header, SPACE);
// ----------------

// Items Filter
		$items_wdgt->addFlicker(get_item_filter_form(), get_profile('web.items.filter.state',0));
//-----

		$show_host = true;
		$show_applications = true;
// <<<--- SELECTED HOST HEADER INFORMATION --->>>	
		if($hostid > 0){
			$show_host = false;

			$header_groupid = array_pop($header_host['groupids']);
			
			$description = array();
			if($header_host['proxy_hostid']){
				$proxy = get_host_by_hostid($header_host['proxy_hostid']);
				$description[] = $proxy['host'].':';
			}			
			$description[] = $header_host['host'];

			$triggers = array(new CLink(S_TRIGGERS, 'triggers.php?groupid='.$header_groupid.'&hostid='.$header_host['hostid']),
							' ('.count($header_host['triggerids']).')');
			$graphs = array(new CLink(S_GRAPHS, 'graphs.php?groupid='.$header_groupid.'&hostid='.$header_host['hostid']),
							' ('.count($header_host['graphids']).')');
			
			$dns = empty($header_host['dns']) ? '-' : $header_host['dns'];
			$ip = empty($header_host['ip']) ? '-' : $header_host['ip'];
			$port = empty($header_host['port']) ? '-' : $header_host['port'];
			if(1 == $header_host['useip'])
				$ip = bold($ip);
			else
				$dns = bold($dns);
				
				
			switch($header_host['status']){
				case HOST_STATUS_MONITORED:
					$status=new CSpan(S_MONITORED, 'off');
					break;
				case HOST_STATUS_NOT_MONITORED:
					$status=new CSpan(S_NOT_MONITORED, 'off');
					break;
				default:
					$status=S_UNKNOWN;
			}

			if($header_host['available'] == HOST_AVAILABLE_TRUE)
				$available=new CSpan(S_AVAILABLE,'off');
			else if($header_host['available'] == HOST_AVAILABLE_FALSE)
				$available=new CSpan(S_NOT_AVAILABLE,'on');
			else if($header_host['available'] == HOST_AVAILABLE_UNKNOWN)
				$available=new CSpan(S_UNKNOWN,'unknown');

				
			$tbl_header_host = new CTable();
			$tbl_header_host->addRow(array(
				new CLink(bold(S_HOST_LIST), 'hosts.php?hostid='.$header_host['hostid'].url_param('groupid')),
				$triggers,
				$graphs,
				array(bold(S_HOST.': '),$description),
				array(bold(S_DNS.': '), $dns),
				array(bold(S_IP.': '), $ip),
				array(bold(S_PORT.': '), $port),
				array(bold(S_STATUS.': '), $status),
				array(bold(S_AVAILABILITY.': '), $available)
				));
				
			$tbl_header_host->setClass('infobox');
			
			$items_wdgt->addItem($tbl_header_host);
		}
// --->>> SELECTED HOST HEADER INFORMATION <<<---

		$options = array(
						'filter' => 1,
						'extendoutput' => 1,
						'select_hosts' => 1,
						'sortfield' => getPageSortField('description'),
						'sortorder' => getPageSortOrder(),
						'limit' => ($config['search_limit']+1)
					);

		$preFilter = count($options);

		if($hostid > 0)
			$options['hostids'] = $hostid;

		if(!zbx_empty($_REQUEST['filter_group']))
			$options['group'] = $_REQUEST['filter_group'];

		if(!zbx_empty($_REQUEST['filter_host']))
			$options['host'] = $_REQUEST['filter_host'];

		if(!zbx_empty($_REQUEST['filter_application']))
			$options['application'] = $_REQUEST['filter_application'];

		if(!zbx_empty($_REQUEST['filter_description']))
			$options['pattern'] = $_REQUEST['filter_description'];

		if(!zbx_empty($_REQUEST['filter_type']) && ($_REQUEST['filter_type'] != -1))
			$options['type'] = $_REQUEST['filter_type'];

		if(!zbx_empty($_REQUEST['filter_key']))
			$options['key'] = $_REQUEST['filter_key'];

		if(!zbx_empty($_REQUEST['filter_snmp_community']))
			$options['snmp_community'] = $_REQUEST['filter_snmp_community'];

		if(!zbx_empty($_REQUEST['filter_snmp_oid']))
			$options['snmp_oid'] = $_REQUEST['filter_snmp_oid'];

		if(!zbx_empty($_REQUEST['filter_snmp_port']))
			$options['snmp_port'] = $_REQUEST['filter_snmp_port'];

		if(!zbx_empty($_REQUEST['filter_value_type']) && $_REQUEST['filter_value_type'] != -1)
			$options['valuetype'] = $_REQUEST['filter_value_type'];

		if(!zbx_empty($_REQUEST['filter_delay']))
			$options['delay'] = $_REQUEST['filter_delay'];

		if(!zbx_empty($_REQUEST['filter_history']))
			$options['history'] = $_REQUEST['filter_history'];

		if(!zbx_empty($_REQUEST['filter_trends']))
			$options['trends'] = $_REQUEST['filter_trends'];

		if(!zbx_empty($_REQUEST['filter_status']) && $_REQUEST['filter_status'] != -1)
			$options['status'] = $_REQUEST['filter_status'];

		$afterFilter = count($options);
//--------------------------

		if($preFilter == $afterFilter)
			$items = array();
		else
			$items = CItem::get($options);
		
// TABLE
		$form = new CForm();
		$form->setName('items');

		$table  = new CTableInfo();
		$table->setHeader(array(
			new CCheckBox('all_items',null,"checkAll('".$form->GetName()."','all_items','group_itemid');"),
			$show_host ? make_sorting_header(S_HOST,'host') : null,
			make_sorting_header(S_DESCRIPTION,'description'),
			make_sorting_header(S_KEY,'key_'),
			make_sorting_header(S_INTERVAL,'delay'),
			make_sorting_header(S_HISTORY,'history'),
			make_sorting_header(S_TRENDS,'trends'),
			make_sorting_header(S_TYPE,'type'),
			make_sorting_header(S_STATUS,'status'),
			S_APPLICATIONS,
			S_ERROR));

		$sql = 'SELECT DISTINCT th.host as template_host,th.hostid as template_hostid, h.host, h.hostid, i.* ';

		foreach($items as $itemid => $db_item){
			$host = array_pop($db_item['hosts']);
			$host = $host['host'];

			$description = array();
			$item_description = item_description($db_item);

			if($db_item['templateid']){
				$template_host = get_realhost_by_itemid($db_item['templateid']);
				array_push($description,
					new CLink($template_host['host'],'?'.
						'hostid='.$template_host['hostid'],
						'unknown'),
					':');
			}

			
			array_push($description, new CLink(
				item_description($db_item),
				'?form=update&itemid='.$db_item['itemid']));

			$status=new CCol(new CLink(item_status2str($db_item['status']),
					'?group_itemid%5B%5D='.$db_item['itemid'].
					'&go='.($db_item['status']?'activate':'disable'),
					item_status2style($db_item['status'])));

			if(!zbx_empty($db_item['error'])){
				$error = new CDiv(SPACE,'iconerror');
				$error->setHint($db_item['error'], '', 'on');
			}
			else{
				$error = new CDiv(SPACE,'iconok');
			}
			
			$applications = implode(', ', get_applications_by_itemid($db_item['itemid'], 'name'));
			if(!is_null($applications) && empty($applications)) $applications = '-';
			$applications = new CCol($applications, 'wraptext');

			$table->addRow(array(
				new CCheckBox('group_itemid['.$db_item['itemid'].']',null,null,$db_item['itemid']),
				$show_host?$host:null,
				$description,
				$db_item['key_'],
				$db_item['delay'],
				$db_item['history'],
				$db_item['trends'],
				item_type2str($db_item['type']),
				$status,
				$applications,
				$error
				));
			$row_count++;
		}

//----- GO ------
		$goBox = new CComboBox('go');
		$goBox->addItem('activate',S_ACTIVATE_SELECTED);
		$goBox->addItem('disable',S_DISABLE_SELECTED);
		$goBox->addItem('massupdate',S_MASS_UPDATE);
		$goBox->addItem('copy_to',S_COPY_SELECTED_TO);
		$goBox->addItem('clean_history',S_CLEAN_HISTORY_SELECTED_ITEMS);
		$goBox->addItem('delete',S_DELETE_SELECTED);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO.' (0)');
		$goButton->setAttribute('id','goButton');
		zbx_add_post_js('chkbxRange.pageGoName = "group_itemid";');

		$table->setFooter(new CCol(array($goBox, $goButton)));
//----

		$form->addItem($table);

		$items_wdgt->addItem($form);
		$items_wdgt->show();
	}

	if(isset($row_count))
		zbx_add_post_js('insert_in_element("numrows","'.$row_count.'");');
?>
<?php

include_once('include/page_footer.php');

?>