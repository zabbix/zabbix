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
$fields = [
	'period' =>			[T_ZBX_INT, O_OPT, P_NZERO,	BETWEEN(ZBX_MIN_PERIOD, ZBX_MAX_PERIOD), null],
	'stime' =>			[T_ZBX_INT, O_OPT, P_NZERO,	null,				null],
	'profileIdx' =>		[T_ZBX_STR, O_OPT, null,		null,				null],
	'profileIdx2' =>	[T_ZBX_STR, O_OPT, null,		null,				null],
	'httptestid' =>		[T_ZBX_INT, O_OPT, P_NZERO,	null,				null],
	'http_item_type' =>	[T_ZBX_INT, O_OPT, null,		null,				null],
	'name' =>			[T_ZBX_STR, O_OPT, null,		null,				null],
	'width' =>			[T_ZBX_INT, O_OPT, null,		BETWEEN(20, 65535),	null],
	'height' =>			[T_ZBX_INT, O_OPT, null,		BETWEEN(0, 65535),	null],
	'ymin_type' =>		[T_ZBX_INT, O_OPT, null,		IN('0,1,2'),		null],
	'ymax_type' =>		[T_ZBX_INT, O_OPT, null,		IN('0,1,2'),		null],
	'ymin_itemid' =>	[T_ZBX_INT, O_OPT, null,		DB_ID,				null],
	'ymax_itemid' =>	[T_ZBX_INT, O_OPT, null,		DB_ID,				null],
	'legend' =>			[T_ZBX_INT, O_OPT, null,		IN('0,1'),			null],
	'showworkperiod' =>	[T_ZBX_INT, O_OPT, null,		IN('0,1'),			null],
	'showtriggers' =>	[T_ZBX_INT, O_OPT, null,		IN('0,1'),			null],
	'graphtype' =>		[T_ZBX_INT, O_OPT, null,		IN('0,1'),			null],
	'yaxismin' =>		[T_ZBX_DBL, O_OPT, null,		null,				null],
	'yaxismax' =>		[T_ZBX_DBL, O_OPT, null,		null,				null],
	'percent_left' =>	[T_ZBX_DBL, O_OPT, null,		BETWEEN(0, 100),	null],
	'percent_right' =>	[T_ZBX_DBL, O_OPT, null,		BETWEEN(0, 100),	null],
	'items' =>			[T_ZBX_STR, O_OPT, null,		null,				null]
];
if (!check_fields($fields)) {
	exit();
}

if ($httptestid = getRequest('httptestid', false)) {
	if (!API::HttpTest()->isReadable([$_REQUEST['httptestid']])) {
		access_deny();
	}

	$color = [
		'current' => 0,
		0 => ['next' => '1'],
		1 => ['color' => 'Red', 'next' => '2'],
		2 => ['color' => 'Dark Green', 'next' => '3'],
		3 => ['color' => 'Blue', 'next' => '4'],
		4 => ['color' => 'Dark Yellow', 'next' => '5'],
		5 => ['color' => 'Cyan', 'next' => '6'],
		6 => ['color' => 'Gray', 'next' => '7'],
		7 => ['color' => 'Dark Red', 'next' => '8'],
		8 => ['color' => 'Green', 'next' => '9'],
		9 => ['color' => 'Dark Blue', 'next' => '10'],
		10 => ['color' => 'Yellow', 'next' => '11'],
		11 => ['color' => 'Black', 'next' => '1']
	];

	$items = [];

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

		$items[] = ['itemid' => $item['itemid'], 'color' => $itemColor];
	}

	$name = getRequest('name', '');
}
elseif ($items = getRequest('items', [])) {
	asort_by_key($items, 'sortorder');

	$dbItems = API::Item()->get([
		'itemids' => zbx_objectValues($items, 'itemid'),
		'output' => ['itemid'],
		'filter' => [
			'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED]
		],
		'webitems' => true,
		'preservekeys' => true
	]);

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

$timeline = CScreenBase::calculateTime([
	'profileIdx' => $profileIdx,
	'profileIdx2' => $profileIdx2,
	'period' => getRequest('period'),
	'stime' => getRequest('stime')
]);

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
