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
require_once('include/graphs.inc.php');
require_once('include/forms.inc.php');

$page['title'] = 'S_CONFIGURATION_OF_GRAPHS';
$page['file'] = 'graph_prototypes.php';
$page['hist_arg'] = array();
$page['scripts'] = array();

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields = array(
		'parent_discoveryid'=>	array(T_ZBX_INT, O_MAND,	 P_SYS,	DB_ID,	NULL),

		'graphid'=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,			'(isset({form})&&({form}=="update"))'),
		'name'=>	array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,		'isset({save}) || isset({preview})', S_NAME),
		'width'=>	array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(20,65535),	'isset({save}) || isset({preview})', S_WIDTH.' (min:20, max:65535)'),
		'height'=>	array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(20,65535),	'isset({save}) || isset({preview})', S_HEIGHT.' (min:20, max:65535)'),

		'ymin_type'=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN('0,1,2'),		null),
		'ymax_type'=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN('0,1,2'),		null),
		'graphtype'=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN('0,1,2,3'),		'isset({save}) || isset({preview})'),
		'yaxismin'=>	array(T_ZBX_DBL, O_OPT,	 NULL,	null,	'(isset({save}) || isset({preview}))&&(({graphtype} == 0) || ({graphtype} == 1))'),
		'yaxismax'=>	array(T_ZBX_DBL, O_OPT,	 NULL,	null,	'(isset({save}) || isset({preview}))&&(({graphtype} == 0) || ({graphtype} == 1))'),
		'graph3d'=>		array(T_ZBX_INT, O_OPT,	P_NZERO,	IN('0,1'),		null),
		'legend'=>		array(T_ZBX_INT, O_OPT,	P_NZERO,	IN('0,1'),		null),
		'ymin_itemid'=>	array(T_ZBX_INT, O_OPT,	 NULL,	DB_ID,	'(isset({save}) || isset({preview}))&&isset({ymin_type})&&({ymin_type}==3)'),
		'ymax_itemid'=>	array(T_ZBX_INT, O_OPT,	 NULL,	DB_ID,	'(isset({save}) || isset({preview}))&&isset({ymax_type})&&({ymax_type}==3)'),
		'percent_left'=>	array(T_ZBX_DBL, O_OPT,	 NULL,	BETWEEN(0,100),	null),
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
// Actions
		'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
// form
		'add_item'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete_item'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),

		'preview'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'clone'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
/* other */
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	$dataValid = check_fields($fields);
	validate_sort_and_sortorder('name', ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go', 'none');

// PERMISSIONS
	if(get_request('parent_discoveryid')){
		$options = array(
			'itemids' => $_REQUEST['parent_discoveryid'],
			'output' => API_OUTPUT_EXTEND,
			'editable' => 1
		);
		$discovery_rule = API::DiscoveryRule()->get($options);
		$discovery_rule = reset($discovery_rule);
		if(!$discovery_rule) access_deny();
		$_REQUEST['hostid'] = $discovery_rule['hostid'];
	}
	else{
		access_deny();
	}

?>
<?php
	$_REQUEST['items'] = get_request('items', array());
	$_REQUEST['group_gid'] = get_request('group_gid', array());
	$_REQUEST['graph3d'] = get_request('graph3d', 0);
	$_REQUEST['legend'] = get_request('legend', 0);

// ---- <ACTIONS> ----
	if(isset($_REQUEST['clone']) && isset($_REQUEST['graphid'])){
		unset($_REQUEST['graphid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['save'])){

		$items = get_request('items', array());
		$itemids = array();
		foreach($items as $inum => $gitem){
			$itemids[$gitem['itemid']] = $gitem['itemid'];
		}

		if(!empty($itemids)){
			$options = array(
				'nodeids'=>get_current_nodeid(true),
				'itemids'=>$itemids,
				'webitems'=>1,
				'editable'=>1
			);
			$db_items = API::Item()->get($options);
			$db_items = zbx_toHash($db_items, 'itemid');

			foreach($itemids as $inum => $itemid){
				if(!isset($db_items[$itemid])){
					access_deny();
				}
			}
		}

		if(empty($items)){
			info(S_REQUIRED_ITEMS_FOR_GRAPH);
			$result = false;
		}
		else{
			if(!isset($_REQUEST['ymin_type'])) $_REQUEST['ymin_type'] = 0;
			if(!isset($_REQUEST['ymax_type'])) $_REQUEST['ymax_type'] = 0;

			if(!isset($_REQUEST['yaxismin'])) $_REQUEST['yaxismin'] = 0;
			if(!isset($_REQUEST['yaxismax'])) $_REQUEST['yaxismax'] = 0;

			$showworkperiod	= isset($_REQUEST['showworkperiod']) ? 1:0;
			$showtriggers	= isset($_REQUEST['showtriggers']) ? 1:0;

			$visible = get_request('visible');
			$percent_left  = 0;
			$percent_right = 0;

			if(isset($visible['percent_left']))	$percent_left = get_request('percent_left', 0);
			if(isset($visible['percent_right']))	$percent_right = get_request('percent_right', 0);

			if($_REQUEST['ymin_itemid'] != 0 && $_REQUEST['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE){
				$_REQUEST['yaxismin']=0;
			}

			if($_REQUEST['ymax_itemid'] != 0 && $_REQUEST['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE){
				$_REQUEST['yaxismax']=0;
			}

			$graph = array(
				'name' => $_REQUEST['name'],
				'width' => $_REQUEST['width'],
				'height' => $_REQUEST['height'],
				'ymin_type' => $_REQUEST['ymin_type'],
				'ymax_type' => $_REQUEST['ymax_type'],
				'yaxismin' => $_REQUEST['yaxismin'],
				'yaxismax' => $_REQUEST['yaxismax'],
				'ymin_itemid' => $_REQUEST['ymin_itemid'],
				'ymax_itemid' => $_REQUEST['ymax_itemid'],
				'show_work_period' => get_request('showworkperiod',0),
				'show_triggers' => get_request('showtriggers',0),
				'graphtype' => $_REQUEST['graphtype'],
				'show_legend' => get_request('legend', 0),
				'show_3d' => get_request('graph3d', 0),
				'percent_left' => $percent_left,
				'percent_right' => $percent_right,
				'gitems' => $items,
				'flags' => ZBX_FLAG_DISCOVERY_CHILD,
			);

			if(isset($_REQUEST['graphid'])){
				$graph['graphid'] = $_REQUEST['graphid'];

				$result = API::GraphPrototype()->update($graph);

				if($result){
					add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_GRAPH,'Graph ID ['.$_REQUEST['graphid'].'] Graph ['.$_REQUEST['name'].']');
				}
			}
			else{
				$result = API::GraphPrototype()->create($graph);

				if($result){
					add_audit(AUDIT_ACTION_ADD, AUDIT_RESOURCE_GRAPH, 'Graph ['.$_REQUEST['name'].']');
				}
			}
			if($result){
				unset($_REQUEST['form']);
			}
		}
		if(isset($_REQUEST['graphid'])){
			show_messages($result, S_GRAPH_UPDATED, S_CANNOT_UPDATE_GRAPH);
		}
		else{
			show_messages($result, S_GRAPH_ADDED, S_CANNOT_ADD_GRAPH);
		}
	}
	else if(isset($_REQUEST['delete']) && isset($_REQUEST['graphid'])){
		$result = API::GraphPrototype()->delete($_REQUEST['graphid']);
		if($result){
			unset($_REQUEST['form']);
		}
		show_messages($result, S_GRAPH_DELETED, S_CANNOT_DELETE_GRAPH);
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
			$tmp = $_REQUEST['items'][$_REQUEST['move_up']];

			$_REQUEST['items'][$_REQUEST['move_up']]['sortorder'] = $_REQUEST['items'][$_REQUEST['move_up'] - 1]['sortorder'];
			$_REQUEST['items'][$_REQUEST['move_up'] - 1]['sortorder'] = $tmp['sortorder'];
		}
	}
	else if(isset($_REQUEST['move_down']) && isset($_REQUEST['items'])){
		if(isset($_REQUEST['items'][$_REQUEST['move_down']])){
			$tmp = $_REQUEST['items'][$_REQUEST['move_down']];

			$_REQUEST['items'][$_REQUEST['move_down']]['sortorder'] = $_REQUEST['items'][$_REQUEST['move_down'] + 1]['sortorder'];
			$_REQUEST['items'][$_REQUEST['move_down'] + 1]['sortorder'] = $tmp['sortorder'];
		}
	}
//------ GO -------
	else if(($_REQUEST['go'] == 'delete') && isset($_REQUEST['group_graphid'])){
		$go_result = API::GraphPrototype()->delete($_REQUEST['group_graphid']);
		show_messages($go_result, S_GRAPHS_DELETED, S_CANNOT_DELETE_GRAPHS);
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}
// ----</ACTIONS>----
?>
<?php

	if(!isset($_REQUEST['form'])){
		$form = new CForm(null, 'get');
		$form->cleanItems();
		$form->addItem(new CSubmit('form', S_CREATE_GRAPH));
		$form->addVar('parent_discoveryid', $_REQUEST['parent_discoveryid']);
	}
	else
		$form = null;

	show_table_header(S_CONFIGURATION_OF_GRAPHS_PROTOTYPES_BIG, $form);


	if(isset($_REQUEST['form'])){
		insert_graph_form();

		echo SBR;
		$table = new CTable(NULL,'graph');
		if(($_REQUEST['graphtype'] == GRAPH_TYPE_PIE || $_REQUEST['graphtype'] == GRAPH_TYPE_EXPLODED) && $dataValid){
			$table->addRow(new CImg('chart7.php?period=3600'.url_param('name').
					url_param('legend').url_param('graph3d').url_param('width').
					url_param('height').url_param('graphtype').url_param('items')));
		}
		else if($dataValid){
			$table->addRow(new CImg('chart3.php?period=3600'.url_param('name').url_param('width').url_param('height').
				url_param('ymin_type').url_param('ymax_type').url_param('yaxismin').url_param('yaxismax').
				url_param('ymin_itemid').url_param('ymax_itemid').
				url_param('showworkperiod').url_param('legend').url_param('showtriggers').url_param('graphtype').
				url_param('percent_left').url_param('percent_right').url_param('items')));
		}
		$table->show();
	}
	else{
/* Table HEADER */
		$graphs_wdgt = new CWidget();

		if(isset($_REQUEST['graphid']) && ($_REQUEST['graphid']==0)){
			unset($_REQUEST['graphid']);
		}

		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');

		$graphs_wdgt->addHeader(array(S_GRAPH_PROTOTYPES_OF_BIG.SPACE, new CSpan($discovery_rule['description'], 'discoveryName')));
		$graphs_wdgt->addHeader($numrows);

// Header Host
		$graphs_wdgt->addItem(get_header_host_table($_REQUEST['hostid']));

/* TABLE */
		$form = new CForm();
		$form->setName('graphs');
		$form->addVar('parent_discoveryid', $_REQUEST['parent_discoveryid']);

		$table = new CTableInfo(S_NO_GRAPHS_DEFINED);
		$table->setHeader(array(
			new CCheckBox('all_graphs',NULL,"checkAll('".$form->getName()."','all_graphs','group_graphid');"),
			make_sorting_header(S_NAME, 'name'),
			S_WIDTH,
			S_HEIGHT,
			make_sorting_header(S_GRAPH_TYPE, 'graphtype')
		));

// get Graphs
		$sortfield = getPageSortField('description');
		$sortorder = getPageSortOrder();

		$options = array(
			'discoveryids' => $_REQUEST['parent_discoveryid'],
			'editable' => 1,
			'output' => API_OUTPUT_EXTEND,
//			'sortfield' => $sortfield,
//			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);
		$graphs = API::GraphPrototype()->get($options);

		foreach($graphs as $gnum => $graph){
			$graphs[$gnum]['graphtype'] = graphType($graph['graphtype']);
		}

		order_result($graphs, $sortfield, $sortorder);
		$paging = getPagingLine($graphs);

		foreach($graphs as $gnum => $graph){
			$graphid = $graph['graphid'];

			$name = array();
			if($graph['templateid'] != 0){
				$real_hosts = get_realhosts_by_graphid($graph['templateid']);
				$real_host = DBfetch($real_hosts);
				$tpl_disc_ruleid = get_realrule_by_itemid_and_hostid($_REQUEST['parent_discoveryid'], $real_host['hostid']);
				$name[] = new CLink($real_host['host'], 'graph_prototypes.php?parent_discoveryid='.$tpl_disc_ruleid, 'unknown');
				$name[] = ':'.$graph['name'];
			}
			else{
				$name[] = new CLink($graph['name'], 'graph_prototypes.php?parent_discoveryid='.$_REQUEST['parent_discoveryid'].
						'&graphid='.$graphid.'&form=update');
			}


			$chkBox = new CCheckBox('group_graphid['.$graphid.']', NULL, NULL, $graphid);
			if($graph['templateid'] > 0) $chkBox->setEnabled(false);

			$table->addRow(array(
				$chkBox,
				$name,
				$graph['width'],
				$graph['height'],
				$graph['graphtype']
			));
		}

//----- GO ------
		$goBox = new CComboBox('go');

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_GRAPHS);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CSubmit('goButton',S_GO);
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "group_graphid";');

		$footer = get_table_header(array($goBox, $goButton));
//----

		$form->addItem(array($paging, $table, $paging, $footer));
		$graphs_wdgt->addItem($form);
		$graphs_wdgt->show();
	}

?>
<?php

include_once('include/page_footer.php');

?>
