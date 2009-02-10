<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	$page['hist_arg'] = array();

include_once 'include/page_header.php';
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(

		'type_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'community_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'securityname_visible'=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		'securitylevel_visible'=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		'authpassphrase_visible'=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		'privpassphras_visible'=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		'port_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'value_type_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'data_type_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'units_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'formula_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'delay_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'delay_flex_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'history_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'trends_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'status_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'logtimefmt_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'delta_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'valuemapid_visible'=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		'trapper_hosts_visible'=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		'applications_visible'=>	array(T_ZBX_STR, O_OPT,  null, null,           null),

		'groupid'=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,null),
		'hostid'=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,'isset({save})'),

		'add_groupid'=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,		'(isset({register})&&({register}=="go"))'),
		'action'=>	array(T_ZBX_STR, O_OPT,	 P_SYS,	NOT_EMPTY,		'(isset({register})&&({register}=="go"))'),

		'copy_type'	=>array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0,1'),	'isset({copy})'),
		'copy_mode'	=>array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0'),null),

		'itemid'=>	array(T_ZBX_INT, O_NO,	 P_SYS,	DB_ID,			'(isset({form})&&({form}=="update"))'),
		'description'=>	array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,	'isset({save})'),
		'key'=>		array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,		'isset({save})'),
		'delay'=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,86400),'isset({save})&&(isset({type})&&({type}!=2))'),
		'new_delay_flex'=>	array(T_ZBX_STR, O_OPT,  NOT_EMPTY,  '','isset({add_delay_flex})&&(isset({type})&&({type}!=2))'),
		'rem_delay_flex'=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,86400),null),
		'delay_flex'=>	array(T_ZBX_STR, O_OPT,  null,  '',null),
		'history'=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),'isset({save})'),
		'status'=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),'isset({save})'),
		'type'=>	array(T_ZBX_INT, O_OPT,  null,  
				IN(array(-1,ITEM_TYPE_ZABBIX,ITEM_TYPE_SNMPV1,ITEM_TYPE_TRAPPER,ITEM_TYPE_SIMPLE,
					ITEM_TYPE_SNMPV2C,ITEM_TYPE_INTERNAL,ITEM_TYPE_SNMPV3,ITEM_TYPE_ZABBIX_ACTIVE,
					ITEM_TYPE_AGGREGATE,ITEM_TYPE_HTTPTEST,ITEM_TYPE_EXTERNAL,ITEM_TYPE_DB_MONITOR,ITEM_TYPE_IPMI)),'isset({save})'),
		'trends'=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),		'isset({save})'),
		'value_type'=>	array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2,3,4'),	'isset({save})'),
		'data_type'=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(ITEM_DATA_TYPE_DECIMAL,ITEM_DATA_TYPE_HEXADECIMAL),
					'isset({save})&&(isset({value_type})&&({value_type}=='.ITEM_VALUE_TYPE_UINT64.'))'),
		'valuemapid'=>	array(T_ZBX_INT, O_OPT,	 null,	DB_ID,				'isset({save})'),
		'params'=>	array(T_ZBX_STR, O_OPT,  NULL,	NULL,'isset({save})'),

		'snmp_community'=>array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,		'isset({save})&&isset({type})&&'.IN('1,4','type')),
		'snmp_oid'=>	array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,			'isset({save})&&isset({type})&&'.IN('1,4,6','type')),
		'snmp_port'=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),	'isset({save})&&isset({type})&&'.IN('1,4,6','type')),

		'snmpv3_securitylevel'=>array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2'),	'isset({save})&&(isset({type})&&({type}==6))'),
		'snmpv3_securityname'=>array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})&&(isset({type})&&({type}==6))'),
		'snmpv3_authpassphrase'=>array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})&&(isset({type})&&({type}==6))'),
		'snmpv3_privpassphrase'=>array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})&&(isset({type})&&({type}==6))'),

		'ipmi_sensor'=>array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})&&(isset({type})&&({type}==12))'),

		'trapper_hosts'=>array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&isset({type})&&({type}==2)'),
		'units'=>	array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})&&isset({type})&&'.IN('0,3','type')),
		'multiplier'=>	array(T_ZBX_INT, O_OPT,  null,  IN('0,1'),	'isset({save})&&isset({type})&&'.IN('0,3','type')),
		'delta'=>	array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2'),	'isset({save})&&isset({type})&&'.IN('0,3','type')),

		'formula'=>	array(T_ZBX_DBL, O_OPT,  null,  null,			'isset({save})&&(isset({multiplier})&&({multiplier}==1))'),
		'logtimefmt'=>	array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&(isset({value_type})&&({value_type}==2))'),
                 
		'group_itemid'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),
		'copy_targetid'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),
		'filter_groupid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	'isset({copy})&&(isset({copy_type})&&({copy_type}==0))'),
		'new_application'=>	array(T_ZBX_STR, O_OPT, null,	null,	'isset({save})'),
		'applications'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),

		'showdisabled'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),	null),
		
		'del_history'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'add_delay_flex'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_delay_flex'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		'register'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'group_task'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'clone'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'update'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'copy'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'select'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_copy_to'=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_mass_update'=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	null,	null,	null),

// filter
		"filter_rst"=>				array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		"filter_set"=>				array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),
		
		'selection_mode'=>			array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),		null),
		
		'filter_node'=>				array(T_ZBX_STR, O_OPT,  null,	null,		null),
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
		'filter_snmpv3_securityname'=>		array(T_ZBX_STR, O_OPT,  null,  null, null),
		'filter_snmpv3_securitylevel'=>		array(T_ZBX_INT, O_OPT,  null,  IN('-1,0,1,2'), null),
		'filter_snmpv3_authpassphrase'=>	array(T_ZBX_STR, O_OPT,  null,  null, null),
		'filter_snmpv3_privpassphrase'=>	array(T_ZBX_STR, O_OPT,  null,  null, null),
		'filter_value_type'=>		array(T_ZBX_INT, O_OPT,  null,  IN('-1,0,1,2,3,4'),null),
		'filter_data_type'=>			array(T_ZBX_INT, O_OPT,  null,  BETWEEN(-1,ITEM_DATA_TYPE_HEXADECIMAL),null),
		'filter_units'=>			array(T_ZBX_STR, O_OPT,  null,  null, null, null),
		'filter_formula'=>			array(T_ZBX_STR, O_OPT,  null,  null, null),
		'filter_delay'=>			array(T_ZBX_INT, O_OPT,  -1,  BETWEEN(0,86400),null),
		'filter_history'=>			array(T_ZBX_INT, O_OPT,  -1,  BETWEEN(0,65535),null),
		'filter_trends'=>			array(T_ZBX_INT, O_OPT,  -1,  BETWEEN(0,65535),null),
		'filter_status'=>			array(T_ZBX_INT, O_OPT,  null,  IN('-1,0,1,3'),null),
		'filter_logtimefmt'=>		array(T_ZBX_STR, O_OPT,  null,  null, null),
		'filter_delta'=>			array(T_ZBX_INT, O_OPT,  null,  IN('-1,0,1,2'), null),
		'filter_trapper_hosts'=>	array(T_ZBX_STR, O_OPT,  null,  null, null),
		
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})'),

	);

	$_REQUEST['showdisabled'] = get_request('showdisabled', get_profile('web.items.showdisabled', 0));
	
	check_fields($fields);
	validate_sort_and_sortorder('i.description',ZBX_SORT_UP);

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
	
	if(isset($_REQUEST['filter_set'])) 
		update_profile('web.items.filter.enabled',1, PROFILE_TYPE_INT);

	if(isset($_REQUEST['filter_rst']))
		update_profile('web.items.filter.enabled',0, PROFILE_TYPE_INT);
		
	$filter_enabled = get_profile('web.items.filter.enabled',0);
	
	$prev_selection_mode = get_profile('web.items.filter.selection_mode', 0);
	$_REQUEST['selection_mode']	= get_request('selection_mode' ,get_profile('web.items.filter.selection_mode', 0));
	
	if(isset($_REQUEST['filter_rst']) || ($_REQUEST['selection_mode'] == 0)){
		$_REQUEST['filter_node'] = null;
		$_REQUEST['filter_group'] = null;
		//$_REQUEST['filter_host'] = null;
		$_REQUEST['filter_application'] = null;
		//$_REQUEST['filter_description'] = null;
		$_REQUEST['filter_type'] = -1;
		//$_REQUEST['filter_key'] = null;
		$_REQUEST['filter_snmp_community'] = null;
		$_REQUEST['filter_snmp_oid'] = null;
		$_REQUEST['filter_snmp_port'] = null;
		$_REQUEST['filter_snmpv3_securityname'] = null;
		$_REQUEST['filter_snmpv3_securitylevel'] = -1;
		$_REQUEST['filter_snmpv3_authpassphrase'] = null;
		$_REQUEST['filter_snmpv3_privpassphrase'] = null;
		$_REQUEST['filter_value_type'] = -1;
		$_REQUEST['filter_data_type'] = -1;
		$_REQUEST['filter_units'] = null;
		$_REQUEST['filter_formula'] = null;
		$_REQUEST['filter_delay'] = null;
		$_REQUEST['filter_history'] = null;
		$_REQUEST['filter_trends'] = null;
		$_REQUEST['filter_status'] = null;
		$_REQUEST['filter_logtimefmt'] = null;
		$_REQUEST['filter_delta'] = null;
		$_REQUEST['filter_trapper_hosts'] = null;
	}
	else{
		$_REQUEST['filter_node']			= empty2null(get_request('filter_node'					,get_profile('web.items.filter.node')));
		$_REQUEST['filter_group']			= empty2null(get_request('filter_group'					,get_profile('web.items.filter.group')));
		//$_REQUEST['filter_host']			= empty2null(get_request('filter_host'					,get_profile('web.items.filter.host')));
		$_REQUEST['filter_application']		= empty2null(get_request('filter_application'			,get_profile('web.items.filter.application')));
		//$_REQUEST['filter_description']		= empty2null(get_request('filter_description'			,get_profile('web.items.filter.description')));
		$_REQUEST['filter_type']			= get_request('filter_type'                 			,get_profile('web.items.filter.type',			-1));
		//$_REQUEST['filter_key']				= empty2null(get_request('filter_key'						,get_profile('web.items.filter.key')));
		$_REQUEST['filter_snmp_community']		= empty2null(get_request('filter_snmp_community'		,get_profile('web.items.filter.snmp_community')));
		$_REQUEST['filter_snmp_oid']			= empty2null(get_request('filter_snmp_oid'				,get_profile('web.items.filter.snmp_oid')));
		$_REQUEST['filter_snmp_port']			= empty2null(get_request('filter_snmp_port'				,get_profile('web.items.filter.snmp_port')));
		$_REQUEST['filter_snmpv3_securityname']	= empty2null(get_request('filter_snmpv3_securityname'	,get_profile('web.items.filter.snmpv3_securityname')));
		$_REQUEST['filter_snmpv3_securitylevel']	= get_request('filter_snmpv3_securitylevel' 		,get_profile('web.items.filter.snmpv3_securitylevel',	-1));
		$_REQUEST['filter_snmpv3_authpassphrase']	= empty2null(get_request('filter_snmpv3_authpassphrase',get_profile('web.items.filter.snmpv3_authpassphrase')));
		$_REQUEST['filter_snmpv3_privpassphrase']	= empty2null(get_request('filter_snmpv3_privpassphrase',get_profile('web.items.filter.snmpv3_privpassphrase')));
		$_REQUEST['filter_value_type']		= get_request('filter_value_type'					,get_profile('web.items.filter.value_type',		-1));
		$_REQUEST['filter_data_type']		= get_request('filter_data_type'					,get_profile('web.items.filter.data_type',		-1));
		$_REQUEST['filter_units']			= empty2null(get_request('filter_units'				,get_profile('web.items.filter.units')));
		$_REQUEST['filter_formula']			= empty2null(get_request('filter_formula'			,get_profile('web.items.filter.formula')));
		$_REQUEST['filter_delay']			= empty2null(get_request('filter_delay'				,get_profile('web.items.filter.delay')));
		$_REQUEST['filter_history']			= empty2null(get_request('filter_history'			,get_profile('web.items.filter.history')));
		$_REQUEST['filter_trends']			= empty2null(get_request('filter_trends'			,get_profile('web.items.filter.trends')));
		$_REQUEST['filter_status']			= empty2null(get_request('filter_status'			,get_profile('web.items.filter.status')));
		$_REQUEST['filter_logtimefmt']		= empty2null(get_request('filter_logtimefmt'		,get_profile('web.items.filter.logtimefmt')));
		$_REQUEST['filter_delta']			= empty2null(get_request('filter_delta'				,get_profile('web.items.filter.delta')));
		$_REQUEST['filter_trapper_hosts']	= empty2null(get_request('filter_trapper_hosts'		,get_profile('web.items.filter.trapper_hosts')));
	}
	
	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['filter_host'] = null;
		$_REQUEST['filter_description'] = null;
		$_REQUEST['filter_key'] = null;
	}
	else{
		$_REQUEST['filter_host']		= empty2null(get_request('filter_host'			,get_profile('web.items.filter.host')));
		$_REQUEST['filter_description']	= empty2null(get_request('filter_description'	,get_profile('web.items.filter.description')));
		$_REQUEST['filter_key']			= empty2null(get_request('filter_key'			,get_profile('web.items.filter.key')));
	}
	
	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst']) || ($prev_selection_mode != $_REQUEST['selection_mode'])){
		update_profile('web.items.filter.selection_mode'            , $_REQUEST['selection_mode'], PROFILE_TYPE_STR);
							        			     			
		update_profile('web.items.filter.node'                 , $_REQUEST['filter_node'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.group'                , $_REQUEST['filter_group'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.host'                 , $_REQUEST['filter_host'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.application'          , $_REQUEST['filter_application'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.description'          , $_REQUEST['filter_description'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.type'                 , $_REQUEST['filter_type'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.key'                  , $_REQUEST['filter_key'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.snmp_community'       , $_REQUEST['filter_snmp_community'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.snmp_oid'             , $_REQUEST['filter_snmp_oid'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.snmp_port'            , $_REQUEST['filter_snmp_port'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.snmpv3_securityname'  , $_REQUEST['filter_snmpv3_securityname'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.snmpv3_securitylevel' , $_REQUEST['filter_snmpv3_securitylevel'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.snmpv3_authpassphrase', $_REQUEST['filter_snmpv3_authpassphrase'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.snmpv3_privpassphrase', $_REQUEST['filter_snmpv3_privpassphrase'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.value_type'           , $_REQUEST['filter_value_type'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.data_type'            , $_REQUEST['filter_data_type'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.units'                , $_REQUEST['filter_units'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.formula'              , $_REQUEST['filter_formula'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.delay'                , $_REQUEST['filter_delay'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.history'              , $_REQUEST['filter_history'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.trends'               , $_REQUEST['filter_trends'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.status'               , $_REQUEST['filter_status'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.logtimefmt'           , $_REQUEST['filter_logtimefmt'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.delta'                , $_REQUEST['filter_delta'], PROFILE_TYPE_STR);
		update_profile('web.items.filter.trapper_hosts'        , $_REQUEST['filter_trapper_hosts'], PROFILE_TYPE_STR);
	}	

	if(!empty($_REQUEST['filter_hostid'])) $_REQUEST['hostid'] = $_REQUEST['filter_hostid'];
// --------------
	$showdisabled = get_request('showdisabled', 0);
	
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE);
	
	if(isset($_REQUEST['hostid']) && ($_REQUEST['hostid'] > 0) && !isset($available_hosts[$_REQUEST['hostid']])){
		unset($_REQUEST['hostid']);
	}

	update_profile('web.items.showdisabled',$showdisabled, PROFILE_TYPE_INT);
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
/*		if($result){
			$host = get_host_by_hostid($item['hostid']);
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_ITEM,S_ITEM.' ['.$item['key_'].'] ['.$_REQUEST['itemid'].'] '.S_HOST.' ['.$host['host'].']');
		}*/
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
		foreach($delay_flex as $val)
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
	else if(isset($_REQUEST['update'])&&isset($_REQUEST['group_itemid'])&&isset($_REQUEST['form_mass_update'])){
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
		unset($_REQUEST['group_itemid'], $_REQUEST['form_mass_update'], $_REQUEST['update']);
	}
	else if(isset($_REQUEST['copy'])&&isset($_REQUEST['group_itemid'])&&isset($_REQUEST['form_copy_to'])){
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
			unset($_REQUEST['form_copy_to']);
		}
		else{
			error('No target selection.');
		}
		show_messages();
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
	else if(isset($_REQUEST['group_task'])&&isset($_REQUEST['group_itemid'])){
// GROUP TASKS
		if($_REQUEST['group_task']==S_DELETE_SELECTED){
			global $USER_DETAILS;
			$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_WRITE);

			$group_itemid = $_REQUEST['group_itemid'];			

			DBstart();

			$sql = 'SELECT h.host, i.itemid, i.key_, i.templateid '.
					' FROM items i, hosts h '.
					' WHERE '.DBcondition('i.itemid',$group_itemid).
						' AND h.hostid=i.hostid'.
						' AND '.DBcondition('h.hostid',$available_hosts);
			$item_res = DBselect($sql);
			while($item = DBfetch($item_res)){
				if($item['templateid']<>0){
					unset($group_itemid[$item['itemid']]);
					continue;
				}

/*				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_ITEM,S_ITEM.' ['.$item['key_'].'] ['.$item['itemid'].'] '.S_HOST.' ['.$item['host'].']');*/
			}

			$result = delete_item($group_itemid);
			$result = DBend($result);
			show_messages($result, S_ITEMS_DELETED, null);
		}
		else if($_REQUEST['group_task']==S_ACTIVATE_SELECTED){
			global $USER_DETAILS;
			$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_WRITE);

			$group_itemid = $_REQUEST['group_itemid'];			

			DBstart();
/*			$sql = 'SELECT h.host, i.itemid, i.key_ '.
					' FROM items i, hosts h '.
					' WHERE '.DBcondition('i.itemid',$group_itemid).
						' AND h.hostid=i.hostid'.
						' AND '.DBcondition('h.hostid',$available_hosts);
			$item_res = DBselect($sql);
			while($item = DBfetch($item_res)){
				add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,S_ITEM.' ['.$item['key_'].'] ['.$item['itemid'].'] '.S_HOST.' ['.$item['host'].']'.S_ITEMS_ACTIVATED);
			}*/
			
			$result = activate_item($group_itemid);
			$result = DBend($result);
			show_messages($result, S_ITEMS_ACTIVATED, null);
		}
		else if($_REQUEST['group_task']==S_DISABLE_SELECTED){
			global $USER_DETAILS;
			$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_WRITE);

			$group_itemid = $_REQUEST['group_itemid'];			

			DBstart();
/*			$sql = 'SELECT h.host, i.itemid, i.key_ '.
					' FROM items i, hosts h '.
					' WHERE '.DBcondition('i.itemid',$group_itemid).
						' AND h.hostid=i.hostid'.
						' AND '.DBcondition('h.hostid',$available_hosts);
			$item_res = DBselect($sql);
			while($item = DBfetch($item_res)){
				add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,S_ITEM.' ['.$item['key_'].'] ['.$item['itemid'].'] '.S_HOST.' ['.$item['host'].']'.S_ITEMS_DISABLED);
			}*/
			
			$result = disable_item($group_itemid);
			$result = DBend($result);
			show_messages($result, S_ITEMS_DISABLED, null);
		}
		else if($_REQUEST['group_task']==S_CLEAN_HISTORY_SELECTED_ITEMS){
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
	}
?>
<?php
	if(isset($_REQUEST['itemid']) && ($_REQUEST['itemid']>0)){
		$sql_from = '';
		$sql_where = '';
		if(isset($_REQUEST['groupid']) && ($_REQUEST['groupid'] > 0)){
			$sql_where.= ' AND hg.groupid='.$_REQUEST['groupid'];
		}
		
		if(isset($_REQUEST['hostid']) && ($_REQUEST['hostid'] > 0)){
			$sql_where.= ' AND hg.hostid='.$_REQUEST['hostid'];
		}
		
		$sql = 'SELECT DISTINCT hg.groupid, hg.hostid '.
				' FROM hosts_groups hg, items i '.
				' WHERE i.itemid='.$_REQUEST['itemid'].
					' AND hg.hostid=i.hostid '.
					$sql_where;
		if($host_group = DBfetch(DBselect($sql,1))){
			if(!isset($_REQUEST['groupid']) || !isset($_REQUEST['hostid'])){
				$_REQUEST['groupid'] = $host_group['groupid'];
				$_REQUEST['hostid'] = $host_group['hostid'];
			}
			else if(($_REQUEST['groupid']!=$host_group['groupid']) || ($_REQUEST['hostid']!=$host_group['hostid'])){
				$_REQUEST['itemid'] = 0;
			}
		}
		else{
			$_REQUEST['itemid'] = 0;
		}
	}
	
	$options = array('only_current_node');
	foreach($options as $option) $params[$option] = 1;

	$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $PAGE_GROUPS['selected'], $params);

	validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS);
	
	$available_groups = $PAGE_GROUPS['groupids'];
	$available_hosts = $PAGE_HOSTS['hostids'];
?>
<?php
	$form = new CForm();
	$form->setMethod('get');
	$form->setName('hdrform');

	$form->addVar('hostid',$_REQUEST['hostid']);
	$form->addVar('groupid',$_REQUEST['groupid']);

	if($PAGE_HOSTS['selected'] > 0)
		$form->addItem(new CButton('form',S_CREATE_ITEM));

	show_table_header(S_CONFIGURATION_OF_ITEMS_BIG, $form);
	echo SBR;
	
	if(isset($_REQUEST['form_mass_update']) && isset($_REQUEST['group_itemid'])){
		insert_mass_update_item_form('group_itemid');
	} 
	else if(isset($_REQUEST['form_copy_to']) && isset($_REQUEST['group_itemid'])){
		insert_copy_elements_to_forms('group_itemid');
	} 
	else if (!isset($_REQUEST['form']) ||  !str_in_array($_REQUEST['form'],array(S_CREATE_ITEM,'update','clone'))) {
// Table HEADER
		$form = new CForm();
		$form->setMethod('get');
		
		$where_case = array();
		$from_tables['h'] = 'hosts h';
		$where_case[] = 'i.hostid=h.hostid';
		$where_case[] =  DBcondition('h.hostid',$available_hosts);
		
// Items Header
 		$form->addItem(array('[', 
			new CLink($showdisabled ? S_HIDE_DISABLED_ITEMS : S_SHOW_DISABLED_ITEMS,
				'?showdisabled='.($showdisabled?0:1).url_param('groupid').url_param('hostid'),null),
			']', SPACE));

		$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
		$cmbHosts = new CComboBox('hostid',$PAGE_HOSTS['selected'],'javascript: submit();');
	
		foreach($PAGE_GROUPS['groups'] as $groupid => $name){
			$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid).$name);
		}
		foreach($PAGE_HOSTS['hosts'] as $hostid => $name){
			$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid).$name);
		}
		
		$form->addItem(array(S_GROUP.SPACE,$cmbGroups));
		$form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
		
		$sql_from = '';
		$sql_where = '';
		if(isset($_REQUEST['groupid']) && ($_REQUEST['groupid'] > 0)){
			$sql_from .= ',hosts_groups hg ';
			$sql_where.= ' AND hg.hostid=h.hostid AND hg.groupid='.$_REQUEST['groupid'];
		}

		if(!$filter_enabled){
			$where_case[] = 'i.hostid='.$_REQUEST['hostid'];
			$show_applications = 1;
			$show_host = 0;
		}

		if($showdisabled == 0) $where_case[] = 'i.status <> 1';
		
		show_table_header(S_ITEMS_BIG, $form);
// ----------------

// Items Filter
		$filterForm = get_item_filter_form();
		
		$filter = create_filter(S_FILTER,null,$filterForm,'item_filter',get_profile('web.items.filter.state',0));
		$filter->Show();

		if($filter_enabled){
			if(ZBX_DISTRIBUTED && isset($_REQUEST['filter_node'])){
				$from_tables['n'] = 'nodes n';
				$where_case[] = 'n.nodeid='.DBid2nodeid('i.itemid');
				$where_case[] = 'n.name like '.zbx_dbstr('%'.$_REQUEST['filter_node'].'%');
			}
			
			if(isset($_REQUEST['filter_group'])){
				$from_tables['hg'] = 'hosts_groups hg';
				$from_tables['g'] = 'groups g';
				$where_case[] = 'i.hostid=hg.hostid';
				$where_case[] = 'g.groupid=hg.groupid';
				$where_case[] = 'g.name like '.zbx_dbstr('%'.$_REQUEST['filter_group'].'%');
			}
			
			if(isset($_REQUEST['filter_host'])){
				$where_case[] = 'h.host like '.zbx_dbstr('%'.$_REQUEST['filter_host'].'%');
			}
			
			if(isset($_REQUEST['filter_application'])){
				$from_tables['a'] = 'applications a';
				$from_tables['ia'] = 'items_applications ia';
				$where_case[] = 'i.itemid=ia.itemid';
				$where_case[] = 'ia.applicationid=a.applicationid';
				$where_case[] = 'a.name like '.zbx_dbstr('%'.$_REQUEST['filter_application'].'%');
			}
			
			if(isset($_REQUEST['filter_type']) && $_REQUEST['filter_type'] != -1){
				$where_case[] = 'i.type='.$_REQUEST['filter_type'];
			}
			
			if(isset($_REQUEST['filter_key'])){
				$where_case[] = 'i.key_ like '.zbx_dbstr('%'.$_REQUEST['filter_key'].'%');
			}
			
			if(isset($_REQUEST['filter_snmp_community'])){
				$where_case[] = 'i.snmp_community like '.zbx_dbstr('%'.$_REQUEST['filter_snmp_community'].'%');
			}
			
			if(isset($_REQUEST['filter_snmp_oid'])){
				$where_case[] = 'i.snmp_oid like '.zbx_dbstr('%'.$_REQUEST['filter_snmp_oid'].'%');
			}
			
			if(isset($_REQUEST['filter_snmp_port'])){
				$where_case[] = 'i.snmp_port='.$_REQUEST['filter_snmp_port'];
			}
			
			if(isset($_REQUEST['filter_snmpv3_securityname'])){
				$where_case[] = 'i.snmpv3_securityname like '.zbx_dbstr('%'.$_REQUEST['filter_snmpv3_securityname'].'%');
			}
			
			if(isset($_REQUEST['filter_snmpv3_securitylevel']) && $_REQUEST['filter_snmpv3_securitylevel'] != -1){
				$where_case[] = 'i.snmpv3_securitylevel='.$_REQUEST['filter_snmpv3_securitylevel'];
			}
			
			if(isset($_REQUEST['filter_snmpv3_authpassphrase'])){
				$where_case[] = 'i.snmpv3_authpassphrase like '.zbx_dbstr('%'.$_REQUEST['filter_snmpv3_authpassphrase'].'%');
			}
			
			if(isset($_REQUEST['filter_snmpv3_privpassphrase'])){
				$where_case[] = 'i.snmpv3_privpassphrase like '.zbx_dbstr('%'.$_REQUEST['filter_snmpv3_privpassphrase'].'%');
			}
			
			if(isset($_REQUEST['filter_value_type']) && $_REQUEST['filter_value_type'] != -1){
				$where_case[] = 'i.value_type='.$_REQUEST['filter_value_type'];
			}
			
			if(isset($_REQUEST['filter_data_type']) && $_REQUEST['filter_data_type'] != -1){
				$where_case[] = 'i.data_type='.$_REQUEST['filter_data_type'];
			}
			
			if(isset($_REQUEST['filter_units'])){
				$where_case[] = 'i.units='.zbx_dbstr($_REQUEST['filter_units']);
			}
			
			if(isset($_REQUEST['filter_formula'])){
				$where_case[] = 'i.formula like '.zbx_dbstr('%'.$_REQUEST['filter_formula'].'%');
			}
			
			if(isset($_REQUEST['filter_delay'])){
				$where_case[] = 'i.delay='.$_REQUEST['filter_delay'];
			}
			
			if(isset($_REQUEST['filter_history'])){
				$where_case[] = 'i.history='.$_REQUEST['filter_history'];
			}
			
			if(isset($_REQUEST['filter_trends'])){
				$where_case[] = 'i.trends='.$_REQUEST['filter_trends'];
			}
			
			if(isset($_REQUEST['filter_status']) && $_REQUEST['filter_status'] != -1){
				$where_case[] = 'i.status='.$_REQUEST['filter_status'];
			}
			
			if(isset($_REQUEST['filter_logtimefmt'])){
				$where_case[] = 'i.logtimefmt='.zbx_dbstr($_REQUEST['filter_logtimefmt']);
			}
			
			if(isset($_REQUEST['filter_delta']) && $_REQUEST['filter_delta'] != -1){
				$where_case[] = 'i.delta='.$_REQUEST['filter_delta'];
			}
			
			if(isset($_REQUEST['filter_trapper_hosts'])){
				$where_case[] = 'i.trapper_hosts like '.zbx_dbstr('%'.$_REQUEST['filter_trapper_hosts'].'%');
			}

			$show_applications = 0;
			$show_host = 1;
		}
//--------------------------

// TABLE
		$form = new CForm();
		$form->setName('items');

		$table  = new CTableInfo();
		$table->setHeader(array(
			$show_host ? make_sorting_link(S_HOST,'h.host') : null,
			array(	new CCheckBox('all_items',null,
					"CheckAll('".$form->GetName()."','all_items');"),
				make_sorting_link(S_DESCRIPTION,'i.description')),
			make_sorting_link(S_KEY,'i.key_'),
			make_sorting_link(nbsp(S_UPDATE_INTERVAL),'i.delay'),
			make_sorting_link(S_HISTORY,'i.history'),
			make_sorting_link(S_TRENDS,'i.trends'),
			make_sorting_link(S_TYPE,'i.type'),
			make_sorting_link(S_STATUS,'i.status'),
			$show_applications ? S_APPLICATIONS : null,
			S_ERROR));

		$from_tables['i'] = 'items i'; /* NOTE: must be added as last element to use left join */

		$sql = 'SELECT DISTINCT th.host as template_host,th.hostid as template_hostid, h.host, h.hostid, i.* '.
				' FROM '.implode(',', $from_tables).
					' LEFT JOIN items ti ON i.templateid=ti.itemid '.
					' LEFT JOIN hosts th ON ti.hostid=th.hostid '.
				' WHERE '.implode(' AND ', $where_case).
					' AND h.hostid='.$PAGE_HOSTS['selected'].
				order_by('h.host,i.description,i.key_,i.delay,i.history,i.trends,i.type,i.status','i.itemid');
		$db_items = DBselect($sql);
		while($db_item = DBfetch($db_items)){
			$description = array();
			$item_description = item_description($db_item);

			if(isset($_REQUEST['filter_description']) && !zbx_stristr($item_description, $_REQUEST['filter_description']) ) continue;

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
				'?form=update&itemid='.$db_item['itemid'].
//				url_param('groupid').
				'&hostid='.$db_item['hostid'],
				'action'));

			$status=new CCol(new CLink(item_status2str($db_item['status']),
					'?group_itemid%5B%5D='.$db_item['itemid'].
					'&group_task='.($db_item['status']?'Activate+selected':'Disable+selected'),
					item_status2style($db_item['status'])));
	
			if($db_item['error'] == ''){
				$error=new CCol('-','off');
			}
			else{
				$error=new CCol($db_item['error'],'on');
			}
			
			$applications = $show_applications ? implode(', ', get_applications_by_itemid($db_item['itemid'], 'name')) : null;
			if(!is_null($applications) && empty($applications)) $applications = ' - ';
			
			$chkBox = new CCheckBox('group_itemid['.$db_item['itemid'].']',null,null,$db_item['itemid']);
			//if($db_item['templateid'] > 0) $chkBox->setEnabled(false);
			$table->addRow(array(
				$show_host ? $db_item['host'] : null,
				array($chkBox, $description),
				$db_item['key_'],
				$db_item['delay'],
				$db_item['history'],
				$db_item['trends'],
				item_type2str($db_item['type']),
				$status,
				$applications,
				$error
				));
		}

		$footerButtons = array();
		array_push($footerButtons, new CButtonQMessage('group_task',S_ACTIVATE_SELECTED,S_ACTIVATE_SELECTED_ITEMS_Q));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButtonQMessage('group_task',S_DISABLE_SELECTED,S_DISABLE_SELECTED_ITEMS_Q));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButtonQMessage('group_task',S_CLEAN_HISTORY_SELECTED_ITEMS,
			S_HISTORY_CLEANING_CAN_TAKE_A_LONG_TIME_CONTINUE_Q));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButtonQMessage('group_task',S_DELETE_SELECTED,S_DELETE_SELECTED_ITEMS_Q));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButton('form_copy_to',S_COPY_SELECTED_TO));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButton('form_mass_update',S_MASS_UPDATE));
		$table->setFooter(new CCol($footerButtons));

		$form->addItem($table);
		$form->Show();
	}

	if(isset($_REQUEST['form'])){
// FORM
		if(str_in_array($_REQUEST['form'],array(S_CREATE_ITEM,'update','clone')) || 
			(($_REQUEST['form']=='mass_update') && isset($_REQUEST['group_itemid'])))
		{
			insert_item_form();
		}
	}
?>
<?php

include_once('include/page_footer.php');

?>