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

$page['title'] = "S_PROXIES";
$page['file'] = 'proxies.php';
$page['hist_arg'] = array('config');
$page['scripts'] = array();

include_once('include/page_header.php');

$_REQUEST['config'] = get_request('config','proxies.php');

$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE);
$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE);


if(isset($_REQUEST['hostid']) && ($_REQUEST['hostid']>0) && !isset($available_hosts[$_REQUEST['hostid']])) {
	access_deny();
}
?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'config'=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),

/* ARRAYS */
		'hostid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID,		'isset({form})&&({form}=="update")'),
		'host'=>	array(T_ZBX_STR, O_OPT,	NULL,   NOT_EMPTY,	'isset({save})'),
		'hosts'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),

// Actions
		'go'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),

// form
		'save'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'clone'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),

/* other */
		'form'=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>array(T_ZBX_STR, O_OPT, NULL,	NULL,	NULL)
	);

	check_fields($fields);
	validate_sort_and_sortorder('h.host',ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go','none');
?>
<?php

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
		unset($_REQUEST['delete']);
	}
	else if(isset($_REQUEST['clone']) && isset($_REQUEST['hostid'])){
		unset($_REQUEST['hostid']);
		$_REQUEST['form'] = 'clone';
	}
// ------- GO --------
	else if(str_in_array($_REQUEST['go'], array('activate','disable')) && isset($_REQUEST['hosts'])){
		$result = true;

		$status = ($_REQUEST['go'] == 'activate')?HOST_STATUS_MONITORED:HOST_STATUS_NOT_MONITORED;
		$hosts = get_request('hosts',array());

		DBstart();
		foreach($hosts as $hostid){
			$sql = 'SELECT  hostid,status '.
					' FROM hosts '.
					' WHERE proxy_hostid='.$hostid.
						' AND '.DBin_node('hostid');
			$db_hosts = DBselect($sql);

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
	}
	else if(($_REQUEST['go'] == 'delete') && isset($_REQUEST['hosts'])){

		$hosts = get_request('hosts',array());

		DBstart();
		foreach($hosts as $hostid){
			$proxy = get_host_by_hostid($hostid);
			$result = delete_proxy($hostid);

			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_PROXY,	'['.$proxy['host'].' ] ['.$proxy['hostid'].']');
		}
		$result = DBend();

		show_messages($result, S_PROXY_DELETED, S_CANNOT_DELETE_PROXY);
	}

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,AVAILABLE_NOCACHE); /* update available_hosts after ACTIONS */
?>
<?php
	$proxies_wdgt = new CWidget();
	$params = array();

	$options = array('only_current_node', 'allow_all');
	if(isset($_REQUEST['form']) || isset($_REQUEST['massupdate'])) array_push($options,'do_not_select_if_empty');

	foreach($options as $option) $params[$option] = 1;
	$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, 0, $params);

	validate_group($PAGE_GROUPS, $PAGE_HOSTS, false);

	$available_groups = $PAGE_GROUPS['groupids'];
	$available_hosts = $PAGE_HOSTS['hostids'];


	$frmForm = new CForm();
	$frmForm->setMethod('get');

// Config
	$cmbConf = new CComboBox('config','proxies.php','javascript: submit()');
	$cmbConf->setAttribute('onchange','javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConf->addItem('nodes.php',S_NODES);
		$cmbConf->addItem('proxies.php',S_PROXIES);

	$frmForm->addItem($cmbConf);

	if(!isset($_REQUEST["form"])){
		$frmForm->addItem(new CButton('form',S_CREATE_PROXY));
	}

	$proxies_wdgt->addPageHeader(S_CONFIGURATION_OF_PROXIES, $frmForm);


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
			$name = $proxy["host"];
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

		$frmHostG = new CFormTable($frm_title,"proxies.php");
		$frmHostG->setHelp("web.proxy.php");
		$frmHostG->addVar("config",get_request("config",5));

		if($_REQUEST['hostid']>0){
			$frmHostG->addVar("hostid",$_REQUEST['hostid']);
		}

		$frmHostG->addRow(S_PROXY_NAME,new CTextBox("host",$name,30));

		$cmbHosts = new CTweenBox($frmHostG,'hosts',$hosts);

		$sql = 'SELECT hostid,proxy_hostid,host '.
				' FROM hosts '.
				' WHERE status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
					' AND '.DBcondition('hostid',$available_hosts).
				' ORDER BY host';
		$db_hosts=DBselect($sql);
		while($db_host=DBfetch($db_hosts)){
			$cmbHosts->addItem($db_host['hostid'],
					get_node_name_by_elid($db_host['hostid'], null, ': ').$db_host["host"],
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

		$proxies_wdgt->addItem($frmHostG);
		$proxies_wdgt->show();
	}
	else {

		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');

		$proxies_wdgt->addHeader(S_PROXIES_BIG);
//		$proxies_wdgt->addHeader($numrows);

		$form = new CForm('proxies.php');
		$form->setMethod('get');

		$form->setName('hosts');
		$form->addVar('config',get_request('config',0));

		$table = new CTableInfo(S_NO_PROXIES_DEFINED);

		$table->setHeader(array(
				new CCheckBox('all_hosts',NULL,"checkAll('".$form->GetName()."','all_hosts','hosts');"),
				make_sorting_link(S_NAME,'g.name'),
				S_LASTSEEN_AGE,
				' # ',
				S_MEMBERS
			));

// sorting
//		order_page_result($proxies, 'description');

// PAGING UPPER
		$paging = BR();
//		$paging = getPagingLine($proxies);
		$proxies_wdgt->addItem($paging);
//---------

		$sql = 'SELECT hostid,host,lastaccess '.
				' FROM hosts'.
				' WHERE status IN ('.HOST_STATUS_PROXY.') '.
					' AND '.DBin_node('hostid').
				order_by('host');
		$db_proxies=DBselect($sql);
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
				new CCheckBox('hosts['.$db_proxy['hostid'].']', NULL, NULL, $db_proxy['hostid']),
				new CLink($db_proxy['host'],
							'proxies.php?form=update&hostid='.$db_proxy['hostid'].url_param('config')),
				$lastclock,
				$count,
				new CCol((empty($hosts)?'-':$hosts), 'wraptext')
				));
		}

// PAGING FOOTER
		$table->addRow(new CCol($paging));
//		$proxies_wdgt->addItem($paging);
//---------

//----- GO ------
		$goBox = new CComboBox('go');
		$goBox->addItem('activate',S_ACTIVATE_SELECTED);
		$goBox->addItem('disable',S_DISABLE_SELECTED);
		$goBox->addItem('delete',S_DELETE_SELECTED);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO.' (0)');
		$goButton->setAttribute('id','goButton');
		zbx_add_post_js('chkbxRange.pageGoName = "hosts";');

		$table->setFooter(new CCol(array($goBox, $goButton)));
//----

		$form->addItem($table);

		$proxies_wdgt->addItem($form);
		$proxies_wdgt->show();
	}
?>
<?php

include_once('include/page_footer.php');

?>
