<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/graphs.inc.php';

$page['file'] = 'chart2.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'graphid' =>	array(T_ZBX_INT, O_MAND, P_SYS,		DB_ID,		null),
	'period' =>		array(T_ZBX_INT, O_OPT, P_NZERO,	BETWEEN(ZBX_MIN_PERIOD, ZBX_MAX_PERIOD), null),
	'stime' =>		array(T_ZBX_STR, O_OPT, P_SYS,		null,		null),
	'border' =>		array(T_ZBX_INT, O_OPT, P_NZERO,	IN('0,1'),	null),
	'width' =>		array(T_ZBX_INT, O_OPT, P_NZERO,	'{}>0',		null),
	'height' =>		array(T_ZBX_INT, O_OPT, P_NZERO,	'{}>0',		null)
);
check_fields($fields);

/*
 * Permissions
 */
if (!DBfetch(DBselect('SELECT g.graphid FROM graphs g WHERE g.graphid='.$_REQUEST['graphid']))) {
	show_error_message(_('No graphs defined.'));
}

$db_data = API::Graph()->get(array(
	'nodeids' => get_current_nodeid(true),
	'graphids' => $_REQUEST['graphid'],
	'output' => API_OUTPUT_EXTEND
));
if (empty($db_data)) {
	access_deny();
}
else {
	$db_data = reset($db_data);
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
navigation_bar_calc();

CProfile::update('web.charts.graphid', $_REQUEST['graphid'], PROFILE_TYPE_ID);

$chart_header = '';
if (id2nodeid($db_data['graphid']) != get_current_nodeid()) {
	$chart_header = get_node_name_by_elid($db_data['graphid'], true, ': ');
}
$chart_header .= $host['name'].': '.$db_data['name'];

$graph = new CChart($db_data['graphtype']);
$graph->setHeader($chart_header);
if (isset($_REQUEST['period'])) {
	$graph->setPeriod($_REQUEST['period']);
}
if (isset($_REQUEST['stime'])) {
	$graph->setSTime($_REQUEST['stime']);
}
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

$graph->showLegend($db_data['show_legend']);
$graph->showWorkPeriod($db_data['show_work_period']);
$graph->showTriggers($db_data['show_triggers']);
$graph->setWidth($width);
$graph->setHeight($height);
$graph->setYMinAxisType($db_data['ymin_type']);
$graph->setYMaxAxisType($db_data['ymax_type']);
$graph->setYAxisMin($db_data['yaxismin']);
$graph->setYAxisMax($db_data['yaxismax']);
$graph->setYMinItemId($db_data['ymin_itemid']);
$graph->setYMaxItemId($db_data['ymax_itemid']);
$graph->setLeftPercentage($db_data['percent_left']);
$graph->setRightPercentage($db_data['percent_right']);

$result = DBselect(
	'SELECT gi.*'.
	' FROM graphs_items gi'.
	' WHERE gi.graphid='.$db_data['graphid'].
	' ORDER BY gi.sortorder, gi.itemid DESC'
);
while ($db_data = DBfetch($result)) {
	$graph->addItem(
		$db_data['itemid'],
		$db_data['yaxisside'],
		$db_data['calc_fnc'],
		$db_data['color'],
		$db_data['drawtype'],
		$db_data['type']
	);
}

$graph->draw();

require_once dirname(__FILE__).'/include/page_footer.php';
?>
