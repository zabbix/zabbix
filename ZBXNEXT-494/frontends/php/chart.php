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

$page['file'] = 'chart.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'type' =>           [T_ZBX_INT, O_OPT, null,   IN([GRAPH_TYPE_NORMAL, GRAPH_TYPE_STACKED]), null],
	'itemids' =>		[T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null],
	'period' =>			[T_ZBX_INT, O_OPT, P_NZERO, BETWEEN(ZBX_MIN_PERIOD, ZBX_MAX_PERIOD), null],
	'stime' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'profileIdx' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'profileIdx2' =>	[T_ZBX_STR, O_OPT, null,	null,		null],
	'updateProfile' =>	[T_ZBX_STR, O_OPT, null,	null,		null],
	'from' =>			[T_ZBX_INT, O_OPT, null,	'{} >= 0',	null],
	'width' =>			[T_ZBX_INT, O_OPT, null,	'{} > 0',	null],
	'height' =>			[T_ZBX_INT, O_OPT, null,	'{} > 0',	null],
	'border' =>			[T_ZBX_INT, O_OPT, null,	IN('0,1'),	null],
	'batch' =>			[T_ZBX_INT, O_OPT, null,	IN('0,1'),	null],
];
check_fields($fields);

$itemIds = getRequest('itemids');

/*
 * Permissions
 */
$items = API::Item()->get([
	'output' => ['itemid', 'name'],
	'selectHosts' => ['name'],
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
$timeline = CScreenBase::calculateTime([
	'profileIdx' => getRequest('profileIdx', 'web.screens'),
	'profileIdx2' => getRequest('profileIdx2'),
	'updateProfile' => getRequest('updateProfile', true),
	'period' => getRequest('period'),
	'stime' => getRequest('stime')
]);

$graph = new CLineGraphDraw(getRequest('type'));
$graph->setPeriod($timeline['period']);
$graph->setSTime($timeline['stime']);

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

if (isset($_REQUEST['from'])) {
	$graph->setFrom($_REQUEST['from']);
}
if (isset($_REQUEST['width'])) {
	$graph->setWidth($_REQUEST['width']);
}
if (isset($_REQUEST['height'])) {
	$graph->setHeight($_REQUEST['height']);
}
if (isset($_REQUEST['border'])) {
	$graph->setBorder(0);
}

foreach ($items as $item) {
	$graph->addItem($item['itemid'], GRAPH_YAXIS_SIDE_DEFAULT, (getRequest('batch')) ? CALC_FNC_AVG : CALC_FNC_ALL,
		rgb2hex(get_next_color(1))
	);
}

$graph->draw();

require_once dirname(__FILE__).'/include/page_footer.php';
