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

$page['file'] = 'chart.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'type' =>           [T_ZBX_INT,			O_OPT, null,	IN([GRAPH_TYPE_NORMAL, GRAPH_TYPE_STACKED]), null],
	'itemids' =>		[T_ZBX_INT,			O_MAND, P_SYS,	DB_ID,		null],
	'from' =>			[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,		null],
	'to' =>				[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,		null],
	'profileIdx' =>		[T_ZBX_STR,			O_OPT, null,	null,		null],
	'profileIdx2' =>	[T_ZBX_STR,			O_OPT, null,	null,		null],
	'width' =>			[T_ZBX_INT,			O_OPT, null,	BETWEEN(CLineGraphDraw::GRAPH_WIDTH_MIN, 65535),	null],
	'height' =>			[T_ZBX_INT,			O_OPT, null,	BETWEEN(CLineGraphDraw::GRAPH_HEIGHT_MIN, 65535),	null],
	'outer' =>			[T_ZBX_INT,			O_OPT, null,	IN('0,1'),	null],
	'batch' =>			[T_ZBX_INT,			O_OPT, null,	IN('0,1'),	null],
	'onlyHeight' =>		[T_ZBX_INT,			O_OPT, null,	IN('0,1'),	null],
	'legend' =>			[T_ZBX_INT,			O_OPT, null,	IN('0,1'),	null],
	'widget_view' =>	[T_ZBX_INT,			O_OPT, null,	IN('0,1'),	null]
];
if (!check_fields($fields)) {
	exit();
}
validateTimeSelectorPeriod(getRequest('from'), getRequest('to'));

$itemIds = getRequest('itemids');

/*
 * Permissions
 */
$items = API::Item()->get([
	'output' => ['itemid', 'type', 'master_itemid', 'name', 'delay', 'units', 'hostid', 'history', 'trends',
		'value_type', 'key_'
	],
	'selectHosts' => ['name', 'host'],
	'itemids' => $itemIds,
	'webitems' => true,
	'preservekeys' => true
]);
foreach ($itemIds as $itemId) {
	if (!isset($items[$itemId])) {
		access_deny();
	}
}

$hostNames = [];
foreach ($items as &$item) {
	$item['hostname'] = $item['hosts'][0]['name'];
	$item['host'] = $item['hosts'][0]['host'];
	if (!in_array($item['hostname'], $hostNames)) {
		$hostNames[] = $item['hostname'];
	}
}
unset($item);
// sort items
CArrayHelper::sort($items, ['name', 'hostname', 'itemid']);

/*
 * Display
 */
$timeline = getTimeSelectorPeriod([
	'profileIdx' => getRequest('profileIdx'),
	'profileIdx2' => getRequest('profileIdx2'),
	'from' => getRequest('from'),
	'to' => getRequest('to')
]);

$graph = new CLineGraphDraw(getRequest('type'));
$graph->setPeriod($timeline['to_ts'] - $timeline['from_ts']);
$graph->setSTime($timeline['from_ts']);
$graph->showLegend(getRequest('legend', 1));

// change how the graph will be displayed if more than one item is selected
if (getRequest('batch')) {
	// set a default header
	if (count($hostNames) == 1) {
		$graph->setHeader($hostNames[0].NAME_DELIMITER._('Item values'));
	}
	else {
		$graph->setHeader(_('Item values'));
	}

	// hide triggers
	$graph->showTriggers(false);
}

if (hasRequest('width')) {
	$graph->setWidth(getRequest('width'));
}
if (hasRequest('height')) {
	$graph->setHeight(getRequest('height'));
}
if (hasRequest('outer')) {
	$graph->setOuter(getRequest('outer'));
}

if (getRequest('widget_view') === '1') {
	$graph->draw_header = false;
	$graph->with_vertical_padding = false;
}

foreach ($items as $item) {
	$graph->addItem($item + [
		'color' => rgb2hex(get_next_color(1)),
		'yaxisside' => GRAPH_YAXIS_SIDE_DEFAULT,
		'calc_fnc' => (getRequest('batch')) ? CALC_FNC_AVG : CALC_FNC_ALL
	]);
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
	$height = $graph->getHeight() + 1;

	if (getRequest('widget_view') === '1') {
		$height = $height - CLineGraphDraw::DEFAULT_TOP_BOTTOM_PADDING;
	}
	header('X-ZBX-SBOX-HEIGHT: '.$height);
}
else {
	$graph->draw();
}

require_once dirname(__FILE__).'/include/page_footer.php';
