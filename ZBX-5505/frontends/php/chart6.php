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
require_once 'include/config.inc.php';
require_once 'include/graphs.inc.php';

$page['file']	= 'chart6.php';
// $page['title']	= "S_CHART";
$page['type']	= PAGE_TYPE_IMAGE;

include_once 'include/page_header.php';

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'graphid'=>		array(T_ZBX_INT, O_MAND,	P_SYS,	DB_ID,		null),
		'period'=>		array(T_ZBX_INT, O_OPT,		P_NZERO,	BETWEEN(ZBX_MIN_PERIOD,ZBX_MAX_PERIOD),	null),
		'stime'=>		array(T_ZBX_STR, O_OPT,		P_SYS,		null,		null),
		'border'=>		array(T_ZBX_INT, O_OPT,		P_NZERO,	IN('0,1'),	null),
		'width'=>		array(T_ZBX_INT, O_OPT,		P_NZERO,	'{}>0',		null),
		'height'=>		array(T_ZBX_INT, O_OPT,		P_NZERO,	'{}>0',		null),
		'graph3d'=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	IN('0,1'),		null),
		'legend'=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	IN('0,1'),		null)
	);

	check_fields($fields);
?>
<?php
	if(!DBfetch(DBselect('select graphid from graphs where graphid='.$_REQUEST['graphid']))){
		show_error_message(S_NO_GRAPH_DEFINED);
	}

	$options = array(
			'graphids' => $_REQUEST['graphid'],
			'select_hosts' => 1,
			'extendoutput' => 1
		);

	$db_data = CGraph::get($options);
	if(empty($db_data)) access_deny();
	else $db_data = reset($db_data);

	$host = reset($db_data['hosts']);

	$effectiveperiod = navigation_bar_calc();

	$graph = new CPie($db_data['graphtype']);

	if(isset($_REQUEST['period']))		$graph->setPeriod($_REQUEST['period']);
	if(isset($_REQUEST['stime']))		$graph->setSTime($_REQUEST['stime']);
	if(isset($_REQUEST['border']))		$graph->setBorder(0);

	$width = get_request('width', 0);

	if($width <= 0) $width = $db_data['width'];

	$height = get_request('height', 0);
	if($height <= 0) $height = $db_data['height'];

	$graph->setWidth($width);
	$graph->setHeight($height);
	$graph->setHeader($host['host'].':'.$db_data['name']);

	if($db_data['show_3d'] == 1) $graph->switchPie3D();
	$graph->showLegend($db_data['show_legend']);

	$sql = 'SELECT gi.* '.
			' FROM graphs_items gi '.
			' WHERE gi.graphid='.$db_data['graphid'].
			' ORDER BY gi.sortorder, gi.itemid DESC';
	$result = DBselect($sql);
	while($db_data=DBfetch($result)){
		$graph->addItem(
			$db_data['itemid'],
			$db_data['calc_fnc'],
			$db_data['color'],
			$db_data['type'],
			$db_data['periods_cnt']
			);
	}
	$graph->draw();
?>
<?php

include_once('include/page_footer.php');

?>
