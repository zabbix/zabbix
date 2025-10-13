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

$page['file'] = 'chart2.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'graphid' =>		[T_ZBX_INT,			O_MAND, P_SYS,	DB_ID,		null],
	'from' =>			[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,		null],
	'to' =>				[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,		null],
	'profileIdx' =>		[T_ZBX_STR,			O_OPT, null,	null,		null],
	'profileIdx2' =>	[T_ZBX_STR,			O_OPT, null,	null,		null],
	'width' =>			[T_ZBX_INT,			O_OPT, null,	BETWEEN(CLineGraphDraw::GRAPH_WIDTH_MIN, 65535),	null],
	'height' =>			[T_ZBX_INT,			O_OPT, null,	BETWEEN(CLineGraphDraw::GRAPH_HEIGHT_MIN, 65535),	null],
	'outer' =>			[T_ZBX_INT,			O_OPT, null,	IN('0,1'),	null],
	'onlyHeight' =>		[T_ZBX_INT,			O_OPT, null,	IN('0,1'),	null],
	'legend' =>			[T_ZBX_INT,			O_OPT, null,	IN('0,1'),	null],
	'widget_view' =>	[T_ZBX_INT,			O_OPT, null,	IN('0,1'),	null],
	'resolve_macros' =>	[T_ZBX_INT,			O_OPT, null,	IN('0,1'),	null]
];
if (!check_fields($fields)) {
	session_write_close();
	exit();
}
validateTimeSelectorPeriod(getRequest('from'), getRequest('to'));

$resolve_macros = (bool) getRequest('resolve_macros', 0);

/*
 * Permissions
 */
$dbGraph = API::Graph()->get([
	'output' => API_OUTPUT_EXTEND,
	'selectGraphItems' => API_OUTPUT_EXTEND,
	'selectHosts' => ['hostid', 'name', 'host'],
	'selectItems' => ['itemid', 'type', 'master_itemid', $resolve_macros ? 'name_resolved' : 'name', 'delay', 'units',
		'hostid', 'history', 'trends', 'value_type', 'key_'
	],
	'graphids' => getRequest('graphid')
]);

if (!$dbGraph) {
	access_deny();
}

$dbGraph = reset($dbGraph);

if ($resolve_macros) {
	$dbGraph['items'] = CArrayHelper::renameObjectsKeys($dbGraph['items'], ['name_resolved' => 'name']);
}

$hosts = array_column($dbGraph['hosts'], null, 'hostid');
$items = array_column($dbGraph['items'], null, 'itemid');

$db_items = API::Item()->get([
	'output' => [],
	'selectPreprocessing' => ['type', 'params'],
	'itemids' => array_keys($items),
	'webitems' => true,
	'nopermissions' => true,
	'preservekeys' => true
]);

foreach ($items as &$item) {
	$item['preprocessing'] = $db_items[$item['itemid']]['preprocessing'];
}
unset($item);

/*
 * Display
 */
$timeline = getTimeSelectorPeriod([
	'profileIdx' => getRequest('profileIdx'),
	'profileIdx2' => getRequest('profileIdx2'),
	'from' => getRequest('from'),
	'to' => getRequest('to')
]);

$graph = new CLineGraphDraw($dbGraph['graphtype']);

if (getRequest('widget_view') === '1') {
	$graph->draw_header = false;
	$graph->with_vertical_padding = false;
}

// array sorting
CArrayHelper::sort($dbGraph['gitems'], [
	['field' => 'sortorder', 'order' => ZBX_SORT_UP],
	['field' => 'itemid', 'order' => ZBX_SORT_DOWN]
]);

foreach ($dbGraph['gitems'] as $graph_item) {
	$item = $items[$graph_item['itemid']];
	$host = $hosts[$item['hostid']];

	$graph->addItem($item + [
		'host' => $host['host'],
		'hostname' => $host['name'],
		'color' => $graph_item['color'],
		'drawtype' => $graph_item['drawtype'],
		'yaxisside' => $graph_item['yaxisside'],
		'calc_fnc' => $graph_item['calc_fnc']
	]);
}

$hostName = '';

foreach ($dbGraph['hosts'] as $gItemHost) {
	if ($hostName === '') {
		$hostName = $gItemHost['name'];
	}
	elseif ($hostName !== $gItemHost['name']) {
		$hostName = '';
		break;
	}
}

$graph->setHeader(($hostName === '') ? $dbGraph['name'] : $hostName.NAME_DELIMITER.$dbGraph['name']);
$graph->setPeriod($timeline['to_ts'] - $timeline['from_ts']);
$graph->setSTime($timeline['from_ts']);

$width = getRequest('width', 0);
if ($width <= 0) {
	$width = $dbGraph['width'];
}

$height = getRequest('height', 0);
if ($height <= 0) {
	$height = $dbGraph['height'];
}

$graph->showLegend(getRequest('legend', $dbGraph['show_legend']));
$graph->showWorkPeriod($dbGraph['show_work_period']);
$graph->showTriggers($dbGraph['show_triggers']);
$graph->setWidth($width);
$graph->setHeight($height);
$graph->setYMinAxisType($dbGraph['ymin_type']);
$graph->setYMaxAxisType($dbGraph['ymax_type']);
$graph->setYAxisMin($dbGraph['yaxismin']);
$graph->setYAxisMax($dbGraph['yaxismax']);

$yaxis_items = array_intersect_key($dbGraph, array_filter([
	'ymin_itemid' => $dbGraph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $dbGraph['ymin_itemid'] != 0,
	'ymax_itemid' => $dbGraph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $dbGraph['ymax_itemid'] != 0
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

$graph->setLeftPercentage($dbGraph['percent_left']);
$graph->setRightPercentage($dbGraph['percent_right']);

if (hasRequest('outer')) {
	$graph->setOuter(getRequest('outer'));
}

$min_dimensions = $graph->getMinDimensions();
if ($min_dimensions['width'] > $graph->getWidth()) {
	$graph->setWidth($min_dimensions['width']);
}
if ($min_dimensions['height'] > $graph->getHeight()) {
	$graph->setHeight($min_dimensions['height']);
}

if (getRequest('onlyHeight', '0') === '1') {
	$graph->drawDimensions();
	header('X-ZBX-SBOX-HEIGHT: '.($graph->getHeight() + 1));
}
else {
	$graph->draw();
}

require_once dirname(__FILE__).'/include/page_footer.php';
