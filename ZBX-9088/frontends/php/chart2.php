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

$page['file'] = 'chart2.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

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
	'width' =>			array(T_ZBX_INT, O_OPT, P_NZERO,	'{}>0',		null),
	'height' =>			array(T_ZBX_INT, O_OPT, P_NZERO,	'{}>0',		null)
);
check_fields($fields);

/*
 * Permissions
 */
if (!DBfetch(DBselect('SELECT g.graphid FROM graphs g WHERE g.graphid='.$_REQUEST['graphid']))) {
	show_error_message(_('No graphs defined.'));
}

$dbGraph = API::Graph()->get(array(
	'nodeids' => get_current_nodeid(true),
	'graphids' => $_REQUEST['graphid'],
	'output' => API_OUTPUT_EXTEND
));
if (empty($dbGraph)) {
	access_deny();
}
else {
	$dbGraph = reset($dbGraph);
}

$host = API::Host()->get(array(
	'nodeids' => get_current_nodeid(true),
	'graphids' => $_REQUEST['graphid'],
	'output' => API_OUTPUT_EXTEND,
	'templated_hosts' => true
));
$host = reset($host);

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

CProfile::update('web.screens.graphid', $_REQUEST['graphid'], PROFILE_TYPE_ID);

$chartHeader = '';
if (id2nodeid($dbGraph['graphid']) != get_current_nodeid()) {
	$chartHeader = get_node_name_by_elid($dbGraph['graphid'], true, ': ');
}
$chartHeader .= $host['name'].': '.$dbGraph['name'];

$graph = new CChart($dbGraph['graphtype']);
$graph->setHeader($chartHeader);
$graph->setPeriod($timeline['period']);
$graph->setSTime($timeline['stime']);

if (isset($_REQUEST['border'])) {
	$graph->setBorder(0);
}

$width = get_request('width', 0);
if ($width <= 0) {
	$width = $dbGraph['width'];
}

$height = get_request('height', 0);
if ($height <= 0) {
	$height = $dbGraph['height'];
}

$graph->showLegend($dbGraph['show_legend']);
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

$dbGraphItems = DBselect(
	'SELECT gi.*'.
	' FROM graphs_items gi'.
	' WHERE gi.graphid='.$dbGraph['graphid'].
	' ORDER BY gi.sortorder, gi.itemid DESC'
);
while ($dbGraphItem = DBfetch($dbGraphItems)) {
	$graph->addItem(
		$dbGraphItem['itemid'],
		$dbGraphItem['yaxisside'],
		$dbGraphItem['calc_fnc'],
		$dbGraphItem['color'],
		$dbGraphItem['drawtype'],
		$dbGraphItem['type']
	);
}

$graph->draw();

require_once dirname(__FILE__).'/include/page_footer.php';
