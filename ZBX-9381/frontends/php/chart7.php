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

$page['file'] = 'chart7.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'period' =>		array(T_ZBX_INT, O_OPT, P_NZERO,	BETWEEN(ZBX_MIN_PERIOD, ZBX_MAX_PERIOD), null),
	'from' =>		array(T_ZBX_INT, O_OPT, P_NZERO,	null,				null),
	'stime' =>		array(T_ZBX_INT, O_OPT, P_NZERO,	null,				null),
	'border' =>		array(T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),			null),
	'name' =>		array(T_ZBX_STR, O_OPT, null,		null,				null),
	'width' =>		array(T_ZBX_INT, O_OPT, null,		BETWEEN(0, 65535),	null),
	'height' =>		array(T_ZBX_INT, O_OPT, null,		BETWEEN(0, 65535),	null),
	'graphtype' =>	array(T_ZBX_INT, O_OPT, null,		IN('2,3'),			null),
	'graph3d' =>	array(T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),			null),
	'legend' =>		array(T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),			null),
	'items' =>		array(T_ZBX_STR, O_OPT, null,		null,				null)
);
$isDataValid = check_fields($fields);

$items = getRequest('items', array());
asort_by_key($items, 'sortorder');

/*
 * Permissions
 */
$dbItems = API::Item()->get(array(
	'itemids' => zbx_objectValues($items, 'itemid'),
	'filter' => array(
		'flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED)
	),
	'output' => array('itemid'),
	'webitems' => true,
	'preservekeys' => true
));

foreach ($items as $item) {
	if (!isset($dbItems[$item['itemid']])) {
		access_deny();
	}
}

/*
 * Validation
 */
$types = array();
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
if ($isDataValid) {
	navigation_bar_calc();

	$graph = new CPieGraphDraw(getRequest('graphtype', GRAPH_TYPE_NORMAL));
	$graph->setHeader(getRequest('name', ''));

	if (!empty($_REQUEST['graph3d'])) {
		$graph->switchPie3D();
	}
	$graph->showLegend(getRequest('legend', 0));

	if (isset($_REQUEST['period'])) {
		$graph->setPeriod($_REQUEST['period']);
	}
	if (isset($_REQUEST['from'])) {
		$graph->setFrom($_REQUEST['from']);
	}
	if (isset($_REQUEST['stime'])) {
		$graph->setSTime($_REQUEST['stime']);
	}
	if (isset($_REQUEST['border'])) {
		$graph->setBorder(0);
	}
	$graph->setWidth(getRequest('width', 400));
	$graph->setHeight(getRequest('height', 300));

	foreach ($items as $item) {
		$graph->addItem($item['itemid'], $item['calc_fnc'], $item['color'], $item['type']);
	}
	$graph->draw();
}

require_once dirname(__FILE__).'/include/page_footer.php';
