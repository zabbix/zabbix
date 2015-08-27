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


require_once 'include/config.inc.php';
require_once 'include/graphs.inc.php';

$page['file'] = 'chart6.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once 'include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'graphid' =>		array(T_ZBX_INT, O_MAND, P_SYS,		DB_ID,		null),
	'screenid' =>		array(T_ZBX_STR, O_OPT, P_SYS,		null,		null),
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
if (!DBfetch(DBselect('SELECT g.graphid FROM graphs g WHERE g.graphid='.$_REQUEST['graphid']))) {
	show_error_message(_('No graphs defined.'));
}

$db_data = API::Graph()->get(array(
	'graphids' => $_REQUEST['graphid'],
	'selectHosts' => API_OUTPUT_EXTEND,
	'output' => API_OUTPUT_EXTEND
));
if (empty($db_data)) {
	access_deny();
}
else {
	$db_data = reset($db_data);
}

$host = reset($db_data['hosts']);

/*
 * Display
 */
$timeline = CScreenBase::calculateTime(array(
	'profileIdx' => get_request('profileIdx', 'web.screens'),
	'profileIdx2' => get_request('profileIdx2'),
	'updateProfile' => get_request('updateProfile', true),
	'period' => get_request('period'),
	'stime' => get_request('stime')
));

$graph = new CPie($db_data['graphtype']);
$graph->setPeriod($timeline['period']);
$graph->setSTime($timeline['stime']);

if (isset($_REQUEST['border'])) {
	$graph->setBorder(0);
}

$width = get_request('width', 0);
if ($width <= 0) {
	$width = $db_data['width'];
}

$height = get_request('height', 0);
if ($height <= 0) {
	$height = $db_data['height'];
}

$graph->setWidth($width);
$graph->setHeight($height);
$graph->setHeader($host['host'].': '.$db_data['name']);

if ($db_data['show_3d']) {
	$graph->switchPie3D();
}
$graph->showLegend($db_data['show_legend']);

$result = DBselect(
	'SELECT gi.*'.
	' FROM graphs_items gi'.
	' WHERE gi.graphid='.$db_data['graphid'].
	' ORDER BY gi.sortorder,gi.itemid DESC'
);
while ($db_data = DBfetch($result)) {
	$graph->addItem(
		$db_data['itemid'],
		$db_data['calc_fnc'],
		$db_data['color'],
		$db_data['type']
	);
}
$graph->draw();

require_once dirname(__FILE__).'/include/page_footer.php';
