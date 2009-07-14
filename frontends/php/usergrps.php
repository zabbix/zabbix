<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
	require_once('include/media.inc.php');
	require_once('include/users.inc.php');
	require_once('include/forms.inc.php');
	require_once('include/js.inc.php');

	$page['title'] = 'S_USER_GROUPS';
	$page['file'] = 'usergrps.php';
	$page['hist_arg'] = array('config');
	$page['scripts'] = array('menu_scripts.js');

include_once('include/page_header.php');

	$_REQUEST['go'] = get_request('go','none');
	$_REQUEST['config'] = get_request('config','usergrps.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'config'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,		NULL),
		
		'perm_details'=>	array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),
		
		'grpaction'=>		array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),
/* group */
		'usrgrpid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		'isset({grpaction})&&(isset({form})&&({form}=="update"))'),
		'group_groupid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),
		'selusrgrp'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),

		'gname'=>				array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,		'isset({save})'),
		'users'=>				array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			null),
		'users_status'=>		array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),		'isset({save})'),
		'gui_access'=>			array(T_ZBX_INT, O_OPT,	null,	IN('0,1,2'),	'isset({save})'),
		'api_access'=>			array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),		'isset({save})'),
		'debug_mode'=>			array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),		'isset({save})'),
		'new_right'=>			array(T_ZBX_STR, O_OPT,	null,	null,			null),
		'right_to_del'=>		array(T_ZBX_STR, O_OPT,	null,	null,			null),
		'group_users_to_del'=>	array(T_ZBX_STR, O_OPT,	null,	null,			null),
		'group_users'=>			array(T_ZBX_STR, O_OPT,	null,	null,			null),
		'group_rights'=>		array(T_ZBX_STR, O_OPT,	null,	null,			null),

		'set_users_status'=>	array(T_ZBX_INT, O_OPT,	null,	IN('0,1'), null),
		'set_gui_access'=>		array(T_ZBX_INT, O_OPT,	null,	IN('0,1,2'), null),
		'set_api_access'=>		array(T_ZBX_INT, O_OPT,	null,	IN('0,1'), null),
		'set_debug_mode'=>		array(T_ZBX_INT, O_OPT,	null,	IN('0,1'), null),

// Actions
		'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),

// form
		'register'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, IN('"add permission","delete permission"'), null),

		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
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
	else if(isset($_REQUEST['new_right'])){
		$_REQUEST['group_rights'] = get_request('group_rights', array());
		foreach($_REQUEST['new_right'] as $id => $right) {
			$_REQUEST['group_rights'][$right['name']] = array('id' => $id, 'permission' => $right['permission']);
		}
	}
	else if(isset($_REQUEST['save'])){
		$group_users	= get_request('group_users', array());
		$group_rights	= get_request('group_rights', array());

		if(isset($_REQUEST['usrgrpid'])){
			$action = AUDIT_ACTION_UPDATE;

			DBstart();
			$result = update_user_group($_REQUEST['usrgrpid'], $_REQUEST['gname'], $_REQUEST['users_status'], $_REQUEST['gui_access'], $_REQUEST['api_access'], $_REQUEST['debug_mode'],$group_users, $group_rights);
			$result = DBend($result);

			show_messages($result, S_GROUP_UPDATED, S_CANNOT_UPDATE_GROUP);
		}
		else{
			$action = AUDIT_ACTION_ADD;

			DBstart();
			$result = add_user_group($_REQUEST['gname'], $_REQUEST['users_status'], $_REQUEST['gui_access'], $_REQUEST['api_access'], $_REQUEST['debug_mode'],$group_users, $group_rights);
			$result = DBend($result);

			show_messages($result, S_GROUP_ADDED, S_CANNOT_ADD_GROUP);
		}

		if($result){
			add_audit($action,AUDIT_RESOURCE_USER_GROUP,'Group name ['.$_REQUEST['gname'].']');
			unset($_REQUEST['form']);
		}
	}
	else if(isset($_REQUEST['delete'])){
		$group = get_group_by_usrgrpid($_REQUEST['usrgrpid']);

		DBstart();
		$result = delete_user_group($_REQUEST['usrgrpid']);
		$result = DBend($result);

		show_messages($result, S_GROUP_DELETED, S_CANNOT_DELETE_GROUP);
		if($result){
			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_USER_GROUP,'Group name ['.$group['name'].']');

			unset($_REQUEST['usrgrpid']);
			unset($_REQUEST['form']);
		}
	}
// -------- GO ---------
	else if($_REQUEST['go'] == 'delete'){
		$groupids = get_request('group_groupid', get_request('usrgrpid'));
		zbx_value2array($groupids);
		
		$groups = array();
		$sql = 'SELECT ug.usrgrpid, ug.name '.
				' FROM usrgrp ug '.
				' WHERE '.DBin_node('ug.usrgrpid').
					' AND '.DBcondition('ug.usrgrpid',$groupids);
		$res = DBselect($sql);
		while($group = DBfetch($res)){
			$groups[$group['usrgrpid']] = $group;
		}
		
		if(!empty($groups)){
			DBstart();
			$result = delete_user_group($groupids,$_REQUEST['set_gui_access']);
			$result = DBend($result);
			
			if($result){
				$audit_action = ($_REQUEST['set_gui_access'] == GROUP_GUI_ACCESS_DISABLED)?AUDIT_ACTION_DISABLE:AUDIT_ACTION_UPDATE;
				foreach($groups as $groupid => $group){
					add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_USER_GROUP,'Group name ['.$group['name'].']');
				}
			}
			
			show_messages($result, S_GROUP_DELETED, S_CANNOT_DELETE_GROUP);
		}
	}
	else if($_REQUEST['go'] == 'set_gui_access'){
		$groupids = get_request('group_groupid', get_request('usrgrpid'));
		zbx_value2array($groupids);
		
		$groups = array();
		$sql = 'SELECT ug.usrgrpid, ug.name '.
				' FROM usrgrp ug '.
				' WHERE '.DBin_node('ug.usrgrpid').
					' AND '.DBcondition('ug.usrgrpid',$groupids);
		$res = DBselect($sql);
		while($group = DBfetch($res)){
			$groups[$group['usrgrpid']] = $group;
		}
		
		if(!empty($groups)){
			DBstart();
			$result = change_group_gui_access($groupids,$_REQUEST['set_gui_access']);
			$result = DBend($result);
			
			if($result){
				$audit_action = ($_REQUEST['set_gui_access'] == GROUP_GUI_ACCESS_DISABLED)?AUDIT_ACTION_DISABLE:AUDIT_ACTION_ENABLE;
				foreach($groups as $groupid => $group){
					add_audit($audit_action,AUDIT_RESOURCE_USER_GROUP,'GUI access for group name ['.$group['name'].']');
				}
			}
			
			show_messages($result, S_GUI_ACCESS_UPDATED, S_CANNOT_UPDATE_GUI_ACCESS);
		}
	}
	else if(str_in_array($_REQUEST['go'], array('enable_api', 'disable_api'))){
		$groupids = get_request('group_groupid', get_request('usrgrpid'));
		zbx_value2array($groupids);
		
		$set_api_access = ($_REQUEST['go'] == 'enable_api')?GROUP_API_ACCESS_ENABLED:GROUP_API_ACCESS_DISABLED;
		
		$groups = array();
		$sql = 'SELECT ug.usrgrpid, ug.name '.
				' FROM usrgrp ug '.
				' WHERE '.DBin_node('ug.usrgrpid').
					' AND '.DBcondition('ug.usrgrpid',$groupids);
		$res = DBselect($sql);
		while($group = DBfetch($res)){
			$groups[$group['usrgrpid']] = $group;
		}
		
		if(!empty($groups)){
			DBstart();
			$result = change_group_api_access($groupids,$set_api_access);
			$result = DBend($result);
			
			if($result){
				$audit_action = ($set_api_access == GROUP_API_ACCESS_DISABLED)?AUDIT_ACTION_DISABLE:AUDIT_ACTION_ENABLE;
				foreach($groups as $groupid => $group){
					add_audit($audit_action,AUDIT_RESOURCE_USER_GROUP,'API access for group name ['.$group['name'].']');
				}
			}
			
			show_messages($result, S_API_ACCESS_UPDATED, S_CANNOT_UPDATE_API_ACCESS);
		}
	}
	else if(str_in_array($_REQUEST['go'], array('enable_debug', 'disable_debug'))){
		$groupids = get_request('group_groupid', get_request('usrgrpid'));
		zbx_value2array($groupids);
		
		$set_debug_mode = ($_REQUEST['go'] == 'enable_debug')?GROUP_DEBUG_MODE_ENABLED:GROUP_DEBUG_MODE_DISABLED;
		
		$groups = array();
		$sql = 'SELECT ug.usrgrpid, ug.name '.
				' FROM usrgrp ug '.
				' WHERE '.DBin_node('ug.usrgrpid').
					' AND '.DBcondition('ug.usrgrpid',$groupids);
		$res = DBselect($sql);
		while($group = DBfetch($res)){
			$groups[$group['usrgrpid']] = $group;
		}
		
		if(!empty($groups)){
			DBstart();
			$result = change_group_debug_mode($groupids,$set_debug_mode);
			$result = DBend($result);
			
			if($result){
				$audit_action = ($set_debug_mode == GROUP_DEBUG_MODE_DISABLED)?AUDIT_ACTION_DISABLE:AUDIT_ACTION_ENABLE;

				foreach($groups as $groupid => $group){
					add_audit($audit_action,AUDIT_RESOURCE_USER_GROUP,'Debug mode for group name ['.$group['name'].']');
				}
			}
			
			show_messages($result, S_DEBUG_MODE_UPDATED, S_CANNOT_UPDATE_DEBUG_MODE);
		}
	}
	else if(str_in_array($_REQUEST['go'], array('enable_status', 'disable_status'))){
		$groupids = get_request('group_groupid', get_request('usrgrpid'));
		zbx_value2array($groupids);
		
		$set_users_status = ($_REQUEST['go'] == 'enable_status')?GROUP_STATUS_ENABLED:GROUP_STATUS_DISABLED;
		
		$groups = array();
		$sql = 'SELECT ug.usrgrpid, ug.name '.
				' FROM usrgrp ug '.
				' WHERE '.DBin_node('ug.usrgrpid').
					' AND '.DBcondition('ug.usrgrpid',$groupids);
		$res = DBselect($sql);
		while($group = DBfetch($res)){
			$groups[$group['usrgrpid']] = $group;
		}
		
		if(!empty($groups)){
			DBstart();
			$result = change_group_status($groupids,$set_users_status);
			$result = DBend($result);
			
			if($result){
				$audit_action = ($set_users_status == GROUP_STATUS_ENABLED)?AUDIT_ACTION_ENABLE:AUDIT_ACTION_DISABLE;
				foreach($groups as $groupid => $group){
					add_audit($audit_action,AUDIT_RESOURCE_USER_GROUP,'User status for group name ['.$group['name'].']');
				}
			}
			
			show_messages($result, S_USERS_STATUS_UPDATED, S_CANNOT_UPDATE_USERS_STATUS);
		}
	}
?>
<?php
	
	$frmForm = new CForm();
	$frmForm->setMethod('get');
	
// Config
	$cmbConf = new CComboBox('config','usergrps.php','javascript: submit()');
	$cmbConf->setAttribute('onchange','javascript: redirect(this.options[this.selectedIndex].value);');	
		$cmbConf->addItem('usergrps.php',S_USER_GROUPS);
		$cmbConf->addItem('users.php',S_USERS);

	$frmForm->addItem($cmbConf);

	$frmForm->addItem(SPACE.'|'.SPACE);
	$frmForm->addItem($btnNew = new CButton('form', S_CREATE_GROUP));
	show_table_header(S_CONFIGURATION_OF_USERS_AND_USER_GROUPS, $frmForm);
	echo SBR;
?>
<?php
	$row_count = 0;

	if(isset($_REQUEST['form'])){
		insert_usergroups_form();
	}
	else{
		$numrows = new CSpan(null,'info');
		$numrows->setAttribute('name','numrows');
		$header = get_table_header(array(S_USER_GROUPS_BIG,
						new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),
						S_FOUND.': ',$numrows,)
						);
		show_table_header($header);
		$form = new CForm();
		$form->setName('usrgrp_form');

		$table = new CTableInfo(S_NO_USER_GROUPS_DEFINED);
		$table->setHeader(array(
			new CCheckBox('all_groups',NULL, "checkAll('".$form->GetName()."','all_groups','group_groupid');"),
			make_sorting_link(S_NAME,'ug.name'),
			'#',
			S_MEMBERS,
			S_USERS_STATUS,
			S_GUI_ACCESS,
			S_API_ACCESS,
			S_DEBUG_MODE
			));

		$usrgrps = array();
		$usrgrpids = array();
		$sql = 'SELECT ug.usrgrpid, ug.name, ug.users_status, ug.gui_access, ug.api_access, ug.debug_mode '.
				' FROM usrgrp ug'.
				' WHERE '.DBin_node('ug.usrgrpid').
				order_by('ug.name');
		$result=DBselect($sql);
		while($usrgrp=DBfetch($result)){
			$usrgrp['users'] = '';
			$usrgrp['userids'] = array();
			$usrgrps[$usrgrp['usrgrpid']] = $usrgrp;
			$usrgrpids[$usrgrp['usrgrpid']] = $usrgrp['usrgrpid'];
		}
		
		
		$users = array();
		$userids = array();
		$sql = 'SELECT u.alias,u.userid,ug.usrgrpid '.
				' FROM users u,users_groups ug '.
				' WHERE u.userid=ug.userid '.
					' AND '.DBcondition('ug.usrgrpid',$usrgrpids).
				' ORDER BY u.alias';
		$db_users=DBselect($sql);
		while($db_user=DBfetch($db_users)){
			$usrgrps[$db_user['usrgrpid']]['userids'][$db_user['userid']] = $db_user['userid'];

			$user_link = new Clink($db_user['alias'],'usergrps.php?form=update&config=0&userid='.$db_user['userid'].'#form');
			
			if(!empty($usrgrps[$db_user['usrgrpid']]['users']))	$usrgrps[$db_user['usrgrpid']]['users'][] = ', ';
			$usrgrps[$db_user['usrgrpid']]['users'][] = $user_link;
		}

		foreach($usrgrps as $usrgrpid => $row){
			$gui_access = user_auth_type2str($row['gui_access']);
			$api_access = ($row['api_access'] == GROUP_API_ACCESS_DISABLED) ? S_DISABLED : S_ENABLED;
			$debug_mode = ($row['debug_mode'] == GROUP_DEBUG_MODE_DISABLED) ? S_DISABLED : S_ENABLED;
			$users_status = ($row['users_status'] == GROUP_STATUS_ENABLED) ? S_ENABLED : S_DISABLED;

			if(granted2update_group($usrgrpid)){

				$next_gui_auth = ($row['gui_access']+1 > GROUP_GUI_ACCESS_DISABLED)?GROUP_GUI_ACCESS_SYSTEM:($row['gui_access']+1);

				$gui_access = new CLink($gui_access,
							'usergrps.php?go=set_gui_access'.
							'&set_gui_access='.$next_gui_auth.
							'&usrgrpid='.$usrgrpid,
							($row['gui_access'] == GROUP_GUI_ACCESS_DISABLED)?'orange':'enabled');

				$users_status = new CLink($users_status,
							'usergrps.php?go='.(($row['users_status'] == GROUP_STATUS_ENABLED)?'disable_status':'enable_status').
							'&usrgrpid='.$usrgrpid,
							($row['users_status'] == GROUP_STATUS_ENABLED)?'enabled':'disabled');

			}
			else{
				$gui_access = new CSpan($gui_access,($row['gui_access'] == GROUP_GUI_ACCESS_DISABLED)?'orange':'green');
				$users_status = new CSpan($users_status,($row['users_status'] == GROUP_STATUS_ENABLED)?'green':'red');
			}

			$api_access = new CLink($api_access,
						'usergrps.php?go='.(($row['api_access'] == GROUP_API_ACCESS_DISABLED)?'enable_api':'disable_api').
						'&usrgrpid='.$usrgrpid,
						($row['api_access'] == GROUP_API_ACCESS_DISABLED)?'enabled':'orange');
			$debug_mode = new CLink($debug_mode,
						'usergrps.php?go='.(($row['debug_mode']==GROUP_DEBUG_MODE_DISABLED)?'enable_debug':'disable_debug').
						'&usrgrpid='.$usrgrpid,
						($row['debug_mode'] == GROUP_DEBUG_MODE_DISABLED)?'enabled':'orange');


			$table->addRow(array(
				new CCheckBox('group_groupid['.$usrgrpid.']',NULL,NULL,$usrgrpid),
				new CLink($row['name'],'usergrps.php?form=update&usrgrpid='.$usrgrpid.'#form'),
				array(new CLink(S_USERS,'users.php?&filter_usrgrpid='.$usrgrpid),' (',count($row['userids']),')'),
				new CCol($row['users'],'wraptext'),
				$users_status,
				$gui_access,
				$api_access,
				$debug_mode
				));
			$row_count++;
		}
		
//----- GO ------
		$goBox = new CComboBox('go');
		$goBox->addItem('enable_status',S_ENABLE_SELECTED);
		$goBox->addItem('disable_status',S_DISABLE_SELECTED);
		$goBox->addItem('enable_api',S_ENABLE_API);
		$goBox->addItem('disable_api',S_DISABLE_API);
		$goBox->addItem('enable_debug',S_ENABLE_DEBUG);
		$goBox->addItem('disable_debug',S_DISABLE_DEBUG);
		$goBox->addItem('delete',S_DELETE_SELECTED);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO.' (0)');
		$goButton->setAttribute('id','goButton');
		zbx_add_post_js('chkbxRange.pageGoName = "group_groupid";');

		$table->setFooter(new CCol(array($goBox, $goButton)));
//----

		$form->addItem($table);
		$form->show();
	}

	zbx_add_post_js('insert_in_element("numrows","'.$row_count.'");');

?>
<?php

include_once 'include/page_footer.php'

?>