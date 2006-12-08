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
	require_once "include/media.inc.php";
	require_once "include/users.inc.php";
	require_once "include/forms.inc.php";

	$page["title"] = "S_USERS";
	$page["file"] = "users.php";

include_once "include/page_header.php";

	insert_confirm_javascript();
?>
<?php
	$_REQUEST["config"]=get_request("config",get_profile("web.users.config",0));
	update_profile("web.users.config",$_REQUEST["config"]);
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"config"=>	array(T_ZBX_INT, O_OPT,	null,	IN("0,1"),	null),
		"perm_details"=>array(T_ZBX_INT, O_OPT,	null,	IN("0,1"),	null),
/* user */
		"userid"=>	array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,'{config}==0&&{form}=="update"'),
		"group_userid"=>array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),

		"alias"=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'{config}==0&&isset({save})'),
		"name"=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'{config}==0&&isset({save})'),
		"surname"=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'{config}==0&&isset({save})'),
		"password1"=>	array(T_ZBX_STR, O_OPT,	null,	null,		'{config}==0&&isset({save})&&{form}!="update"&&isset({change_password})'),
		"password2"=>	array(T_ZBX_STR, O_OPT,	null,	null,		'{config}==0&&isset({save})&&{form}!="update"&&isset({change_password})'),
		"user_type"=>	array(T_ZBX_INT, O_OPT,	null,	IN('1,2,3'),	'{config}==0&&isset({save})'),
		"user_groups"=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'{config}==0&&isset({save})'),
		"user_groups_to_del"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,	null),
		"user_medias"=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),
		"user_medias_to_del"=>	array(T_ZBX_STR, O_OPT,	null,	DB_ID,	null),
		"new_group"=>	array(T_ZBX_STR, O_OPT,	null,	null,	null),
		"new_media"=>	array(T_ZBX_STR, O_OPT,	null,	null,	null),
		"enable_media"=>array(T_ZBX_INT, O_OPT,	null,	null,		null),
		"disable_media"=>array(T_ZBX_INT, O_OPT,null,	null,		null),
		"lang"=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'{config}==0&&isset({save})'),
		"autologout"=>	array(T_ZBX_INT, O_OPT,	null,	BETWEEN(0,3600),'{config}==0&&isset({save})'),
		"url"=>		array(T_ZBX_STR, O_OPT,	null,	null,		'{config}==0&&isset({save})'),
		"refresh"=>	array(T_ZBX_INT, O_OPT,	null,	BETWEEN(0,3600),'{config}==0&&isset({save})'),

		"right"=>	array(T_ZBX_STR, O_NO,	null,	NOT_EMPTY,
					'{register}=="add permission"&&isset({userid})'),
		"permission"=>	array(T_ZBX_STR, O_NO,	null,	NOT_EMPTY,
					'{register}=="add permission"&&isset({userid})'),
		"id"=>		array(T_ZBX_INT, O_NO,	null,	DB_ID,
					'{register}=="add permission"&&isset({userid})'),
		"rightid"=>	array(T_ZBX_INT, O_NO,  null,   DB_ID,
                                        '{register}=="delete permission"&&isset({userid})'),
/* group */
		"usrgrpid"=>	array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,'{config}==1&&{form}=="update"'),
		"group_groupid"=>array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),

		"gname"=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'{config}==1&&isset({save})'),
		"users"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	null),
		"new_right"=>	array(T_ZBX_STR, O_OPT,	null,	null,	null),
		"new_user"=>	array(T_ZBX_STR, O_OPT,	null,	null,	null),
		"right_to_del"=>array(T_ZBX_STR, O_OPT,	null,	null,	null),
		"group_users_to_del"=>	array(T_ZBX_STR, O_OPT,	null,	null,	null),
		"group_users"=>	array(T_ZBX_STR, O_OPT,	null,	null,	null),
		"group_rights"=>array(T_ZBX_STR, O_OPT,	null,	null,	null),

/* actions */
		"register"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	
					IN('"add permission","delete permission"'), null),

		"save"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"delete"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"delete_selected"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"del_user_group"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"del_user_media"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		"del_read_only"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"del_read_write"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"del_deny"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		"del_group_user"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		"add_read_only"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"add_read_write"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"add_deny"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		"change_password"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"cancel"=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
/* other */
		"form"=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_refresh"=>array(T_ZBX_STR, O_OPT, null,	null,	null)
	);


	check_fields($fields);

	if(isset($_REQUEST["usrgrpid"]) and 
		DBfetch(DBselect('select id from users_groups where userid='.$USER_DETAILS['userid'].' and usrgrpid='.$_REQUEST["usrgrpid"])))
	{
			access_deny();
	}
?>
<?php
	if($_REQUEST["config"]==0)
	{
		if(isset($_REQUEST["new_group"]))
		{
			$_REQUEST['user_groups'] = get_request('user_groups', array());
			foreach($_REQUEST['new_group'] as $id => $val)
				$_REQUEST['user_groups'][$id] = $val;
		}
		elseif(isset($_REQUEST["new_media"]))
		{
			$_REQUEST["user_medias"] = get_request('user_medias', array());
			array_push($_REQUEST["user_medias"], $_REQUEST["new_media"]);
		}
		elseif(isset($_REQUEST["user_medias"]) && isset($_REQUEST["enable_media"]))
		{
			if(isset($_REQUEST["user_medias"][$_REQUEST["enable_media"]]))
			{
				$_REQUEST["user_medias"][$_REQUEST["enable_media"]]['active'] = 0;
			}
		}
		elseif(isset($_REQUEST["user_medias"]) && isset($_REQUEST["disable_media"]))
		{
			if(isset($_REQUEST["user_medias"][$_REQUEST["disable_media"]]))
			{
				$_REQUEST["user_medias"][$_REQUEST["disable_media"]]['active'] = 1;
			}
		}
		elseif(isset($_REQUEST["save"]))
		{
			$user_groups = get_request('user_groups', array());
			$user_medias = get_request('user_medias', array());

			$_REQUEST["password1"] = get_request("password1", null);
			$_REQUEST["password2"] = get_request("password2", null);

			if(isset($_REQUEST["password1"]) && $_REQUEST["password1"] == "" && $_REQUEST["alias"]!="guest")
			{
				show_error_message(S_ONLY_FOR_GUEST_ALLOWED_EMPTY_PASSWORD);
			}
			elseif($_REQUEST["password1"]!=$_REQUEST["password2"]){
				if(isset($_REQUEST["userid"]))
					show_error_message(S_CANNOT_UPDATE_USER_BOTH_PASSWORDS);
				else
					show_error_message(S_CANNOT_ADD_USER_BOTH_PASSWORDS_MUST);
			} else {
				if(isset($_REQUEST["userid"])){
					$action = AUDIT_ACTION_UPDATE;
					$result=update_user($_REQUEST["userid"],
						$_REQUEST["name"],$_REQUEST["surname"],$_REQUEST["alias"],
						$_REQUEST["password1"],$_REQUEST["url"],$_REQUEST["autologout"],
						$_REQUEST["lang"],$_REQUEST["refresh"],$_REQUEST["user_type"],
						$user_groups, $user_medias);

					show_messages($result, S_USER_UPDATED, S_CANNOT_UPDATE_USER);
				} else {
					$action = AUDIT_ACTION_ADD;
					$result=add_user(
						$_REQUEST["name"],$_REQUEST["surname"],$_REQUEST["alias"],
						$_REQUEST["password1"],$_REQUEST["url"],$_REQUEST["autologout"],
						$_REQUEST["lang"],$_REQUEST["refresh"],$_REQUEST["user_type"],
						$user_groups, $user_medias);

					show_messages($result, S_USER_ADDED, S_CANNOT_ADD_USER);
				}
				if($result){
					add_audit($action,AUDIT_RESOURCE_USER,
						"User alias [".$_REQUEST["alias"].
						"] name [".$_REQUEST["name"]."] surname [".
						$_REQUEST["surname"]."]");
					unset($_REQUEST["form"]);
				}
			}
		}
		elseif(isset($_REQUEST["del_user_media"]))
		{
			$user_medias_to_del = get_request('user_medias_to_del', array());
			foreach($user_medias_to_del as $mediaid)
			{
				if(isset($_REQUEST['user_medias'][$mediaid]))
					unset($_REQUEST['user_medias'][$mediaid]);
			}
			
		}
		elseif(isset($_REQUEST["del_user_group"]))
		{
			$user_groups_to_del = get_request('user_groups_to_del', array());
			foreach($user_groups_to_del as $groupid)
			{
				if(isset($_REQUEST['user_groups'][$groupid]))
					unset($_REQUEST['user_groups'][$groupid]);
			}
			
		}
		elseif(isset($_REQUEST["delete_selected"])&&isset($_REQUEST['group_userid']))
		{
			$group_userid = get_request('group_userid', array());
			foreach($group_userid as $userid)
			{
				if(!($user_data = get_user_by_userid($userid))) continue;

				$result = delete_user($userid);
				show_messages($result, S_USER_DELETED, S_CANNOT_DELETE_USER);
				if($result){
					add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_USER,
					"User alias [".$user_data["alias"]."] name [".$user_data["name"]."] surname [".
					$user_data["surname"]."]");
				}
			}
		}
		elseif(isset($_REQUEST["delete"])&&isset($_REQUEST["userid"]))
		{
			$user=get_user_by_userid($_REQUEST["userid"]);
			$result=delete_user($_REQUEST["userid"]);
			show_messages($result, S_USER_DELETED, S_CANNOT_DELETE_USER);
			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_USER,
					"User alias [".$user["alias"]."] name [".$user["name"]."] surname [".
					$user["surname"]."]");

				unset($_REQUEST["userid"]);
				unset($_REQUEST["form"]);
			}
		}
	}
	else /* config == 1 */
	{
		if(isset($_REQUEST['del_deny'])&&isset($_REQUEST['right_to_del']['deny']))
		{
			$_REQUEST['group_rights'] = get_request('group_rights',array());
			foreach($_REQUEST['right_to_del']['deny'] as $name)
			{
				if(!isset($_REQUEST['group_rights'][$name])) continue;
				if($_REQUEST['group_rights'][$name]['permission'] == PERM_DENY)
					unset($_REQUEST['group_rights'][$name]);
			}
		}
		elseif(isset($_REQUEST['del_read_only'])&&isset($_REQUEST['right_to_del']['read_only']))
		{
			$_REQUEST['group_rights'] = get_request('group_rights',array());
			foreach($_REQUEST['right_to_del']['read_only'] as $name)
			{
				if(!isset($_REQUEST['group_rights'][$name])) continue;
				if($_REQUEST['group_rights'][$name]['permission'] == PERM_READ_ONLY)
					unset($_REQUEST['group_rights'][$name]);
			}
		}
		elseif(isset($_REQUEST['del_read_write'])&&isset($_REQUEST['right_to_del']['read_write']))
		{
			$_REQUEST['group_rights'] = get_request('group_rights',array());
			foreach($_REQUEST['right_to_del']['read_write'] as $name)
			{
				if(!isset($_REQUEST['group_rights'][$name])) continue;
				if($_REQUEST['group_rights'][$name]['permission'] == PERM_READ_WRITE)
					unset($_REQUEST['group_rights'][$name]);
			}
		}
		elseif(isset($_REQUEST["new_right"]))
		{
			$_REQUEST['group_rights'] = get_request('group_rights', array());
			foreach(array('type', 'id', 'permission') as $fld_name)
				$_REQUEST['group_rights'][$_REQUEST['new_right']['name']][$fld_name] = $_REQUEST['new_right'][$fld_name];
		}
		elseif(isset($_REQUEST["new_user"]))
		{
			$_REQUEST['group_users'] = get_request('group_users', array());
			$_REQUEST['group_users'][$_REQUEST['new_user']['userid']] = $_REQUEST['new_user']['alias'];
		}
		elseif(isset($_REQUEST["del_group_user"])&&isset($_REQUEST['group_users_to_del']))
		{
			foreach($_REQUEST['group_users_to_del'] as $userid)
				if(isset($_REQUEST['group_users'][$userid]))
					unset($_REQUEST['group_users'][$userid]);
		}
		elseif(isset($_REQUEST["save"]))
		{
			$group_users	= get_request("group_users", array());;
			$group_rights	= get_request("group_rights", array());;

			if(isset($_REQUEST["usrgrpid"])){
				$action = AUDIT_ACTION_UPDATE;
				$result=update_user_group($_REQUEST["usrgrpid"], $_REQUEST["gname"], $group_users, $group_rights);
				show_messages($result, S_GROUP_UPDATED, S_CANNOT_UPDATE_GROUP);
			}else{
				$action = AUDIT_ACTION_ADD;
				$result=add_user_group($_REQUEST["gname"], $group_users, $group_rights);
				show_messages($result, S_GROUP_ADDED, S_CANNOT_ADD_GROUP);
			}

			if($result){
				add_audit($action,AUDIT_RESOURCE_USER_GROUP,"Group name [".$_REQUEST["gname"]."]");
				unset($_REQUEST["form"]);
			}
		}
		elseif(isset($_REQUEST["delete_selected"])&&isset($_REQUEST['group_groupid']))
		{
			$group_groupid = get_request('group_groupid', array());
			foreach($group_groupid as $usrgrpid)
			{
				if(!($group = get_group_by_usrgrpid($usrgrpid))) continue;

				$result = delete_user_group($usrgrpid);
				show_messages($result, S_GROUP_DELETED, S_CANNOT_DELETE_GROUP);
				if($result){
					add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_USER_GROUP,"Group name [".$group["name"]."]");
				}
			}
		}
		elseif(isset($_REQUEST["delete"]))
		{
			$group = get_group_by_usrgrpid($_REQUEST["usrgrpid"]);

			$result=delete_user_group($_REQUEST["usrgrpid"]);
			show_messages($result, S_GROUP_DELETED, S_CANNOT_DELETE_GROUP);
			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_USER_GROUP,"Group name [".$group["name"]."]");

				unset($_REQUEST["usrgrpid"]);
				unset($_REQUEST["form"]);
			}
		}
	}
?>
<?php
	$frmForm = new CForm();

	$cmbConf = new CComboBox("config",$_REQUEST["config"],"submit()");
	$cmbConf->AddItem(0,S_USERS);
	$cmbConf->AddItem(1,S_USER_GROUPS);

	$frmForm->AddItem($cmbConf);
	$frmForm->AddItem(SPACE."|".SPACE);
	$frmForm->AddItem($btnNew = new CButton("form",($_REQUEST["config"] == 0) ? S_CREATE_USER : S_CREATE_GROUP));
	show_table_header(S_CONFIGURATION_OF_USERS_AND_USER_GROUPS, $frmForm);
	echo BR; 
?>
<?php
	if($_REQUEST["config"]==0)
	{
		if(isset($_REQUEST["form"]))
		{
			insert_user_form(get_request("userid",null));
		}
		else
		{
			$form = new CForm(null,'post');
			$form->SetName('users');

			show_table_header(S_USERS_BIG);
			$table=new CTableInfo(S_NO_USERS_DEFINED);
			$table->setHeader(array(
				 array(  new CCheckBox("all_users",NULL,
                                        "CheckAll('".$form->GetName()."','all_users');"),
					S_ALIAS
				),
				S_NAME,S_SURNAME,S_USER_TYPE,S_GROUPS,S_IS_ONLINE_Q));
		
			$db_users=DBselect("select userid,alias,name,surname,type,autologout ".
				" from users where ".DBid2nodeid('userid')."=".$ZBX_CURNODEID.
				" order by alias");
			while($db_user=DBfetch($db_users))
			{
				$db_sessions = DBselect("select count(*) as count, max(s.lastaccess) as lastaccess".
					" from sessions s, users u".
					" where s.userid=".$db_user["userid"]." and s.userid=u.userid and (s.lastaccess+u.autologout)>=".time());
				$db_ses_cnt=DBfetch($db_sessions);

				if($db_ses_cnt["count"]>0 || $db_user["autologout"] == 0)
					$online=new CCol(S_YES.' ('.date('r',$db_ses_cnt['lastaccess']).')',"enabled");
				else
					$online=new CCol(S_NO,"disabled");
				
				$user_groups = array();
				$db_groups = DBselect("select g.name from usrgrp g, users_groups ug".
					" where g.usrgrpid=ug.usrgrpid and ug.userid=".$db_user['userid']);
				while($db_group = DBfetch($db_groups))
					array_push($user_groups,$db_group['name']);
					
		
				$table->addRow(array(
					array(
						new CCheckBox("group_userid[]",NULL,NULL,$db_user["userid"]),
						new CLink($db_user["alias"],
							"users.php?form=update".url_param("config").
							"&userid=".$db_user["userid"]."#form", 'action')
					),
					$db_user["name"],
					$db_user["surname"],
					user_type2str($db_user['type']),
					implode(BR,$user_groups),
					$online
					));
			}
			$table->SetFooter(new CCol(new CButton('delete_selected',S_DELETE_SELECTED,
				"return Confirm('".S_DELETE_SELECTED_USERS_Q."');")));

			$form->AddItem($table);
			$form->show();
		}
	}
	elseif($_REQUEST["config"]==1)
	{
		if(isset($_REQUEST["form"]))
		{
			insert_usergroups_form();
		}
		else
		{
			show_table_header(S_USER_GROUPS_BIG);
			$form = new CForm();

			$table = new CTableInfo(S_NO_USER_GROUPS_DEFINED);
			$table->setHeader(array(
				 array(  new CCheckBox("all_groups",NULL,
                                        "CheckAll('".$form->GetName()."','all_groups');"),
					S_NAME),
				S_MEMBERS));
		
			$result=DBselect("select usrgrpid,name from usrgrp".
					" where ".DBid2nodeid('usrgrpid')."=".$ZBX_CURNODEID.
					" order by name");
			while($row=DBfetch($result))
			{
				$users = array();

				$db_users=DBselect("select distinct u.alias,u.userid from users u,users_groups ug ".
					"where u.userid=ug.userid and ug.usrgrpid=".$row["usrgrpid"].
					" order by alias");

				while($db_user=DBfetch($db_users))	$users[$db_user['userid']] = $db_user["alias"];
				if(isset($users[$USER_DETAILS['userid']])) continue;

				$table->addRow(array(
					array(
						 new CCheckBox("group_groupid[]",NULL,NULL,$row["usrgrpid"]),
						$alias = new CLink($row["name"],
							"users.php?form=update".url_param("config").
							"&usrgrpid=".$row["usrgrpid"]."#form", 'action')
					),
					implode(', ',$users)));
			}
			$table->SetFooter(new CCol(new CButton('delete_selected',S_DELETE_SELECTED,
				"return Confirm('".S_DELETE_SELECTED_GROUPS_Q."');")));

			$form->AddItem($table);
			$form->Show();
		}
	}
?>
<?php

include_once "include/page_footer.php"

?>
