<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
$page['scripts'] = array();

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
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
	validate_sort_and_sortorder('name',ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go','none');
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
			$_REQUEST['group_rights'][$id] = array(
				'name' => $right['name'],
				'permission' => $right['permission'],
				'id' => $id,
			);
		}
	}
	else if(isset($_REQUEST['save'])){
		$usrgrp = array(
			'name' => $_REQUEST['gname'],
			'users_status' => $_REQUEST['users_status'],
			'gui_access' => $_REQUEST['gui_access'],
			'api_access' => $_REQUEST['api_access'],
			'debug_mode' => $_REQUEST['debug_mode'],
			'userids' => get_request('group_users', array()),
			'rights' => array_values(get_request('group_rights', array())),
		);

		if(isset($_REQUEST['usrgrpid'])){
			$action = AUDIT_ACTION_UPDATE;

			$usrgrp['usrgrpid'] = $_REQUEST['usrgrpid'];

			$result = CUserGroup::update($usrgrp);
			show_messages($result, S_GROUP_UPDATED, S_CANNOT_UPDATE_GROUP);
		}
		else{
			$action = AUDIT_ACTION_ADD;

			$result = CUserGroup::create($usrgrp);
			show_messages($result, S_GROUP_ADDED, S_CANNOT_ADD_GROUP);
		}

		if($result){
			add_audit($action,AUDIT_RESOURCE_USER_GROUP,'Group name ['.$_REQUEST['gname'].']');
			unset($_REQUEST['form']);
		}
	}
	else if(isset($_REQUEST['delete'])){
		$group = CUserGroup::get(array('usrgrpids' => $_REQUEST['usrgrpid'], 'extendoutput' => 1));
		$group = reset($group);

		$result = CUserGroup::delete($_REQUEST['usrgrpid']);

		show_messages($result, S_GROUP_DELETED, S_CANNOT_DELETE_GROUP);
		if($result){
			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_USER_GROUP,'Group name ['.$group['name'].']');

			unset($_REQUEST['usrgrpid']);
			unset($_REQUEST['form']);
		}
	}
// -------- GO ---------
	else if($_REQUEST['go'] == 'delete'){
		$groupids = get_request('group_groupid', array());

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
			$go_result = CUserGroup::delete($groupids);

			if($go_result){
				foreach($groups as $groupid => $group){
					add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_USER_GROUP,'Group name ['.$group['name'].']');
				}
			}

			show_messages($go_result, S_GROUP_DELETED, S_CANNOT_DELETE_GROUP);
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
			$go_result = change_group_gui_access($groupids,$_REQUEST['set_gui_access']);
			$go_result = DBend($go_result);

			if($go_result){
				$audit_action = ($_REQUEST['set_gui_access'] == GROUP_GUI_ACCESS_DISABLED)?AUDIT_ACTION_DISABLE:AUDIT_ACTION_ENABLE;
				foreach($groups as $groupid => $group){
					add_audit($audit_action,AUDIT_RESOURCE_USER_GROUP,'GUI access for group name ['.$group['name'].']');
				}
			}

			show_messages($go_result, S_GUI_ACCESS_UPDATED, S_CANNOT_UPDATE_GUI_ACCESS);
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
			$go_result = change_group_api_access($groupids,$set_api_access);
			$go_result = DBend($go_result);

			if($go_result){
				$audit_action = ($set_api_access == GROUP_API_ACCESS_DISABLED)?AUDIT_ACTION_DISABLE:AUDIT_ACTION_ENABLE;
				foreach($groups as $groupid => $group){
					add_audit($audit_action,AUDIT_RESOURCE_USER_GROUP,'API access for group name ['.$group['name'].']');
				}
			}

			show_messages($go_result, S_API_ACCESS_UPDATED, S_CANNOT_UPDATE_API_ACCESS);
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
			$go_result = change_group_debug_mode($groupids,$set_debug_mode);
			$go_result = DBend($go_result);

			if($go_result){
				$audit_action = ($set_debug_mode == GROUP_DEBUG_MODE_DISABLED)?AUDIT_ACTION_DISABLE:AUDIT_ACTION_ENABLE;

				foreach($groups as $groupid => $group){
					add_audit($audit_action,AUDIT_RESOURCE_USER_GROUP,'Debug mode for group name ['.$group['name'].']');
				}
			}

			show_messages($go_result, S_DEBUG_MODE_UPDATED, S_CANNOT_UPDATE_DEBUG_MODE);
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
			$go_result = change_group_status($groupids,$set_users_status);
			$go_result = DBend($go_result);

			if($go_result){
				$audit_action = ($set_users_status == GROUP_STATUS_ENABLED)?AUDIT_ACTION_ENABLE:AUDIT_ACTION_DISABLE;
				foreach($groups as $groupid => $group){
					add_audit($audit_action,AUDIT_RESOURCE_USER_GROUP,'User status for group name ['.$group['name'].']');
				}
			}

			show_messages($go_result, S_USERS_STATUS_UPDATED, S_CANNOT_UPDATE_USERS_STATUS);
		}
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}
?>
<?php

// Config
	$frmForm = new CForm(null, 'get');

	$cmbConf = new CComboBox('config','usergrps.php');
	$cmbConf->setAttribute('onchange','javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConf->addItem('usergrps.php',S_USER_GROUPS);
		$cmbConf->addItem('users.php',S_USERS);

	$frmForm->addItem(array($cmbConf,SPACE,new CButton('form', S_CREATE_GROUP)));

	$usrgroup_wdgt = new CWidget();
	$usrgroup_wdgt->addPageHeader(S_CONFIGURATION_OF_USERS_AND_USER_GROUPS, $frmForm);


	if(isset($_REQUEST['form'])){
		$usrgroup_wdgt->addItem(insert_usergroups_form());
	}
	else{
		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');

		$usrgroup_wdgt->addHeader(S_USER_GROUPS_BIG);
		$usrgroup_wdgt->addHeader($numrows);

// Groups table
		$form = new CForm();
		$form->setName('usrgrp_form');

		$table = new CTableInfo(S_NO_USER_GROUPS_DEFINED);
		$table->setHeader(array(
			new CCheckBox('all_groups',NULL, "checkAll('".$form->GetName()."','all_groups','group_groupid');"),
			make_sorting_header(S_NAME,'name'),
			'#',
			S_MEMBERS,
			S_USERS_STATUS,
			S_GUI_ACCESS,
			S_API_ACCESS,
			S_DEBUG_MODE
		));

		$sortfield = getPageSortField('name');
		$sortorder = getPageSortOrder();

		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'select_users' => API_OUTPUT_EXTEND,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);
		$usrgrps = CUserGroup::get($options);

// sorting
		order_result($usrgrps, $sortfield, $sortorder);
		$paging = getPagingLine($usrgrps);
//---------

		foreach($usrgrps as $ugnum => $usrgrp){
			$usrgrpid = $usrgrp['usrgrpid'];

			$api_access = ($usrgrp['api_access'] == GROUP_API_ACCESS_ENABLED)
				? new CLink(S_ENABLED, 'usergrps.php?go=disable_api&usrgrpid='.$usrgrpid, 'orange')
				: new CLink(S_DISABLED, 'usergrps.php?go=enable_api&usrgrpid='.$usrgrpid, 'enabled');

			$debug_mode = ($usrgrp['debug_mode'] == GROUP_DEBUG_MODE_ENABLED)
				? new CLink(S_ENABLED, 'usergrps.php?go=disable_debug&usrgrpid='.$usrgrpid, 'orange')
				: new CLink(S_DISABLED, 'usergrps.php?go=enable_debug&usrgrpid='.$usrgrpid, 'enabled');

			$gui_access = user_auth_type2str($usrgrp['gui_access']);

			$gui_access_style = 'enabled';
			if(GROUP_GUI_ACCESS_INTERNAL == $usrgrp['gui_access']) $gui_access_style = 'orange';
			if(GROUP_GUI_ACCESS_DISABLED == $usrgrp['gui_access']) $gui_access_style = 'disabled';

			if(granted2update_group($usrgrpid)){

				$next_gui_auth = ($usrgrp['gui_access']+1 > GROUP_GUI_ACCESS_DISABLED)?GROUP_GUI_ACCESS_SYSTEM:($usrgrp['gui_access']+1);

				$gui_access = new CLink(
									$gui_access,
									'usergrps.php?go=set_gui_access&set_gui_access='.$next_gui_auth.'&usrgrpid='.$usrgrpid,
									$gui_access_style
								);

				$users_status = ($usrgrp['users_status'] == GROUP_STATUS_ENABLED)
					? new CLink(S_ENABLED, 'usergrps.php?go=disable_status&usrgrpid='.$usrgrpid, 'enabled')
					: new CLink(S_DISABLED, 'usergrps.php?go=enable_status&usrgrpid='.$usrgrpid, 'disabled');
			}
			else{
				$gui_access = new CSpan($gui_access, $gui_access_style);
				$users_status = ($usrgrp['users_status'] == GROUP_STATUS_ENABLED)? new CSpan(S_ENABLED, 'enabled') : new CSpan(S_DISABLED, 'disabled');
			}

			if(isset($usrgrp['users'])){

				$usrgrpusers = $usrgrp['users'];
				order_result($usrgrpusers, 'alias');

				$users = array();
				foreach($usrgrpusers as $unum => $user){
				$user_type_style = 'enabled';
				if(USER_TYPE_ZABBIX_ADMIN == $user['type']) $user_type_style = 'orange';
				if(USER_TYPE_SUPER_ADMIN == $user['type']) $user_type_style = 'disabled';

				$user_status_style = 'enabled';
				if(GROUP_GUI_ACCESS_DISABLED == $user['gui_access']) $user_status_style = 'disabled';
				if(GROUP_STATUS_DISABLED == $user['users_status']) $user_status_style = 'disabled';


				$users[] = new CLink($user['alias'],'users.php?form=update&userid='.$user['userid'], $user_status_style);//, $user_type_style);
					$users[] = ', ';
				}
				array_pop($users);

			}

			$table->addRow(array(
				new CCheckBox('group_groupid['.$usrgrpid.']', NULL, NULL, $usrgrpid),
				new CLink($usrgrp['name'], 'usergrps.php?form=update&usrgrpid='.$usrgrpid),
				array(new CLink(S_USERS,'users.php?&filter_usrgrpid='.$usrgrpid), ' (', count($usrgrp['users']), ')'),
				new CCol($users, 'wraptext'),
				$users_status,
				$gui_access,
				$api_access,
				$debug_mode
			));
		}

// goBox
		$goBox = new CComboBox('go');

		$goOption = new CComboItem('enable_status',S_ENABLE_SELECTED);
		$goOption->setAttribute('confirm',S_ENABLE_SELECTED_GROUPS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('disable_status',S_DISABLE_SELECTED);
		$goOption->setAttribute('confirm',S_DISABLE_SELECTED_GROUPS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('enable_api',S_ENABLE_API);
		$goOption->setAttribute('confirm',S_ENABLE_API_SELECTED_GROUPS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('disable_api',S_DISABLE_API);
		$goOption->setAttribute('confirm',S_DISABLE_API_SELECTED_GROUPS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('enable_debug',S_ENABLE_DEBUG);
		$goOption->setAttribute('confirm',S_ENABLE_DEBUG_SELECTED_GROUPS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('disable_debug',S_DISABLE_DEBUG);
		$goOption->setAttribute('confirm',S_DISABLE_DEBUG_SELECTED_GROUPS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_GROUPS_Q);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO);
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "group_groupid";');

		$footer = get_table_header(array($goBox, $goButton));


		$form->addItem(array($paging,$table,$paging,$footer));

		$usrgroup_wdgt->addItem($form);
	}

	$usrgroup_wdgt->show();


include_once('include/page_footer.php');
?>
