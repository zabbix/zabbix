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

	$page["title"] = "S_MEDIA";
	$page["file"] = "popup_media.php";

	define('ZBX_PAGE_NO_MENU', 1);
	
include_once "include/page_header.php";

	insert_confirm_javascript();
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"dstfrm"=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		NULL),
		"mediatypeid"=>	array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'isset({add})'),
		"sendto"=>	array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,	'isset({add})'),
		"period"=>	array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,	'isset({add})'),
		"active"=>	array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,	'isset({add})'),

		"severity"=>	array(T_ZBX_INT, O_OPT,	NULL,	NOT_EMPTY,	NULL),
/* actions */
		"add"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
/* other */
		"form"=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>array(T_ZBX_STR, O_OPT, NULL,	NULL,	NULL)
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

function add_media(formname,mediatypeid,sendto,period,active,severity)
{
        var form = window.opener.document.forms[formname];

        if(!form)
        {
                window.close();
		return false;
        }

	add_var_to_opener_obj(form,'new_media[mediatypeid]',mediatypeid);
	add_var_to_opener_obj(form,'new_media[sendto]',sendto);
	add_var_to_opener_obj(form,'new_media[period]',period);
	add_var_to_opener_obj(form,'new_media[active]',active);
	add_var_to_opener_obj(form,'new_media[severity]',severity);

	form.submit();
	window.close();
	return true;
}
-->
</script>
<?php
	if(isset($_REQUEST['add']))
	{
		if(validate_period($_REQUEST['period']) != 0)
		{
			error("Icorrect time period");
		}
		else
		{
			$severity = 0;
			$_REQUEST['severity'] = get_request('severity',array());
			foreach($_REQUEST['severity'] as $id)
				$severity |= 1 << $id;

?>
<script language="JavaScript" type="text/javascript">
<!--
<?php
			echo "add_media('".
				$_REQUEST['dstfrm']."',".
				$_REQUEST['mediatypeid'].",'".
				$_REQUEST['sendto']."','".
				$_REQUEST['period']."',".
				$_REQUEST['active'].",".
				$severity.");\n";
?>
-->
</script>
<?php
		}
	}
?>
<?php
	echo BR;

	insert_media_form();

?>
<?php

include_once "include/page_footer.php";

?>
