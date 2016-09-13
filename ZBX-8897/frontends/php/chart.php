<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
$fields = array(
	'itemid' =>			array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null),
	'period' =>			array(T_ZBX_INT, O_OPT, P_NZERO, BETWEEN(ZBX_MIN_PERIOD, ZBX_MAX_PERIOD), null),
	'stime' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'profileIdx' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'profileIdx2' =>	array(T_ZBX_STR, O_OPT, null,	null,		null),
	'updateProfile' =>	array(T_ZBX_STR, O_OPT, null,	null,		null),
	'from' =>			array(T_ZBX_INT, O_OPT, null,	'{}>=0',	null),
	'width' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(20, 65535),	null),
	'height' =>			array(T_ZBX_INT, O_OPT, null,	'{}>0',		null),
	'border' =>			array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null)
);
if (!check_fields($fields)) {
	exit();
}

/*
 * Permissions
 */
$dbItems = API::Item()->get(array(
	'output' => array('itemid'),
	'itemids' => $_REQUEST['itemid'],
	'webitems' => true,
));
if (!$dbItems) {
	access_deny();
}

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

$graph = new CLineGraphDraw();
$graph->setPeriod($timeline['period']);
$graph->setSTime($timeline['stime']);

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
$graph->addItem($_REQUEST['itemid'], GRAPH_YAXIS_SIDE_DEFAULT, CALC_FNC_ALL);
$graph->draw();

require_once dirname(__FILE__).'/include/page_footer.php';
