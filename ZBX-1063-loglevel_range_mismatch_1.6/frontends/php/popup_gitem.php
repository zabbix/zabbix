<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	require_once "include/config.inc.php";
	require_once "include/triggers.inc.php";
	require_once "include/forms.inc.php";
	require_once "include/js.inc.php";
	
	$dstfrm		= get_request("dstfrm",		0);	// destination form

	$page["title"] = "S_GRAPH_ITEM";
	$page["file"] = "popup_gitem.php";

	define('ZBX_PAGE_NO_MENU', 1);
	
include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"dstfrm"=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		null),

		"graphid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,			null),
		"gid"=>			array(T_ZBX_INT, O_OPT,  P_SYS,	BETWEEN(0,65535),	null),
		"graphtype"=>	array(T_ZBX_INT, O_OPT,	 null,	IN("0,1,2,3"),		'isset({save})'),
		"list_name"=>	array(T_ZBX_STR, O_OPT,  P_SYS,	NOT_EMPTY,			'isset({save})&&isset({gid})'),
		"itemid"=>		array(T_ZBX_INT, O_OPT,  null,	DB_ID.'({}!=0)',	'isset({save})'),
		"color"=>		array(T_ZBX_CLR, O_OPT,  null,	null,				'isset({save})'),
		"drawtype"=>	array(T_ZBX_INT, O_OPT,  null,	IN(graph_item_drawtypes()),'isset({save})&&(({graphtype} == 0) || ({graphtype} == 1))'),
		"sortorder"=>	array(T_ZBX_INT, O_OPT,  null,	BETWEEN(0,65535),	'isset({save})&&(({graphtype} == 0) || ({graphtype} == 1))'),
		"yaxisside"=>	array(T_ZBX_INT, O_OPT,  null,	IN("0,1"),			'isset({save})&&(({graphtype} == 0) || ({graphtype} == 1))'),
		"calc_fnc"=>	array(T_ZBX_INT, O_OPT,	 null,	IN("1,2,4,7,9"),	'isset({save})'),
		"type"=>		array(T_ZBX_INT, O_OPT,	 null,	IN("0,1,2"),		'isset({save})'),
		"periods_cnt"=>	array(T_ZBX_INT, O_OPT,	 null,	BETWEEN(0,360),		'isset({save})'),

		"only_hostid"=>	array(T_ZBX_INT, O_OPT,  null,	DB_ID,			null),
		"monitored_hosts"=>array(T_ZBX_INT, O_OPT,  null,	IN("0,1"),	null),
/* actions */
		"add"=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
/* other */
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_refresh"=>array(T_ZBX_STR, O_OPT, null,	null,	null)
	);

	check_fields($fields);
	
	insert_js_function('add_graph_item');
	insert_js_function('update_graph_item');

	$_REQUEST['drawtype'] = get_request('drawtype',0);
	$_REQUEST['yaxisside'] = get_request('yaxisside',0);
	$_REQUEST['sortorder'] = get_request('sortorder',0);
	$graphid = get_request('graphid',false);


	if(isset($_REQUEST['type']) && ($_REQUEST['type'] == GRAPH_ITEM_SUM) && ($graphid !== false)){
		$sql = 'SELECT COUNT(itemid) as items'.
				' FROM graphs_items '.
				' WHERE type='.GRAPH_ITEM_SUM.
					' AND graphid='.$graphid.
					' AND itemid<>'.$_REQUEST['itemid'];
		$res = DBselect($sql);
		while($rows = DBfetch($res)){
			if(isset($rows['items']) && ($rows['items'] > 0)){
				show_messages(false, null, S_ANOTHER_ITEM_SUM);
				if(isset($_REQUEST['save'])) unset($_REQUEST['save']);
				$_REQUEST['type'] = GRAPH_ITEM_SIMPLE;
			}
		}
	}
	
	if(isset($_REQUEST['save']) && !isset($_REQUEST['gid']))
	{
?>
<script language="JavaScript" type="text/javascript">
<!--
<?php
		
		echo "add_graph_item('".
			$_REQUEST['dstfrm']."','".
			$_REQUEST['itemid']."','".
			$_REQUEST['color']."',".
			$_REQUEST['drawtype'].",".
			$_REQUEST['sortorder'].",".
			$_REQUEST['yaxisside'].",".
			$_REQUEST['calc_fnc'].",".
			$_REQUEST['type'].",".
			$_REQUEST['periods_cnt'].");\n";
?>
-->
</script>
<?php
	}
	if(isset($_REQUEST['save']) && isset($_REQUEST['gid']))
	{
?>
<script language="JavaScript" type="text/javascript">
<!--
<?php
				
		echo "update_graph_item('".
			$_REQUEST['dstfrm']."','".
			$_REQUEST['list_name']."','".
			$_REQUEST['gid']."','".
			$_REQUEST['itemid']."','".
			$_REQUEST['color']."',".
			$_REQUEST['drawtype'].",".
			$_REQUEST['sortorder'].",".
			$_REQUEST['yaxisside'].",".
			$_REQUEST['calc_fnc'].",".
			$_REQUEST['type'].",".
			$_REQUEST['periods_cnt'].");\n";
?>
-->
</script>
<?php
	}
	else
	{
?>
<?php
	echo SBR;

	insert_graphitem_form();

	}
?>
<?php

include_once "include/page_footer.php";

?>
