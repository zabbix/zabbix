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
	require_once('include/forms.inc.php');

	$page['title'] = "S_PROXIES";
	$page['file'] = 'proxies.php';
	// $page['hist_arg'] = array('groupid','config','hostid');
	// $page['scripts'] = array('menu_scripts.js','calendar.js');

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

/* application */
		'applicationid'=>	array(T_ZBX_INT,O_OPT,	P_SYS,	DB_ID,		'(isset({config})&&({config}==4))&&(isset({form})&&({form}=="update"))'),
		'appname'=>			array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,	'(isset({config})&&({config}==4))&&isset({save})'),
		'apphostid'=>		array(T_ZBX_INT, O_OPT, NULL,	DB_ID.'{}>0',	'(isset({config})&&({config}==4))&&isset({save})'),
		'apptemplateid'=>	array(T_ZBX_INT,O_OPT,	NULL,	DB_ID,	NULL),

/* host linkage form */
		'tname'=>			array(T_ZBX_STR, O_OPT,	NULL,   NOT_EMPTY,	'isset({config})&&({config}==2)&&isset({save})'),
		'twb_groupid'=> 	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,	NULL),

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

	update_profile('web.hosts.config',$_REQUEST['config'], PROFILE_TYPE_INT);
?>
<?php

/************ ACTIONS FOR HOSTS ****************/
/* this code menages operations to unlink 1 template from multiple hosts */
	if($_REQUEST['config']==2 && (isset($_REQUEST['save']))){

		$hosts = get_request('hosts',array());

		if(isset($_REQUEST['hostid'])){
			$templateid=$_REQUEST['hostid'];
			$result = true;

// Permission check
			$hosts = array_intersect($hosts,$available_hosts);
//-- unlink --
			DBstart();

			$linked_hosts = array();
			$db_childs = get_hosts_by_templateid($templateid);
			while($db_child = DBfetch($db_childs)){
				$linked_hosts[$db_child['hostid']] = $db_child['hostid'];
			}

			$unlink_hosts = array_diff($linked_hosts,$hosts);

			foreach($unlink_hosts as $id => $value){
				$result &= unlink_template($value, $templateid, false);
			}
//----------
//-- link --
			$link_hosts = array_diff($hosts,$linked_hosts);

			$template_name=DBfetch(DBselect('SELECT host FROM hosts WHERE hostid='.$templateid));

			foreach($link_hosts as $id => $hostid){

				$host_groups=array();
				$db_hosts_groups = DBselect('SELECT groupid FROM hosts_groups WHERE hostid='.$hostid);
				while($hg = DBfetch($db_hosts_groups)) $host_groups[] = $hg['groupid'];

				$host=get_host_by_hostid($hostid);

				$templates_tmp=get_templates_by_hostid($hostid);
				$templates_tmp[$templateid]=$template_name['host'];

				$result &= update_host($hostid,
								$host['host'],$host['port'],$host['status'],$host['useip'],$host['dns'],
								$host['ip'],$host['proxy_hostid'],$templates_tmp,$host['useipmi'],$host['ipmi_ip'],
								$host['ipmi_port'],$host['ipmi_authtype'],$host['ipmi_privilege'],$host['ipmi_username'],
								$host['ipmi_password'],null,$host_groups);
			}
//----------
			$result = DBend($result);

			show_messages($result, S_LINK_TO_TEMPLATE, S_CANNOT_LINK_TO_TEMPLATE);
/*			if($result){
				$host=get_host_by_hostid($templateid);
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,
					'Host ['.$host['host'].'] '.
					'Mass Linkage '.
					'Status ['.$host['status'].']');
			}*/
//---
			unset($_REQUEST['save']);
			unset($_REQUEST['hostid']);
			unset($_REQUEST['form']);
		}
	}
/* UNLINK HOST */

/****** ACTIONS FOR GROUPS **********/
/* CLONE HOST */

	if(isset($_REQUEST['save'])){
		$result = true;
		$hosts = get_request('hosts',array());

		DBstart();
		if(isset($_REQUEST['hostid'])){
			$result 	= update_proxy($_REQUEST['hostid'], $_REQUEST['host'], $hosts);
			$action		= AUDIT_ACTION_UPDATE;
			$msg_ok		= S_PROXY_UPDATED;
			$msg_fail	= S_CANNOT_UPDATE_PROXY;
			$hostid		= $_REQUEST['hostid'];
		}
		else {
			if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
				access_deny();

			$hostid		= add_proxy($_REQUEST['host'], $hosts);
			$action		= AUDIT_ACTION_ADD;
			$msg_ok		= S_PROXY_ADDED;
			$msg_fail	= S_CANNOT_ADD_PROXY;
		}
		$result = DBend($result);

		show_messages($result, $msg_ok, $msg_fail);
		if($result){
			add_audit($action,AUDIT_RESOURCE_PROXY,'['.$_REQUEST['host'].' ] ['.$hostid.']');
			unset($_REQUEST['form']);
		}
		unset($_REQUEST['save']);
	}
	else if(isset($_REQUEST['delete'])){
		$result = false;

		if(isset($_REQUEST['hostid'])){
			if($proxy = get_host_by_hostid($_REQUEST['hostid'])){
				DBstart();
				$result = delete_proxy($_REQUEST['hostid']);
				$result = DBend();
			}
			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_PROXY,'['.$proxy['host'].' ] ['.$proxy['hostid'].']');
			}

			show_messages($result, S_PROXY_DELETED, S_CANNOT_DELETE_PROXY);
			unset($_REQUEST['form']);
			unset($_REQUEST['hostid']);
		}
		else {
			$hosts = get_request('hosts',array());

			foreach($hosts as $hostid){
				$proxy = get_host_by_hostid($hostid);

				DBstart();
				$result = delete_proxy($hostid);
				$result = DBend();

				if(!$result) break;

				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_PROXY,	'['.$proxy['host'].' ] ['.$proxy['hostid'].']');
			}

			show_messages($result, S_PROXY_DELETED, S_CANNOT_DELETE_PROXY);
		}
		unset($_REQUEST['delete']);
	}
	else if(isset($_REQUEST['clone']) && isset($_REQUEST['hostid'])){
		unset($_REQUEST['hostid']);
		$_REQUEST['form'] = 'clone';
	}
	else if((isset($_REQUEST['activate']) || isset($_REQUEST['disable']))){
		$result = true;

		$status = isset($_REQUEST['activate']) ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
		$hosts = get_request('hosts',array());

		DBstart();
		foreach($hosts as $hostid){
			$db_hosts = DBselect('SELECT  hostid,status '.
								' FROM hosts '.
								' WHERE proxy_hostid='.$hostid.
									' AND '.DBin_node('hostid'));

			while($db_host = DBfetch($db_hosts)){
				$old_status = $db_host['status'];
				if($old_status == $status) continue;

				$result &= update_host_status($db_host['hostid'], $status);
				if(!$result) continue;

/*				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,'Old status ['.$old_status.'] '.'New status ['.$status.'] ['.$db_host['hostid'].']');*/
			}
		}
		$result = DBend($result && !empty($hosts));
		show_messages($result, S_HOST_STATUS_UPDATED, NULL);

		if(isset($_REQUEST['activate']))
			unset($_REQUEST['activate']);
		else
			unset($_REQUEST['disable']);
	}


	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,AVAILABLE_NOCACHE); /* update available_hosts after ACTIONS */
?>
<?php
	$params = array();
	
	$options = array('only_current_node', 'allow_all');
	if(isset($_REQUEST['form']) || isset($_REQUEST['massupdate'])) array_push($options,'do_not_select_if_empty');

	foreach($options as $option) $params[$option] = 1;
	$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, 0, $params);

	validate_group($PAGE_GROUPS, $PAGE_HOSTS, false);
			
//	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,AVAILABLE_NOCACHE); /* update available_hosts after ACTIONS */
	$available_groups = $PAGE_GROUPS['groupids'];
	$available_hosts = $PAGE_HOSTS['hostids'];


	show_table_header(S_CONFIGURATION_OF_HOSTS_GROUPS_AND_TEMPLATES, $frmForm);

	$row_count = 0;

	echo SBR;
	if(isset($_REQUEST["form"])){
	
		global	$USER_DETAILS;
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY);

		$hosts = array();
		$frm_title = S_PROXY;

		if($_REQUEST['hostid']>0){
			$proxy = get_host_by_hostid($_REQUEST['hostid']);
			$frm_title = S_PROXY.' ['.$proxy["host"].']';
		}

		if(($_REQUEST['hostid']>0) && !isset($_REQUEST["form_refresh"])){
			$name=$proxy["host"];
			$db_hosts=DBselect('SELECT hostid '.
				' FROM hosts '.
				' WHERE status NOT IN ('.HOST_STATUS_DELETED.') '.
					' AND proxy_hostid='.$_REQUEST['hostid']);

			while($db_host=DBfetch($db_hosts))
				array_push($hosts, $db_host['hostid']);
		}
		else{
			$name=get_request("host","");
		}

		$frmHostG = new CFormTable($frm_title,"hosts.php");
		$frmHostG->SetHelp("web.proxy.php");
		$frmHostG->addVar("config",get_request("config",5));

		if($_REQUEST['hostid']>0){
			$frmHostG->addVar("hostid",$_REQUEST['hostid']);
		}

		$frmHostG->addRow(S_PROXY_NAME,new CTextBox("host",$name,30));

		$cmbHosts = new CTweenBox($frmHostG,'hosts',$hosts);
		$db_hosts=DBselect('SELECT hostid,proxy_hostid,host '.
							' FROM hosts '.
							' WHERE status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
								' AND '.DBcondition('hostid',$available_hosts).
							' ORDER BY host');
		while($db_host=DBfetch($db_hosts)){
			$cmbHosts->addItem($db_host['hostid'],
					get_node_name_by_elid($db_host['hostid']).$db_host["host"],
					NULL,
					($db_host["proxy_hostid"] == 0 || ($_REQUEST['hostid']>0) && ($db_host["proxy_hostid"] == $_REQUEST['hostid'])));
		}
		$frmHostG->addRow(S_HOSTS,$cmbHosts->Get(S_PROXY.SPACE.S_HOSTS,S_OTHER.SPACE.S_HOSTS));

		$frmHostG->addItemToBottomRow(new CButton("save",S_SAVE));
		if($_REQUEST['hostid']>0){
			$frmHostG->addItemToBottomRow(SPACE);
			$frmHostG->addItemToBottomRow(new CButton("clone",S_CLONE));
			$frmHostG->addItemToBottomRow(SPACE);
			$frmHostG->addItemToBottomRow(
				new CButtonDelete("Delete selected proxy?",
					url_param("form").url_param("config").url_param("hostid")
				)
			);
		}
		$frmHostG->addItemToBottomRow(SPACE);
		$frmHostG->addItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmHostG->Show();
	}
	else {
		$frmForm = new CForm();
		$frmForm->setMethod('get');
		
		$btn = new CButton('form',S_CREATE_PROXY);

		$frmForm->addItem($cmbConf);
		if(isset($btn) && !isset($_REQUEST['form'])){
			$frmForm->addItem(SPACE);
			$frmForm->addItem($btn);
		}
		
		$numrows = new CSpan(null,'info');
		$numrows->addOption('name','numrows');
		$header = get_table_header(array(S_PROXIES_BIG,
						new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),
						S_FOUND.': ',$numrows,)
						);
		show_table_header($header);

		$form = new CForm('hosts.php');
		$form->setMethod('get');

		$form->setName('hosts');
		$form->addVar('config',get_request('config',0));

		$table = new CTableInfo(S_NO_PROXIES_DEFINED);

		$table->setHeader(array(
				array(new CCheckBox('all_hosts',NULL,"CheckAll('".$form->GetName()."','all_hosts');"),
					SPACE,
					make_sorting_link(S_NAME,'g.name')),
					S_LASTSEEN_AGE,
					' # ',
					S_MEMBERS
				));

		$db_proxies=DBselect('SELECT hostid,host,lastaccess '.
							' FROM hosts'.
							' WHERE status IN ('.HOST_STATUS_PROXY.') '.
								' AND '.DBin_node('hostid').
							order_by('host'));

		while($db_proxy=DBfetch($db_proxies)){
			$count = 0;
			$hosts = array();

			$sql = 'SELECT DISTINCT host,status '.
					' FROM hosts'.
					' WHERE proxy_hostid='.$db_proxy['hostid'].
						' AND '.DBcondition('hostid',$available_hosts).
						' AND status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
					' ORDER BY host';
			$db_hosts = DBselect($sql);
			while($db_host=DBfetch($db_hosts)){
				$style = ($db_host['status']==HOST_STATUS_MONITORED)?NULL:(($db_host['status']==HOST_STATUS_TEMPLATE)?'unknown' :'on');
				array_push($hosts, empty($hosts) ? '' : ', ', new CSpan($db_host['host'], $style));
				$count++;
			}

			if($db_proxy['lastaccess'] != 0)
				$lastclock = zbx_date2age($db_proxy['lastaccess']);
			else
				$lastclock = '-';

			$table->addRow(array(
				array(
					new CCheckBox('hosts['.$db_proxy['hostid'].']', NULL, NULL, $db_proxy['hostid']),
					SPACE,
					new CLink($db_proxy['host'],
							'hosts.php?form=update&hostid='.$db_proxy['hostid'].url_param('config'),
							'action')
				),
				$lastclock,
				$count,
				new CCol((empty($hosts)?'-':$hosts), 'wraptext')
				));
			$row_count++;
		}

		$table->setFooter(new CCol(array(
			new CButtonQMessage('activate',S_ACTIVATE_SELECTED,S_ACTIVATE_SELECTED_HOSTS_Q),
			SPACE,
			new CButtonQMessage('disable',S_DISABLE_SELECTED,S_DISABLE_SELECTED_HOSTS_Q),
			SPACE,
			new CButtonQMessage('delete',S_DELETE_SELECTED,S_DELETE_SELECTED_GROUPS_Q)
		)));

		$form->addItem($table);
		$form->show();
		
		zbx_add_post_js('insert_in_element("numrows","'.$row_count.'");');
	}

include_once 'include/page_footer.php';

?>
