<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/media.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/js.inc.php';

$page['title'] = _('Configuration of user groups');
$page['file'] = 'usergrps.php';
$page['hist_arg'] = array('config');

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'grpaction' =>			array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	// group
	'usrgrpid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({grpaction})&&isset({form})&&{form}=="update"'),
	'group_groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'selusrgrp' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'gname' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})'),
	'users' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'gui_access' =>			array(T_ZBX_INT, O_OPT, null,	IN('0,1,2'),'isset({save})'),
	'users_status' =>		array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'debug_mode' =>			array(T_ZBX_INT, O_OPT, null,	IN('1'),	null),
	'new_right' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'right_to_del' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'group_users_to_del' =>	array(T_ZBX_STR, O_OPT, null,	null,		null),
	'group_users' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'group_rights' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'set_users_status' =>	array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'set_gui_access' =>		array(T_ZBX_INT, O_OPT, null,	IN('0,1,2'),null),
	'set_debug_mode' =>		array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	// actions
	'go' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'register' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, IN('"add permission","delete permission"'), null),
	'save' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete_selected' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'del_user_group' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'del_user_media' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'del_read_only' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'del_read_write' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'del_deny' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'del_group_user' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'add_read_only' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'add_read_write' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'add_deny' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'change_password' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>				array(T_ZBX_STR, O_OPT, P_SYS,		 null,	null),
	// form
	'form' =>				array(T_ZBX_STR, O_OPT, P_SYS,		 null,	null),
	'form_refresh' =>		array(T_ZBX_STR, O_OPT, null,		 null,	null)
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

$_REQUEST['users_status'] = isset($_REQUEST['users_status']) ? 0 : 1;
$_REQUEST['debug_mode'] = get_request('debug_mode', 0);

/*
 * Permissions
 */
if (isset($_REQUEST['usrgrpid'])) {
	$dbUserGroup = API::UserGroup()->get(array(
		'usrgrpids' => $_REQUEST['usrgrpid'],
		'output' => API_OUTPUT_EXTEND
	));

	if (!$dbUserGroup) {
		access_deny();
	}
}
elseif (isset($_REQUEST['go'])) {
	if (!isset($_REQUEST['group_groupid']) || !is_array($_REQUEST['group_groupid'])) {
		access_deny();
	}
	else {
		$dbUserGroupCount = API::UserGroup()->get(array(
			'usrgrpids' => $_REQUEST['group_groupid'],
			'countOutput' => true
		));

		if ($dbUserGroupCount != count($_REQUEST['group_groupid'])) {
			access_deny();
		}
	}
}
$_REQUEST['go'] = get_request('go', 'none');

/*
 * Actions
 */
if (isset($_REQUEST['del_deny']) && isset($_REQUEST['right_to_del']['deny'])) {
	$_REQUEST['group_rights'] = get_request('group_rights', array());

	foreach ($_REQUEST['right_to_del']['deny'] as $name) {
		if (!isset($_REQUEST['group_rights'][$name])) {
			continue;
		}

		if ($_REQUEST['group_rights'][$name]['permission'] == PERM_DENY) {
			unset($_REQUEST['group_rights'][$name]);
		}
	}
}
elseif (isset($_REQUEST['del_read_only']) && isset($_REQUEST['right_to_del']['read_only'])) {
	$_REQUEST['group_rights'] = get_request('group_rights', array());

	foreach ($_REQUEST['right_to_del']['read_only'] as $name) {
		if (!isset($_REQUEST['group_rights'][$name])) {
			continue;
		}

		if ($_REQUEST['group_rights'][$name]['permission'] == PERM_READ) {
			unset($_REQUEST['group_rights'][$name]);
		}
	}
}
elseif (isset($_REQUEST['del_read_write']) && isset($_REQUEST['right_to_del']['read_write'])) {
	$_REQUEST['group_rights'] = get_request('group_rights', array());

	foreach ($_REQUEST['right_to_del']['read_write'] as $name) {
		if (!isset($_REQUEST['group_rights'][$name])) {
			continue;
		}

		if ($_REQUEST['group_rights'][$name]['permission'] == PERM_READ_WRITE) {
			unset($_REQUEST['group_rights'][$name]);
		}
	}
}
elseif (isset($_REQUEST['new_right'])) {
	$_REQUEST['group_rights'] = get_request('group_rights', array());

	foreach ($_REQUEST['new_right'] as $id => $right) {
		$_REQUEST['group_rights'][$id] = array(
			'name' => $right['name'],
			'permission' => $right['permission'],
			'id' => $id
		);
	}
}
elseif (isset($_REQUEST['save'])) {
	$userGroup = array(
		'name' => $_REQUEST['gname'],
		'users_status' => $_REQUEST['users_status'],
		'gui_access' => $_REQUEST['gui_access'],
		'debug_mode' => $_REQUEST['debug_mode'],
		'userids' => get_request('group_users', array()),
		'rights' => array_values(get_request('group_rights', array()))
	);

	if (isset($_REQUEST['usrgrpid'])) {
		$userGroup['usrgrpid'] = $_REQUEST['usrgrpid'];
		$result = API::UserGroup()->update($userGroup);

		$action = AUDIT_ACTION_UPDATE;
		show_messages($result, _('Group updated'), _('Cannot update group'));
	}
	else {
		$result = API::UserGroup()->create($userGroup);

		$action = AUDIT_ACTION_ADD;
		show_messages($result, _('Group added'), _('Cannot add group'));
	}

	if ($result) {
		add_audit($action, AUDIT_RESOURCE_USER_GROUP, 'Group name ['.$_REQUEST['gname'].']');
		unset($_REQUEST['form']);
		clearCookies($result);
	}
}
elseif (isset($_REQUEST['delete'])) {
	DBstart();

	$result = API::UserGroup()->delete(array($_REQUEST['usrgrpid']));
	$result = DBend($result);

	show_messages($result, _('Group deleted'), _('Cannot delete group'));
	clearCookies($result);

	if ($result) {
		$group = reset($dbUserGroup);

		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER_GROUP, 'Group name ['.$group['name'].']');
		unset($_REQUEST['usrgrpid'], $_REQUEST['form']);
	}
}
elseif ($_REQUEST['go'] == 'delete') {
	$groupIds = get_request('group_groupid', array());
	$groups = array();

	$dbGroups = DBselect(
		'SELECT ug.usrgrpid,ug.name'.
		' FROM usrgrp ug'.
		' WHERE '.dbConditionInt('ug.usrgrpid', $groupIds).
			andDbNode('ug.usrgrpid')
	);
	while ($group = DBfetch($dbGroups)) {
		$groups[$group['usrgrpid']] = $group;
	}

	if ($groups) {
		$goResult = API::UserGroup()->delete($groupIds);

		if ($goResult) {
			foreach ($groups as $groupId => $group) {
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER_GROUP, 'Group name ['.$group['name'].']');
			}
		}

		show_messages($goResult, _('Group deleted'), _('Cannot delete group'));
		clearCookies($goResult);
	}
}
elseif ($_REQUEST['go'] == 'set_gui_access') {
	$groupIds = get_request('group_groupid', get_request('usrgrpid'));
	zbx_value2array($groupIds);

	$groups = array();
	$dbGroups = DBselect(
		'SELECT ug.usrgrpid,ug.name'.
		' FROM usrgrp ug'.
		' WHERE '.dbConditionInt('ug.usrgrpid', $groupIds).
			andDbNode('ug.usrgrpid')
	);
	while ($group = DBfetch($dbGroups)) {
		$groups[$group['usrgrpid']] = $group;
	}

	if ($groups) {
		DBstart();

		$goResult = change_group_gui_access($groupIds, $_REQUEST['set_gui_access']);
		$goResult = DBend($goResult);

		if ($goResult) {
			$auditAction = ($_REQUEST['set_gui_access'] == GROUP_GUI_ACCESS_DISABLED) ? AUDIT_ACTION_DISABLE : AUDIT_ACTION_ENABLE;

			foreach ($groups as $groupId => $group) {
				add_audit($auditAction, AUDIT_RESOURCE_USER_GROUP, 'GUI access for group name ['.$group['name'].']');
			}
		}

		show_messages($goResult, _('Frontend access updated'), _('Cannot update frontend access'));
		clearCookies($goResult);
	}
}
elseif (str_in_array($_REQUEST['go'], array('enable_debug', 'disable_debug'))) {
	$groupIds = get_request('group_groupid', get_request('usrgrpid'));
	zbx_value2array($groupIds);

	$setDebugMode = ($_REQUEST['go'] == 'enable_debug') ? GROUP_DEBUG_MODE_ENABLED : GROUP_DEBUG_MODE_DISABLED;

	$groups = array();
	$dbGroup = DBselect(
		'SELECT ug.usrgrpid,ug.name'.
		' FROM usrgrp ug'.
		' WHERE '.dbConditionInt('ug.usrgrpid', $groupIds).
			andDbNode('ug.usrgrpid')
	);
	while ($group = DBfetch($dbGroup)) {
		$groups[$group['usrgrpid']] = $group;
	}

	if ($groups) {
		DBstart();

		$goResult = change_group_debug_mode($groupIds, $setDebugMode);
		$goResult = DBend($goResult);

		if ($goResult) {
			$auditAction = ($setDebugMode == GROUP_DEBUG_MODE_DISABLED) ? AUDIT_ACTION_DISABLE : AUDIT_ACTION_ENABLE;

			foreach ($groups as $groupId => $group) {
				add_audit($auditAction, AUDIT_RESOURCE_USER_GROUP, 'Debug mode for group name ['.$group['name'].']');
			}
		}

		show_messages($goResult, _('Debug mode updated'), _('Cannot update debug mode'));
		clearCookies($goResult);
	}
}
elseif (str_in_array(getRequest('go'), array('enable_status', 'disable_status'))) {
	$groupIds = getRequest('group_groupid', getRequest('usrgrpid'));
	zbx_value2array($groupIds);

	$enable = (getRequest('go') == 'enable_status');
	$status = $enable ? GROUP_STATUS_ENABLED : GROUP_STATUS_DISABLED;
	$auditAction = $enable ? AUDIT_ACTION_ENABLE : AUDIT_ACTION_DISABLE;
	$groups = array();

	$dbGroups = DBselect(
		'SELECT ug.usrgrpid,ug.name'.
		' FROM usrgrp ug'.
		' WHERE '.dbConditionInt('ug.usrgrpid', $groupIds).
			andDbNode('ug.usrgrpid')
	);
	while ($group = DBfetch($dbGroups)) {
		$groups[$group['usrgrpid']] = $group;
	}
	$updated = count($groups);

	if ($groups) {
		DBstart();

		$result = change_group_status($groupIds, $status);
		$result = DBend($result);

		if ($result) {
			foreach ($groups as $group) {
				add_audit($auditAction, AUDIT_RESOURCE_USER_GROUP, 'User status for group name ['.$group['name'].']');
			}
		}

		$messageSuccess = $enable
			? _n('User group enabled', 'User groups enabled', $updated)
			: _n('User group disabled', 'User groups disabled', $updated);
		$messageFailed = $enable
			? _n('Cannot enable user group', 'Cannot enable user groups', $updated)
			: _n('Cannot disable user group', 'Cannot disable user groups', $updated);

		show_messages($result, $messageSuccess, $messageFailed);
		clearCookies($result);
	}
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'usrgrpid' => get_request('usrgrpid'),
		'form' => get_request('form'),
		'form_refresh' => get_request('form_refresh', 0)
	);

	if (isset($_REQUEST['usrgrpid'])) {
		$data['usrgrp'] = reset($dbUserGroup);
	}

	if (isset($_REQUEST['usrgrpid']) && !isset($_REQUEST['form_refresh'])) {
		$data['name'] = $data['usrgrp']['name'];
		$data['users_status'] = $data['usrgrp']['users_status'];
		$data['gui_access'] = $data['usrgrp']['gui_access'];
		$data['debug_mode'] = $data['usrgrp']['debug_mode'];

		// group users
		$data['group_users'] = array();

		$dbUsers = DBselect(
			'SELECT DISTINCT u.userid '.
			' FROM users u,users_groups ug '.
			' WHERE u.userid=ug.userid '.
				' AND ug.usrgrpid='.zbx_dbstr($data['usrgrpid'])
		);
		while ($dbUser = DBfetch($dbUsers)) {
			$data['group_users'][$dbUser['userid']] = $dbUser['userid'];
		}

		// group rights
		$data['group_rights'] = array();

		$dbRights = DBselect(
			'SELECT r.*,n.name AS nodename,g.name AS name'.
			' FROM groups g'.
				' LEFT JOIN rights r ON r.id=g.groupid'.
				' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('g.groupid').
			' WHERE r.groupid='.zbx_dbstr($data['usrgrpid'])
		);
		while ($dbRight = DBfetch($dbRights)) {
			if (!empty($dbRight['nodename'])) {
				$dbRight['name'] = $dbRight['nodename'].NAME_DELIMITER.$dbRight['name'];
			}

			$data['group_rights'][$dbRight['id']] = array(
				'permission' => $dbRight['permission'],
				'name' => $dbRight['name'],
				'id' => $dbRight['id']
			);
		}
	}
	else {
		$data['name'] = get_request('gname', '');
		$data['users_status'] = get_request('users_status', GROUP_STATUS_ENABLED);
		$data['gui_access'] = get_request('gui_access', GROUP_GUI_ACCESS_SYSTEM);
		$data['debug_mode'] = get_request('debug_mode', GROUP_DEBUG_MODE_DISABLED);
		$data['group_users'] = get_request('group_users', array());
		$data['group_rights'] = get_request('group_rights', array());
	}

	$data['selected_usrgrp'] = get_request('selusrgrp', 0);

	// sort group rights
	order_result($data['group_rights'], 'name');

	// get users
	if ($data['selected_usrgrp'] > 0) {
		$sqlFrom = ',users_groups g';
		$sqlWhere =
			' WHERE '.dbConditionInt('u.userid', $data['group_users']).
				' OR (u.userid=g.userid AND g.usrgrpid='.zbx_dbstr($data['selected_usrgrp']).andDbNode('u.userid').')';
	}
	else {
		$sqlFrom = '';
		$sqlWhere = whereDbNode('u.userid');
	}

	$data['users'] = DBfetchArray(DBselect(
		'SELECT DISTINCT u.userid,u.alias,u.name,u.surname'.
		' FROM users u'.$sqlFrom.
			$sqlWhere
	));
	order_result($data['users'], 'alias');

	// get user groups
	$data['usergroups'] = DBfetchArray(DBselect(
		'SELECT ug.usrgrpid,ug.name'.
		' FROM usrgrp ug'.
			whereDbNode('usrgrpid')
	));
	order_result($data['usergroups'], 'name');

	// render view
	$userGroupsView = new CView('administration.usergroups.edit', $data);
	$userGroupsView->render();
	$userGroupsView->show();
}
else {
	$data = array(
		'displayNodes' => is_array(get_current_nodeid())
	);

	$sortfield = getPageSortField('name');

	$data['usergroups'] = API::UserGroup()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'selectUsers' => API_OUTPUT_EXTEND,
		'sortfield' => $sortfield,
		'limit' => $config['search_limit'] + 1
	));

	// sorting & paging
	order_result($data['usergroups'], $sortfield, getPageSortOrder());
	$data['paging'] = getPagingLine($data['usergroups'], array('usrgrpid'));

	// nodes
	if ($data['displayNodes']) {
		foreach ($data['usergroups'] as &$userGroup) {
			$userGroup['nodename'] = get_node_name_by_elid($userGroup['usrgrpid'], true);
		}
		unset($userGroup);
	}

	// render view
	$userGroupsView = new CView('administration.usergroups.list', $data);
	$userGroupsView->render();
	$userGroupsView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
