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
	require_once('include/maintenances.inc.php');
	require_once('include/forms.inc.php');

	$page['title'] = 'S_MAINTENANCE';
	$page['file'] = 'maintenance.php';
	$page['hist_arg'] = array('groupid','config','hostid');
	$page['scripts'] = array('menu_scripts.js','calendar.js');

include_once('include/page_header.php');

	$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE);
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE);

	if(isset($_REQUEST['groupid']) && ($_REQUEST['groupid']>0) && !isset($available_groups[$_REQUEST['groupid']])){
		access_deny();
	}
	if(isset($_REQUEST['hostid']) && ($_REQUEST['hostid']>0) && !isset($available_hosts[$_REQUEST['hostid']])) {
		access_deny();
	}
	if(isset($_REQUEST['apphostid']) && ($_REQUEST['apphostid']>0) && !isset($available_hosts[$_REQUEST['apphostid']])) {
		access_deny();
	}

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
/* ARRAYS */
		'hosts'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groups'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'hostids'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groupids'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'applications'=>array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
/* host */
		'hostid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID,		'isset({config})&&({config}==0||{config}==5||{config}==2)&&isset({form})&&({form}=="update")'),
		'host'=>	array(T_ZBX_STR, O_OPT,	NULL,   NOT_EMPTY,	'isset({config})&&({config}==0||{config}==3||{config}==5)&&isset({save})&&!isset({massupdate})'),
		'proxy_hostid'=>array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,		'isset({config})&&({config}==0)&&isset({save})&&!isset({massupdate})'),
		'dns'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,		'(isset({config})&&({config}==0))&&isset({save})&&!isset({massupdate})'),
		'useip'=>	array(T_ZBX_STR, O_OPT, NULL,	IN('0,1'),	'(isset({config})&&({config}==0))&&isset({save})&&!isset({massupdate})'),
		'ip'=>		array(T_ZBX_IP, O_OPT, NULL,	NULL,		'(isset({config})&&({config}==0))&&isset({save})&&!isset({massupdate})'),
		'port'=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,65535),'(isset({config})&&({config}==0))&&isset({save})&&!isset({massupdate})'),
		'status'=>	array(T_ZBX_INT, O_OPT,	NULL,	IN('0,1,3'),	'(isset({config})&&({config}==0))&&isset({save})&&!isset({massupdate})'),

		'newgroup'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	NULL),
		'templates'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	NULL),
		'clear_templates'=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,	NULL),

		'useipmi'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,			NULL),
		'ipmi_ip'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,			'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_port'=>		array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,65535),	'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_authtype'=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(-1,6),		'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_privilege'=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(1,5),		'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_username'=>	array(T_ZBX_STR, O_OPT,	NULL,	NULL,			'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_password'=>	array(T_ZBX_STR, O_OPT,	NULL,	NULL,			'isset({useipmi})&&!isset({massupdate})'),

		'useprofile'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	NULL),
		'devicetype'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'name'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'os'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'serialno'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'tag'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'macaddress'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'hardware'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'software'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'contact'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'location'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'notes'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),

		'useprofile_ext'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	NULL),
		'ext_host_profiles'=> 	array(T_ZBX_STR, O_OPT, P_UNSET_EMPTY,   NULL,   NULL),

/* mass update*/
		'massupdate'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'visible'=>			array(T_ZBX_STR, O_OPT,	null, 	null,	null),

/* group */
		'groupid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		'(isset({config})&&({config}==1))&&(isset({form})&&({form}=="update"))'),
		'gname'=>			array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	'(isset({config})&&({config}==1))&&isset({save})'),

// maintenance
		'maintenanceid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		'(isset({config})&&({config}==6))&&(isset({form})&&({form}=="update"))'),
		'maintenanceids'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, 		NULL),
		'mname'=>				array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	'(isset({config})&&({config}==6))&&isset({save})'),
		'maintenance_type'=>	array(T_ZBX_INT, O_OPT,  null,	null,		'(isset({config})&&({config}==6))&&isset({save})'),

		'description'=>			array(T_ZBX_STR, O_OPT,	NULL,	null,					'(isset({config})&&({config}==6))&&isset({save})'),
		'active_since'=>		array(T_ZBX_INT, O_OPT,  null,	BETWEEN(1,time()*2),	'(isset({config})&&({config}==6))&&isset({save})'),
		'active_till'=>			array(T_ZBX_INT, O_OPT,  null,	BETWEEN(1,time()*2),	'(isset({config})&&({config}==6))&&isset({save})'),

		'new_timeperiod'=>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({add_timeperiod})'),

		'timeperiods'=>			array(T_ZBX_STR, O_OPT, null,	null, null),
		'g_timeperiodid'=>		array(null, O_OPT, null, null, null),

		'edit_timeperiodid'=>	array(null, O_OPT, P_ACT,	DB_ID,	null),

/* actions */
		'add_timeperiod'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, 	null, null),
		'del_timeperiod'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel_new_timeperiod'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		'activate'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
		'disable'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),

		'add_to_group'=>		array(T_ZBX_INT, O_OPT, P_SYS|P_ACT, DB_ID, NULL),
		'delete_from_group'=>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT, DB_ID, NULL),

		'unlink'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   NULL,	NULL),
		'unlink_and_clear'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   NULL,	NULL),

		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'clone'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'full_clone'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete_and_clear'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),

/* other */
		'form'=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>array(T_ZBX_STR, O_OPT, NULL,	NULL,	NULL)
	);
	check_fields($fields);
	validate_sort_and_sortorder('h.host',ZBX_SORT_UP);

/************ ACTIONS FOR HOSTS ****************/
/* this code menages operations to unlink 1 template from multiple hosts */


	if(inarr_isset(array('clone','maintenanceid'))){
		unset($_REQUEST['maintenanceid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['cancel_new_timeperiod'])){
		unset($_REQUEST['new_timeperiod']);
	}
	else if(isset($_REQUEST['save'])){
		if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
			access_deny();

		$maintenance = array('name' => $_REQUEST['mname'],
					'maintenance_type' => $_REQUEST['maintenance_type'],
					'description'=>	$_REQUEST['description'],
					'active_since'=> $_REQUEST['active_since'],
					'active_till' => zbx_empty($_REQUEST['active_till'])?0:$_REQUEST['active_till']
				);

		$timeperiods = get_request('timeperiods', array());

		DBstart();

// update available_hosts after ACTIONS
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY,null,AVAILABLE_NOCACHE);
		if(isset($_REQUEST['maintenanceid'])) delete_timeperiods_by_maintenanceid($_REQUEST['maintenanceid']);

		$timeperiodids = array();
		foreach($timeperiods as $id => $timeperiod){
			$timeperiodid = add_timeperiod($timeperiod);
			$timeperiodids[$timeperiodid] = $timeperiodid;
		}


		if(isset($_REQUEST['maintenanceid'])){

			$maintenanceid=$_REQUEST['maintenanceid'];

			$result = update_maintenance($maintenanceid, $maintenance);

			$msg1 = S_MAINTENANCE_UPDATED;
			$msg2 = S_CANNOT_UPDATE_MAINTENANCE;
		}
		else {
			$result = $maintenanceid = add_maintenance($maintenance);

			$msg1 = S_MAINTENANCE_ADDED;
			$msg2 = S_CANNOT_ADD_MAINTENANCE;
		}

		save_maintenances_windows($maintenanceid, $timeperiodids);

		$hostids = get_request('hostids', array());
		save_maintenance_host_links($maintenanceid, $hostids);

		$groupids = get_request('groupids', array());
		save_maintenance_group_links($maintenanceid, $groupids);

		$result = DBend($result);
		show_messages($result,$msg1,$msg2);


		if($result){ // result - OK
			add_audit(!isset($_REQUEST['maintenanceid'])?AUDIT_ACTION_ADD:AUDIT_ACTION_UPDATE,
				AUDIT_RESOURCE_MAINTENANCE,
				S_NAME.': '.$_REQUEST['mname']);

			unset($_REQUEST['form']);
		}
	}
	else if(isset($_REQUEST['delete'])){
		if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY))) access_deny();

		$maintenanceids = get_request('maintenanceid', array());
		if(isset($_REQUEST['maintenanceids']))
			$maintenanceids = $_REQUEST['maintenanceids'];

		zbx_value2array($maintenanceids);

		$maintenances = array();
		foreach($maintenanceids as $id => $maintenanceid){
			$maintenances[$maintenanceid] = get_maintenance_by_maintenanceid($maintenanceid);
		}

		DBstart();
		$result = delete_maintenance($maintenanceids);
		$result = DBend($result);

		show_messages($result,S_MAINTENANCE_DELETED,S_CANNOT_DELETE_MAINTENANCE);
		if($result){
			foreach($maintenances as $maintenanceid => $maintenance){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_MAINTENANCE,'Id ['.$maintenanceid.'] '.S_NAME.' ['.$maintenance['name'].']');
			}

			unset($_REQUEST['form']);
			unset($_REQUEST['maintenanceid']);
		}
	}
	else if(inarr_isset(array('add_timeperiod','new_timeperiod'))){
		$new_timeperiod = $_REQUEST['new_timeperiod'];

// START TIME
		$new_timeperiod['start_time'] = ($new_timeperiod['hour'] * 3600) + ($new_timeperiod['minute'] * 60);
//--

// PERIOD
		$new_timeperiod['period'] = ($new_timeperiod['period_days'] * 86400) + ($new_timeperiod['period_hours'] * 3600) +
				($new_timeperiod['period_minutes'] * 60);
//--

// DAYSOFWEEK
		if(!isset($new_timeperiod['dayofweek'])){
			$dayofweek = '';

			$dayofweek .= (!isset($new_timeperiod['dayofweek_su']))?'0':'1';
			$dayofweek .= (!isset($new_timeperiod['dayofweek_sa']))?'0':'1';
			$dayofweek .= (!isset($new_timeperiod['dayofweek_fr']))?'0':'1';
			$dayofweek .= (!isset($new_timeperiod['dayofweek_th']))?'0':'1';
			$dayofweek .= (!isset($new_timeperiod['dayofweek_we']))?'0':'1';
			$dayofweek .= (!isset($new_timeperiod['dayofweek_tu']))?'0':'1';
			$dayofweek .= (!isset($new_timeperiod['dayofweek_mo']))?'0':'1';

			$new_timeperiod['dayofweek'] = bindec($dayofweek);
		}
//--

// MONTHS
		if(!isset($new_timeperiod['month'])){
			$month = '';

			$month .= (!isset($new_timeperiod['month_dec']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_nov']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_oct']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_sep']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_aug']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_jul']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_jun']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_may']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_apr']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_mar']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_feb']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_jan']))?'0':'1';

			$new_timeperiod['month'] = bindec($month);
		}
//--

		if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY){
			if($new_timeperiod['month_date_type'] > 0){
				$new_timeperiod['day'] = 0;
			}
			else{
				$new_timeperiod['every'] = 0;
				$new_timeperiod['dayofweek'] = 0;
			}
		}

		$_REQUEST['timeperiods'] = get_request('timeperiods',array());

		$result = false;
		if($new_timeperiod['period'] < 300) {	/* 5 min */
			info(S_INCORRECT_PERIOD);
		}
		else if(($new_timeperiod['hour'] > 23) || ($new_timeperiod['minute'] > 59)){
			info(S_INCORRECT_MAINTENANCE_PERIOD);
		}
		else if(($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME) && ($new_timeperiod['date'] < 1)){
			info(S_INCORRECT_MAINTENANCE_PERIOD);
		}
		else if(($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_DAILY) && ($new_timeperiod['every'] < 1)){
			info(S_INCORRECT_MAINTENANCE_PERIOD);
		}
		else if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY){
			if(($new_timeperiod['every'] < 1) || ($new_timeperiod['dayofweek'] < 1)){
				info(S_INCORRECT_MAINTENANCE_PERIOD);
			}
			else{
				$result = true;
			}
		}
		else if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY){
			if($new_timeperiod['month'] < 1){
				info(S_INCORRECT_MAINTENANCE_PERIOD);
			}
			else if(($new_timeperiod['day'] == 0) && ($new_timeperiod['dayofweek'] < 1)){
				info(S_INCORRECT_MAINTENANCE_PERIOD);
			}
			else if((($new_timeperiod['day'] < 1) || ($new_timeperiod['day'] > 31)) && ($new_timeperiod['dayofweek'] == 0)){
				info(S_INCORRECT_MAINTENANCE_PERIOD);
			}
			else{
				$result = true;
			}
		}
		else{
			$result = true;
		}

		if($result){
			if(!isset($new_timeperiod['id'])){
				if(!str_in_array($new_timeperiod,$_REQUEST['timeperiods']))
					array_push($_REQUEST['timeperiods'],$new_timeperiod);
			}
			else{
				$id = $new_timeperiod['id'];
				unset($new_timeperiod['id']);
				$_REQUEST['timeperiods'][$id] = $new_timeperiod;
			}

			unset($_REQUEST['new_timeperiod']);
		}
	}
	else if(inarr_isset(array('del_timeperiod','g_timeperiodid'))){
		$_REQUEST['timeperiods'] = get_request('timeperiods',array());
		foreach($_REQUEST['g_timeperiodid'] as $val){
			unset($_REQUEST['timeperiods'][$val]);
		}
	}
	else if(inarr_isset(array('edit_timeperiodid'))){
		$_REQUEST['edit_timeperiodid'] = array_keys($_REQUEST['edit_timeperiodid']);
		$edit_timeperiodid = $_REQUEST['edit_timeperiodid'] = array_pop($_REQUEST['edit_timeperiodid']);
		$_REQUEST['timeperiods'] = get_request('timeperiods',array());

		if(isset($_REQUEST['timeperiods'][$edit_timeperiodid])){
			$_REQUEST['new_timeperiod'] = $_REQUEST['timeperiods'][$edit_timeperiodid];
			$_REQUEST['new_timeperiod']['id'] = $edit_timeperiodid;
		}
	}


	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,AVAILABLE_NOCACHE); /* update available_hosts after ACTIONS */

	$params = array();
	
	$options = array('only_current_node','allow_all');

	foreach($options as $option) $params[$option] = 1;
	$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $PAGE_GROUPS['selected'], $params);

	validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS,false);


//	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,AVAILABLE_NOCACHE); /* update available_hosts after ACTIONS */
	$available_groups = $PAGE_GROUPS['groupids'];
	$available_hosts = $PAGE_HOSTS['hostids'];


	$frmForm = new CForm();
	$frmForm->setMethod('get');

	if(!isset($_REQUEST['form'])){
		$frmForm->addItem(new CButton('form',S_CREATE_MAINTENANCE_PERIOD););
	}

	show_table_header(S_CONFIGURATION_OF_HOSTS_GROUPS_AND_TEMPLATES, $frmForm);
?>
<?php
	$row_count = 0;

	if(isset($_REQUEST["form"])){
		$frmMaintenance = new CForm('hosts.php','post');
		$frmMaintenance->setName(S_MAINTENANCE);

		$frmMaintenance->addVar('form',get_request('form',1));

		$from_rfr = get_request('form_refresh',0);
		$frmMaintenance->addVar('form_refresh',$from_rfr+1);

		$frmMaintenance->addVar('config',get_request('config',6));

		if(isset($_REQUEST['maintenanceid']))
			$frmMaintenance->addVar('maintenanceid',$_REQUEST['maintenanceid']);

		$left_tab = new CTable();
		$left_tab->setCellPadding(3);
		$left_tab->setCellSpacing(3);

		$left_tab->addOption('border',0);

		$left_tab->addRow(create_hat(
				S_MAINTENANCE,
				get_maintenance_form(),//null,
				null,
				'hat_maintenance'
			));

		$left_tab->addRow(create_hat(
				S_MAINTENANCE_PERIODS,
				get_maintenance_periods(),//null
				null,
				'hat_timeperiods'
			));

		if(isset($_REQUEST['new_timeperiod'])){
			$new_timeperiod = $_REQUEST['new_timeperiod'];

			$left_tab->addRow(create_hat(
					(is_array($new_timeperiod) && isset($new_timeperiod['id']))?S_EDIT_MAINTENANCE_PERIOD:S_NEW_MAINTENANCE_PERIOD,
					get_timeperiod_form(),//nulls
					null,
					'hat_new_timeperiod'
				));
		}

		$right_tab = new CTable();
		$right_tab->setCellPadding(3);
		$right_tab->setCellSpacing(3);

		$right_tab->addOption('border',0);

		$right_tab->addRow(create_hat(
				S_HOSTS_IN_MAINTENANCE,
				get_maintenance_hosts_form($frmMaintenance),//null,
				null,
				'hat_host_link'
			));

		$right_tab->addRow(create_hat(
				S_GROUPS_IN_MAINTENANCE,
				get_maintenance_groups_form($frmMaintenance),//null,
				null,
				'hat_group_link'
			));



		$td_l = new CCol($left_tab);
		$td_l->addOption('valign','top');

		$td_r = new CCol($right_tab);
		$td_r->addOption('valign','top');

		$outer_table = new CTable();
		$outer_table->addOption('border',0);
		$outer_table->setCellPadding(1);
		$outer_table->setCellSpacing(1);
		$outer_table->addRow(array($td_l,$td_r));

		$frmMaintenance->additem($outer_table);

		show_messages();
		$frmMaintenance->show();
//			insert_maintenance_form();
	}
	else {
		echo SBR;
// Table HEADER
		$form = new CForm();
		$form->setMethod('get');

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

		$numrows = new CSpan(null,'info');
		$numrows->addOption('name','numrows');
		$header = get_table_header(array(S_MAINTENANCE_PERIODS,
						new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),
						S_FOUND.': ',$numrows,)
						);
		show_table_header($header, $form);
// ----
		$available_maintenances = get_accessible_maintenance_by_user(PERM_READ_WRITE);

		$sqls = array();

		$maintenances = array();
		$maintenanceids = array();

		if(isset($_REQUEST['hostid']) && ($_REQUEST['hostid']>0)){
			$sqls[] = 'SELECT m.* '.
				' FROM maintenances m, maintenances_hosts mh '.
				' WHERE '.DBin_node('m.maintenanceid').
					' AND '.DBcondition('m.maintenanceid',$available_maintenances).
					' AND mh.hostid='.$_REQUEST['hostid'].
					' AND m.maintenanceid=mh.maintenanceid ';
				' ORDER BY m.name';

			$sqls[] = 'SELECT m.* '.
				' FROM maintenances m, maintenances_groups mg, hosts_groups hg '.
				' WHERE '.DBin_node('m.maintenanceid').
					' AND '.DBcondition('m.maintenanceid',$available_maintenances).
					' AND hg.hostid='.$_REQUEST['hostid'].
					' AND mg.groupid=hg.groupid '.
					' AND m.maintenanceid=mg.maintenanceid ';
				' ORDER BY m.name';
		}
		else if(isset($_REQUEST['groupid']) && ($_REQUEST['groupid']>0)){
			$sqls[] = 'SELECT m.* '.
				' FROM maintenances m, maintenances_groups mg '.
				' WHERE '.DBin_node('m.maintenanceid').
					' AND '.DBcondition('m.maintenanceid',$available_maintenances).
					' AND mg.groupid='.$_REQUEST['groupid'].
					' AND m.maintenanceid=mg.maintenanceid ';
				' ORDER BY m.name';

			$sqls[] = 'SELECT m.* '.
				' FROM maintenances m, maintenances_hosts mh, hosts_groups hg '.
				' WHERE '.DBin_node('m.maintenanceid').
					' AND '.DBcondition('m.maintenanceid',$available_maintenances).
					' AND hg.groupid='.$_REQUEST['groupid'].
					' AND mh.hostid=hg.hostid '.
					' AND m.maintenanceid=mh.maintenanceid ';
				' ORDER BY m.name';			}
		else{
			$sqls[] = 'SELECT m.* '.
				' FROM maintenances m '.
				' WHERE '.DBin_node('m.maintenanceid').
					' AND '.DBcondition('m.maintenanceid',$available_maintenances).
				' ORDER BY m.name';
		}

		foreach($sqls as $num => $sql){
			$db_maintenances = DBselect($sql);
			while($maintenance = DBfetch($db_maintenances)){
				$maintenances[$maintenance['maintenanceid']] = $maintenance;
				$maintenanceids[$maintenance['maintenanceid']] = $maintenance['maintenanceid'];
			}
		}


		$form = new CForm(null,'post');
		$form->setName('maintenances');

		$table = new CTableInfo();
		$table->setHeader(array(
			array(
				new CCheckBox('all_maintenances',NULL,"CheckAll('".$form->GetName()."','all_maintenances','group_maintenanceid');"),
				make_sorting_link(S_NAME,'m.name')
			),
			S_TYPE,
			S_STATUS,
			S_DESCRIPTION
			));

		foreach($maintenances as $maintenanceid => $maintenance){

			if($maintenance['active_till'] < time()) $mnt_status = new CSpan(S_EXPIRED,'red');
			else $mnt_status = new CSpan(S_ACTIVE,'green');

			$table->addRow(array(
				array(
					new CCheckBox('maintenanceids['.$maintenance['maintenanceid'].']',NULL,NULL,$maintenance['maintenanceid']),
					new CLink($maintenance['name'],
						'hosts.php?form=update'.url_param('config').
						'&maintenanceid='.$maintenance['maintenanceid'].'#form', 'action')
				),
				$maintenance['maintenance_type']?S_NO_DATA_PROCESSING:S_NORMAL_PROCESSING,
				$mnt_status,
				$maintenance['description']
				));
			$row_count++;
		}
//			$table->setFooter(new CCol(new CButtonQMessage('delete_selected',S_DELETE_SELECTED,S_DELETE_SELECTED_USERS_Q)));

		$table->setFooter(new CCol(array(
			new CButtonQMessage('delete',S_DELETE_SELECTED,S_DELETE_SELECTED_GROUPS_Q)
		)));

		$form->addItem($table);

		$form->show();
		
		zbx_add_post_js('insert_in_element("numrows","'.$row_count.'");');
	}


include_once 'include/page_footer.php';

?>
