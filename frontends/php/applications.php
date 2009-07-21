<?php
/*
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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

	$page['title'] = "S_APPLICATIONS";
	$page['file'] = 'applications.php';
	$page['hist_arg'] = array('groupid','config','hostid');
	$page['scripts'] = array();

include_once('include/page_header.php');

	$_REQUEST['config'] = get_request('config','applications.php');

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
		// 0 - hosts; 1 - groups; 2 - linkages; 3 - templates; 4 - applications; 5 - Proxies; 6 - maintenance
		'config'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,	NULL),

//ARRAYS
		'hosts'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groups'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'hostids'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groupids'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'applications'=>array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),

		'hostid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID,		'isset({form})&&({form}=="update")'),
		'groupid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		'isset({form})&&({form}=="update")'),

// application
		'applicationid'=>	array(T_ZBX_INT,O_OPT,	P_SYS,	DB_ID,		'isset({form})&&({form}=="update")'),
		'appname'=>			array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,	'isset({save})'),
		'apphostid'=>		array(T_ZBX_INT, O_OPT, NULL,	DB_ID.'{}>0',	'isset({save})'),
		'apptemplateid'=>	array(T_ZBX_INT,O_OPT,	NULL,	DB_ID,	NULL),

// actions
		'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),

// form
		'add_to_group'=>		array(T_ZBX_INT, O_OPT, P_SYS|P_ACT, DB_ID, NULL),
		'delete_from_group'=>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT, DB_ID, NULL),

		'save'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'clone'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>				array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),

/* other */
		'form'=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>array(T_ZBX_STR, O_OPT, NULL,	NULL,	NULL)
	);
	check_fields($fields);
	validate_sort_and_sortorder('h.host',ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go','none');
?>
<?php

/****** APPLICATIONS **********/
	if(isset($_REQUEST['save'])){
		DBstart();
		if(isset($_REQUEST['applicationid'])){
			$result = update_application($_REQUEST['applicationid'],$_REQUEST['appname'], $_REQUEST['apphostid']);
			$action		= AUDIT_ACTION_UPDATE;
			$msg_ok		= S_APPLICATION_UPDATED;
			$msg_fail	= S_CANNOT_UPDATE_APPLICATION;
			$applicationid = $_REQUEST['applicationid'];
		}
		else {
			$applicationid = add_application($_REQUEST['appname'], $_REQUEST['apphostid']);
			$action		= AUDIT_ACTION_ADD;
			$msg_ok		= S_APPLICATION_ADDED;
			$msg_fail	= S_CANNOT_ADD_APPLICATION;
		}
		$result = DBend($applicationid);

		show_messages($result, $msg_ok, $msg_fail);
		if($result){
			add_audit($action,AUDIT_RESOURCE_APPLICATION,S_APPLICATION.' ['.$_REQUEST['appname'].' ] ['.$applicationid.']');
			unset($_REQUEST['form']);
		}
		unset($_REQUEST['save']);
	}
	else if(isset($_REQUEST['delete'])){
		if(isset($_REQUEST['applicationid'])){
			$result = false;
			if($app = get_application_by_applicationid($_REQUEST['applicationid'])){
				$host = get_host_by_hostid($app['hostid']);

				DBstart();
				$result=delete_application($_REQUEST['applicationid']);
				$result = DBend($result);
			}
			show_messages($result, S_APPLICATION_DELETED, S_CANNOT_DELETE_APPLICATION);

			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_APPLICATION,'Application ['.$app['name'].'] from host ['.$host['host'].']');
			}

			unset($_REQUEST['form']);
			unset($_REQUEST['applicationid']);
		}
	}
// -------- GO ---------------
	else if($_REQUEST['go'] == 'delete'){
/* group operations */
		$result = true;

		$applications = get_request('applications',array());
		$db_applications = DBselect('SELECT applicationid, name, hostid '.
								' FROM applications '.
								' WHERE '.DBin_node('applicationid'));

		DBstart();
		while($db_app = DBfetch($db_applications)){
			if(!uint_in_array($db_app['applicationid'],$applications))	continue;

			$result &= delete_application($db_app['applicationid']);

			if($result){
				$host = get_host_by_hostid($db_app['hostid']);
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_APPLICATION,'Application ['.$db_app['name'].'] from host ['.$host['host'].']');
			}
		}
		$result = DBend($result);

		show_messages(true, S_APPLICATION_DELETED, NULL);
	}
	else if(str_in_array($_REQUEST['go'], array('activate','disable'))){
/* group operations */
		$result = true;
		$applications = get_request('applications',array());

		DBstart();
		foreach($applications as $id => $appid){

			$sql = 'SELECT ia.itemid,i.hostid,i.key_'.
					' FROM items_applications ia '.
					  ' LEFT JOIN items i ON ia.itemid=i.itemid '.
					' WHERE ia.applicationid='.$appid.
					  ' AND i.hostid='.$_REQUEST['hostid'].
					  ' AND '.DBin_node('ia.applicationid');

			$res_items = DBselect($sql);
			while($item=DBfetch($res_items)){
				if($_REQUEST['go'] == 'activate'){
					$result&=activate_item($item['itemid']);
				}
				else{
					$result&=disable_item($item['itemid']);
				}
			}
		}
		$result = DBend($result);
		if($_REQUEST['go'] == 'activate')
			show_messages($result, S_ITEMS_ACTIVATED, null);
		else
			show_messages($result, S_ITEMS_DISABLED, null);
	}

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,AVAILABLE_NOCACHE); /* update available_hosts after ACTIONS */
?>
<?php
	$params = array();

	$options = array('only_current_node');
	if(isset($_REQUEST['form'])) array_push($options,'do_not_select_if_empty');

	foreach($options as $option) $params[$option] = 1;
	$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $PAGE_GROUPS['selected'], $params);

	validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS);

//	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,AVAILABLE_NOCACHE); /* update available_hosts after ACTIONS */
	$available_groups = $PAGE_GROUPS['groupids'];
	$available_hosts = $PAGE_HOSTS['hostids'];
?>
<?php

	$frmForm = new CForm();
	$frmForm->setMethod('get');

// Config
	$cmbConf = new CComboBox('config','applications.php','javascript: submit()');
	$cmbConf->setAttribute('onchange','javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConf->addItem('templates.php',S_TEMPLATES);
		$cmbConf->addItem('hosts.php',S_HOSTS);
		$cmbConf->addItem('items.php',S_ITEMS);
		$cmbConf->addItem('triggers.php',S_TRIGGERS);
		$cmbConf->addItem('graphs.php',S_GRAPHS);
		$cmbConf->addItem('applications.php',S_APPLICATIONS);

	$btn = new CButton('form',S_CREATE_APPLICATION);
	$frmForm->addVar('hostid',get_request('hostid',0));

	$frmForm->addItem($cmbConf);
	if(isset($btn) && !isset($_REQUEST['form'])){
		$frmForm->addItem(SPACE);
		$frmForm->addItem($btn);
	}

	show_table_header(S_CONFIGURATION_OF_APPLICATIONS, $frmForm);
?>
<?php
	$row_count = 0;

// APP 4
	echo SBR;
	if(isset($_REQUEST['form'])){
		insert_application_form();
	}
	else {
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
		$numrows->setAttribute('name','numrows');
		$header = get_table_header(array(S_APPLICATIONS_BIG,
						new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),
						S_FOUND.': ',$numrows,)
						);
		show_table_header($header, $form);

/* TABLE */

		$form = new CForm();
		$form->setName('applications');
		$form->addVar('groupid', $PAGE_GROUPS['selected']);
		$form->addVar('hostid', $PAGE_HOSTS['selected']);

		$table = new CTableInfo();
		$table->setHeader(array(
			new CCheckBox('all_applications',NULL,"checkAll('".$form->GetName()."','all_applications','applications');"),
			make_sorting_link(S_APPLICATION,'a.name'),
			S_SHOW
			));

		$sql = 'SELECT a.* '.
				' FROM applications a'.
				' WHERE a.hostid='.$_REQUEST['hostid'].
				order_by('a.name');
		$db_applications = DBselect($sql);
		while($db_app = DBfetch($db_applications)){
			if($db_app['templateid']==0){
				$name = new CLink($db_app['name'],'applications.php?form=update&applicationid='.$db_app['applicationid']);
			}
			else {
				$template_host = get_realhost_by_applicationid($db_app['templateid']);
				$name = array(
					new CLink($template_host['host'],'applications.php?hostid='.$template_host['hostid']),
					':',
					$db_app['name']
					);
			}
			$items = get_items_by_applicationid($db_app['applicationid']);
			$rows=0;
			while(DBfetch($items))	$rows++;

			$table->addRow(array(
				new CCheckBox('applications['.$db_app['applicationid'].']',NULL,NULL,$db_app['applicationid']),
				$name,
				array(new CLink(S_ITEMS,'items.php?hostid='.$db_app['hostid']),
				SPACE.'('.$rows.')')
				));
			$row_count++;
		}

// goBox
		$goBox = new CComboBox('go');
		$goBox->addItem('activate',S_ACTIVATE_ITEMS);
		$goBox->addItem('disable',S_DISABLE_ITEMS);
		$goBox->addItem('delete',S_DELETE_SELECTED);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO.' (0)');
		$goButton->setAttribute('id','goButton');
		zbx_add_post_js('chkbxRange.pageGoName = "applications";');
//----
		$table->setFooter(new CCol(array($goBox, $goButton)));

		$form->addItem($table);
		$form->show();
	}

zbx_add_post_js('insert_in_element("numrows","'.$row_count.'");');

?>
<?php

include_once 'include/page_footer.php';

?>
