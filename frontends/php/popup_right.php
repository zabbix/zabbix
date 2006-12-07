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

	$page["title"] = "S_RESOURCE";
	$page["file"] = "popup_right.php";

	define('ZBX_PAGE_NO_MENU', 1);
	
include_once "include/page_header.php";

	insert_confirm_javascript();
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"dstfrm"=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		NULL),
		"permission"=>	array(T_ZBX_INT, O_MAND,P_SYS,	IN(PERM_DENY.','.PERM_READ_ONLY.','.PERM_READ_WRITE),	NULL),
		"type"=>	array(T_ZBX_INT, O_OPT, P_SYS,	IN(RESOURCE_TYPE_GROUP.(ZBX_DISTRIBUTED ? RESOURCE_TYPE_NODE.',' : '')), NULL)
	);

	check_fields($fields);

	$dstfrm		= get_request("dstfrm",		0);			// destination form
	$permission	= get_request("permission",	PERM_DENY);		// right
	$type		= get_request("type",		get_profile('web.right_type.last', RESOURCE_TYPE_GROUP));	// type of resource

	update_profile('web.right_type.last', $type);
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

function add_right(formname,type,id,permission,name)
{
        var form = window.opener.document.forms[formname];

        if(!form)
        {
                window.close();
		return false;
        }

	add_var_to_opener_obj(form,'new_right[type]',type);
	add_var_to_opener_obj(form,'new_right[id]',id);
	add_var_to_opener_obj(form,'new_right[permission]',permission);
	add_var_to_opener_obj(form,'new_right[name]',name);

	form.submit();
	window.close();
	return true;
}
-->
</script>
<?php
	$frmTitle = new CForm();
	$frmTitle->AddVar('dstfrm',$dstfrm);
	$frmTitle->AddVar('permission', $permission);
	if(ZBX_DISTRIBUTED)
	{
		$cmbResourceType = new CComboBox('type',$type,'submit();');
		$cmbResourceType->AddItem(RESOURCE_TYPE_NODE, S_NODES);
		$cmbResourceType->AddItem(RESOURCE_TYPE_GROUP, S_HOST_GROUPS);
		$frmTitle->AddItem(array(
			S_RESOURCE_TYPE, SPACE,
			$cmbResourceType));
	}
	show_table_header(permission2str($permission),$frmTitle);

	$table = new CTableInfo(S_NO_RESOURCES_DEFINED);
	$table->SetHeader(array(S_NAME));
	
	$db_resources = null;

	if(ZBX_DISTRIBUTED && $type == RESOURCE_TYPE_NODE)
	{
		$db_resources = DBselect('select n.name as name, n.nodeid as id from nodes n order by n.name');
	}
	elseif($type == RESOURCE_TYPE_GROUP)
	{
		$db_resources = DBselect('select n.name as node_name, g.name as name, g.groupid as id'.
			' from groups g left join nodes n on '.DBid2nodeid('g.groupid').'=n.nodeid '.
			' order by n.name, g.name');
	}

	while($db_resource = DBfetch($db_resources))
	{
		if(isset($db_resource['node_name']))
			$db_resource['name'] = $db_resource['node_name'].':'.$db_resource['name'];

		$name = new CLink($db_resource['name'],'#','action');
		$name->SetAction("return add_right('".$dstfrm."',".$type.",".$db_resource['id'].",".$permission.",'".$db_resource['name']."');");

		$table->AddRow(array($name));
	}

	$table->Show();
?>
<?php

include_once "include/page_footer.php";

?>
