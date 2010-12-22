<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once('include/config.inc.php');

$page['file']	= 'chart.php';
// $page['title']	= "S_CHART";
$page['type']	= PAGE_TYPE_IMAGE;

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'itemid'=>		array(T_ZBX_INT, O_MAND,P_SYS,	DB_ID,		null),
		'period'=>		array(T_ZBX_INT, O_OPT,	null,	BETWEEN(ZBX_MIN_PERIOD,ZBX_MAX_PERIOD),	null),
		'from'=>		array(T_ZBX_INT, O_OPT,	null,	'{}>=0',	null),
		'width'=>		array(T_ZBX_INT, O_OPT,	null,	'{}>0',		null),
		'height'=>		array(T_ZBX_INT, O_OPT,	null,	'{}>0',		null),
		'border'=>		array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),
		'stime'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,		null)
	);

	check_fields($fields);
?>
<?php
	if(!DBfetch(DBselect('select itemid from items where itemid='.$_REQUEST['itemid']))){
		show_error_message(S_NO_ITEM_DEFINED);
	}

	$options = array(
		'itemids' => $_REQUEST['itemid'],
		'webitems' => 1,
		'nodeids' => get_current_nodeid(true)
	);

	$db_data = CItem::get($options);
	if(empty($db_data)) access_deny();

	$graph = new CChart();

	$effectiveperiod = navigation_bar_calc('web.item.graph',$_REQUEST['itemid']);

	if(isset($_REQUEST['period']))		$graph->setPeriod($_REQUEST['period']);
	if(isset($_REQUEST['from']))		$graph->setFrom($_REQUEST['from']);
	if(isset($_REQUEST['width']))		$graph->setWidth($_REQUEST['width']);
	if(isset($_REQUEST['height']))		$graph->setHeight($_REQUEST['height']);
	if(isset($_REQUEST['border']))		$graph->setBorder(0);
	if(isset($_REQUEST['stime']))		$graph->setSTime($_REQUEST['stime']);

	$graph->addItem($_REQUEST['itemid'], GRAPH_YAXIS_SIDE_DEFAULT, CALC_FNC_ALL);
	$graph->draw();

?>
<?php

include_once('include/page_footer.php');

?>