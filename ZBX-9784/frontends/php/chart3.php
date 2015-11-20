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
	'profileIdx' =>		array(T_ZBX_STR, O_OPT, null,		null,				null),
	'profileIdx2' =>	array(T_ZBX_STR, O_OPT, null,		null,				null),
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

if ($httptestid = getRequest('httptestid', false)) {
	if (!API::HttpTest()->isReadable(array($_REQUEST['httptestid']))) {
		access_deny();
	}

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
			' AND hs.httptestid='.zbx_dbstr($httptestid).
			' AND hs.httpstepid=hi.httpstepid'.
			' AND hi.type='.zbx_dbstr(getRequest('http_item_type', HTTPSTEP_ITEM_TYPE_TIME)).
		' ORDER BY hs.no DESC'
	);
	while ($item = DBfetch($dbItems)) {
		$itemColor = $color[$color['current'] = $color[$color['current']]['next']]['color'];

		$items[] = array('itemid' => $item['itemid'], 'color' => $itemColor);
	}

	$httpTest = get_httptest_by_httptestid($httptestid);

	$name = CMacrosResolverHelper::resolveHttpTestName($httpTest['hostid'], $httpTest['name']);
}
elseif ($items = getRequest('items', array())) {
	asort_by_key($items, 'sortorder');

	$dbItems = API::Item()->get(array(
		'itemids' => zbx_objectValues($items, 'itemid'),
		'output' => array('itemid'),
		'filter' => array(
			'flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED)
		),
		'webitems' => true,
		'preservekeys' => true
	));

	foreach ($items as $item) {
		if (!isset($dbItems[$item['itemid']])) {
			access_deny();
		}
	}
	$name = getRequest('name', '');
}
else {
	show_error_message(_('No items defined.'));
	exit;
}

/*
 * Display
 */
$profileIdx = getRequest('profileIdx', 'web.httptest');
$profileIdx2 = getRequest('httptestid', getRequest('profileIdx2'));

$timeline = CScreenBase::calculateTime(array(
	'profileIdx' => $profileIdx,
	'profileIdx2' => $profileIdx2,
	'period' => getRequest('period'),
	'stime' => getRequest('stime')
));

CProfile::update($profileIdx.'.httptestid', $profileIdx2, PROFILE_TYPE_ID);

$graph = new CLineGraphDraw(getRequest('graphtype', GRAPH_TYPE_NORMAL));
$graph->setHeader($name);
$graph->setPeriod($timeline['period']);
$graph->setSTime($timeline['stime']);
$graph->setWidth(getRequest('width', 900));
$graph->setHeight(getRequest('height', 200));
$graph->showLegend(getRequest('legend', 1));
$graph->showWorkPeriod(getRequest('showworkperiod', 1));
$graph->showTriggers(getRequest('showtriggers', 1));
$graph->setYMinAxisType(getRequest('ymin_type', GRAPH_YAXIS_TYPE_CALCULATED));
$graph->setYMaxAxisType(getRequest('ymax_type', GRAPH_YAXIS_TYPE_CALCULATED));
$graph->setYAxisMin(getRequest('yaxismin', 0.00));
$graph->setYAxisMax(getRequest('yaxismax', 100.00));
$graph->setYMinItemId(getRequest('ymin_itemid', 0));
$graph->setYMaxItemId(getRequest('ymax_itemid', 0));
$graph->setLeftPercentage(getRequest('percent_left', 0));
$graph->setRightPercentage(getRequest('percent_right', 0));

foreach ($items as $item) {
	$graph->addItem(
		$item['itemid'],
		isset($item['yaxisside']) ? $item['yaxisside'] : null,
		isset($item['calc_fnc']) ? $item['calc_fnc'] : null,
		isset($item['color']) ? $item['color'] : null,
		isset($item['drawtype']) ? $item['drawtype'] : null
	);
}

$graph->draw();

require_once dirname(__FILE__).'/include/page_footer.php';
