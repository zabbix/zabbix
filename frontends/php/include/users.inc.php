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
	function	user_type2str($user_type_int)
	{
		$str_user_type[USER_TYPE_ZABBIX_USER]	= S_ZABBIX_USER;
		$str_user_type[USER_TYPE_ZABBIX_ADMIN]	= S_ZABBIX_ADMIN;
		$str_user_type[USER_TYPE_SUPER_ADMIN]	= S_SUPER_ADMIN;

		if(isset($str_user_type[$user_type_int]))
			return $str_user_type[$user_type_int];

		return S_UNCNOWN;
	}

	# Add User definition

	function	add_user($name,$surname,$alias,$passwd,$url,$autologout,$lang,$refresh,$user_type,$user_groups,$user_medias)
	{
		global $USER_DETAILS;
		global $ZBX_CURNODEID;

		if($USER_DETAILS['type'] != USER_TYPE_SUPER_ADMIN)
		{
			error("Insufficient permissions");
			return 0;
		}

		if(DBfetch(DBselect("select * from users where alias=".zbx_dbstr($alias)." and ".DBid2nodeid('userid')."=".$ZBX_CURNODEID)))
		{
			error('User "'.$alias.'" already exists');
			return 0;
		}

		$userid = get_dbid("users","userid");

		$result =  DBexecute('insert into users (userid,name,surname,alias,passwd,url,autologout,lang,refresh,type)'.
			' values ('.$userid.','.zbx_dbstr($name).','.zbx_dbstr($surname).','.zbx_dbstr($alias).','.
			zbx_dbstr(md5($passwd)).','.zbx_dbstr($url).','.$autologout.','.zbx_dbstr($lang).','.$refresh.','.$user_type.')');

		if($result)
		{
			DBexecute('delete from users_groups where userid='.$userid);
			foreach($user_groups as $groupid => $grou_pname)
			{
				$users_groups_id = get_dbid("users_groups","id");
				$result = DBexecute('insert into users_groups (id,usrgrpid,userid)'.
					'values('.$users_groups_id.','.$groupid.','.$userid.')');

				if($result == false) break;
			}
			if($result)
			{
				DBexecute('delete from media where userid='.$userid);
				foreach($user_medias as $mediaid => $media_data)
				{
					$mediaid = get_dbid("media","mediaid");
					$result = DBexecute('insert into media (mediaid,userid,mediatypeid,sendto,active,severity,period)'.
						' values ('.$mediaid.','.$userid.','.$media_data['mediatypeid'].','.
						zbx_dbstr($media_data['sendto']).','.$media_data['active'].','.$media_data['severity'].','.
						zbx_dbstr($media_data['period']).')');

					if($result == false) break;
				}
			}
		}

		return $result;
	}

	# Update User definition

	function	update_user($userid,$name,$surname,$alias,$passwd, $url,$autologout,$lang,$refresh,$user_type,$user_groups,$user_medias)
	{
		global $ZBX_CURNODEID;

		if(DBfetch(DBselect("select * from users where alias=".zbx_dbstr($alias).
			" and userid<>$userid and ".DBid2nodeid('userid')."=".$ZBX_CURNODEID)))
		{
			error("User '$alias' already exists");
			return 0;
		}

		$result = DBexecute("update users set name=".zbx_dbstr($name).",surname=".zbx_dbstr($surname).","."alias=".zbx_dbstr($alias).
			(isset($passwd) ? (',passwd='.zbx_dbstr(md5($passwd))) : '').
			",url=".zbx_dbstr($url).","."autologout=$autologout,lang=".zbx_dbstr($lang).",refresh=$refresh,".
			"type=$user_type where userid=$userid");

		if($result)
		{
			DBexecute('delete from users_groups where userid='.$userid);
			foreach($user_groups as $groupid => $grou_pname)
			{
				$users_groups_id = get_dbid("users_groups","id");
				$result = DBexecute('insert into users_groups (id,usrgrpid,userid)'.
					'values('.$users_groups_id.','.$groupid.','.$userid.')');

				if($result == false) break;
			}
			if($result)
			{
				DBexecute('delete from media where userid='.$userid);
				foreach($user_medias as $mediaid => $media_data)
				{
					$mediaid = get_dbid("media","mediaid");
					$result = DBexecute('insert into media (mediaid,userid,mediatypeid,sendto,active,severity,period)'.
						' values ('.$mediaid.','.$userid.','.$media_data['mediatypeid'].','.
						zbx_dbstr($media_data['sendto']).','.$media_data['active'].','.$media_data['severity'].','.
						zbx_dbstr($media_data['period']).')');

					if($result == false) break;
				}
			}
		}

		return $result;
	}

	# Update User Profile

	function	update_user_profile($userid,$passwd, $url,$autologout,$lang,$refresh)
	{
		global $USER_DETAILS;

		if($userid!=$USER_DETAILS["userid"])
		{
			access_deny();
		}

		return DBexecute("update users set url=".zbx_dbstr($url).",autologout=$autologout,lang=".zbx_dbstr($lang).
				(isset($passwd) ? (',passwd='.zbx_dbstr(md5($passwd))) : '').
				",refresh=$refresh where userid=$userid");
	}

	# Delete User definition

	function	delete_user($userid)
	{

		if(DBfetch(DBselect('select * from users where userid='.$userid.' and alias=\'guest\'')))
		{
			error("Cannot delete user 'guest'");
			return	false;
		}

		while($row=DBfetch(DBselect('select actionid from actions where userid='.$userid)))
		{
			$result = delete_action($row["actionid"]);
			if(!$result) return $result;
		}

		$result = DBexecute('delete from media where userid='.$userid);
		if(!$result) return $result;

		$result = DBexecute('delete from profiles where userid='.$userid);
		if(!$result) return $result;

		$result = DBexecute('delete from users_groups where userid='.$userid);
		if(!$result) return $result;

		$result = DBexecute('delete from users where userid='.$userid);

		return $result;
	}


	function	get_user_by_userid($userid)
	{
		if($row = DBfetch(DBselect("select * from users where userid=$userid")))
		{
			return	$row;
		}
		error("No user with id [$userid]");
		return	false;
	}

/**************************
	USER GROUPS
**************************/

	function	add_user_group($name,$users=array(),$rights=array())
	{
		global $ZBX_CURNODEID;

		if(DBfetch(DBselect("select * from usrgrp where name=".zbx_dbstr($name)." and ".DBid2nodeid('usrgrpid')."=".$ZBX_CURNODEID)))
		{
			error("Group '$name' already exists");
			return 0;
		}

		$usrgrpid=get_dbid("usrgrp","usrgrpid");

		$result=DBexecute("insert into usrgrp (usrgrpid,name) values ($usrgrpid,".zbx_dbstr($name).")");
		if(!$result)	return	$result;
		
		$result=DBexecute("delete from users_groups where usrgrpid=".$usrgrpid);
		foreach($users as $userid => $name)
		{
			$id = get_dbid('users_groups','id');
			$result=DBexecute('insert into users_groups (id,usrgrpid,userid) values ('.$id.','.$usrgrpid.','.$userid.')');
			if(!$result)	return	$result;
		}

		$result=DBexecute("delete from rights where groupid=".$usrgrpid);
		foreach($rights as $right)
		{
			$id = get_dbid('rights','rightid');
			$result=DBexecute('insert into rights (rightid,groupid,type,permission,id)'.
				' values ('.$id.','.$usrgrpid.','.$right['type'].','.$right['permission'].','.$right['id'].')');
			if(!$result)	return	$result;
		}

		return $result;
	}

	function	update_user_group($usrgrpid,$name,$users=array(),$rights=array())
	{
		global $ZBX_CURNODEID;

		if(DBfetch(DBselect("select * from usrgrp where name=".zbx_dbstr($name).
			" and usrgrpid<>".$usrgrpid." and ".DBid2nodeid('usrgrpid')."=".$ZBX_CURNODEID)))
		{
			error("Group '$name' already exists");
			return 0;
		}

		$result=DBexecute("update usrgrp set name=".zbx_dbstr($name)." where usrgrpid=$usrgrpid");
		if(!$result)
		{
			return	$result;
		}
		
		$result=DBexecute("delete from users_groups where usrgrpid=".$usrgrpid);
		foreach($users as $userid => $name)
		{
			$id = get_dbid('users_groups','id');
			$result=DBexecute('insert into users_groups (id,usrgrpid,userid) values ('.$id.','.$usrgrpid.','.$userid.')');
			if(!$result)	return	$result;
		}

		$result=DBexecute("delete from rights where groupid=".$usrgrpid);
		foreach($rights as $right)
		{
			$id = get_dbid('rights','rightid');
			$result=DBexecute('insert into rights (rightid,groupid,type,permission,id)'.
				' values ('.$id.','.$usrgrpid.','.$right['type'].','.$right['permission'].','.$right['id'].')');
			if(!$result)	return	$result;
		}

		return $result;
	}

	function	delete_user_group($usrgrpid)
	{
		$result = DBexecute("delete from rights where groupid=$usrgrpid");
		if(!$result)	return	$result;

		$result = DBexecute("delete from users_groups where usrgrpid=$usrgrpid");
		if(!$result)	return	$result;

		$result = DBexecute("delete from usrgrp where usrgrpid=$usrgrpid");
		return	$result;
	}

	function	get_group_by_usrgrpid($usrgrpid)
	{
		if($row = DBfetch(DBselect("select * from usrgrp where usrgrpid=".$usrgrpid)))
		{
			return $row;
		}
		error("No user groups with id [$usrgrpid]");
		return  FALSE;
	}
?>
