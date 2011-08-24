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

	$page['title'] = 'S_PROXIES';
	$page['file'] = 'proxies.php';
	$page['hist_arg'] = array('');

	include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'hostid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID,		'isset({form})&&({form}=="update")'),
		'host'=>	array(T_ZBX_STR, O_OPT,	NULL,   NOT_EMPTY,	'isset({save})'),
		'status'=>	array(T_ZBX_INT, O_OPT,	NULL,   BETWEEN(HOST_STATUS_PROXY_ACTIVE,HOST_STATUS_PROXY_PASSIVE), 'isset({save})'),
		'useip'=>	array(T_ZBX_STR, O_OPT, NULL,	IN('0,1'),	'isset({save})'),
		'dns'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,		'isset({save})'),
		'ip'=>		array(T_ZBX_IP,  O_OPT, NULL,	NULL,		'isset({save})'),
		'port'=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,65535),'isset({save})'),
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
	validate_sort_and_sortorder('host', ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go', 'none');
?>
<?php
	if(isset($_REQUEST['save'])){
		$result = true;
		$hosts = get_request('hosts',array());

		DBstart();
		if(isset($_REQUEST['hostid'])){
			$result 	= update_proxy($_REQUEST['hostid'], $_REQUEST['host'], $_REQUEST['status'], $_REQUEST['useip'], $_REQUEST['dns'], $_REQUEST['ip'], $_REQUEST['port'], $hosts);
			$action		= AUDIT_ACTION_UPDATE;
			$msg_ok		= S_PROXY_UPDATED;
			$msg_fail	= S_CANNOT_UPDATE_PROXY;
			$hostid		= $_REQUEST['hostid'];
		}
		else {
			if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
				access_deny();

			$result = $hostid = add_proxy($_REQUEST['host'], $_REQUEST['status'], $_REQUEST['useip'], $_REQUEST['dns'], $_REQUEST['ip'], $_REQUEST['port'], $hosts);
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
		unset($_REQUEST['hosts']);
		$_REQUEST['form'] = 'clone';
	}
// ------- GO --------
	else if(str_in_array($_REQUEST['go'], array('activate','disable')) && isset($_REQUEST['hosts'])){
		$go_result = true;

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

				$go_result &= update_host_status($db_host['hostid'], $status);
				if(!$go_result) continue;

/*				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,'Old status ['.$old_status.'] '.'New status ['.$status.'] ['.$db_host['hostid'].']');*/
			}
		}

		$go_result = DBend($go_result && !empty($hosts));
		show_messages($go_result, S_HOST_STATUS_UPDATED, NULL);
	}
	else if(($_REQUEST['go'] == 'delete') && isset($_REQUEST['hosts'])){

		$hosts = get_request('hosts',array());

		DBstart();
		foreach($hosts as $hostid){
			$proxy = get_host_by_hostid($hostid);
			$go_result = delete_proxy($hostid);

			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_PROXY,	'['.$proxy['host'].' ] ['.$proxy['hostid'].']');
		}
		$go_result = DBend();

		show_messages($go_result, S_PROXY_DELETED, S_CANNOT_DELETE_PROXY);
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}

?>
<?php
	$proxies_wdgt = new CWidget();

	$frmForm = new CForm();
	$cmbConf = new CComboBox('config', 'proxies.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConf->addItem('nodes.php',S_NODES);
		$cmbConf->addItem('proxies.php',S_PROXIES);
	$frmForm->addItem($cmbConf);
	if(!isset($_REQUEST['form'])){
		$frmForm->addItem(new CButton('form',S_CREATE_PROXY));
	}
	$proxies_wdgt->addPageHeader(S_CONFIGURATION_OF_PROXIES, $frmForm);


	if(isset($_REQUEST['form'])){
		$_REQUEST['hostid'] = get_request('hostid', 0);
		$frm_title = S_PROXY;
		
		$frmHostG = new CFormTable($frm_title, 'proxies.php');
		$frmHostG->setHelp('web.proxy.php');

		if($_REQUEST['hostid'] > 0){
			$proxy = get_host_by_hostid($_REQUEST['hostid']);
			$frm_title = S_PROXY.' ['.$proxy['host'].']';
			$frmHostG->addVar('hostid',$_REQUEST['hostid']);
		}

		if(($_REQUEST['hostid'] > 0) && !isset($_REQUEST['form_refresh'])){
			$name = $proxy['host'];
			$status = $proxy['status'];
			if ($status == HOST_STATUS_PROXY_PASSIVE){
				$useip = $proxy['useip'];
				$dns = $proxy['dns'];
				$ip = $proxy['ip'];
				$port = $proxy['port'];
			}
			else{
				$useip = get_request('useip', 1);
				$dns = get_request('dns', '');
				$ip = get_request('ip', '0.0.0.0');
				$port = get_request('port', 10051);
			}
			$db_hosts = DBselect(
				'SELECT hostid '.
				' FROM hosts '.
				' WHERE proxy_hostid='.$_REQUEST['hostid']);

			$hosts = array();
			while($db_host = DBfetch($db_hosts))
				array_push($hosts, $db_host['hostid']);
		}
		else{
			$name = get_request('host', '');
			$status = get_request('status', HOST_STATUS_PROXY_ACTIVE);
			$useip = get_request('useip', 1);
			$dns = get_request('dns', '');
			$ip = get_request('ip', '0.0.0.0');
			$port = get_request('port', 10051);
			$hosts = get_request('hosts',array());
		}

		$frmHostG->addRow(S_PROXY_NAME, new CTextBox('host', $name, 30));

		$statusBox = new CComboBox('status', $status, 'submit()');
		$statusBox->addItem(HOST_STATUS_PROXY_ACTIVE, S_PROXY_ACTIVE);
		$statusBox->addItem(HOST_STATUS_PROXY_PASSIVE, S_PROXY_PASSIVE);
		$frmHostG->addRow(S_PROXY_MODE, $statusBox);

		if ($status == HOST_STATUS_PROXY_PASSIVE){
			$frmHostG->addRow(S_DNS_NAME, new CTextBox('dns', $dns, '40'));
			if(defined('ZBX_HAVE_IPV6')){
				$frmHostG->addRow(S_IP_ADDRESS, new CTextBox('ip', $ip, '39'));
			}
			else{
				$frmHostG->addRow(S_IP_ADDRESS, new CTextBox('ip', $ip, '15'));
			}

			$cmbConnectBy = new CComboBox('useip', $useip);
			$cmbConnectBy->addItem(0, S_DNS_NAME);
			$cmbConnectBy->addItem(1, S_IP_ADDRESS);
			$frmHostG->addRow(S_CONNECT_TO, $cmbConnectBy);

			$frmHostG->addRow(S_PORT, new CNumericBox('port', $port, 5));
		}
		else{
			$frmHostG->addVar('dns', $dns);
			$frmHostG->addVar('ip', $ip);
			$frmHostG->addVar('useip', $useip);
			$frmHostG->addVar('port', $port);
		}

		$cmbHosts = new CTweenBox($frmHostG, 'hosts', $hosts);

		$sql = 'SELECT hostid, proxy_hostid, host '.
				' FROM hosts '.
				' WHERE status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
				' AND '.DBin_node('hostid').
				' ORDER BY host';
		$db_hosts=DBselect($sql);
		while($db_host=DBfetch($db_hosts)){
			$cmbHosts->addItem(
				$db_host['hostid'],
				$db_host['host'],
				NULL,
				($db_host['proxy_hostid'] == 0 || ($_REQUEST['hostid']>0) && ($db_host['proxy_hostid'] == $_REQUEST['hostid']))
			);
		}
		$frmHostG->addRow(S_HOSTS,$cmbHosts->Get(S_PROXY.SPACE.S_HOSTS,S_OTHER.SPACE.S_HOSTS));

		$frmHostG->addItemToBottomRow(new CButton('save',S_SAVE));
		if($_REQUEST['hostid']>0){
			$frmHostG->addItemToBottomRow(array(
				SPACE, new CButton('clone',S_CLONE), 
				SPACE, new CButtonDelete(S_DELETE_SELECTED_PROXY_Q, url_param('form').url_param('hostid')),
				SPACE
			));
		}
		$frmHostG->addItemToBottomRow(new CButtonCancel());

		$proxies_wdgt->addItem($frmHostG);
		$proxies_wdgt->show();
	}
	else{
		$numrows = new CDiv();
		$numrows->setAttribute('name', 'numrows');
		$proxies_wdgt->addHeader(S_PROXIES_BIG);
		$proxies_wdgt->addHeader($numrows);

		$form = new CForm('proxies.php', 'get');
		$form->setName('hosts');

		$table = new CTableInfo(S_NO_PROXIES_DEFINED);
		$table->setHeader(array(
			new CCheckBox('all_hosts', NULL, "checkAll('".$form->GetName()."','all_hosts','hosts');"),
			make_sorting_header(S_NAME, 'host'),
			S_MODE,
			S_LASTSEEN_AGE,
			S_HOST_COUNT,
			S_ITEM_COUNT,
			S_REQUIRED_PERFORMANCE,
			S_HOSTS,
		));

		$sortfield = getPageSortField('host');
		$sortorder = getPageSortOrder();

		$options = array(
			'editable' => 1,
			'select_hosts' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);
		$proxies = CProxy::get($options);
		$proxies = zbx_toHash($proxies, 'proxyid');

// ordering && paging
		order_result($proxies, $sortfield, $sortorder);
		$paging = getPagingLine($proxies);
// --

// CALCULATE PERFORMANCE {{{ 
		$proxyids = array_keys($proxies);
		$sql = 'SELECT h.proxy_hostid, sum(1.0/i.delay) as qps '.
				' FROM items i,hosts h '.
				' WHERE i.status='.ITEM_STATUS_ACTIVE.
					' AND i.hostid=h.hostid '.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND i.delay<>0'.
					' AND '.DBcondition('h.proxy_hostid', $proxyids).
				' GROUP BY h.proxy_hostid';
		$db_perf = DBselect($sql);
		while($perf = DBfetch($db_perf)){
			$proxies[$perf['proxy_hostid']]['perf'] = round($perf['qps'], 2);
		}
// }}} CALCULATE PERFORMANCE


// CALCULATE ITEMS {{{
		$options = array(
			'groupCount' => 1,
			'countOutput' => 1,
			'proxyids' => $proxyids,
			'webitems' => 1,
			'monitored' => 1,
		);
		$proxy_items = CItem::get($options);
		foreach($proxy_items as $pitems){
			if(!isset($proxies[$pitems['proxy_hostid']]['item_count'])) $proxies[$pitems['proxy_hostid']]['item_count'] = 0;
			$proxies[$pitems['proxy_hostid']]['item_count'] += $pitems['rowscount'];
		}
// }}} CALCULATE ITEMS

		foreach($proxies as $pnum => $proxy){
			$hosts = array();
			
			foreach($proxy['hosts'] as $host){
				$style = ($host['status']==HOST_STATUS_MONITORED) ? 'off':(($host['status']==HOST_STATUS_TEMPLATE)?'unknown' :'on');
				$hosts[] = new CLink($host['host'], 'hosts.php?form=update&hostid='.$host['hostid'], $style);
				$hosts[] = ', ';
			}
			array_pop($hosts);

			$table->addRow(array(
				new CCheckBox('hosts['.$proxy['proxyid'].']', NULL, NULL, $proxy['proxyid']),
				new CLink($proxy['host'], 'proxies.php?form=update&hostid='.$proxy['proxyid']),
				$proxy['status'] == HOST_STATUS_PROXY_ACTIVE ? S_PROXY_ACTIVE : S_PROXY_PASSIVE,
				($proxy['lastaccess'] == 0) ? '-' : zbx_date2age($proxy['lastaccess']),
				count($proxy['hosts']),
				isset($proxy['item_count']) ? $proxy['item_count'] : 0,
				isset($proxy['perf']) ? $proxy['perf'] : '-',
				new CCol((empty($hosts) ? '-' : $hosts), 'wraptext')
			));
		}


//----- GO ------
		$goBox = new CComboBox('go');

		$goOption = new CComboItem('activate',S_ACTIVATE_SELECTED);
		$goOption->setAttribute('confirm',S_ENABLE_SELECTED_PROXIES);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('disable',S_DISABLE_SELECTED);
		$goOption->setAttribute('confirm',S_DISABLE_SELECTED_PROXIES);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_PROXIES);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CButton('goButton', S_GO.' (0)');
		$goButton->setAttribute('id', 'goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "hosts";');
// --

		$footer = get_table_header(array($goBox, $goButton));

		$table = array($paging, $table, $paging, $footer);
		$form->addItem($table);
		$proxies_wdgt->addItem($form);
		$proxies_wdgt->show();
	}


include_once('include/page_footer.php');
?>
