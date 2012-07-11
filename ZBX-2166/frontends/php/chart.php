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

$page['file'] = 'chart.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'itemid' =>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null),
	'period' =>	array(T_ZBX_INT, O_OPT, null,	BETWEEN(ZBX_MIN_PERIOD, ZBX_MAX_PERIOD), null),
	'from' =>	array(T_ZBX_INT, O_OPT, null,	'{}>=0',	null),
	'width' =>	array(T_ZBX_INT, O_OPT, null,	'{}>0',		null),
	'height' =>	array(T_ZBX_INT, O_OPT, null,	'{}>0',		null),
	'border' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'stime' =>	array(T_ZBX_STR, O_OPT, P_SYS,	null,		null)
);
check_fields($fields);

/*
 * Permissions
 */
if (!DBfetch(DBselect('SELECT i.itemid FROM items i WHERE i.itemid='.$_REQUEST['itemid']))) {
	show_error_message(_('No items defined.'));
}

$dbItems = API::Item()->get(array(
	'itemids' => $_REQUEST['itemid'],
	'webitems' => true,
	'nodeids' => get_current_nodeid(true)
));
if (empty($dbItems)) {
	access_deny();
}

/*
 * Display
 */
navigation_bar_calc('web.item.graph', $_REQUEST['itemid']);

$graph = new CChart();
if (isset($_REQUEST['period'])) {
	$graph->setPeriod($_REQUEST['period']);
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
if (isset($_REQUEST['stime'])) {
	$graph->setSTime($_REQUEST['stime']);
}
$graph->addItem($_REQUEST['itemid'], GRAPH_YAXIS_SIDE_DEFAULT, CALC_FNC_ALL);
$graph->draw();

require_once dirname(__FILE__).'/include/page_footer.php';
?>
