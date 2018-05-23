<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	'widget_view' =>	[T_ZBX_INT,			O_OPT, null,	IN('0,1'),	null]
];
if (!check_fields($fields)) {
	exit();
}

/*
 * Permissions
 */
$dbGraph = API::Graph()->get([
	'output' => API_OUTPUT_EXTEND,
	'selectGraphItems' => API_OUTPUT_EXTEND,
	'selectHosts' => ['hostid', 'name', 'host'],
	'selectItems' => ['itemid', 'type', 'master_itemid', 'name', 'delay', 'units', 'hostid', 'history', 'trends',
		'value_type', 'key_'
	],
	'graphids' => $_REQUEST['graphid']
]);

if (!$dbGraph) {
	access_deny();
}
else {
	$dbGraph = reset($dbGraph);
}

/*
 * Display
 */
$timeline = calculateTime([
	'profileIdx' => getRequest('profileIdx'),
	'profileIdx2' => getRequest('profileIdx2'),
	'updateProfile' => false,
	'from' => getRequest('from'),
	'to' => getRequest('to')
]);

CProfile::update('web.screens.graphid', $_REQUEST['graphid'], PROFILE_TYPE_ID);

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

$hosts = zbx_toHash($dbGraph['hosts'], 'hostid');
$items = zbx_toHash($dbGraph['items'], 'itemid');

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
$graph->setYMinItemId($dbGraph['ymin_itemid']);
$graph->setYMaxItemId($dbGraph['ymax_itemid']);
$graph->setLeftPercentage($dbGraph['percent_left']);
$graph->setRightPercentage($dbGraph['percent_right']);

if (hasRequest('outer')) {
	$graph->setOuter(getRequest('outer'));
}

$min_dimentions = $graph->getMinDimensions();
if ($min_dimentions['width'] > $graph->getWidth()) {
	$graph->setWidth($min_dimentions['width']);
}
if ($min_dimentions['height'] > $graph->getHeight()) {
	$graph->setHeight($min_dimentions['height']);
}

if (getRequest('onlyHeight', '0') === '1') {
	$graph->drawDimensions();
	$height = $graph->getHeight();

	if (getRequest('widget_view') === '1') {
		$height = $height - CLineGraphDraw::DEFAULT_TOP_BOTTOM_PADDING;
	}
	header('X-ZBX-SBOX-HEIGHT: '.$height);
}
else {
	$graph->draw();
}

require_once dirname(__FILE__).'/include/page_footer.php';
