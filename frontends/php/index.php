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
	require_once "include/forms.inc.php";

	$page["title"]	= "S_ZABBIX_BIG";
	$page["file"]	= "index.php";
	
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"name"=>		array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,	'isset({enter})'),
		"password"=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,		'isset({enter})'),
		"sessionid"=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,		NULL),
		"message"=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,		NULL),
		"reconnect"=>		array(T_ZBX_INT, O_OPT,	P_ACT, BETWEEN(0,65535),NULL),
                "enter"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
                "form"=>		array(T_ZBX_STR, O_OPT, P_SYS,  NULL,   	NULL),
                "form_refresh"=>	array(T_ZBX_INT, O_OPT, NULL,   NULL,   	NULL)
	);
	check_fields($fields);
?>
<?php
	if(isset($_REQUEST["reconnect"]) && isset($_COOKIE["sessionid"]))
	{
		DBexecute("delete from sessions where sessionid=".zbx_dbstr($_COOKIE["sessionid"]));
		setcookie("sessionid",$_COOKIE["sessionid"],time()-3600); /* NOTE: don't use zbx_setcookie */
		unset($_COOKIE["sessionid"]);
	}

	if(isset($_REQUEST["enter"])&&($_REQUEST["enter"]=="Enter"))
	{
		$name = get_request("name","");
		$password = md5(get_request("password",""));

		$row = DBfetch(DBselect("select u.userid,u.alias,u.name,u.surname,u.url,u.refresh from users u where".
			" u.alias=".zbx_dbstr($name)." and u.passwd=".zbx_dbstr($password).
			" and ".DBid2nodeid('u.userid')."=".$ZBX_LOCALNODEID));

		if($row)
		{
			$sessionid = md5(time().$password.$name.rand(0,10000000));
			setcookie("sessionid",$sessionid,time()+3600); /* NOTE: don't use zbx_setcookie */
			$_COOKIE["sessionid"]	= $sessionid;	/* Required ! */
			
			DBexecute("insert into sessions (sessionid,userid,lastaccess)".
				" values (".zbx_dbstr($sessionid).",".$row["userid"].",".time().")");

			if($row["url"] != '')
			{
				Redirect($row["url"]);
				return;
			}
		}
		else
		{
			$_REQUEST['message'] = "Login name or password is incorrect";
		}
	}

include_once "include/page_header.php";
	
	if(isset($_REQUEST['message'])) show_error_message($_REQUEST['message']);
?>
<?php
	if(!isset($_COOKIE["sessionid"]))
	{
		insert_login_form();
	}
	else
	{
		$logoff = new CLink('here', '?reconnect=1');

		echo "<div align=center>";
		echo "Press ".$logoff->ToString()." to disconnect/reconnect";
		echo "</div>";
	}	
?>
<?php

include_once "include/page_footer.php";

?>
