<?php
/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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
	$page["title"]="Zabbix main page";
	$page["file"]="index.php";

	include "include/config.inc.php";

	if(isset($HTTP_POST_VARS["password"]))
	{
		$password=$HTTP_POST_VARS["password"];
	}
	else
	{
		unset($password);
	}
	if(isset($HTTP_POST_VARS["name"]))
	{
		$name=$HTTP_POST_VARS["name"];
	}
	else
	{
		unset($name);
	}
	if(isset($HTTP_POST_VARS["register"]))
	{
		$register=$HTTP_POST_VARS["register"];
	}
	else
	{
		unset($register);
	}
	if(isset($HTTP_GET_VARS["reconnect"]))
	{
		$reconnect=$HTTP_GET_VARS["reconnect"];
	}
	else
	{
		unset($reconnect);
	}
	if(isset($HTTP_COOKIE_VARS["sessionid"]))
	{
		$sessionid=$HTTP_COOKIE_VARS["sessionid"];
	}
	else
	{
		unset($sessionid);
	}


	if(isset($reconnect))
	{
		$sql="delete from sessions where sessionid='$sessionid'";
		DBexecute($sql);
		setcookie("sessionid",$sessionid,time()-3600);
		unset($sessionid);
	}

	if(isset($register)&&($register=="Enter"))
	{
		$password=md5($password);
		$sql="select u.userid,u.alias,u.name,u.surname from users u where u.alias='$name' and u.passwd='$password'";
		$result=DBselect($sql);
		if(DBnum_rows($result)==1)
		{
			$USER_DETAILS["userid"]=DBget_field($result,0,0);
			$USER_DETAILS["alias"]=DBget_field($result,0,1);
			$USER_DETAILS["name"]=DBget_field($result,0,2);
			$USER_DETAILS["surname"]=DBget_field($result,0,3);
			$sessionid=md5(time().$password.$name.rand(0,10000000));
			setcookie("sessionid",$sessionid,time()+3600);
// Required !
			$HTTP_COOKIE_VARS["sessionid"]=$sessionid;
			$sql="insert into sessions (sessionid,userid,lastaccess) values ('$sessionid',".$USER_DETAILS["userid"].",".time().")";
			DBexecute($sql);
		}
	}

	show_header($page["title"],0,0);
?>

<?php
	if(!isset($sessionid))
	{
//		echo "-",$HTTP_COOKIE_VARS["sessionid"],"-<br>";
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
	show_footer();
?>
