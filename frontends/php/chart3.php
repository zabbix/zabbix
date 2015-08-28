<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/graphs.inc.php';

$page['file'] = 'chart3.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'period' =>			array(T_ZBX_INT, O_OPT, P_NZERO,	BETWEEN(ZBX_MIN_PERIOD, ZBX_MAX_PERIOD), null),
	'stime' =>			array(T_ZBX_INT, O_OPT, P_NZERO,	null,				null),
	'httptestid' =>		array(T_ZBX_INT, O_OPT, P_NZERO,	null,				null),
	'http_item_type' =>	array(T_ZBX_INT, O_OPT, null,		null,				null),
	'name' =>			array(T_ZBX_STR, O_OPT, null,		null,				null),
	'width' =>			array(T_ZBX_INT, O_OPT, null,		BETWEEN(20, 65535),	null),
	'height' =>			array(T_ZBX_INT, O_OPT, null,		BETWEEN(0, 65535),	null),
	'ymin_type' =>		array(T_ZBX_INT, O_OPT, null,		IN('0,1,2'),		null),
	'ymax_type' =>		array(T_ZBX_INT, O_OPT, null,		IN('0,1,2'),		null),
	'ymin_itemid' =>	array(T_ZBX_INT, O_OPT, null,		DB_ID,				null),
	'ymax_itemid' =>	array(T_ZBX_INT, O_OPT, null,		DB_ID,				null),
	'legend' =>			array(T_ZBX_INT, O_OPT, null,		IN('0,1'),			null),
	'showworkperiod' =>	array(T_ZBX_INT, O_OPT, null,		IN('0,1'),			null),
	'showtriggers' =>	array(T_ZBX_INT, O_OPT, null,		IN('0,1'),			null),
	'graphtype' =>		array(T_ZBX_INT, O_OPT, null,		IN('0,1'),			null),
	'yaxismin' =>		array(T_ZBX_DBL, O_OPT, null,		null,				null),
	'yaxismax' =>		array(T_ZBX_DBL, O_OPT, null,		null,				null),
	'percent_left' =>	array(T_ZBX_DBL, O_OPT, null,		BETWEEN(0, 100),	null),
	'percent_right' =>	array(T_ZBX_DBL, O_OPT, null,		BETWEEN(0, 100),	null),
	'items' =>			array(T_ZBX_STR, O_OPT, null,		null,				null)
);
if (!check_fields($fields)) {
	exit();
}

if ($httptestid = get_request('httptestid', false)) {
	$color = array(
		'current' => 0,
		0 => array('next' => '1'),
		1 => array('color' => 'Red', 'next' => '2'),
		2 => array('color' => 'Dark Green', 'next' => '3'),
		3 => array('color' => 'Blue', 'next' => '4'),
		4 => array('color' => 'Dark Yellow', 'next' => '5'),
		5 => array('color' => 'Cyan', 'next' => '6'),
		6 => array('color' => 'Gray', 'next' => '7'),
		7 => array('color' => 'Dark Red', 'next' => '8'),
		8 => array('color' => 'Green', 'next' => '9'),
		9 => array('color' => 'Dark Blue', 'next' => '10'),
		10 => array('color' => 'Yellow', 'next' => '11'),
		11 => array('color' => 'Black', 'next' => '1')
	);

	$items = array();

	$dbItems = DBselect(
		'SELECT i.itemid'.
		' FROM httpstepitem hi,items i,httpstep hs'.
		' WHERE i.itemid=hi.itemid'.
			' AND hs.httptestid='.$httptestid.
			' AND hs.httpstepid=hi.httpstepid'.
			' AND hi.type='.get_request('http_item_type', HTTPSTEP_ITEM_TYPE_TIME).
		' ORDER BY hs.no DESC'
	);
	while ($item = DBfetch($dbItems)) {
		$itemColor = $color[$color['current'] = $color[$color['current']]['next']]['color'];

		$items[] = array('itemid' => $item['itemid'], 'color' => $itemColor);
	}

	$httptest = get_httptest_by_httptestid($httptestid);
	$name = $httptest['name'];
}
else {
	$items = get_request('items', array());
	asort_by_key($items, 'sortorder');

	$dbItems = API::Item()->get(array(
		'webitems' => true,
		'itemids' => zbx_objectValues($items, 'itemid'),
		'nodeids' => get_current_nodeid(true),
		'output' => API_OUTPUT_SHORTEN,
		'preservekeys' => true,
		'filter' => array('flags' => null)
	));

	$dbItems = zbx_toHash($dbItems, 'itemid');
	foreach ($items as $item) {
		if (!isset($dbItems[$item['itemid']])) {
			access_deny();
		}
	}
	$name = get_request('name', '');
}

/*
 * Display
 */
$graph = new CChart(get_request('graphtype', GRAPH_TYPE_NORMAL));
$graph->setHeader($name);

navigation_bar_calc();

$graph->setPeriod($_REQUEST['period']);
$graph->setSTime($_REQUEST['stime']);
$graph->setWidth(get_request('width', 900));
$graph->setHeight(get_request('height', 200));
$graph->showLegend(get_request('legend', 1));
$graph->showWorkPeriod(get_request('showworkperiod', 1));
$graph->showTriggers(get_request('showtriggers', 1));
$graph->setYMinAxisType(get_request('ymin_type', GRAPH_YAXIS_TYPE_CALCULATED));
$graph->setYMaxAxisType(get_request('ymax_type', GRAPH_YAXIS_TYPE_CALCULATED));
$graph->setYAxisMin(get_request('yaxismin', 0.00));
$graph->setYAxisMax(get_request('yaxismax', 100.00));
$graph->setYMinItemId(get_request('ymin_itemid', 0));
$graph->setYMaxItemId(get_request('ymax_itemid', 0));
$graph->setLeftPercentage(get_request('percent_left', 0));
$graph->setRightPercentage(get_request('percent_right', 0));

foreach ($items as $inum => $item) {
	$graph->addItem(
		$item['itemid'],
		isset($item['yaxisside']) ? $item['yaxisside'] : null,
		isset($item['calc_fnc']) ? $item['calc_fnc'] : null,
		isset($item['color']) ? $item['color'] : null,
		isset($item['drawtype']) ? $item['drawtype'] : null,
		isset($item['type']) ? $item['type'] : null
	);
	unset($items[$inum]);
}
$graph->draw();

require_once dirname(__FILE__).'/include/page_footer.php';
