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
require_once('include/graphs.inc.php');

$page['file']	= 'chart3.php';
// $page['title']	= 'S_CHART';
$page['type']	= PAGE_TYPE_IMAGE;

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'period'=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	BETWEEN(ZBX_MIN_PERIOD,ZBX_MAX_PERIOD),	null),
		'stime'=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	null,			null),

		'httptestid'=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	null,			null),
		'http_item_type'=>	array(T_ZBX_INT, O_OPT,	null,	null,			null),

		'name'=>	array(T_ZBX_STR, O_OPT,	NULL,		null,			null),
		'width'=>	array(T_ZBX_INT, O_OPT,	NULL,		BETWEEN(0,65535),	null),
		'height'=>	array(T_ZBX_INT, O_OPT,	NULL,		BETWEEN(0,65535),	null),

		'ymin_type'=>	array(T_ZBX_INT, O_OPT,	NULL,		IN('0,1,2'),		null),
		'ymax_type'=>	array(T_ZBX_INT, O_OPT,	NULL,		IN('0,1,2'),		null),

		'ymin_itemid'=>	array(T_ZBX_INT, O_OPT,	NULL,		DB_ID,	null),
		'ymax_itemid'=>	array(T_ZBX_INT, O_OPT,	NULL,		DB_ID,	null),

		'legend'=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN('0,1'),	NULL),
		'showworkperiod'=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN('0,1'),	NULL),
		'showtriggers'=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN('0,1'),	NULL),

		'graphtype'=>	array(T_ZBX_INT, O_OPT,	NULL,		IN('0,1'),		null),

		'yaxismin'=>	array(T_ZBX_DBL, O_OPT,	NULL,		null,	null),
		'yaxismax'=>	array(T_ZBX_DBL, O_OPT,	NULL,		null,	null),

		'percent_left'=>	array(T_ZBX_DBL, O_OPT,	 NULL,	BETWEEN(0,100),	null),
		'percent_right'=>	array(T_ZBX_DBL, O_OPT,	 NULL,	BETWEEN(0,100),	null),

		'items'=>	array(T_ZBX_STR, O_OPT,	NULL,		null,			null)
	);

	check_fields($fields);
?>
<?php
	if($httptestid = get_request('httptestid', false)){

		$color = array(
			'current' => 0,
			0  => array('next' => '1'),
			1  => array('color' => 'Red', 			'next' => '2'),
			2  => array('color' => 'Dark Green',	'next' => '3'),
			3  => array('color' => 'Blue', 			'next' => '4'),
			4  => array('color' => 'Dark Yellow', 	'next' => '5'),
			5  => array('color' => 'Cyan', 			'next' => '6'),
			6  => array('color' => 'Gray',			'next' => '7'),
			7  => array('color' => 'Dark Red',		'next' => '8'),
			8  => array('color' => 'Green',			'next' => '9'),
			9  => array('color' => 'Dark Blue', 	'next' => '10'),
			10 => array('color' => 'Yellow', 		'next' => '11'),
			11 => array('color' => 'Black',	 		'next' => '1')
		);

		$items = array();
		$sql = 'SELECT i.itemid'.
				' FROM httpstepitem hi, items i, httpstep hs'.
				' WHERE i.itemid=hi.itemid'.
					' AND hs.httptestid='.$httptestid.
					' AND hs.httpstepid=hi.httpstepid'.
					' AND hi.type='.get_request('http_item_type', HTTPSTEP_ITEM_TYPE_TIME).
				' ORDER BY hs.no DESC';

		$db_items = DBselect($sql);
		while($item_data = DBfetch($db_items)){
			$item_color = $color[$color['current'] = $color[$color['current']]['next']]['color'];

			$items[] = array(
				'itemid' => $item_data['itemid'],
				'color' => $item_color
			);
		}

		$httptest = get_httptest_by_httptestid($httptestid);
		$graph_name = $httptest['name'];
	}
	else{
		$items = get_request('items', array());
		asort_by_key($items, 'sortorder');

		$options = array(
			'webitems' => 1,
			'itemids' => zbx_objectValues($items, 'itemid'),
			'nodeids' => get_current_nodeid(true),
			'output' => API_OUTPUT_SHORTEN,
		);
		$db_data = CItem::get($options);
		$db_data = zbx_toHash($db_data, 'itemid');
		foreach($items as $id => $gitem){
			if(!isset($db_data[$gitem['itemid']])) access_deny();
		}
		$graph_name = get_request('name', '');
	}

	$graph = new CChart(get_request('graphtype', GRAPH_TYPE_NORMAL));
	$graph->setHeader($graph_name);

	navigation_bar_calc();

//SDI($_REQUEST['stime']);
	$graph->setPeriod($_REQUEST['period']);
	$graph->setSTime($_REQUEST['stime']);

	$graph->setWidth(get_request('width',		900));
	$graph->setHeight(get_request('height',		200));

//	$graph->showLegend(get_request('legend'	,1));

	$graph->showWorkPeriod(get_request('showworkperiod'	,1));
	$graph->showTriggers(get_request('showtriggers'		,1));

	$graph->setYMinAxisType(get_request('ymin_type'		,GRAPH_YAXIS_TYPE_CALCULATED));
	$graph->setYMaxAxisType(get_request('ymax_type'		,GRAPH_YAXIS_TYPE_CALCULATED));

	$graph->setYAxisMin(get_request('yaxismin'		,0.00));
	$graph->setYAxisMax(get_request('yaxismax'		,100.00));

	$graph->setYMinItemId(get_request('ymin_itemid'		,0));
	$graph->setYMaxItemId(get_request('ymax_itemid'		,0));

	$graph->setLeftPercentage(get_request('percent_left',0));
	$graph->setRightPercentage(get_request('percent_right',0));

	foreach($items as $gnum => $gitem){
		$graph->addItem(
			$gitem['itemid'],
			isset($gitem['yaxisside'])?$gitem['yaxisside']:null,
			isset($gitem['calc_fnc'])?$gitem['calc_fnc']:null,
			isset($gitem['color'])?$gitem['color']:null,
			isset($gitem['drawtype'])?$gitem['drawtype']:null,
			isset($gitem['type'])?$gitem['type']:null,
			isset($gitem['periods_cnt'])?$gitem['periods_cnt']:null
			);

		unset($items[$gnum]);
	}
	$graph->draw();


include_once('include/page_footer.php');
?>
