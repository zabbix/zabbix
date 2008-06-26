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
	require_once "include/js.inc.php";

	$page["title"] = "S_USERS";
	$page["file"] = "users.php";
	$page['hist_arg'] = array('config');

include_once "include/page_header.php";

?>
<?php
	$_REQUEST["config"]=get_request("config",get_profile("web.users.config",0));
	update_profile("web.users.config",$_REQUEST["config"],PROFILE_TYPE_INT);
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'config'=>	array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),
		'perm_details'=>array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),
/* user */
		'userid'=>	array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,'(isset({config})&&({config}==0))&&(isset({form})&&({form}=="update"))'),
		'group_userid'=>array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),

		'alias'=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'(isset({config})&&({config}==0))&&isset({save})'),
		'name'=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'(isset({config})&&({config}==0))&&isset({save})'),
		'surname'=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'(isset({config})&&({config}==0))&&isset({save})'),
		'password1'=>	array(T_ZBX_STR, O_OPT,	null,	null,		'(isset({config})&&({config}==0))&&isset({save})&&(isset({form})&&({form}!="update"))&&isset({change_password})'),
		"password2"=>	array(T_ZBX_STR, O_OPT,	null,	null,		'(isset({config})&&({config}==0))&&isset({save})&&(isset({form})&&({form}!="update"))&&isset({change_password})'),
		'user_type'=>	array(T_ZBX_INT, O_OPT,	null,	IN('1,2,3'),	'(isset({config})&&({config}==0))&&isset({save})'),
		'user_groups'=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),//'(isset({config})&&({config}==0))&&isset({save})'),
		'user_groups_to_del'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,	null),
		'user_medias'=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),
		'user_medias_to_del'=>	array(T_ZBX_STR, O_OPT,	null,	DB_ID,	null),
		'new_group'=>	array(T_ZBX_STR, O_OPT,	null,	null,	null),
		'new_media'=>	array(T_ZBX_STR, O_OPT,	null,	null,	null),
		'enable_media'=>array(T_ZBX_INT, O_OPT,	null,	null,		null),
		'disable_media'=>array(T_ZBX_INT, O_OPT,null,	null,		null),
		'lang'=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'(isset({config})&&({config}==0))&&isset({save})'),
		'theme'=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'(isset({config})&&({config}==0))&&isset({save})'),
		'autologin'=>	array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),
		'autologout'=>	array(T_ZBX_INT, O_OPT,	null,	BETWEEN(0,3600),'(isset({config})&&({config}==0))&&isset({save})'),
		'url'=>		array(T_ZBX_STR, O_OPT,	null,	null,		'(isset({config})&&({config}==0))&&isset({save})'),
		'refresh'=>	array(T_ZBX_INT, O_OPT,	null,	BETWEEN(0,3600),'(isset({config})&&({config}==0))&&isset({save})'),

		'right'=>	array(T_ZBX_STR, O_NO,	null,	NOT_EMPTY,
					'(isset({register})&&({register}=="add permission"))&&isset({userid})'),
		'permission'=>	array(T_ZBX_STR, O_NO,	null,	NOT_EMPTY,
					'(isset({register})&&({register}=="add permission"))&&isset({userid})'),
		'id'=>		array(T_ZBX_INT, O_NO,	null,	DB_ID,
					'(isset({register})&&({register}=="add permission"))&&isset({userid})'),
		'rightid'=>	array(T_ZBX_INT, O_NO,  null,   DB_ID,
                                        '(isset({register})&&({register}=="delete permission"))&&isset({userid})'),
		'grpaction'=>	array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),
/* group */
		'usrgrpid'=>	array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,'(isset({config})&&(({config}==1) || isset({grpaction})))&&(isset({form})&&({form}=="update"))'),
		'group_groupid'=>array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),

		'gname'=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'(isset({config})&&({config}==1))&&isset({save})'),
		'users'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	null),
		'users_status'=>array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	'(isset({config})&&({config}==1))&&isset({save})'),
		'gui_access'=>	array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	'(isset({config})&&({config}==1))&&isset({save})'),
		'new_right'=>	array(T_ZBX_STR, O_OPT,	null,	null,	null),
		'new_user'=>	array(T_ZBX_STR, O_OPT,	null,	null,	null),
		'right_to_del'=>array(T_ZBX_STR, O_OPT,	null,	null,	null),
		'group_users_to_del'=>	array(T_ZBX_STR, O_OPT,	null,	null,	null),
		'group_users'=>	array(T_ZBX_STR, O_OPT,	null,	null,	null),
		'group_rights'=>array(T_ZBX_STR, O_OPT,	null,	null,	null),
		
		'set_users_status'=>	array(T_ZBX_INT, O_OPT,	null,	IN('0,1'), null),
		'set_gui_access'=>		array(T_ZBX_INT, O_OPT,	null,	IN('0,1'), null),

/* actions */
		'register'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	
					IN('"add permission","delete permission"'), null),

		'save'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete_selected'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_user_group'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_user_media'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		'del_read_only'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_read_write'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_deny'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		'del_group_user'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		'add_read_only'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'add_read_write'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'add_deny'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		'change_password'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
/* other */
		'form'=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>array(T_ZBX_STR, O_OPT, null,	null,	null)
	);


	check_fields($fields);
	validate_sort_and_sortorder('u.alias',ZBX_SORT_UP);	
?>
<?php
	if($_REQUEST['config']==0){
		if(isset($_REQUEST['new_group'])){
			$_REQUEST['user_groups'] = get_request('user_groups', array());
			foreach($_REQUEST['new_group'] as $id => $val)
				$_REQUEST['user_groups'][$id] = $val;
		}
		else if(isset($_REQUEST['new_media'])){
			$_REQUEST['user_medias'] = get_request('user_medias', array());
			array_push($_REQUEST['user_medias'], $_REQUEST['new_media']);
		}
		else if(isset($_REQUEST['user_medias']) && isset($_REQUEST['enable_media'])){
			if(isset($_REQUEST['user_medias'][$_REQUEST['enable_media']])){
				$_REQUEST['user_medias'][$_REQUEST['enable_media']]['active'] = 0;
			}
		}
		else if(isset($_REQUEST['user_medias']) && isset($_REQUEST['disable_media'])){
			if(isset($_REQUEST['user_medias'][$_REQUEST['disable_media']])){
				$_REQUEST['user_medias'][$_REQUEST['disable_media']]['active'] = 1;
			}
		}
		else if(isset($_REQUEST['save'])){
			$user_groups = get_request('user_groups', array());
			$user_medias = get_request('user_medias', array());

			$_REQUEST["password1"] = get_request("password1", null);
			$_REQUEST["password2"] = get_request("password2", null);

			if(isset($_REQUEST["password1"]) && empty($_REQUEST["password1"]) && $_REQUEST["alias"]!=ZBX_GUEST_USER){
				show_error_message(S_ONLY_FOR_GUEST_ALLOWED_EMPTY_PASSWORD);
			}
			else if($_REQUEST["password1"]!=$_REQUEST["password2"]){
				if(isset($_REQUEST["userid"]))
					show_error_message(S_CANNOT_UPDATE_USER_BOTH_PASSWORDS);
				else
					show_error_message(S_CANNOT_ADD_USER_BOTH_PASSWORDS_MUST);
			}
			else if(isset($_REQUEST["password1"]) && $_REQUEST["password1"] != "" && $_REQUEST["alias"]==ZBX_GUEST_USER){
				show_error_message(S_FOR_GUEST_PASSWORD_MUST_BE_EMPTY);
			}
			else {
				if(isset($_REQUEST["userid"])){
					$action = AUDIT_ACTION_UPDATE;
					DBstart();
					$result=update_user($_REQUEST["userid"],
						$_REQUEST["name"],$_REQUEST["surname"],$_REQUEST["alias"],
						$_REQUEST["password1"],$_REQUEST["url"],get_request("autologin",0),$_REQUEST["autologout"],
						$_REQUEST["lang"],$_REQUEST['theme'],$_REQUEST["refresh"],$_REQUEST["user_type"],
						$user_groups, $user_medias);
					$result = DBend();
					
					show_messages($result, S_USER_UPDATED, S_CANNOT_UPDATE_USER);
				} 
				else {
					$action = AUDIT_ACTION_ADD;
					DBstart();
					$result=add_user(
						$_REQUEST["name"],$_REQUEST["surname"],$_REQUEST["alias"],
						$_REQUEST["password1"],$_REQUEST["url"],get_request("autologin",0),$_REQUEST["autologout"],
						$_REQUEST["lang"],$_REQUEST['theme'],$_REQUEST["refresh"],$_REQUEST["user_type"],
						$user_groups, $user_medias);
					$result = DBend();
					
					show_messages($result, S_USER_ADDED, S_CANNOT_ADD_USER);
				}
				if($result){
					add_audit($action,AUDIT_RESOURCE_USER,"User alias [".$_REQUEST["alias"]."] name [".$_REQUEST["name"]."] surname [".$_REQUEST["surname"]."]");
					unset($_REQUEST["form"]);
				}
			}
		}
		else if(isset($_REQUEST["del_user_media"])){
			$user_medias_to_del = get_request('user_medias_to_del', array());
			foreach($user_medias_to_del as $mediaid){
				if(isset($_REQUEST['user_medias'][$mediaid]))
					unset($_REQUEST['user_medias'][$mediaid]);
			}
			
		}
		else if(isset($_REQUEST["del_user_group"])){
			$user_groups_to_del = get_request('user_groups_to_del', array());
			foreach($user_groups_to_del as $groupid){
				if(isset($_REQUEST['user_groups'][$groupid]))
					unset($_REQUEST['user_groups'][$groupid]);
			}
			
		}
		else if(isset($_REQUEST["delete_selected"])&&isset($_REQUEST['group_userid'])){
			$result = false;

			$group_userid = get_request('group_userid', array());
			foreach($group_userid as $userid){
				if(!($user_data = get_user_by_userid($userid))) continue;

				DBstart();
				$result = delete_user($userid);
				$result = DBend();
				if($result){
					add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_USER,
						"User alias [".$user_data["alias"]."] name [".$user_data["name"]."] surname [".
						$user_data["surname"]."]");
				}
			}
			
			show_messages($result, S_USER_DELETED,NULL);
		}
		else if(isset($_REQUEST["delete"])&&isset($_REQUEST["userid"])){
			$user=get_user_by_userid($_REQUEST["userid"]);
			
			DBstart();
			$result=delete_user($_REQUEST["userid"]);
			$result = DBend();
			
			show_messages($result, S_USER_DELETED, S_CANNOT_DELETE_USER);
			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_USER,
					"User alias [".$user["alias"]."] name [".$user["name"]."] surname [".
					$user["surname"]."]");

				unset($_REQUEST["userid"]);
				unset($_REQUEST["form"]);
			}
		}
// Add USER to GROUP
		else if(isset($_REQUEST['grpaction'])&&isset($_REQUEST['usrgrpid'])&&isset($_REQUEST['userid'])&&($_REQUEST['grpaction']==1)){
			$user=get_user_by_userid($_REQUEST["userid"]);
			$group=get_group_by_usrgrpid($_REQUEST["usrgrpid"]);
			
			DBstart();
			$result=add_user_to_group($_REQUEST['userid'],$_REQUEST['usrgrpid']);
			$result = DBend();
			
			show_messages($result, S_USER_UPDATED, S_CANNOT_UPDATE_USER);
			if($result){
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_USER_GROUP,
					"User alias [".$user["alias"]."] name [".$user["name"]."] surname [".$user["surname"]."]");
				
				unset($_REQUEST["usrgrpid"]);
				unset($_REQUEST["userid"]);
			}
			unset($_REQUEST['grpaction']);
			unset($_REQUEST["form"]);
		}
// Remove USER from GROUP
		else if(isset($_REQUEST['grpaction'])&&isset($_REQUEST['usrgrpid'])&&isset($_REQUEST['userid'])&&($_REQUEST['grpaction']==0)){
			$user=get_user_by_userid($_REQUEST["userid"]);
			$group=get_group_by_usrgrpid($_REQUEST["usrgrpid"]);
			
			DBstart();
			$result=remove_user_from_group($_REQUEST['userid'],$_REQUEST['usrgrpid']);
			$result = DBend();
			
			show_messages($result, S_USER_UPDATED, S_CANNOT_UPDATE_USER);
			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_USER_GROUP,
					"User alias [".$user["alias"]."] name [".$user["name"]."] surname [".$user["surname"]."]");

				unset($_REQUEST["usrgrpid"]);
				unset($_REQUEST["userid"]);
			}
			unset($_REQUEST['grpaction']);
			unset($_REQUEST["form"]);
		}
	}
	else /* config == 1 */{
		if(isset($_REQUEST['del_deny'])&&isset($_REQUEST['right_to_del']['deny'])){
			$_REQUEST['group_rights'] = get_request('group_rights',array());
			foreach($_REQUEST['right_to_del']['deny'] as $name){
				if(!isset($_REQUEST['group_rights'][$name])) continue;
				if($_REQUEST['group_rights'][$name]['permission'] == PERM_DENY)
					unset($_REQUEST['group_rights'][$name]);
			}
		}
		else if(isset($_REQUEST['del_read_only'])&&isset($_REQUEST['right_to_del']['read_only'])){
			$_REQUEST['group_rights'] = get_request('group_rights',array());
			foreach($_REQUEST['right_to_del']['read_only'] as $name){
				if(!isset($_REQUEST['group_rights'][$name])) continue;
				if($_REQUEST['group_rights'][$name]['permission'] == PERM_READ_ONLY)
					unset($_REQUEST['group_rights'][$name]);
			}
		}
		else if(isset($_REQUEST['del_read_write'])&&isset($_REQUEST['right_to_del']['read_write'])){
			$_REQUEST['group_rights'] = get_request('group_rights',array());
			foreach($_REQUEST['right_to_del']['read_write'] as $name){
				if(!isset($_REQUEST['group_rights'][$name])) continue;
				if($_REQUEST['group_rights'][$name]['permission'] == PERM_READ_WRITE)
					unset($_REQUEST['group_rights'][$name]);
			}
		}
		else if(isset($_REQUEST["new_right"])){
			$_REQUEST['group_rights'] = get_request('group_rights', array());
			foreach(array('type', 'id', 'permission') as $fld_name)
				$_REQUEST['group_rights'][$_REQUEST['new_right']['name']][$fld_name] = $_REQUEST['new_right'][$fld_name];
		}
		else if(isset($_REQUEST["new_user"])){
			$_REQUEST['group_users'] = get_request('group_users', array());
			$_REQUEST['group_users'][$_REQUEST['new_user']['userid']] = $_REQUEST['new_user']['alias'];
		}
		else if(isset($_REQUEST["del_group_user"])&&isset($_REQUEST['group_users_to_del'])){
			foreach($_REQUEST['group_users_to_del'] as $userid)
				if(isset($_REQUEST['group_users'][$userid]))
					unset($_REQUEST['group_users'][$userid]);
		}
		else if(isset($_REQUEST["save"])){
			$group_users	= get_request("group_users", array());;
			$group_rights	= get_request("group_rights", array());;

			if(isset($_REQUEST["usrgrpid"])){
				$action = AUDIT_ACTION_UPDATE;
				
				DBstart();
				$result=update_user_group($_REQUEST["usrgrpid"], $_REQUEST["gname"], $_REQUEST['users_status'], $_REQUEST['gui_access'], $group_users, $group_rights);
				$result = DBend();
				
				show_messages($result, S_GROUP_UPDATED, S_CANNOT_UPDATE_GROUP);
			}
			else{
				$action = AUDIT_ACTION_ADD;
				
				DBstart();
				$result=add_user_group($_REQUEST["gname"], $_REQUEST['users_status'], $_REQUEST['gui_access'], $group_users, $group_rights);
				$result = DBend();
				
				show_messages($result, S_GROUP_ADDED, S_CANNOT_ADD_GROUP);
			}

			if($result){
				add_audit($action,AUDIT_RESOURCE_USER_GROUP,"Group name [".$_REQUEST["gname"]."]");
				unset($_REQUEST["form"]);
			}
		}
		else if(isset($_REQUEST["delete_selected"])&&isset($_REQUEST['group_groupid'])){
			$result = false;
			$group_groupid = get_request('group_groupid', array());
			foreach($group_groupid as $usrgrpid){
				if(!($group = get_group_by_usrgrpid($usrgrpid))) continue;

				DBstart();
				$result = delete_user_group($usrgrpid);
				$result = DBend();
				if($result){
					add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_USER_GROUP,"Group name [".$group["name"]."]");
				}
			}
			if($result){
				show_messages($result, S_GROUP_DELETED);
			}
		}
		else if(isset($_REQUEST["delete"])){
			$group = get_group_by_usrgrpid($_REQUEST["usrgrpid"]);
			
			DBstart();
			$result=delete_user_group($_REQUEST["usrgrpid"]);
			$result = DBend();
			
			show_messages($result, S_GROUP_DELETED, S_CANNOT_DELETE_GROUP);
			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_USER_GROUP,"Group name [".$group["name"]."]");

				unset($_REQUEST["usrgrpid"]);
				unset($_REQUEST["form"]);
			}
		}
		else if(isset($_REQUEST['set_gui_access'])&&isset($_REQUEST['usrgrpid'])){
			$group=get_group_by_usrgrpid($_REQUEST["usrgrpid"]);
			$result=change_group_gui_access($_REQUEST["usrgrpid"],$_REQUEST['set_gui_access']);
			
			$status_msg1 = ($_REQUEST['set_gui_access'] == GROUP_GUI_ACCESS_ENABLED)?S_ENABLED:S_DISABLED;
			$status_msg2 = ($_REQUEST['set_gui_access'] == GROUP_GUI_ACCESS_ENABLED)?S_ENABLE:S_DISABLE;
			
			show_messages($result, S_GROUP.SPACE.'"'.$group['name'].'"'.SPACE.S_GUI_ACCESS.SPACE.$status_msg1, S_CANNOT.SPACE.$status_msg2.SPACE.S_GROUP);
			if($result){
				$audit_action = ($_REQUEST['set_gui_access'] == GROUP_GUI_ACCESS_ENABLED)?AUDIT_ACTION_ENABLE:AUDIT_ACTION_DISABLE;
				add_audit($audit_action,AUDIT_RESOURCE_USER_GROUP,'GUI access for group name ['.$group['name'].']');
				
				unset($_REQUEST["usrgrpid"]);
			}
			unset($_REQUEST['form']);
		}
		else if(isset($_REQUEST["set_users_status"])&&isset($_REQUEST["usrgrpid"])){
			$group=get_group_by_usrgrpid($_REQUEST["usrgrpid"]);
			$result=change_group_status($_REQUEST["usrgrpid"],$_REQUEST['set_users_status']);
			
			$status_msg1 = ($_REQUEST['set_users_status'] == GROUP_STATUS_ENABLED)?S_ENABLED:S_DISABLED;
			$status_msg2 = ($_REQUEST['set_users_status'] == GROUP_STATUS_ENABLED)?S_ENABLE:S_DISABLE;
			
			show_messages($result, S_GROUP.SPACE.'"'.$group['name'].'"'.SPACE.$status_msg1, S_CANNOT.SPACE.$status_msg2.SPACE.S_GROUP);
			if($result){
				$audit_action = ($_REQUEST['set_users_status'] == GROUP_STATUS_ENABLED)?AUDIT_ACTION_ENABLE:AUDIT_ACTION_DISABLE;
				add_audit($audit_action,AUDIT_RESOURCE_USER_GROUP,'Group name ['.$group['name'].']');
				
				unset($_REQUEST["usrgrpid"]);
			}
			unset($_REQUEST['form']);
		}
	}
?>
<?php
	$frmForm = new CForm();
	$frmForm->SetMethod('get');
	
	$cmbConf = new CComboBox("config",$_REQUEST["config"],"submit()");
	$cmbConf->AddItem(0,S_USERS);
	$cmbConf->AddItem(1,S_USER_GROUPS);

	$frmForm->AddItem($cmbConf);
	$frmForm->AddItem(SPACE."|".SPACE);
	$frmForm->AddItem($btnNew = new CButton("form",($_REQUEST["config"] == 0) ? S_CREATE_USER : S_CREATE_GROUP));
	show_table_header(S_CONFIGURATION_OF_USERS_AND_USER_GROUPS, $frmForm);
	echo SBR; 
?>
<?php
	if($_REQUEST["config"]==0){
		if(isset($_REQUEST["form"])){
			insert_user_form(get_request("userid",null));
		}
		else{
			$form = new CForm(null,'post');
			$form->SetName('users');

			show_table_header(S_USERS_BIG);
			$table=new CTableInfo(S_NO_USERS_DEFINED);
			$table->setHeader(array(
				array(new CCheckBox("all_users",NULL,
                                        "CheckAll('".$form->GetName()."','all_users','group_userid');"),
					make_sorting_link(S_ALIAS,'u.alias')
				),
				make_sorting_link(S_NAME,'u.name'),
				make_sorting_link(S_SURNAME,'u.surname'),
				make_sorting_link(S_USER_TYPE,'u.type'),
				S_GROUPS,
				S_IS_ONLINE_Q,
				S_GUI_ACCESS,
				S_STATUS,
				S_ACTIONS
				));
		
			$db_users=DBselect('SELECT u.userid,u.alias,u.name,u.surname,u.type,u.autologout '.
							' FROM users u'.
							' WHERE '.DBin_node('u.userid').
							order_by('u.alias,u.name,u.surname,u.type','u.userid'));
			while($db_user=DBfetch($db_users)){
//Log Out 10min or Autologout time
				$online_time = (($db_user['autologout'] == 0) || (ZBX_USER_ONLINE_TIME<$db_user['autologout']))?ZBX_USER_ONLINE_TIME:$db_user['autologout'];
				$online=new CCol(S_NO,"disabled");
				
				$sql = 'SELECT s.lastaccess, s.status'.
						' FROM sessions s, users u'.
						' WHERE s.userid='.$db_user['userid'].
							' AND s.userid=u.userid '.
						' ORDER BY lastaccess DESC';

				$db_sessions = DBselect($sql,1);
				if($db_ses=DBfetch($db_sessions)){
					if((ZBX_SESSION_ACTIVE == $db_ses['status']) && (($db_ses['lastaccess']+$online_time) >= time())){
						$online=new CCol(S_YES.' ('.date('r',$db_ses['lastaccess']).')',"enabled");
					}
					else{
						$online=new CCol(S_NO.' ('.date('r',$db_ses['lastaccess']).')',"disabled");
					}
				}	
				
				$user_groups = array();
				$db_groups = DBselect('SELECT g.name '.
									' FROM usrgrp g, users_groups ug '.
									' WHERE g.usrgrpid=ug.usrgrpid '.
										' and ug.userid='.$db_user['userid']);
										
				while($db_group = DBfetch($db_groups))
					array_push($user_groups,empty($user_groups)?'':BR(),$db_group['name']);
				
				$db_user['users_status'] = check_perm2system($db_user['userid']);
				$db_user['gui_access'] = check_perm2login($db_user['userid']);
				
				$users_status = ($db_user['users_status'])?S_ENABLED:S_DISABLED;
				$gui_access =  ($db_user['gui_access'])?S_ENABLED:S_DISABLED;

				$gui_access = new CSpan($gui_access,($db_user['gui_access'])?'green':'orange');
				$users_status = new CSpan($users_status,($db_user['users_status'])?'green':'red');

				$action = get_user_actionmenu($db_user['userid']);				
/*				if((bccomp($USER_DETAILS['userid'],$db_user['userid']) != 0)){
					$action = get_user_actionmenu($db_user['userid']);
				}
				else{
					$action = new CSpan(S_SELECT);
					$action->AddOption('style','color: #888888;');
				}
//*/		
				$table->addRow(array(
					array(
						new CCheckBox("group_userid[".$db_user["userid"]."]",NULL,NULL,$db_user["userid"]),
						new CLink($db_user["alias"],
							"users.php?form=update".url_param("config").
							"&userid=".$db_user["userid"]."#form", 'action')
					),
					$db_user["name"],
					$db_user["surname"],
					user_type2str($db_user['type']),
					$user_groups,
					$online,
					$gui_access,
					$users_status,
					$action
					));
			}
			$table->SetFooter(new CCol(new CButtonQMessage('delete_selected',S_DELETE_SELECTED,S_DELETE_SELECTED_USERS_Q)));

			$form->AddItem($table);
			$form->show();

			$jsmenu = new CPUMenu(null,270);
			$jsmenu->InsertJavaScript();
		}
	}
	else if($_REQUEST["config"]==1){
		if(isset($_REQUEST["form"])){
			insert_usergroups_form();
		}
		else{
			show_table_header(S_USER_GROUPS_BIG);
			$form = new CForm();

			$table = new CTableInfo(S_NO_USER_GROUPS_DEFINED);
			$table->setHeader(array(
				 array(  new CCheckBox("all_groups",NULL,
                                        "CheckAll('".$form->GetName()."','all_groups');"),
					make_sorting_link(S_NAME,'ug.name')),
				S_MEMBERS,
				S_GUI_ACCESS,
				S_USERS_STATUS
				));
		
			$result=DBselect('SELECT ug.usrgrpid, ug.name, ug.users_status, ug.gui_access '.
							' FROM usrgrp ug'.
							' WHERE '.DBin_node('ug.usrgrpid').
							order_by('ug.name'));
			while($row=DBfetch($result))
			{
				$users = array();
				$users_id = array();

				$db_users=DBselect('SELECT DISTINCT u.alias,u.userid '.
								' FROM users u,users_groups ug '.
								' WHERE u.userid=ug.userid '.
									' AND ug.usrgrpid='.$row['usrgrpid'].
								' ORDER BY u.alias');

				while($db_user=DBfetch($db_users)){
					$users[$db_user['userid']] = $db_user['alias'];
				}
				
				$gui_access =  ($row['gui_access'] == GROUP_GUI_ACCESS_ENABLED)?S_ENABLED:S_DISABLED;				
				$users_status = ($row['users_status'] == GROUP_STATUS_ENABLED)?S_ENABLED:S_DISABLED;
				
				if(granted2update_group($row['usrgrpid'])){
					$gui_access = new CLink($gui_access,
								'users.php?form=update'.
								'&set_gui_access='.(($row['gui_access'] == GROUP_GUI_ACCESS_ENABLED)?GROUP_GUI_ACCESS_DISABLED:GROUP_GUI_ACCESS_ENABLED).
								'&usrgrpid='.$row["usrgrpid"].
								url_param("config"),

								($row['gui_access'] == GROUP_GUI_ACCESS_ENABLED)?'enabled':'orange');

					$users_status = new CLink($users_status,
								'users.php?form=update'.
								'&set_users_status='.(($row['users_status'] == GROUP_STATUS_ENABLED)?GROUP_STATUS_DISABLED:GROUP_STATUS_ENABLED).
								'&usrgrpid='.$row["usrgrpid"].
								url_param("config"),
								($row['users_status'] == GROUP_STATUS_ENABLED)?'enabled':'disabled');

				}
				else{
					$gui_access = new CSpan($gui_access,($row['gui_access'] == GROUP_GUI_ACCESS_ENABLED)?'green':'orange');
					$users_status = new CSpan($users_status,($row['users_status'] == GROUP_STATUS_ENABLED)?'green':'red');
				}
								
				$table->addRow(array(
					array(
						 new CCheckBox('group_groupid['.$row["usrgrpid"].']',NULL,NULL,$row["usrgrpid"]),
						$alias = new CLink($row["name"],
							"users.php?form=update".url_param("config").
							"&usrgrpid=".$row["usrgrpid"]."#form", 'action')
					),
					implode(', ',$users),
					$gui_access,
					$users_status
					));
			}
			$table->SetFooter(new CCol(new CButtonQMessage('delete_selected',S_DELETE_SELECTED,S_DELETE_SELECTED_GROUPS_Q)));

			$form->AddItem($table);
			$form->Show();
		}
	}
?>
<?php

include_once "include/page_footer.php"

?>
