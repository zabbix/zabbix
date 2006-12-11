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

	$dstfrm		= get_request("dstfrm",		0);	// destination form

	$page["title"] = "S_GRAPH_ITEM";
	$page["file"] = "popup_gitem.php";

	define('ZBX_PAGE_NO_MENU', 1);
	
include_once "include/page_header.php";

	insert_confirm_javascript();
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"dstfrm"=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		null),

		"gid"=>		array(T_ZBX_INT, O_OPT,  P_SYS,	BETWEEN(0,65535),	null),
		"list_name"=>	array(T_ZBX_STR, O_OPT,  P_SYS,	NOT_EMPTY,		'isset({save})&&isset({gid})'),
		"itemid"=>	array(T_ZBX_INT, O_OPT,  null,	DB_ID.'({}!=0)',	'isset({save})'),
		"color"=>	array(T_ZBX_CLR, O_OPT,  null,	null,			'isset({save})'),
		"drawtype"=>	array(T_ZBX_INT, O_OPT,  null,	IN("0,1,2,3"),		'isset({save})'),
		"sortorder"=>	array(T_ZBX_INT, O_OPT,  null,	BETWEEN(0,65535),	'isset({save})'),
		"yaxisside"=>	array(T_ZBX_INT, O_OPT,  null,	IN("0,1"),		'isset({save})'),
		"calc_fnc"=>	array(T_ZBX_INT, O_OPT,	 null,	IN("1,2,4,7"),		'isset({save})'),
		"type"=>	array(T_ZBX_INT, O_OPT,	 null,	IN("0,1"),		'isset({save})'),
		"periods_cnt"=>	array(T_ZBX_INT, O_OPT,	 null,	BETWEEN(0,360),		'isset({save})'),
		"graphtype"=>	array(T_ZBX_INT, O_OPT,	 null,	IN("0,1"),		'isset({save})'),

		"only_hostid"=>	array(T_ZBX_INT, O_OPT,  null,	DB_ID,			null),
/* actions */
		"add"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"save"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
/* other */
		"form"=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_refresh"=>array(T_ZBX_STR, O_OPT, null,	null,	null)
	);

	check_fields($fields);
?>
<script language="JavaScript" type="text/javascript">
<!--

function add_var_to_opener_obj(obj,name,value)
{
        new_variable = window.opener.document.createElement('input');
        new_variable.type = 'hidden';
        new_variable.name = name;
        new_variable.value = value;

        obj.appendChild(new_variable);
}

function add_graph_item(formname,itemid,color,drawtype,sortorder,yaxisside,calc_fnc,type,periods_cnt)
{
        var form = window.opener.document.forms[formname];

        if(!form)
        {
                window.close();
		return false;
        }

	add_var_to_opener_obj(form,'new_graph_item[itemid]',itemid);
	add_var_to_opener_obj(form,'new_graph_item[color]',color);
	add_var_to_opener_obj(form,'new_graph_item[drawtype]',drawtype);
	add_var_to_opener_obj(form,'new_graph_item[sortorder]',sortorder);
	add_var_to_opener_obj(form,'new_graph_item[yaxisside]',yaxisside);
	add_var_to_opener_obj(form,'new_graph_item[calc_fnc]',calc_fnc);
	add_var_to_opener_obj(form,'new_graph_item[type]',type);
	add_var_to_opener_obj(form,'new_graph_item[periods_cnt]',periods_cnt);
	
	form.submit();
	window.close();
	return true;
}

function update_graph_item(formname,list_name,gid,itemid,color,drawtype,sortorder,yaxisside,calc_fnc,type,periods_cnt)
{
        var form = window.opener.document.forms[formname];

        if(!form)
        {
                window.close();
		return false;
        }

	add_var_to_opener_obj(form,list_name + '[' + gid + '][itemid]',itemid);
	add_var_to_opener_obj(form,list_name + '[' + gid + '][color]',color);
	add_var_to_opener_obj(form,list_name + '[' + gid + '][drawtype]',drawtype);
	add_var_to_opener_obj(form,list_name + '[' + gid + '][sortorder]',sortorder);
	add_var_to_opener_obj(form,list_name + '[' + gid + '][yaxisside]',yaxisside);
	add_var_to_opener_obj(form,list_name + '[' + gid + '][calc_fnc]',calc_fnc);
	add_var_to_opener_obj(form,list_name + '[' + gid + '][type]',type);
	add_var_to_opener_obj(form,list_name + '[' + gid + '][periods_cnt]',periods_cnt);
	
	form.submit();
	window.close();
	return true;
}
-->
</script>
<?php
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
	echo BR;

	insert_graphitem_form();

	}
?>
<?php

include_once "include/page_footer.php";

?>
