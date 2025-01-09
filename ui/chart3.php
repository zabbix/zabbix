<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/graphs.inc.php';

$page['file'] = 'chart3.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'from' =>			[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,		null,				null],
	'to' =>				[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,		null,				null],
	'profileIdx' =>		[T_ZBX_STR,			O_OPT, null,		null,				null],
	'profileIdx2' =>	[T_ZBX_STR,			O_OPT, null,		null,				null],
	'httptestid' =>		[T_ZBX_INT,			O_OPT, P_NZERO,		null,				null],
	'http_item_type' =>	[T_ZBX_INT,			O_OPT, null,		null,				null],
	'name' =>			[T_ZBX_STR,			O_OPT, null,		null,				null],
	'width' =>			[T_ZBX_INT,			O_OPT, null,	BETWEEN(CLineGraphDraw::GRAPH_WIDTH_MIN, 65535),	null],
	'height' =>			[T_ZBX_INT,			O_OPT, null,	BETWEEN(CLineGraphDraw::GRAPH_HEIGHT_MIN, 65535),	null],
	'ymin_type' =>		[T_ZBX_INT,			O_OPT, null,		IN('0,1,2'),		null],
	'ymax_type' =>		[T_ZBX_INT,			O_OPT, null,		IN('0,1,2'),		null],
	'ymin_itemid' =>	[T_ZBX_INT,			O_OPT, null,		DB_ID,				null],
	'ymax_itemid' =>	[T_ZBX_INT,			O_OPT, null,		DB_ID,				null],
	'legend' =>			[T_ZBX_INT,			O_OPT, null,		IN('0,1'),			null],
	'showworkperiod' =>	[T_ZBX_INT,			O_OPT, null,		IN('0,1'),			null],
	'showtriggers' =>	[T_ZBX_INT,			O_OPT, null,		IN('0,1'),			null],
	'graphtype' =>		[T_ZBX_INT,			O_OPT, null,		IN('0,1'),			null],
	'yaxismin' =>		[T_ZBX_DBL,			O_OPT, null,		null,				null],
	'yaxismax' =>		[T_ZBX_DBL,			O_OPT, null,		null,				null],
	'percent_left' =>	[T_ZBX_DBL,			O_OPT, null,		BETWEEN_DBL(0, 100, 4),	null],
	'percent_right' =>	[T_ZBX_DBL,			O_OPT, null,		BETWEEN_DBL(0, 100, 4),	null],
	'outer' =>			[T_ZBX_INT,			O_OPT, null,		IN('0,1'),			null],
	'items' =>			[T_ZBX_STR,			O_OPT, P_ONLY_TD_ARRAY,	null,			null],
	'i' =>				[T_ZBX_STR,			O_OPT, P_ONLY_ARRAY,	null,			null],
	'onlyHeight' =>		[T_ZBX_INT,			O_OPT, null,		IN('0,1'),			null],
	'widget_view' =>	[T_ZBX_INT,			O_OPT, null,		IN('0,1'),			null],
	'resolve_macros' =>	[T_ZBX_INT,			O_OPT, null,		IN('0,1'),			null]
];
if (!check_fields($fields)) {
	session_write_close();
	exit();
}
validateTimeSelectorPeriod(getRequest('from'), getRequest('to'));

$graph_items = [];

if ($httptestid = getRequest('httptestid', false)) {
	$httptests = API::HttpTest()->get([
		'output' => [],
		'httptestids' => $httptestid,
		'selectHosts' => ['hostid', 'name', 'host']
	]);

	if (!$httptests) {
		access_deny();
	}

	$colors = ['Red', 'Dark Green', 'Blue', 'Dark Yellow', 'Cyan', 'Gray', 'Dark Red', 'Green', 'Dark Blue', 'Yellow',
		'Black'
	];
	$color = false;
	$items = [];
	$hosts = zbx_toHash($httptests[0]['hosts'], 'hostid');

	$dbItems = DBselect(
		'SELECT i.itemid,i.type,ir.name_resolved AS name,i.delay,i.units,i.hostid,i.history,i.trends,i.value_type,'.
			'i.key_'.
		' FROM httpstepitem hi,items i,item_rtname ir,httpstep hs'.
		' WHERE i.itemid=hi.itemid'.
			' AND i.itemid=ir.itemid'.
			' AND hs.httptestid='.zbx_dbstr($httptestid).
			' AND hs.httpstepid=hi.httpstepid'.
			' AND hi.type='.zbx_dbstr(getRequest('http_item_type', HTTPSTEP_ITEM_TYPE_TIME)).
		' ORDER BY hs.no DESC'
	);
	while ($item = DBfetch($dbItems)) {
		$graph_items[] = $item + [
			'color' => ($color === false) ? reset($colors) : $color,
			'host' => $hosts[$item['hostid']]['host'],
			'hostname' => $hosts[$item['hostid']]['name']
		];
		$color = next($colors);
	}

	$name = getRequest('name', '');
}
elseif (hasRequest('i') || hasRequest('items')) {
	if (hasRequest('i')) {
		$items = array_map('expandShortGraphItem', getRequest('i'));
	}
	else {
		$items = getRequest('items');
	}

	CArrayHelper::sort($items, ['sortorder']);

	$resolve_macros = (bool) getRequest('resolve_macros', 0);

	$options = [
		'output' => ['itemid', 'type', 'name', 'master_itemid', 'delay', 'units', 'hostid', 'history', 'trends',
			'value_type', 'key_', 'flags'
		],
		'selectHosts' => ['hostid', 'name', 'host'],
		'itemids' => array_column($items, 'itemid'),
		'filter' => [
			'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED]
		],
		'webitems' => true,
		'preservekeys' => true
	];

	if ($resolve_macros) {
		$options['output'][] = 'name_resolved';
	}

	$db_items = API::Item()->get($options);

	if ($resolve_macros) {
		foreach ($db_items as &$item) {
			if ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL || $item['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				$item = CArrayHelper::renameKeys($item, ['name_resolved' => 'name']);
			}
		}
		unset($item);
	}

	foreach ($items as $item) {
		if (!array_key_exists($item['itemid'], $db_items)) {
			access_deny();
		}
		$host = reset($db_items[$item['itemid']]['hosts']);
		$graph_items[] = $db_items[$item['itemid']] + $item + [
			'host' => $host['host'],
			'hostname' => $host['name']
		];
	}

	foreach ($graph_items as &$graph_item) {
		unset($graph_item['hosts']);
	}
	unset($graph_item);

	$name = getRequest('name', '');
}
else {
	show_error_message(_('No items defined.'));
	session_write_close();
	exit();
}

/*
 * Display
 */
$timeline = getTimeSelectorPeriod([
	'profileIdx' => getRequest('profileIdx', 'web.httpdetails.filter'),
	'profileIdx2' => getRequest('httptestid', getRequest('profileIdx2')),
	'from' => getRequest('from'),
	'to' => getRequest('to')
]);

CProfile::update($timeline['profileIdx'].'.httptestid', $timeline['profileIdx2'], PROFILE_TYPE_ID);

$graph = new CLineGraphDraw(getRequest('graphtype', GRAPH_TYPE_NORMAL));
$graph->setHeader($name);
$graph->setPeriod($timeline['to_ts'] - $timeline['from_ts']);
$graph->setSTime($timeline['from_ts']);
$graph->setWidth(getRequest('width', 900));
$graph->setHeight(getRequest('height', 200));
$graph->showLegend(getRequest('legend', 1));
$graph->showWorkPeriod(getRequest('showworkperiod', 1));
$graph->showTriggers(getRequest('showtriggers', 1));
$graph->setYAxisMin(getRequest('yaxismin', 0.00));
$graph->setYAxisMax(getRequest('yaxismax', 100.00));

$yaxis_items = [
	'ymin_type' => getRequest('ymin_type', GRAPH_YAXIS_TYPE_CALCULATED),
	'ymax_type' => getRequest('ymax_type', GRAPH_YAXIS_TYPE_CALCULATED),
	'ymin_itemid' => getRequest('ymin_itemid', 0),
	'ymax_itemid' => getRequest('ymax_itemid', 0)
];

$graph->setYMinAxisType($yaxis_items['ymin_type']);
$graph->setYMaxAxisType($yaxis_items['ymax_type']);

$yaxis_items = array_intersect_key($yaxis_items, array_filter([
	'ymin_itemid' => $yaxis_items['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $yaxis_items['ymin_itemid'] != 0,
	'ymax_itemid' => $yaxis_items['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $yaxis_items['ymax_itemid'] != 0
]));

if ($yaxis_items) {
	$db_items = API::Item()->get([
		'itemids' => array_values($yaxis_items),
		'filter' => ['value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]],
		'webitems' => true,
		'preservekeys' => true
	]);

	if (array_key_exists('ymin_itemid', $yaxis_items) && array_key_exists($yaxis_items['ymin_itemid'], $db_items)) {
		$graph->setYMinItemId($yaxis_items['ymin_itemid']);
	}

	if (array_key_exists('ymax_itemid', $yaxis_items) && array_key_exists($yaxis_items['ymax_itemid'], $db_items)) {
		$graph->setYMaxItemId($yaxis_items['ymax_itemid']);
	}
}

$graph->setLeftPercentage(getRequest('percent_left', 0));
$graph->setRightPercentage(getRequest('percent_right', 0));
$graph->setOuter(getRequest('outer', 0));

if (getRequest('widget_view') === '1') {
	$graph->draw_header = false;
	$graph->with_vertical_padding = false;
}

foreach ($graph_items as $graph_item) {
	$graph->addItem($graph_item);
}

if (getRequest('onlyHeight', '0') === '1') {
	$graph->drawDimensions();
	header('X-ZBX-SBOX-HEIGHT: '.($graph->getHeight() + 1));
}
else {
	$graph->draw();
}

require_once __DIR__.'/include/page_footer.php';
