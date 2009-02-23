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
	require_once('include/graphs.inc.php');
	require_once('include/forms.inc.php');

	$page['title'] = "S_CONFIGURATION_OF_GRAPHS";
	$page['file'] = 'graphs.php';
	$page['hist_arg'] = array();
	$page['scripts'] = array('graphs.js');

include_once 'include/page_header.php';

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>	array(T_ZBX_INT, O_OPT,	 NULL,	DB_ID,	NULL),
		'hostid'=>	array(T_ZBX_INT, O_OPT,	 NULL,	DB_ID,	NULL),

		'copy_type'	=>array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0,1'),'isset({copy})'),
		'copy_mode'	=>array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0'),NULL),

		'graphid'=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,			'(isset({form})&&({form}=="update"))'),
		'name'=>	array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,		'isset({save})'),
		'width'=>	array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,65535),	'isset({save})'),
		'height'=>	array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,65535),	'isset({save})'),

		'ymin_type'=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN('0,1,2'),		null),
		'ymax_type'=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN('0,1,2'),		null),

		'graphtype'=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN('0,1,2,3'),		'isset({save})'),

		'yaxismin'=>	array(T_ZBX_DBL, O_OPT,	 NULL,	null,	'isset({save})&&(({graphtype} == 0) || ({graphtype} == 1))'),
		'yaxismax'=>	array(T_ZBX_DBL, O_OPT,	 NULL,	null,	'isset({save})&&(({graphtype} == 0) || ({graphtype} == 1))'),
		'graph3d'=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	IN('0,1'),		null),
		'legend'=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	IN('0,1'),		null),
		"ymin_itemid"=>	array(T_ZBX_INT, O_OPT,	 NULL,	DB_ID,	'isset({save})&&isset({ymin_type})&&({ymin_type}==3)'),		"ymax_itemid"=>	array(T_ZBX_INT, O_OPT,	 NULL,	DB_ID,	'isset({save})&&isset({ymax_type})&&({ymax_type}==3)'),				'percent_left'=>	array(T_ZBX_DBL, O_OPT,	 NULL,	BETWEEN(0,100),	null),
		'percent_right'=>	array(T_ZBX_DBL, O_OPT,	 NULL,	BETWEEN(0,100),	null),
		'visible'=>			array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,1),	null),

		'items'=>		array(T_ZBX_STR, O_OPT,  NULL,	null,		null),
		'new_graph_item'=>	array(T_ZBX_STR, O_OPT,  NULL,	null,		null),
		'group_gid'=>		array(T_ZBX_STR, O_OPT,  NULL,	null,		null),
		'move_up'=>		array(T_ZBX_INT, O_OPT,  NULL,	null,		null),
		'move_down'=>		array(T_ZBX_INT, O_OPT,  NULL,	null,		null),

		'showworkperiod'=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN('1'),	NULL),
		'showtriggers'=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN('1'),	NULL),

		'group_graphid'=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID, NULL),
		'copy_targetid'=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID, NULL),
		'filter_groupid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, 'isset({copy})&&(isset({copy_type})&&({copy_type}==0))'),
/* actions */
		'add_item'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete_item'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),

		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'clone'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'copy'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
/* other */
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_copy_to'=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	check_fields($fields);
	validate_sort_and_sortorder('g.name',ZBX_SORT_UP);

?>
<?php

	$_REQUEST['items'] = get_request('items', array());
	$_REQUEST['group_gid'] = get_request('group_gid', array());
	$_REQUEST['graph3d'] = get_request('graph3d', 0);
	$_REQUEST['legend'] = get_request('legend', 0);

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE);
	$available_hosts_all_nodes = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,get_current_nodeid(true));
	$available_graphs = get_accessible_graphs(PERM_READ_WRITE,array(),null,get_current_nodeid(true));	// OPTIMAZE

// ---- <ACTIONS> ----
	if(isset($_REQUEST['clone']) && isset($_REQUEST['graphid'])){
		unset($_REQUEST['graphid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['save'])){

		$items = get_request('items', array());
		$itemids = array();
		foreach($items as $gitem){
			$itemids[$gitem['itemid']] = $gitem['itemid'];
		}

		if(!empty($itemids)){
			$sql = 'SELECT h.hostid '.
					' FROM hosts h,items i '.
					' WHERE h.hostid=i.hostid '.
						' AND '.DBcondition('i.itemid', $itemids).
						' AND '.DBcondition('h.hostid',$available_hosts_all_nodes,true);
			if(DBfetch(DBselect($sql,1))){
				access_deny();
			}
		}

		if(count($items) <= 0){
			info(S_REQUIRED_ITEMS_FOR_GRAPH);
		}
		else{
			isset($_REQUEST["ymin_type"])?(''):($_REQUEST["ymin_type"]=0);
			isset($_REQUEST["ymax_type"])?(''):($_REQUEST["ymax_type"]=0);

			isset($_REQUEST['yaxismin'])?(''):($_REQUEST['yaxismin']=0);
			isset($_REQUEST['yaxismax'])?(''):($_REQUEST['yaxismax']=0);

			$showworkperiod	= isset($_REQUEST['showworkperiod']) ? 1 : 0;
			$showtriggers	= isset($_REQUEST['showtriggers']) ? 1 : 0;

			$visible = get_request('visible');
			$percent_left  = 0;
			$percent_right = 0;

			if(isset($visible['percent_left'])) 	$percent_left  = get_request('percent_left', 0);
			if(isset($visible['percent_right']))	$percent_right = get_request('percent_right', 0);
			
			if(($_REQUEST['ymin_itemid'] != 0) && ($_REQUEST['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE)){
				$_REQUEST['yaxismin']=0;
			}
			
			if(($_REQUEST['ymax_itemid'] != 0)  && ($_REQUEST['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE)){
				$_REQUEST['yaxismax']=0;
			}
			
			if(isset($_REQUEST['graphid'])){

				DBstart();
				update_graph_with_items($_REQUEST['graphid'],
					$_REQUEST['name'],$_REQUEST['width'],$_REQUEST['height'],
					$_REQUEST['ymin_type'],$_REQUEST['ymax_type'],$_REQUEST['yaxismin'],$_REQUEST['yaxismax'],
					$_REQUEST['ymin_itemid'],$_REQUEST['ymax_itemid'],
					$showworkperiod,$showtriggers,$_REQUEST['graphtype'],$_REQUEST['legend'],
					$_REQUEST['graph3d'],$percent_left,$percent_right,$items);
				$result = DBend();

				if($result){
					add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_GRAPH,'Graph ID ['.$_REQUEST['graphid'].'] Graph ['.$_REQUEST['name'].']');
				}
				show_messages($result, S_GRAPH_UPDATED, S_CANNOT_UPDATE_GRAPH);
			}
			else{
				DBstart();
				add_graph_with_items($_REQUEST['name'],$_REQUEST['width'],$_REQUEST['height'],
					$_REQUEST['ymin_type'],$_REQUEST['ymax_type'],$_REQUEST['yaxismin'],$_REQUEST['yaxismax'],
					$_REQUEST['ymin_itemid'],$_REQUEST['ymax_itemid'],
					$showworkperiod,$showtriggers,$_REQUEST['graphtype'],$_REQUEST['legend'],
					$_REQUEST['graph3d'],$percent_left,$percent_right,$items);
				$result = DBend();

				if($result){
					add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_GRAPH,'Graph ['.$_REQUEST['name'].']');
				}
				show_messages($result, S_GRAPH_ADDED, S_CANNOT_ADD_GRAPH);
			}
			if($result){
				unset($_REQUEST['form']);
			}
		}
	}
	else if(isset($_REQUEST['delete']) && isset($_REQUEST['graphid'])){
		$graph=get_graph_by_graphid($_REQUEST['graphid']);

		if(!isset($available_graphs[$_REQUEST['graphid']])){
			access_deny();
		}

		DBstart();
			$result = delete_graph($_REQUEST['graphid']);
		$result = DBend($result);

		if($result){
			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_GRAPH,'Graph ['.$graph['name'].']');
			unset($_REQUEST['form']);
		}
		show_messages($result, S_GRAPH_DELETED, S_CANNOT_DELETE_GRAPH);
	}
	else if(isset($_REQUEST['delete']) && isset($_REQUEST['group_graphid'])){
		$group_graphid = $_REQUEST['group_graphid'];
		$group_graphid = zbx_uint_array_intersect($group_graphid,$available_graphs);
		$result = false;

		DBstart();
		foreach($group_graphid as $id => $graphid){
			$graph=get_graph_by_graphid($graphid);
			if($graph['templateid']<>0)	continue;
			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_GRAPH,'Graph ['.$graph['name'].']');
		}
		if(!empty($group_graphid)){
			$result = delete_graph($group_graphid);
		}

		$result = DBend($result);

		show_messages($result, S_ITEMS_DELETED, S_CANNOT_DELETE_ITEMS);
	}
	else if(isset($_REQUEST['copy'])&&isset($_REQUEST['group_graphid'])&&isset($_REQUEST['form_copy_to'])){
		if(isset($_REQUEST['copy_targetid']) && $_REQUEST['copy_targetid'] > 0 && isset($_REQUEST['copy_type'])){
			if(0 == $_REQUEST['copy_type']){ /* hosts */
				$hosts_ids = $_REQUEST['copy_targetid'];
			}
			else{ /* groups */
				$hosts_ids = array();

				$sql = 'SELECT DISTINCT h.hostid '.
						' FROM hosts h, hosts_groups hg'.
						' WHERE h.hostid=hg.hostid '.
							' AND '.DBcondition('hg.groupid',$_REQUEST['copy_targetid']).
							' AND '.DBcondition('h.hostid',$available_hosts_all_nodes);
				$db_hosts = DBselect($sql);
				while($db_host = DBfetch($db_hosts)){
					array_push($hosts_ids, $db_host['hostid']);
				}
			}
			DBstart();
			foreach($_REQUEST['group_graphid'] as $graph_id)
				foreach($hosts_ids as $host_id){
					copy_graph_to_host($graph_id, $host_id, true);
				}
			$result = DBend();
			unset($_REQUEST['form_copy_to']);
		}
		else{
			error('No target selection.');
		}
		show_messages();
	}
	else if(isset($_REQUEST['delete_item']) && isset($_REQUEST['group_gid'])){

		foreach($_REQUEST['items'] as $gid => $data){
			if(!isset($_REQUEST['group_gid'][$gid])) continue;
			unset($_REQUEST['items'][$gid]);
		}
		unset($_REQUEST['delete_item'], $_REQUEST['group_gid']);
	}
	else if(isset($_REQUEST['new_graph_item'])){
		$new_gitem = get_request('new_graph_item', array());

		foreach($_REQUEST['items'] as $gid => $data){
			if(	(bccomp($new_gitem['itemid'] , $data['itemid'])==0) &&
				$new_gitem['yaxisside'] == $data['yaxisside'] &&
				$new_gitem['calc_fnc'] == $data['calc_fnc'] &&
				$new_gitem['type'] == $data['type'] &&
				$new_gitem['periods_cnt'] == $data['periods_cnt'])
			{
				$already_exist = true;
				break;
			}
		}

		if(!isset($already_exist)){
			array_push($_REQUEST['items'], $new_gitem);
		}
	}
	else if(isset($_REQUEST['move_up']) && isset($_REQUEST['items'])){
		if(isset($_REQUEST['items'][$_REQUEST['move_up']])){
			if($_REQUEST['items'][$_REQUEST['move_up']]['sortorder'] > 0){

				$_REQUEST['items'][$_REQUEST['move_up']]['sortorder'] = ''.($_REQUEST['items'][$_REQUEST['move_up']]['sortorder'] - 1);
			}
		}
	}
	else if(isset($_REQUEST['move_down']) && isset($_REQUEST['items'])){
		if(isset($_REQUEST['items'][$_REQUEST['move_down']])){
			if($_REQUEST['items'][$_REQUEST['move_down']]['sortorder'] < 1000){

				$_REQUEST['items'][$_REQUEST['move_down']]['sortorder']++;
			}
		}
	}
// ----</ACTIONS>----
?>
<?php
	if(isset($_REQUEST['hostid']) && !isset($_REQUEST['groupid']) && !isset($_REQUEST['graphid'])){
		$sql = 'SELECT DISTINCT hg.groupid '.
				' FROM hosts_groups hg '.
				' WHERE hg.hostid='.$_REQUEST['hostid'];
		if($group=DBfetch(DBselect($sql, 1))){
			$_REQUEST['groupid'] = $group['groupid'];
		}
	}
	
	if(isset($_REQUEST['graphid']) && ($_REQUEST['graphid']>0)){
		$sql_from = '';
		$sql_where = '';
		if(isset($_REQUEST['groupid']) && ($_REQUEST['groupid'] > 0)){
			$sql_where.= ' AND hg.groupid='.$_REQUEST['groupid'];
		}

		if(isset($_REQUEST['hostid']) && ($_REQUEST['hostid'] > 0)){
			$sql_where.= ' AND hg.hostid='.$_REQUEST['hostid'];
		}
		$sql = 'SELECT DISTINCT hg.groupid, hg.hostid '.
				' FROM hosts_groups hg '.
				' WHERE EXISTS( SELECT DISTINCT i.itemid '.
									' FROM items i, graphs_items gi '.
									' WHERE i.hostid=hg.hostid '.
										' AND i.itemid=gi.itemid '.
										' AND gi.graphid='.$_REQUEST['graphid'].')'.
						$sql_where;
		if($host_group = DBfetch(DBselect($sql,1))){
			if(!isset($_REQUEST['groupid']) || !isset($_REQUEST['hostid'])){
				$_REQUEST['groupid'] = $host_group['groupid'];
				$_REQUEST['hostid'] = $host_group['hostid'];
			}
			else if(($_REQUEST['groupid']!=$host_group['groupid']) || ($_REQUEST['hostid']!=$host_group['hostid'])){
				$_REQUEST['graphid'] = 0;
			}
		}
		else{
//			$_REQUEST['graphid'] = 0;
		}
	}

	$params=array();
	$options = array('only_current_node','not_proxy_hosts');
	foreach($options as $option) $params[$option] = 1;

	$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $PAGE_GROUPS['selected'], $params);

	validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS);

	$available_groups = $PAGE_GROUPS['groupids'];
	$available_hosts = $PAGE_HOSTS['hostids'];
	$available_graphs = get_accessible_graphs(PERM_READ_WRITE,$available_hosts,null,get_current_nodeid(true),null,0);
?>
<?php
	$form = new CForm();
	$form->setMethod('get');

	if(!isset($_REQUEST['form']))
		$form->addItem(new CButton('form',S_CREATE_GRAPH));

	show_table_header(S_CONFIGURATION_OF_GRAPHS_BIG,$form);
	echo SBR;

	if(isset($_REQUEST['form_copy_to']) && isset($_REQUEST['group_graphid'])){
		insert_copy_elements_to_forms('group_graphid');
	}
	else if(isset($_REQUEST['form'])){
		insert_graph_form();
		echo SBR;
		$table = new CTable(NULL,'graph');
		if(($_REQUEST['graphtype'] == GRAPH_TYPE_PIE) || ($_REQUEST['graphtype'] == GRAPH_TYPE_EXPLODED)){
			$table->addRow(new CImg('chart7.php?period=3600'.url_param('items').
				url_param('name').url_param('legend').url_param('graph3d').url_param('width').url_param('height').url_param('graphtype')));
			$table->Show();
		}
		else{
			$table->addRow(new CImg('chart3.php?period=3600'.url_param('items').
				url_param('name').url_param('width').url_param('height').
				url_param('ymin_type').url_param('ymax_type').url_param('yaxismin').url_param('yaxismax').
				url_param('ymin_itemid').url_param('ymax_itemid').
				url_param('show_work_period').url_param('show_triggers').url_param('graphtype').
				url_param('percent_left').url_param('percent_right')));
			$table->Show();
		}
	}
	else {
/* Table HEADER */
		if(isset($_REQUEST['graphid']) && ($_REQUEST['graphid']==0)){
			unset($_REQUEST['graphid']);
		}

		$r_form = new CForm();
		$r_form->setMethod('get');

		$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
		$cmbHosts = new CComboBox('hostid',$PAGE_HOSTS['selected'],'javascript: submit();');

		foreach($PAGE_GROUPS['groups'] as $groupid => $name){
			$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid).$name);
		}
		foreach($PAGE_HOSTS['hosts'] as $hostid => $name){
			$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid).$name);
		}

		$r_form->addItem(array(S_GROUP.SPACE,$cmbGroups));
		$r_form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));

		$row_count = 0;
		$numrows = new CSpan(null,'info');
		$numrows->addOption('name','numrows');
		$header = get_table_header(array(S_GRAPHS_BIG,
						new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),
						S_FOUND.': ',$numrows,)
						);							
		show_table_header($header, $r_form);

/* TABLE */
		$form = new CForm();
		$form->setName('graphs');
		$form->addVar('hostid',$_REQUEST['hostid']);

		$table = new CTableInfo(S_NO_GRAPHS_DEFINED);
		$table->setHeader(array(
			$_REQUEST['hostid'] != 0 ? NULL : S_HOSTS,
			array(	new CCheckBox('all_graphs',NULL,"CheckAll('".$form->GetName()."','all_graphs');"),
				make_sorting_link(S_NAME,'g.name')),
			make_sorting_link(S_WIDTH,'g.width'),
			make_sorting_link(S_HEIGHT,'g.height'),
			make_sorting_link(S_GRAPH_TYPE,'g.graphtype')));

		$sql_from = '';
		$sql_where = '';
		if($PAGE_HOSTS['selected'] > 0)
			$sql_where.= ' AND i.hostid='.$PAGE_HOSTS['selected'];

		$sql = 'SELECT DISTINCT g.* '.
				' FROM graphs g, graphs_items gi,items i '.$sql_from.
				' WHERE '.DBcondition('g.graphid',$available_graphs).
					' AND gi.graphid=g.graphid '.
					' AND i.itemid=gi.itemid '.
					$sql_where.
				order_by('g.name,g.width,g.height,g.graphtype','g.graphid');
		$result = DBselect($sql);
		while($row=DBfetch($result)){
			if($_REQUEST['hostid'] != 0){
				$host_list = NULL;
			}
			else{
				$host_list = array();
				$db_hosts = get_hosts_by_graphid($row['graphid']);
				while($db_host = DBfetch($db_hosts)){
					array_push($host_list, $db_host['host']);
				}
				$host_list = implode(',',$host_list);
			}

			if($row['templateid']==0){
				$name = new CLink($row['name'],
					'graphs.php?graphid='.$row['graphid'].'&form=update','action');
			}
			else {
				$real_hosts = get_realhosts_by_graphid($row['templateid']);
				$real_host = DBfetch($real_hosts);
				if($real_host){
					$name = array(
						new CLink($real_host['host'],'graphs.php?'.
							'hostid='.$real_host['hostid'],
							'action'),
						':',
						$row['name']
						);
				}
				else{
					array_push($description,
						new CSpan('error','on'),
						':',
						expand_trigger_description($row['triggerid'])
						);
				}
			}

			$chkBox = new CCheckBox('group_graphid['.$row['graphid'].']',NULL,NULL,$row['graphid']);
			if($row['templateid'] > 0) $chkBox->SetEnabled(false);

			switch($row['graphtype']){
				case  GRAPH_TYPE_STACKED:
					$graphtype = S_STACKED;
					break;
				case  GRAPH_TYPE_PIE:
					$graphtype = S_PIE;
					break;
				case  GRAPH_TYPE_EXPLODED:
					$graphtype = S_EXPLODED;
					break;
				default:
					$graphtype = S_NORMAL;
					break;
			}

			$table->addRow(array(
				$host_list,
				array($chkBox, $name),
				$row['width'],
				$row['height'],
				$graphtype
				));
			$row_count++;
		}

		$table->SetFooter(new CCol(array(
			new CButtonQMessage('delete',S_DELETE_SELECTED,S_DELETE_SELECTED_ITEMS_Q),
			SPACE,
			new CButton('form_copy_to',S_COPY_SELECTED_TO)
		)));

		$form->AddItem($table);
		$form->Show();
	}
	
	zbx_add_post_js('insert_in_element("numrows","'.$row_count.'");');
?>
<?php

include_once 'include/page_footer.php';

?>
