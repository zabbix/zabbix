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
	$page["title"]="S_ZABBIX_BIG";
	$page["file"]="index.php";

	include "include/config.inc.php";
	include "include/forms.inc.php";
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"name"=>		array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,	'isset({enter})'),
		"password"=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,		'isset({enter})'),
	//	"sessionid"=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,		NULL),
		"reconnect"=>		array(T_ZBX_INT, O_OPT,	P_ACT, BETWEEN(0,65535),NULL),
                "enter"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
                "form"=>		array(T_ZBX_STR, O_OPT, P_SYS,  NULL,   	NULL),
                "form_refresh"=>	array(T_ZBX_INT, O_OPT, NULL,   NULL,   	NULL)
	);
	check_fields($fields);
?>
<?php
	if(isset($_REQUEST["reconnect"]) && isset($_COOKIE["zbx_sessionid"]))
	{
		add_audit(AUDIT_ACTION_LOGOUT,AUDIT_RESOURCE_USER,"Manual Logout");
		
		DBexecute("delete from sessions where sessionid=".zbx_dbstr($_COOKIE["zbx_sessionid"]));
		setcookie("zbx_sessionid",$_COOKIE["zbx_sessionid"],time()-3600);
		unset($_COOKIE["zbx_sessionid"]);

		echo "<HTML><HEAD>";
		echo "<META HTTP-EQUIV=\"Refresh\" CONTENT=\"0; URL=index.php\">";
		echo "</HEAD></HTML>";
		return;
	}

	if(isset($_REQUEST["enter"])&&($_REQUEST["enter"]=="Enter"))
	{
		$name = get_request("name","");
		$password = md5(get_request("password",""));

		$result=DBselect("select u.userid,u.alias,u.name,u.surname,u.url,u.refresh from users u where".
			" u.alias=".zbx_dbstr($name)." and u.passwd=".zbx_dbstr($password));

		$row=DBfetch($result);
		if($row)
		{
			$USER_DETAILS["userid"]	= $row["userid"];
			$USER_DETAILS["alias"]	= $row["alias"];
			$USER_DETAILS["name"]	= $row["name"];
			$USER_DETAILS["surname"]= $row["surname"];
			$USER_DETAILS["url"]	= $row["url"];
			$USER_DETAILS["refresh"]= $row["refresh"];
			
			$sessionid = md5(time().$password.$name.rand(0,10000000));
			setcookie("zbx_sessionid",$sessionid,time()+3600);
			$_COOKIE["zbx_sessionid"]	= $sessionid; /* Required ! */
			
			DBexecute("insert into sessions (sessionid,userid,lastaccess)".
				" values (".zbx_dbstr($sessionid).",".$USER_DETAILS["userid"].",".time().")");

			add_audit(AUDIT_ACTION_LOGIN,AUDIT_RESOURCE_USER,"Correct login [".$name."]");
			
			if($USER_DETAILS["url"] != '')
			{
				echo "<HTML><HEAD>";
        			echo "<META HTTP-EQUIV=\"Refresh\" CONTENT=\"0; URL=".$USER_DETAILS["url"]."\">";
				echo "</HEAD></HTML>";
				return;
			}
		}
		else
		{
			add_audit(AUDIT_ACTION_LOGIN,AUDIT_RESOURCE_USER,"Login failed [".$name."]");
		}
	}

	show_header($page["title"],0,0);
?>

<?php
	if(!isset($_COOKIE["zbx_sessionid"]))
	{
		insert_login_form();
	}
	else
	{
		echo "<div align=center>";
		echo "Press <a href=\"index.php?reconnect=1\">here</a> to disconnect/reconnect";
		echo "</div>";
	}	
?>


<?php
	show_page_footer();
?>
