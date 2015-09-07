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

$page['file'] = 'chart6.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'graphid' =>		array(T_ZBX_INT, O_MAND, P_SYS,		DB_ID,		null),
	'period' =>			array(T_ZBX_INT, O_OPT, P_NZERO,	BETWEEN(ZBX_MIN_PERIOD, ZBX_MAX_PERIOD), null),
	'stime' =>			array(T_ZBX_STR, O_OPT, P_SYS,		null,		null),
	'profileIdx' =>		array(T_ZBX_STR, O_OPT, null,		null,		null),
	'profileIdx2' =>	array(T_ZBX_STR, O_OPT, null,		null,		null),
	'updateProfile' =>	array(T_ZBX_STR, O_OPT, null,		null,		null),
	'border' =>			array(T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),	null),
	'width' =>			array(T_ZBX_INT, O_OPT, P_NZERO,	BETWEEN(20, 65535),	null),
	'height' =>			array(T_ZBX_INT, O_OPT, P_NZERO,	'{}>0',		null),
	'graph3d' =>		array(T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),	null),
	'legend' =>			array(T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),	null)
);
if (!check_fields($fields)) {
	exit();
}

/*
 * Permissions
 */
$dbGraph = API::Graph()->get(array(
	'output' => API_OUTPUT_EXTEND,
	'selectGraphItems' => array('itemid', 'calc_fnc', 'color', 'type'),
	'selectHosts' => array('name'),
	'graphids' => $_REQUEST['graphid']
));

if (!$dbGraph) {
	access_deny();
}
else {
	$dbGraph = reset($dbGraph);
}

/*
 * Display
 */
$timeline = CScreenBase::calculateTime(array(
	'profileIdx' => getRequest('profileIdx', 'web.screens'),
	'profileIdx2' => getRequest('profileIdx2'),
	'updateProfile' => getRequest('updateProfile', true),
	'period' => getRequest('period'),
	'stime' => getRequest('stime')
));

$graph = new CPieGraphDraw($dbGraph['graphtype']);
$graph->setPeriod($timeline['period']);
$graph->setSTime($timeline['stime']);

if (isset($_REQUEST['border'])) {
	$graph->setBorder(0);
}

$width = getRequest('width', 0);
if ($width <= 0) {
	$width = $dbGraph['width'];
}

$height = getRequest('height', 0);
if ($height <= 0) {
	$height = $dbGraph['height'];
}

$graph->setWidth($width);
$graph->setHeight($height);

// array sorting
CArrayHelper::sort($dbGraph['gitems'], array(
	array('field' => 'sortorder', 'order' => ZBX_SORT_UP),
	array('field' => 'itemid', 'order' => ZBX_SORT_DOWN)
));

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
$graph->showLegend($dbGraph['show_legend']);
$graph->draw();

require_once dirname(__FILE__).'/include/page_footer.php';
