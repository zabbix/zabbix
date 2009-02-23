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
	require_once('include/graphs.inc.php');
	
	$page['file']	= 'chart2.php';
	$page['title']	= 'S_CHART';
	$page['type']	= PAGE_TYPE_IMAGE;

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'graphid'=>		array(T_ZBX_INT, O_MAND,	P_SYS,	DB_ID,		null),
		'period'=>		array(T_ZBX_INT, O_OPT,		P_NZERO,	BETWEEN(ZBX_MIN_PERIOD,ZBX_MAX_PERIOD),	null),
		'from'=>		array(T_ZBX_INT, O_OPT,		P_NZERO,	null,		null),
		'stime'=>		array(T_ZBX_STR, O_OPT,		P_SYS,		null,		null),
		'border'=>		array(T_ZBX_INT, O_OPT,		P_NZERO,	IN('0,1'),	null),
		'width'=>		array(T_ZBX_INT, O_OPT,		P_NZERO,	'{}>0',		null),
		'height'=>		array(T_ZBX_INT, O_OPT,		P_NZERO,	'{}>0',		null),
	);

	check_fields($fields);
?>
<?php
	if(!DBfetch(DBselect('SELECT graphid FROM graphs WHERE graphid='.$_REQUEST['graphid']))){
		show_error_message(S_NO_GRAPH_DEFINED);
	}

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_ONLY,PERM_RES_IDS_ARRAY, get_current_nodeid(true));
	
	if(!graph_accessible($_REQUEST['graphid'])){
		access_deny();
	}
	
	$effectiveperiod = navigation_bar_calc();
	
	if(($_REQUEST['graphid']>0) && ($_REQUEST['period'] >= ZBX_MIN_PERIOD)){
		update_profile('web.graph.period',$_REQUEST['period'],PROFILE_TYPE_INT,$_REQUEST['graphid']);
	}
	
	update_profile('web.charts.graphid',$_REQUEST['graphid']);	
		
	$sql = 'SELECT g.*,h.host,h.hostid '.
				' FROM graphs g '.
					' LEFT JOIN graphs_items gi ON g.graphid=gi.graphid '.
					' LEFT JOIN items i ON gi.itemid=i.itemid '.
					' LEFT JOIN hosts h ON i.hostid=h.hostid '.
				' WHERE g.graphid='.$_REQUEST['graphid'].
					' AND '.DBcondition('h.hostid',$available_hosts);
					
	$db_data = DBfetch(DBselect($sql));
	
	$graph = new CChart($db_data['graphtype']);

	$chart_header = '';
	if(id2nodeid($db_data['hostid']) != get_current_nodeid()){
		$chart_header = get_node_name_by_elid($db_data['hostid'], true);
	}
	$chart_header.= $db_data['host'].':'.$db_data['name'];
	$graph->setHeader($chart_header);
	
	if(isset($_REQUEST['period']))		$graph->SetPeriod($_REQUEST['period']);
	if(isset($_REQUEST['from']))		$graph->SetFrom($_REQUEST['from']);
	if(isset($_REQUEST['stime']))		$graph->SetSTime($_REQUEST['stime']);
	if(isset($_REQUEST['border']))		$graph->SetBorder(0);

	$width = get_request('width', 0);

	if($width <= 0) $width = $db_data['width'];

	$height = get_request('height', 0);
	if($height <= 0) $height = $db_data['height'];

	$graph->showWorkPeriod($db_data['show_work_period']);
	$graph->showTriggers($db_data['show_triggers']);

	$graph->setWidth($width);
	$graph->setHeight($height);


	$graph->setYMinAxisType($db_data['ymin_type']);
	$graph->setYMaxAxisType($db_data['ymax_type']);

	$graph->setYAxisMin($db_data['yaxismin']);
	$graph->setYAxisMax($db_data['yaxismax']);
	
	$graph->setYMinItemId($db_data['ymin_itemid']);
	$graph->setYMaxItemId($db_data['ymax_itemid']);

	$graph->setLeftPercentage($db_data['percent_left']);
	$graph->setRightPercentage($db_data['percent_right']);
	
	$result = DBselect('SELECT gi.* '.
		' FROM graphs_items gi '.
		' WHERE gi.graphid='.$db_data['graphid'].
		' ORDER BY gi.sortorder, gi.itemid DESC');

	while($db_data=DBfetch($result)){
		$graph->addItem(
			$db_data['itemid'],
			$db_data['yaxisside'],
			$db_data['calc_fnc'],
			$db_data['color'],
			$db_data['drawtype'],
			$db_data['type'],
			$db_data['periods_cnt']
			);
	}
	$graph->draw();
?>
<?php

include_once('include/page_footer.php');

?>
