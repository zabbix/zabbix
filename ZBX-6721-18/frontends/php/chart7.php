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
require_once('include/graphs.inc.php');

$page['file']	= 'chart7.php';
// $page['title']	= "S_CHART";
$page['type']	= PAGE_TYPE_IMAGE;

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'period'=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	BETWEEN(ZBX_MIN_PERIOD,ZBX_MAX_PERIOD),	null),
		'from'=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	null,			null),
		'stime'=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	null,			null),
		'border'=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	IN('0,1'),		null),
		'name'=>	array(T_ZBX_STR, O_OPT,	NULL,		null,			null),
		'width'=>	array(T_ZBX_INT, O_OPT,	NULL,		BETWEEN(0,65535),	null),
		'height'=>	array(T_ZBX_INT, O_OPT,	NULL,		BETWEEN(0,65535),	null),
		'graphtype'=>	array(T_ZBX_INT, O_OPT,	NULL,		IN('2,3'),		null),
		'graph3d'=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	IN('0,1'),		null),
		'legend'=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	IN('0,1'),		null),
		'items'=>	array(T_ZBX_STR, O_OPT,	NULL,		null,			null)
	);

	check_fields($fields);
?>
<?php

	$items = get_request('items', array());
	asort_by_key($items, 'sortorder');

	$options = array(
		'webitems' => 1,
		'itemids' => zbx_objectValues($items, 'itemid'),
		'nodeids' => get_current_nodeid(true)
	);

	$db_data = CItem::get($options);
	$db_data = zbx_toHash($db_data, 'itemid');
	foreach($items as $id => $gitem){
		if(!isset($db_data[$gitem['itemid']])) access_deny();
	}

	$effectiveperiod = navigation_bar_calc();

	$graph = new CPie(get_request('graphtype', GRAPH_TYPE_NORMAL));
	$graph->setHeader(get_request('name', ''));

	$graph3d = get_request('graph3d',0);
	$legend = get_request('legend',0);

	if($graph3d == 1) $graph->switchPie3D();
	$graph->showLegend($legend);

	unset($host);

	if(isset($_REQUEST['period']))		$graph->SetPeriod($_REQUEST['period']);
	if(isset($_REQUEST['from']))		$graph->SetFrom($_REQUEST['from']);
	if(isset($_REQUEST['stime']))		$graph->SetSTime($_REQUEST['stime']);
	if(isset($_REQUEST['border']))		$graph->SetBorder(0);

	$graph->SetWidth(get_request('width',		400));
	$graph->SetHeight(get_request('height',		300));

	foreach($items as $id => $gitem){
//		SDI($gitem);
		$graph->addItem(
			$gitem['itemid'],
			$gitem['calc_fnc'],
			$gitem['color'],
			$gitem['type'],
			$gitem['periods_cnt']
			);

//		unset($items[$id]);
	}
	$graph->Draw();
?>
<?php

include_once('include/page_footer.php');

?>
