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
	require_once('include/config.inc.php');
	require_once('include/triggers.inc.php');
	require_once('include/forms.inc.php');

	$dstfrm		= get_request('dstfrm',		0);	// destination form

	$page['title'] = "S_STEP_OF_SCENARIO";
	$page['file'] = 'popup_httpstep.php';

	define('ZBX_PAGE_NO_MENU', 1);

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'dstfrm'=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		null),

		'stepid'=>		array(T_ZBX_INT, O_OPT,  P_SYS,	BETWEEN(0,65535),	null),
		'list_name'=>	array(T_ZBX_STR, O_OPT,  P_SYS,	NOT_EMPTY,		'isset({save})&&isset({stepid})'),

		'name'=>	array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY.KEY_PARAM(),'isset({save})'),
		'url'=>		array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,		'isset({save})'),
		'posts'=>	array(T_ZBX_STR, O_OPT,  null,	null,			'isset({save})'),
		'timeout'=>	array(T_ZBX_INT, O_OPT,  null,	BETWEEN(0,65535),	'isset({save})'),
		'required'=>	array(T_ZBX_STR, O_OPT,  null,	null,			'isset({save})'),
		'status_codes'=>array(T_ZBX_INT_RANGE, O_OPT,  null,	null,		'isset({save})'),

		'add'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'save'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		'form'=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>array(T_ZBX_STR, O_OPT, null,	null,	null)
	);

	check_fields($fields);
?>
<script language="JavaScript" type="text/javascript">
<!--

function add_var_to_opener_obj(obj,name,value){
        new_variable = window.opener.document.createElement('input');
        new_variable.type = 'hidden';
        new_variable.name = name;
        new_variable.value = value;

        obj.appendChild(new_variable);
}

-->
</script>
<?php
	if(isset($_REQUEST['save']) && !isset($_REQUEST['stepid']))
	{
?>
<script language="JavaScript" type="text/javascript">
<!--

function add_httpstep(formname,name,timeout,url,posts,required,status_codes){
        var form = window.opener.document.forms[formname];

        if(!form){
			close_window();
			return false;
        }

	add_var_to_opener_obj(form,'new_httpstep[name]',name);
	add_var_to_opener_obj(form,'new_httpstep[timeout]',timeout);
	add_var_to_opener_obj(form,'new_httpstep[url]',url);
	add_var_to_opener_obj(form,'new_httpstep[posts]',posts);
	add_var_to_opener_obj(form,'new_httpstep[required]',required);
	add_var_to_opener_obj(form,'new_httpstep[status_codes]',status_codes);

	form.submit();
	close_window();
	return true;
}

<?php
		echo 'add_httpstep('.
			zbx_jsvalue($_REQUEST['dstfrm']).','.
			zbx_jsvalue($_REQUEST['name']).','.
			zbx_jsvalue($_REQUEST['timeout']).','.
			zbx_jsvalue($_REQUEST['url']).','.
			zbx_jsvalue($_REQUEST['posts']).','.
			zbx_jsvalue($_REQUEST['required']).','.
			zbx_jsvalue($_REQUEST['status_codes']).");\n";
?>
-->
</script>
<?php
	}
	if(isset($_REQUEST['save']) && isset($_REQUEST['stepid']))
	{
?>
<script language="JavaScript" type="text/javascript">
<!--

function update_httpstep(formname,list_name,stepid,name,timeout,url,posts,required,status_codes){
	var form = window.opener.document.forms[formname];

	if(!form){
		close_window();
		return false;
	}

	add_var_to_opener_obj(form,list_name + '[' + stepid + '][name]',name);
	add_var_to_opener_obj(form,list_name + '[' + stepid + '][timeout]',timeout);
	add_var_to_opener_obj(form,list_name + '[' + stepid + '][url]',url);
	add_var_to_opener_obj(form,list_name + '[' + stepid + '][posts]',posts);
	add_var_to_opener_obj(form,list_name + '[' + stepid + '][required]',required);
	add_var_to_opener_obj(form,list_name + '[' + stepid + '][status_codes]',status_codes);


	form.submit();
	close_window();
return true;
}

<?php
		echo 'update_httpstep('.
			zbx_jsvalue($_REQUEST['dstfrm']).','.
			zbx_jsvalue($_REQUEST['list_name']).','.
			zbx_jsvalue($_REQUEST['stepid']).','.
			zbx_jsvalue($_REQUEST['name']).','.
			zbx_jsvalue($_REQUEST['timeout']).','.
			zbx_jsvalue($_REQUEST['url']).','.
			zbx_jsvalue($_REQUEST['posts']).','.
			zbx_jsvalue($_REQUEST['required']).','.
			zbx_jsvalue($_REQUEST['status_codes']).");\n";
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

	insert_httpstep_form();

	}
?>
<?php

include_once('include/page_footer.php');

?>
