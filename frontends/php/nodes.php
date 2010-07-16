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
	require_once('include/nodes.inc.php');

	$page['title'] = 'S_NODES';
	$page['file'] = 'nodes.php';
	$page['hist_arg'] = array();

	include_once('include/page_header.php');
?>
<?php
	$fields=array(
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
// media form
		'nodeid'=>			array(T_ZBX_INT, O_NO,	null,	DB_ID,			'(isset({form})&&({form}=="update"))'),

		'new_nodeid'=>		array(T_ZBX_INT, O_OPT,	null,	DB_ID,			'isset({save})'),
		'name'=>			array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,		'isset({save})'),
		'timezone'=>		array(T_ZBX_INT, O_OPT,	null,	BETWEEN(-12,+13),	'isset({save})'),
		'ip'=>				array(T_ZBX_IP,	 O_OPT,	null,	null,			'isset({save})'),
		'node_type'=>		array(T_ZBX_INT, O_OPT,	null,
			IN(ZBX_NODE_CHILD.','.ZBX_NODE_MASTER.','.ZBX_NODE_LOCAL),		'isset({save})&&!isset({nodeid})'),
		'masterid' => 		array(T_ZBX_INT, O_OPT,	null,	DB_ID,	null),
		'port'=>			array(T_ZBX_INT, O_OPT,	null,	BETWEEN(1,65535),	'isset({save})'),
		'slave_history'=>	array(T_ZBX_INT, O_OPT,	null,	BETWEEN(0,65535),	'isset({save})'),
		'slave_trends'=>	array(T_ZBX_INT, O_OPT,	null,	BETWEEN(0,65535),	'isset({save})'),
/* actions */
		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
/* other */
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	check_fields($fields);
	validate_sort_and_sortorder();

	$available_nodes = get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_LIST);

	if (0 == count($available_nodes) ){
		access_deny();
	}

?>
<?php
	if(isset($_REQUEST['save'])){
		$result = false;
		if(isset($_REQUEST['nodeid'])){
			$audit_action = AUDIT_ACTION_UPDATE;
			DBstart();
			$result = update_node($_REQUEST['nodeid'], $_REQUEST['new_nodeid'],
				$_REQUEST['name'], $_REQUEST['timezone'], $_REQUEST['ip'], $_REQUEST['port'],
				$_REQUEST['slave_history'], $_REQUEST['slave_trends']);
			$result = DBend($result);
			$nodeid = $_REQUEST['nodeid'];
			show_messages($result, S_NODE_UPDATED, S_CANNOT_UPDATE_NODE);
		}
		else{
			$audit_action = AUDIT_ACTION_ADD;

			$_REQUEST['masterid'] = isset($_REQUEST['masterid']) ? $_REQUEST['masterid'] : null;
			DBstart();
			$nodeid = add_node($_REQUEST['new_nodeid'],
				$_REQUEST['name'], $_REQUEST['timezone'], $_REQUEST['ip'], $_REQUEST['port'],
				$_REQUEST['slave_history'], $_REQUEST['slave_trends'], $_REQUEST['node_type'], $_REQUEST['masterid']);
			$result = DBend($nodeid);
			show_messages($result, S_NODE_ADDED, S_CANNOT_ADD_NODE);
		}
		add_audit_if($result,$audit_action,AUDIT_RESOURCE_NODE,'Node ['.$_REQUEST['name'].'] id ['.$nodeid.']');
		if($result){
			unset($_REQUEST['form']);
		}
	}
	else if(isset($_REQUEST['delete'])){
		$node_data = get_node_by_nodeid($_REQUEST['nodeid']);

		DBstart();
		$result = delete_node($_REQUEST['nodeid']);
		$result = DBend($result);
		show_messages($result, S_NODE_DELETED, S_CANNOT_DELETE_NODE);

		add_audit_if($result,AUDIT_ACTION_DELETE,AUDIT_RESOURCE_NODE,'Node ['.$node_data['name'].'] id ['.$node_data['nodeid'].']');
		if($result){
			unset($_REQUEST['form'],$node_data);
		}
	}
?>
<?php

	$nodes_wdgt = new CWidget();

	$available_nodes = get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_LIST, null, null, false);

	$frmForm = new CForm(null, 'get');
	$cmbConf = new CComboBox('config', 'nodes.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConf->addItem('nodes.php', S_NODES);
		$cmbConf->addItem('proxies.php', S_PROXIES);
	$frmForm->addItem($cmbConf);

	if(!isset($_REQUEST['form']) && ZBX_DISTRIBUTED){
		$frmForm->addItem(new CButton('form', S_NEW_NODE));
	}

	$nodes_wdgt->addPageHeader(S_CONFIGURATION_OF_NODES, $frmForm);

	if(ZBX_DISTRIBUTED){
		global $ZBX_NODES, $ZBX_LOCMASTERID;

		if(isset($_REQUEST['form'])){
			$frm_title = S_NODE;
			if(isset($_REQUEST['nodeid'])){
				$node_data = get_node_by_nodeid($_REQUEST['nodeid']);
				$frm_title .= ' "'.$node_data['name'].'"';
			}

			$master_node = DBfetch(DBselect('SELECT name FROM nodes WHERE masterid=0 AND nodetype='.ZBX_NODE_LOCAL));
			$has_master = (!$master_node) ? true : false;


			$frmNode= new CFormTable($frm_title);
			$frmNode->setHelp('node.php');

			if(isset($_REQUEST['nodeid'])){
				$frmNode->addVar('nodeid', $_REQUEST['nodeid']);
			}

			if(isset($_REQUEST['nodeid']) && !isset($_REQUEST['form_refresh'])){
				$new_nodeid = $node_data['nodeid'];
				$name = $node_data['name'];
				$timezone = $node_data['timezone'];
				$ip = $node_data['ip'];
				$port = $node_data['port'];
				$slave_history = $node_data['slave_history'];
				$slave_trends = $node_data['slave_trends'];
				$masterid = $node_data['masterid'];
				$node_type = detect_node_type($node_data);
			}
			else{
				$new_nodeid = get_request('new_nodeid', 0);
				$name = get_request('name', '');
				$timezone = get_request('timezone', 0);
				$ip = get_request('ip', '127.0.0.1');
				$port = get_request('port', 10051);
				$slave_history = get_request('slave_history', 90);
				$slave_trends = get_request('slave_trends', 365);
				$node_type = get_request('node_type', ZBX_NODE_CHILD);
				$masterid = get_request('masterid', get_current_nodeid(false));
			}


			$frmNode->addRow(S_NAME, new CTextBox('name', $name, 40));
			$frmNode->addRow(S_ID, new CNumericBox('new_nodeid', $new_nodeid, 10));

			if(isset($_REQUEST['nodeid'])){
				$cmbNodeType = new CTextBox('node_type_name', node_type2str($node_type), null, 'yes');
			}
			else{
				$cmbNodeType = new CComboBox('node_type', $node_type, 'submit()');
				$cmbNodeType->addItem(ZBX_NODE_CHILD, S_CHILD);
				if(!$has_master){
					$cmbNodeType->addItem(ZBX_NODE_MASTER, S_MASTER);
				}
			}
			$frmNode->addRow(S_TYPE, $cmbNodeType);

			if($node_type == ZBX_NODE_CHILD){
				if(isset($_REQUEST['nodeid'])){
					$master_cb = new CTextBox('master_name', $ZBX_NODES[$ZBX_NODES[$_REQUEST['nodeid']]['masterid']]['name'], null, 'yes');
				}
				else{
					$master_cb = new CComboBox('masterid', $masterid);
					foreach($ZBX_NODES as $node){
						if($node['nodeid'] == $ZBX_LOCMASTERID) continue;
						$master_cb->addItem($node['nodeid'], $node['name']);
					}
				}
				$frmNode->addRow(S_MASTER_NODE, $master_cb);
			}

			$cmbTimeZone = new CComboBox('timezone', $timezone);
			for($i = -12; $i <= 13; $i++){
				$cmbTimeZone->addItem($i, 'GMT'.sprintf('%+03d:00', $i));
			}
			$frmNode->addRow(S_TIME_ZONE, $cmbTimeZone);
			$frmNode->addRow(S_IP, new CTextBox('ip', $ip, 15));
			$frmNode->addRow(S_PORT, new CNumericBox('port', $port, 5));
			$frmNode->addRow(S_DO_NOT_KEEP_HISTORY_OLDER_THAN, new CNumericBox('slave_history', $slave_history, 6));
			$frmNode->addRow(S_DO_NOT_KEEP_TRENDS_OLDER_THAN, new CNumericBox('slave_trends', $slave_trends, 6));


			$frmNode->addItemToBottomRow(new CButton('save', S_SAVE));
			if(isset($_REQUEST['nodeid']) && $node_type != ZBX_NODE_LOCAL){
				$frmNode->addItemToBottomRow(SPACE);
				$frmNode->addItemToBottomRow(new CButtonDelete(S_DELETE_SELECTED_NODE_Q, url_param('form').url_param('nodeid')));
			}
			$frmNode->addItemToBottomRow(SPACE);
			$frmNode->addItemToBottomRow(new CButtonCancel());

			$nodes_wdgt->addItem($frmNode);
		}
		else{
			$nodes_wdgt->addHeader(S_NODES_BIG);
			$nodes_wdgt->addItem(BR());

			$table=new CTableInfo(S_NO_NODES_DEFINED);
			$table->setHeader(array(
				make_sorting_header(S_ID, 'n.nodeid'),
				make_sorting_header(S_NAME, 'n.name'),
				make_sorting_header(S_TIME_ZONE, 'n.timezone'),
				make_sorting_header(S_IP.':'.S_PORT, 'n.ip')
			));

			$sql = 'SELECT n.* '.
					' FROM nodes n'.
					order_by('n.nodeid,n.name,n.timezone,n.ip','n.masterid');
			$db_nodes = DBselect($sql);

			while($node = DBfetch($db_nodes)){
				$table->addRow(array(
					$node['nodeid'],
					array(
						get_node_path($node['masterid']),
						new CLink(($node['nodetype'] ? new CSpan($node['name'], 'bold') : $node['name']), '?&form=update&nodeid='.$node['nodeid'])),
					new CSpan('GMT'.sprintf('%+03d:00', $node['timezone']), $node['nodetype'] ? 'bold' : null),
					new CSpan($node['ip'].':'.$node['port'],
					$node['nodetype'] ? 'bold' : null)
				));
			}
			
			$nodes_wdgt->addItem($table);
		}
	}
	else{
		$nodes_wdgt->addItem(new CTable(new CCol(S_NOT_DM_SETUP, 'center')));
	}

	$nodes_wdgt->show();


include_once('include/page_footer.php');
?>
