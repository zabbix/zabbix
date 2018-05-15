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

$page['file'] = 'chart6.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'graphid' =>		[T_ZBX_INT,			O_MAND, P_SYS,		DB_ID,		null],
	'from' =>			[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,		null,		null],
	'to' =>				[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,		null,		null],
	'profileIdx' =>		[T_ZBX_STR,			O_OPT, null,		null,		null],
	'profileIdx2' =>	[T_ZBX_STR,			O_OPT, null,		null,		null],
	'updateProfile' =>	[T_ZBX_STR,			O_OPT, null,		null,		null],
	'width' =>			[T_ZBX_INT,			O_OPT, P_NZERO,	BETWEEN(20, 65535),	null],
	'height' =>			[T_ZBX_INT,			O_OPT, P_NZERO,	'{} > 0',	null],
	'graph3d' =>		[T_ZBX_INT,			O_OPT, P_NZERO,	IN('0,1'),	null],
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
	'selectGraphItems' => ['itemid', 'sortorder', 'color', 'calc_fnc', 'type'],
	'selectHosts' => ['name'],
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
	'profileIdx' => getRequest('profileIdx', 'web.screens'),
	'profileIdx2' => getRequest('profileIdx2'),
	'updateProfile' => (getRequest('updateProfile', '0') === '1'),
	'from' => getRequest('from'),
	'to' => getRequest('to')
]);

$from = parseRelativeDate($timeline['from'], true);
$to = parseRelativeDate($timeline['to'], false);

if ($from === null || $to === null) {
	$from = parseRelativeDate(ZBX_PERIOD_DEFAULT, true);
	$to = parseRelativeDate('now', false);
}

$from = $from->getTimestamp();
$to = $to->getTimestamp();

$graph = new CPieGraphDraw($dbGraph['graphtype']);
$graph->setPeriod($to - $from);
$graph->setSTime($from);

$width = getRequest('width', 0);
if ($width <= 0) {
	$width = $dbGraph['width'];
}

$height = getRequest('height', 0);
if ($height <= 0) {
	$height = $dbGraph['height'];
}

if (getRequest('widget_view') === '1') {
	$graph->draw_header = false;
	$graph->with_vertical_padding = false;
}

$graph->setWidth($width);
$graph->setHeight($height);

// array sorting
CArrayHelper::sort($dbGraph['gitems'], [
	['field' => 'sortorder', 'order' => ZBX_SORT_UP]
]);

// get graph items
foreach ($dbGraph['gitems'] as $gItem) {
	$graph->addItem(
		$gItem['itemid'],
		$gItem['calc_fnc'],
		$gItem['color'],
		$gItem['type']
	);
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

if ($dbGraph['show_3d']) {
	$graph->switchPie3D();
}
$graph->showLegend(getRequest('legend', $dbGraph['show_legend']));
$graph->draw();

require_once dirname(__FILE__).'/include/page_footer.php';
