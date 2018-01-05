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

$page['file'] = 'chart7.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'period' =>			[T_ZBX_INT, O_OPT, P_NZERO,	BETWEEN(ZBX_MIN_PERIOD, ZBX_MAX_PERIOD), null],
	'stime' =>			[T_ZBX_INT, O_OPT, P_NZERO,	null,				null],
	'isNow' =>			[T_ZBX_INT, O_OPT, null,	IN('0,1'),			null],
	'profileIdx' =>		[T_ZBX_STR, O_OPT, null,	null,				null],
	'profileIdx2' =>	[T_ZBX_STR, O_OPT, null,	null,				null],
	'updateProfile' =>	[T_ZBX_STR, O_OPT, null,	null,				null],
	'name' =>			[T_ZBX_STR, O_OPT, null,	null,				null],
	'width' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(20, 65535),	null],
	'height' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535),	null],
	'graphtype' =>		[T_ZBX_INT, O_OPT, null,	IN('2,3'),			null],
	'graph3d' =>		[T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),			null],
	'legend' =>			[T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),			null],
	'items' =>			[T_ZBX_STR, O_OPT, null,	null,				null]
];
if (!check_fields($fields)) {
	exit();
}

$items = getRequest('items', []);
CArrayHelper::sort($items, ['sortorder']);

/*
 * Permissions
 */
$dbItems = API::Item()->get([
	'itemids' => zbx_objectValues($items, 'itemid'),
	'filter' => [
		'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED]
	],
	'output' => ['itemid'],
	'webitems' => true,
	'preservekeys' => true
]);

foreach ($items as $item) {
	if (!isset($dbItems[$item['itemid']])) {
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
$timeline = calculateTime([
	'profileIdx' => getRequest('profileIdx', 'web.screens'),
	'profileIdx2' => getRequest('profileIdx2'),
	'updateProfile' => (getRequest('updateProfile', '0') === '1'),
	'period' => getRequest('period'),
	'stime' => getRequest('stime'),
	'isNow' => getRequest('isNow')
]);

$graph = new CPieGraphDraw(getRequest('graphtype', GRAPH_TYPE_NORMAL));
$graph->setHeader(getRequest('name', ''));
$graph->setPeriod($timeline['period']);
$graph->setSTime($timeline['stime']);

if (!empty($_REQUEST['graph3d'])) {
	$graph->switchPie3D();
}
$graph->showLegend(getRequest('legend', 0));
$graph->setWidth(getRequest('width', 400));
$graph->setHeight(getRequest('height', 300));

foreach ($items as $item) {
	$graph->addItem($item['itemid'], $item['calc_fnc'], $item['color'], $item['type']);
}
$graph->draw();

require_once dirname(__FILE__).'/include/page_footer.php';
