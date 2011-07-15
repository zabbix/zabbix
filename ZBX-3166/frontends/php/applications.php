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
require_once('include/forms.inc.php');

$page['title'] = 'S_APPLICATIONS';
$page['file'] = 'applications.php';
$page['hist_arg'] = array('groupid', 'hostid');
$page['scripts'] = array();

include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'hosts'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groups'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'hostids'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groupids'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'applications'=>array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),

		'hostid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID, NULL),
		'groupid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
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
	validate_sort_and_sortorder('name', ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go', 'none');


// PERMISSIONS
	if(get_request('groupid', 0) > 0){
		$groupids = available_groups($_REQUEST['groupid'], 1);
		if(empty($groupids)) access_deny();
	}

	if(get_request('hostid', 0) > 0){
		$hostids = available_hosts($_REQUEST['hostid'], 1);
		if(empty($hostids)) access_deny();
	}

	if(get_request('apphostid', 0) > 0){
		$hostids = available_hosts($_REQUEST['apphostid'], 1);
		if(empty($hostids)) access_deny();
	}
?>
<?php

/****** APPLICATIONS **********/
	if(isset($_REQUEST['save'])){
		DBstart();
		if(isset($_REQUEST['applicationid'])){
			$applicationid = update_application($_REQUEST['applicationid'],$_REQUEST['appname'], $_REQUEST['apphostid']);
			$action		= AUDIT_ACTION_UPDATE;
			$msg_ok		= S_APPLICATION_UPDATED;
			$msg_fail	= S_CANNOT_UPDATE_APPLICATION;
			
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
				$result = CApplication::delete($_REQUEST['applicationid']);
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
		$go_result = true;

		$applications = get_request('applications',array());

		DBstart();
		$sql = 'SELECT a.applicationid, a.name, a.hostid '.
				' FROM applications a'.
				' WHERE '.DBin_node('a.applicationid').
					' AND '.DBcondition('a.applicationid', $applications);
		$db_applications = DBselect($sql);
		while($db_app = DBfetch($db_applications)){
			if(!isset($applications[$db_app['applicationid']]))	continue;

			$go_result &= (bool) CApplication::delete($db_app['applicationid']);

			if($go_result){
				$host = get_host_by_hostid($db_app['hostid']);
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_APPLICATION,'Application ['.$db_app['name'].'] from host ['.$host['host'].']');
			}
		}
		$go_result = DBend($go_result);

		show_messages($go_result, S_APPLICATION_DELETED, S_CANNOT_DELETE_APPLICATION);
	}
	else if(str_in_array($_REQUEST['go'], array('activate','disable'))){
/* group operations */
		$go_result = true;
		$applications = get_request('applications',array());

		DBstart();
		foreach($applications as $id => $appid){

			$sql = 'SELECT ia.itemid,i.hostid,i.key_'.
					' FROM items_applications ia '.
						' LEFT JOIN items i ON ia.itemid=i.itemid '.
					' WHERE ia.applicationid='.$appid.
						' AND i.hostid='.$_REQUEST['hostid'].
						' AND i.type<>9'.
						' AND '.DBin_node('ia.applicationid');

			$res_items = DBselect($sql);
			while($item=DBfetch($res_items)){
				if($_REQUEST['go'] == 'activate'){
					$go_result&=activate_item($item['itemid']);
				}
				else{
					$go_result&=disable_item($item['itemid']);
				}
			}
		}
		$go_result = DBend($go_result);
		if($_REQUEST['go'] == 'activate')
			show_messages($go_result, S_ITEMS_ACTIVATED, null);
		else
			show_messages($go_result, S_ITEMS_DISABLED, null);
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}
?>
<?php
	$options = array(
		'groups' => array('editable' => 1),
		'hosts' => array('editable' => 1, 'templated_hosts' => 1),
		'hostid' => get_request('hostid', null),
		'groupid' => get_request('groupid', null),
	);
	$pageFilter = new CPageFilter($options);
	$_REQUEST['groupid'] = $pageFilter->groupid;
	$_REQUEST['hostid'] = $pageFilter->hostid;
?>
<?php
	$app_wdgt = new CWidget();

	$frmForm = new CForm(null, 'get');
	$frmForm->addVar('hostid',get_request('hostid', 0));

	if(!isset($_REQUEST['form'])){
		$frmForm->addItem(new CButton('form', S_CREATE_APPLICATION));
	}

	$app_wdgt->addPageheader(S_CONFIGURATION_OF_APPLICATIONS, $frmForm);
?>
<?php
	if(isset($_REQUEST['form'])){
		$frm_title = S_NEW_APPLICATION;

		if(isset($_REQUEST['applicationid'])){
			$result=DBselect('SELECT * FROM applications WHERE applicationid='.$_REQUEST['applicationid']);
			$row=DBfetch($result);
			$frm_title = S_APPLICATION.': "'.$row['name'].'"';
		}

		if(isset($_REQUEST["applicationid"]) && !isset($_REQUEST["form_refresh"])){
			$appname = $row["name"];
			$apphostid = $row['hostid'];
		}
		else{
			$appname = get_request("appname","");
			$apphostid = get_request("apphostid",get_request("hostid",0));
		}

		$db_host = get_host_by_hostid($apphostid,1 /* no error message */);
		if($db_host){
			$apphost = $db_host["host"];
		}
		else{
			$apphost = '';
			$apphostid = 0;
		}

		$frmApp = new CFormTable($frm_title);
		$frmApp->setHelp("web.applications.php");

		if(isset($_REQUEST["applicationid"]))
			$frmApp->addVar("applicationid",$_REQUEST["applicationid"]);

		$frmApp->addRow(S_NAME,new CTextBox("appname",$appname,32));

		$frmApp->addVar("apphostid",$apphostid);

		if(!isset($_REQUEST["applicationid"])){
			// any new application can SELECT host
			$frmApp->addRow(S_HOST,array(
				new CTextBox("apphost",$apphost,32,'yes'),
				new CButton("btn1",S_SELECT,
					"return PopUp('popup.php?dstfrm=".$frmApp->getName().
					"&dstfld1=apphostid&dstfld2=apphost&srctbl=hosts_and_templates&srcfld1=hostid&srcfld2=host&noempty=1',450,450);",
					'T')
				));
		}

		$frmApp->addItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($_REQUEST['applicationid'])){
			$frmApp->addItemToBottomRow(SPACE);
			$frmApp->addItemToBottomRow(new CButtonDelete(S_DELETE_APPLICATION,
					url_param('config').url_param('hostid').url_param('groupid').
					url_param('form').url_param('applicationid')));
		}

		$frmApp->addItemToBottomRow(SPACE);
		$frmApp->addItemToBottomRow(new CButtonCancel(url_param("config").url_param("hostid").url_param('groupid')));

		$app_wdgt->addItem($frmApp);
	}
	else{

		$form = new CForm(null, 'get');
		$form->addItem(array(S_GROUP.SPACE,$pageFilter->getGroupsCB()));
		$form->addItem(array(SPACE.S_HOST.SPACE,$pageFilter->getHostsCB()));

		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');

		$app_wdgt->addHeader(S_APPLICATIONS_BIG, $form);
		$app_wdgt->addHeader($numrows);

		$form = new CForm();
		$table = new CTableInfo('...');

		if($pageFilter->hostsSelected){
// Header Host
			if($_REQUEST['hostid'] > 0){
				$tbl_header_host = get_header_host_table($_REQUEST['hostid'], array('items', 'triggers', 'graphs'));
				$app_wdgt->addItem($tbl_header_host);
			}

			$form->setName('applications');
			$form->addVar('groupid', $_REQUEST['groupid']);
			$form->addVar('hostid', $_REQUEST['hostid']);

			$table->setHeader(array(
				new CCheckBox('all_applications',NULL,"checkAll('".$form->getName()."','all_applications','applications');"),
				(($_REQUEST['hostid'] > 0) ? null : S_HOST),
				make_sorting_header(S_APPLICATION, 'name'),
				S_SHOW
			));


			$sortfield = getPageSortField('name');
			$sortorder = getPageSortOrder();
			$options = array(
				'output' => API_OUTPUT_SHORTEN,
				'editable' => 1,
				'sortfield' => $sortfield,
				'sortorder' => $sortorder,
				'limit' => ($config['search_limit']+1)
			);
			if($pageFilter->hostid > 0)
				$options['hostids'] = $pageFilter->hostid;
			else if($pageFilter->groupid > 0)
				$options['groupids'] = $pageFilter->groupid;
			$applications = CApplication::get($options);

			$paging = getPagingLine($applications);

			$options = array(
				'applicationids' => zbx_objectValues($applications, 'applicationid'),
				'output' => API_OUTPUT_EXTEND,
				'select_items' => API_OUTPUT_REFER,
				'expandData' => 1,
			);
			$applications = CApplication::get($options);

			order_result($applications, $sortfield, $sortorder);

			foreach($applications as $anum => $application){
				$applicationid = $application['applicationid'];

				if($application['templateid']==0){
					$name = new CLink($application['name'],'applications.php?form=update&applicationid='.$applicationid);
				}
				else{
					$template_host = get_realhost_by_applicationid($application['templateid']);
					$name = array(
						new CLink($template_host['host'], 'applications.php?hostid='.$template_host['hostid'], 'unknown'),
						':',
						$application['name']
					);
				}
				$table->addRow(array(
					new CCheckBox('applications['.$applicationid.']',NULL,NULL,$applicationid),
					(($_REQUEST['hostid'] > 0) ? null : $application['host']),
					$name,
					array(new CLink(S_ITEMS,'items.php?hostid='.$_REQUEST['hostid'].'&filter_set=1&filter_application='.urlencode($application['name'])),
					SPACE.'('.count($application['items']).')')
				));
			}

	// goBox
			$goBox = new CComboBox('go');
			$goOption = new CComboItem('activate', S_ACTIVATE_SELECTED);
			$goOption->setAttribute('confirm', S_ACTIVATE_SELECTED_APPLICATIONS);
			$goBox->addItem($goOption);

			$goOption = new CComboItem('disable', S_DISABLE_SELECTED);
			$goOption->setAttribute('confirm', S_DISABLE_SELECTED_APPLICATIONS);
			$goBox->addItem($goOption);

			$goOption = new CComboItem('delete', S_DELETE_SELECTED);
			$goOption->setAttribute('confirm', S_DELETE_SELECTED_APPLICATIONS);
			$goBox->addItem($goOption);

			// goButton name is necessary!!!
			$goButton = new CButton('goButton',S_GO.' (0)');
			$goButton->setAttribute('id','goButton');

			zbx_add_post_js('chkbxRange.pageGoName = "applications";');

			$footer = get_table_header(new CCol(array($goBox, $goButton)));
	//----

			$table = array($paging,$table,$paging,$footer);
		}

		$form->addItem($table);
		$app_wdgt->addItem($form);
	}

	$app_wdgt->show();
?>
<?php

include_once('include/page_footer.php');

?>