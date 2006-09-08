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


define("ANY_ELEMENT_RIGHT",	-1);
define("GROUP_RIGHT",		0);

	function	check_authorisation()
	{
		global	$page;
		global	$PHP_AUTH_USER,$PHP_AUTH_PW;
		global	$USER_DETAILS;
		global	$USER_RIGHTS;
		global	$_COOKIE;
		global	$_REQUEST;

		$USER_DETAILS = NULL;
		$USER_RIGHTS = array();

		if(isset($_COOKIE["sessionid"]))
		{
			$sessionid = $_COOKIE["sessionid"];
			$USER_DETAILS = DBfetch(DBselect("select u.*,s.* from sessions s,users u".
				" where s.sessionid=".zbx_dbstr($sessionid)." and s.userid=u.userid".
				" and ((s.lastaccess+u.autologout>".time().") or (u.autologout=0))"));

			if(!$USER_DETAILS)
			{
				$USER_DETAILS = array("alias"=>"- unknown -","userid"=>0);

				setcookie("sessionid",$sessionid,time()-3600);
				unset($_COOKIE["sessionid"]);
				unset($sessionid);

				show_header("Login",0,0,1);
				show_error_message("Session was ended, please relogin!");
				show_page_footer();
				exit;
			}
		} else {
			$USER_DETAILS = DBfetch(DBselect("select u.* from users u where u.alias='guest'"));
		}

		if($USER_DETAILS)
		{
			if(isset($sessionid))
			{
				setcookie("sessionid",$sessionid);
				DBexecute("update sessions set lastaccess=".time()." where sessionid=".zbx_dbstr($sessionid));
			}

			$USER_RIGHTS = array();

			$db_rights = DBselect("select * from rights where userid=".$USER_DETAILS["userid"]);
			while($db_right = DBfetch($db_rights))
			{
				$usr_right = array(
					"name"=>	$db_right["name"],
					"id"=>		$db_right["id"],
					"permission"=>	$db_right["permission"]
					);

				array_push($USER_RIGHTS,$usr_right);
			}
			return;
		}
		else
		{
			$USER_DETAILS = array("alias"=>"- unknown -","userid"=>0);
		}

// Incorrect login

		if(isset($sessionid))
		{
			setcookie("sessionid",$sessionid,time()-3600);
			unset($_COOKIE["sessionid"]);
		}

		if($page["file"]!="index.php")
		{
			echo "<meta http-equiv=\"refresh\" content=\"0; url=index.php\">";
			exit;
		}
		show_header("Login",0,0,1);
		show_error_message("Login name or password is incorrect");
		insert_login_form();
		show_page_footer();
		
		//END TODO
		exit;
	}

	function	permission2int($permission)
	{
		$int_rights = array(
			"A" => 3,
			"U" => 2,
			"R" => 1,
			"H" => 0
			);

		if(isset($int_rights[$permission])) 
			return ($int_rights[$permission]);

		return ($int_rights["R"]);
	}

	function	permission_min($permission1, $permission2) // NOTE: only for integer permissions !!! see: permission2int
	{
		if(is_null($permission1) && is_null($permission2)) return NULL;
		if(is_null($permission1))	return $permission2;
		if(is_null($permission2))	return $permission1;
		return min($permission1,$permission2);
	}
	function	permission_max($permission1, $permission2) // NOTE: only for integer permissions !!! see: permission2int
	{
		if(is_null($permission1) && is_null($permission2)) return NULL;
		if(is_null($permission1))	return $permission2;
		if(is_null($permission2))	return $permission1;
		return max($permission1,$permission2);
	}

	function	check_right($right,$permission,$id = GROUP_RIGHT)
	{
		global $USER_RIGHTS;

		$default_permission = permission2int("H");
		$group_permission = NULL;
		$id_permission = NULL;
		$any_permission = NULL;

		$permission = permission2int($permission);

		if(count($USER_RIGHTS) > 0)
		{
			foreach($USER_RIGHTS as $usr_right)
			{
				$int_permision = permission2int($usr_right["permission"]);
				if($usr_right["name"] == $right) {

					if($usr_right["id"] == $id)
						$id_permission = permission_max($id_permission, $int_permision);
					if($usr_right["id"] == GROUP_RIGHT)
						$group_permission = permission_max($group_permission, $int_permision);
					else
						$any_permission = permission_max($any_permission, $int_permision);
				}
				if($usr_right["name"] == 'Default permission') 
				{
					$default_permission = permission_max($default_permission, $int_permision);
				}
			}
		}

		if($id == ANY_ELEMENT_RIGHT)
			$access = $any_permission;
		else
			$access = $id_permission;
		
		if(is_null($access))	$access = $group_permission;
		if(is_null($access))    $access = $default_permission;


//SDI($right.": ".$access." >= ".$permission);
		return (($access >= $permission) ? 1 : 0);
	}

	function	check_anyright($right,$permission)
	{
		return check_right($right,$permission, ANY_ELEMENT_RIGHT);
	}


?>
