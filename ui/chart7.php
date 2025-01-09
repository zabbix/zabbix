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

$page['file'] = 'chart7.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'from' =>			[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,				null],
	'to' =>				[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,				null],
	'profileIdx' =>		[T_ZBX_STR,			O_OPT, null,	null,				null],
	'profileIdx2' =>	[T_ZBX_STR,			O_OPT, null,	null,				null],
	'name' =>			[T_ZBX_STR,			O_OPT, null,	null,				null],
	'width' =>			[T_ZBX_INT,			O_OPT, null,	BETWEEN(CPieGraphDraw::GRAPH_WIDTH_MIN, 65535),		null],
	'height' =>			[T_ZBX_INT,			O_OPT, null,	BETWEEN(CPieGraphDraw::GRAPH_HEIGHT_MIN, 65535),	null],
	'graphtype' =>		[T_ZBX_INT,			O_OPT, null,	IN('2,3'),			null],
	'graph3d' =>		[T_ZBX_INT,			O_OPT, P_NZERO,	IN('0,1'),			null],
	'legend' =>			[T_ZBX_INT,			O_OPT, null,	IN('0,1'),			null],
	'i' =>				[T_ZBX_STR,			O_OPT, P_ONLY_ARRAY,	null,		null],
	'items' =>			[T_ZBX_STR,			O_OPT, P_ONLY_TD_ARRAY,	null,		null],
	'widget_view' =>	[T_ZBX_INT,			O_OPT, null,	IN('0,1'),			null],
	'resolve_macros' =>	[T_ZBX_INT,			O_OPT, null,	IN('0,1'),			null]
];
if (!check_fields($fields)) {
	session_write_close();
	exit();
}
validateTimeSelectorPeriod(getRequest('from'), getRequest('to'));

$items = hasRequest('i')
	? array_map('expandShortGraphItem', getRequest('i'))
	: getRequest('items', []);

if (!$items) {
	show_error_message(_('No items defined.'));
	session_write_close();
	exit();
}

CArrayHelper::sort($items, ['sortorder']);

/*
 * Permissions
 */
$db_items = API::Item()->get([
	'output' => ['value_type'],
	'itemids' => array_column($items, 'itemid'),
	'filter' => [
		'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED]
	],
	'webitems' => true,
	'preservekeys' => true
]);

foreach ($items as $item) {
	if (!array_key_exists('itemid', $item) || !array_key_exists($item['itemid'], $db_items)) {
		access_deny();
	}
}

/*
 * Validation
 */
$types = [];
foreach ($items as $item) {
	if ($item['type'] == GRAPH_ITEM_SUM) {
		if (!in_array($item['type'], $types)) {
			array_push($types, $item['type']);
		}
		else {
			show_error_message(_('Cannot display more than one item with type "Graph sum".'));
			break;
		}
	}
}

/*
 * Display
 */
$timeline = getTimeSelectorPeriod([
	'profileIdx' => getRequest('profileIdx'),
	'profileIdx2' => getRequest('profileIdx2'),
	'from' => getRequest('from'),
	'to' => getRequest('to')
]);

$graph = new CPieGraphDraw(getRequest('graphtype', GRAPH_TYPE_NORMAL));
$graph->setHeader(getRequest('name', ''));
$graph->setPeriod($timeline['to_ts'] - $timeline['from_ts']);
$graph->setSTime($timeline['from_ts']);

if (!empty($_REQUEST['graph3d'])) {
	$graph->switchPie3D();
}

if (getRequest('widget_view') === '1') {
	$graph->draw_header = false;
	$graph->with_vertical_padding = false;
}

$graph->showLegend(getRequest('legend', 0));
$graph->setWidth(getRequest('width', 400));
$graph->setHeight(getRequest('height', 300));

$resolve_macros = (bool) getRequest('resolve_macros', 0);

foreach ($items as $item) {
	if ($db_items[$item['itemid']]['value_type'] != ITEM_VALUE_TYPE_BINARY) {
		$graph->addItem($item['itemid'], $resolve_macros, $item['calc_fnc'], $item['color'], $item['type']);
	}
}
$graph->draw();

require_once dirname(__FILE__).'/include/page_footer.php';
