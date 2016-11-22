<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
$page['scripts'] = ['multiselect.js'];

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	// group
	'usrgrpid' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'],
	'group_groupid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'selusrgrp' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'gname' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Group name')],
	'gui_access' =>			[T_ZBX_INT, O_OPT, null,	IN('0,1,2'),'isset({add}) || isset({update})'],
	'users_status' =>		[T_ZBX_INT, O_OPT, null,	IN([GROUP_STATUS_ENABLED, GROUP_STATUS_DISABLED]),	null],
	'debug_mode' =>			[T_ZBX_INT, O_OPT, null,	IN('1'),	null],
	'group_users' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'groups_rights' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'set_gui_access' =>		[T_ZBX_INT, O_OPT, null,	IN('0,1,2'),null],
	// actions
	'action' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
								IN('"usergroup.massdisable","usergroup.massdisabledebug","usergroup.massdelete",'.
									'"usergroup.massenable","usergroup.massenabledebug","usergroup.set_gui_access"'
								),
								null
							],
	'add' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete_selected' =>	[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_permission' =>		[T_ZBX_STR, O_OPT, null,		 null,	null],
	'new_permission' =>		[T_ZBX_STR, O_OPT, null,		 null,	null],
	'groupids' =>			[T_ZBX_STR, O_OPT, null,		 null,	null],
	'subgroups' =>			[T_ZBX_STR, O_OPT, null,		 null,	null],
	// form
	'form' =>				[T_ZBX_STR, O_OPT, P_SYS,		 null,	null],
	'form_refresh' =>		[T_ZBX_INT, O_OPT, null,		 null,	null],
	// filter
	'filter_set' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_name' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_users_status' =>[T_ZBX_INT, O_OPT, null,	IN([-1, GROUP_STATUS_ENABLED, GROUP_STATUS_DISABLED]),		null],
	// sort and sortorder
	'sort' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"name"'),								null],
	'sortorder' =>			[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

$_REQUEST['users_status'] = hasRequest('users_status') ? GROUP_STATUS_ENABLED : GROUP_STATUS_DISABLED;
$_REQUEST['debug_mode'] = getRequest('debug_mode', 0);

/*
 * Permissions
 */
if (isset($_REQUEST['usrgrpid'])) {
	$dbUserGroup = API::UserGroup()->get([
		'usrgrpids' => $_REQUEST['usrgrpid'],
		'output' => API_OUTPUT_EXTEND
	]);

	if (!$dbUserGroup) {
		access_deny();
	}
}
elseif (hasRequest('action')) {
	if (!hasRequest('group_groupid') || !is_array(getRequest('group_groupid'))) {
		access_deny();
	}
	else {
		$dbUserGroupCount = API::UserGroup()->get([
			'output' => ['usrgrpid'],
			'usrgrpids' => getRequest('group_groupid'),
			'countOutput' => true
		]);

		if ($dbUserGroupCount != count(getRequest('group_groupid'))) {
			access_deny();
		}
	}
}

/*
 * Actions
 */
if (hasRequest('add') || hasRequest('update')) {
	$userGroup = [
		'name' => getRequest('gname'),
		'users_status' => getRequest('users_status'),
		'gui_access' => getRequest('gui_access'),
		'debug_mode' => getRequest('debug_mode'),
		'userids' => getRequest('group_users', []),
		'rights' => [],
	];

	$groups_rights = applyHostGroupRights(getRequest('groups_rights', []));

	foreach ($groups_rights as $groupid => $group_rights) {
		if ($groupid != 0 && $group_rights['permission'] != PERM_NONE) {
			$userGroup['rights'][] = [
				'id' => $groupid,
				'permission' => $group_rights['permission']
			];
		}
	}

	DBstart();

	if (hasRequest('update')) {
		$userGroup['usrgrpid'] = getRequest('usrgrpid');
		$result = API::UserGroup()->update($userGroup);

		$messageSuccess = _('Group updated');
		$messageFailed = _('Cannot update group');
		$action = AUDIT_ACTION_UPDATE;
	}
	else {
		$result = API::UserGroup()->create($userGroup);

		$messageSuccess = _('Group added');
		$messageFailed = _('Cannot add group');
		$action = AUDIT_ACTION_ADD;
	}

	if ($result) {
		add_audit($action, AUDIT_RESOURCE_USER_GROUP, 'Group name ['.$userGroup['name'].']');
		unset($_REQUEST['form']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (isset($_REQUEST['delete'])) {
	DBstart();

	$result = API::UserGroup()->delete([$_REQUEST['usrgrpid']]);

	if ($result) {
		$group = reset($dbUserGroup);

		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER_GROUP, 'Group name ['.$group['name'].']');
		unset($_REQUEST['usrgrpid'], $_REQUEST['form']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Group deleted'), _('Cannot delete group'));
}
elseif (hasRequest('action') && getRequest('action') === 'usergroup.massdelete' && hasRequest('group_groupid')) {
	$groupIds = getRequest('group_groupid');
	$groups = [];

	$dbGroups = DBselect(
		'SELECT ug.usrgrpid,ug.name'.
		' FROM usrgrp ug'.
		' WHERE '.dbConditionInt('ug.usrgrpid', $groupIds)
	);
	while ($group = DBfetch($dbGroups)) {
		$groups[$group['usrgrpid']] = $group;
	}

	if ($groups) {
		DBstart();

		$result = API::UserGroup()->delete($groupIds);

		if ($result) {
			foreach ($groups as $groupId => $group) {
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER_GROUP, 'Group name ['.$group['name'].']');
			}
		}

		$result = DBend($result);

		if ($result) {
			uncheckTableRows();
		}
		show_messages($result, _('Group deleted'), _('Cannot delete group'));
	}
}
elseif (hasRequest('action') && getRequest('action') === 'usergroup.set_gui_access') {
	$groupIds = getRequest('group_groupid', getRequest('usrgrpid'));
	zbx_value2array($groupIds);

	$groups = [];
	$dbGroups = DBselect(
		'SELECT ug.usrgrpid,ug.name'.
		' FROM usrgrp ug'.
		' WHERE '.dbConditionInt('ug.usrgrpid', $groupIds)
	);
	while ($group = DBfetch($dbGroups)) {
		$groups[$group['usrgrpid']] = $group;
	}

	if ($groups) {
		DBstart();

		$result = change_group_gui_access($groupIds, getRequest('set_gui_access'));

		if ($result) {
			$auditAction = (getRequest('set_gui_access') == GROUP_GUI_ACCESS_DISABLED) ? AUDIT_ACTION_DISABLE : AUDIT_ACTION_ENABLE;

			foreach ($groups as $groupId => $group) {
				add_audit($auditAction, AUDIT_RESOURCE_USER_GROUP, 'GUI access for group name ['.$group['name'].']');
			}
		}

		$result = DBend($result);

		if ($result) {
			uncheckTableRows();
		}
		show_messages($result, _('Frontend access updated'), _('Cannot update frontend access'));
	}
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['usergroup.massenabledebug', 'usergroup.massdisabledebug'])) {
	$groupIds = getRequest('group_groupid', getRequest('usrgrpid'));
	zbx_value2array($groupIds);

	$setDebugMode = (getRequest('action') == 'usergroup.massenabledebug') ? GROUP_DEBUG_MODE_ENABLED : GROUP_DEBUG_MODE_DISABLED;

	$groups = [];
	$dbGroup = DBselect(
		'SELECT ug.usrgrpid,ug.name'.
		' FROM usrgrp ug'.
		' WHERE '.dbConditionInt('ug.usrgrpid', $groupIds)
	);
	while ($group = DBfetch($dbGroup)) {
		$groups[$group['usrgrpid']] = $group;
	}

	if ($groups) {
		DBstart();

		$result = change_group_debug_mode($groupIds, $setDebugMode);

		if ($result) {
			$auditAction = ($setDebugMode == GROUP_DEBUG_MODE_DISABLED) ? AUDIT_ACTION_DISABLE : AUDIT_ACTION_ENABLE;

			foreach ($groups as $groupId => $group) {
				add_audit($auditAction, AUDIT_RESOURCE_USER_GROUP, 'Debug mode for group name ['.$group['name'].']');
			}
		}

		$result = DBend($result);

		if ($result) {
			uncheckTableRows();
		}
		show_messages($result, _('Debug mode updated'), _('Cannot update debug mode'));
	}
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['usergroup.massenable', 'usergroup.massdisable'])) {
	$groupIds = getRequest('group_groupid', getRequest('usrgrpid'));
	zbx_value2array($groupIds);

	$enable = (getRequest('action') == 'usergroup.massenable');
	$status = $enable ? GROUP_STATUS_ENABLED : GROUP_STATUS_DISABLED;
	$auditAction = $enable ? AUDIT_ACTION_ENABLE : AUDIT_ACTION_DISABLE;
	$groups = [];

	$dbGroups = DBselect(
		'SELECT ug.usrgrpid,ug.name'.
		' FROM usrgrp ug'.
		' WHERE '.dbConditionInt('ug.usrgrpid', $groupIds)
	);
	while ($group = DBfetch($dbGroups)) {
		$groups[$group['usrgrpid']] = $group;
	}
	$updated = count($groups);

	if ($groups) {
		DBstart();

		$result = change_group_status($groupIds, $status);

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

		$result = DBend($result);

		if ($result) {
			uncheckTableRows();
		}
		show_messages($result, $messageSuccess, $messageFailed);
	}
}

/*
 * Display
 */
if (hasRequest('form')) {
	$data = [
		'usrgrpid' => getRequest('usrgrpid', 0),
		'form' => getRequest('form'),
		'name' => getRequest('gname', ''),
		'users_status' => hasRequest('form_refresh') ? getRequest('users_status') : GROUP_STATUS_ENABLED,
		'gui_access' => getRequest('gui_access', GROUP_GUI_ACCESS_SYSTEM),
		'debug_mode' => getRequest('debug_mode', GROUP_DEBUG_MODE_DISABLED),
		'group_users' => hasRequest('form_refresh') ? getRequest('group_users', []) : [],
		'form_refresh' => getRequest('form_refresh', 0)
	];

	if ($data['usrgrpid'] != 0) {
		// User group exists, but there might be no permissions set yet.
		$db_user_group = reset($dbUserGroup);
		$data['name'] = getRequest('gname', $db_user_group['name']);
		$data['users_status'] = hasRequest('form_refresh')
			? getRequest('users_status')
			: $db_user_group['users_status'];
		$data['gui_access'] = getRequest('gui_access', $db_user_group['gui_access']);
		$data['debug_mode'] = hasRequest('form_refresh') ? getRequest('debug_mode') : $db_user_group['debug_mode'];

		if (!hasRequest('form_refresh')) {
			$dbUsers = DBselect(
				'SELECT ug.userid'.
				' FROM users_groups ug'.
				' WHERE ug.usrgrpid='.zbx_dbstr($data['usrgrpid'])
			);

			while ($dbUser = DBfetch($dbUsers)) {
				$data['group_users'][] = $dbUser['userid'];
			}
		}
	}

	$data['groups_rights'] = hasRequest('form_refresh')
		? getRequest('groups_rights', [])
		: collapseHostGroupRights(getHostGroupsRights($data['usrgrpid'] == 0 ? [] : [$data['usrgrpid']]));

	if (hasRequest('add_permission')) {
		// Add new permission with submit().
		if (hasRequest('subgroups')) {
			$groupids = [];
			$groupids_subgroupids = getRequest('groupids', []);
		}
		else {
			$groupids = getRequest('groupids', []);
			$groupids_subgroupids = [];
		}

		$new_permission = getRequest('new_permission', PERM_NONE);

		$data['groups_rights'] = collapseHostGroupRights(
			applyHostGroupRights($data['groups_rights'], $groupids, $groupids_subgroupids, $new_permission)
		);
	}

	$data['selected_usrgrp'] = getRequest('selusrgrp', 0);

	// get users
	if ($data['selected_usrgrp'] > 0) {
		$sqlFrom = ',users_groups g';
		$sqlWhere =
			' WHERE '.dbConditionInt('u.userid', $data['group_users']).
				' OR (u.userid=g.userid AND g.usrgrpid='.zbx_dbstr($data['selected_usrgrp']).')';
	}
	else {
		$sqlFrom = '';
		$sqlWhere = '';
	}

	$data['users'] = DBfetchArray(DBselect(
		'SELECT DISTINCT u.userid,u.alias,u.name,u.surname'.
		' FROM users u'.$sqlFrom.
			$sqlWhere
	));
	order_result($data['users'], 'alias');

	// get user groups
	$data['usergroups'] = DBfetchArray(DBselect(
		'SELECT ug.usrgrpid,ug.name FROM usrgrp ug'
	));

	order_result($data['usergroups'], 'name');

	// render view
	$view = new CView('administration.usergroups.edit', $data);
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	// filter
	if (hasRequest('filter_set')) {
		CProfile::update('web.usergroup.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
		CProfile::update('web.usergroup.filter_users_status', getRequest('filter_users_status', -1), PROFILE_TYPE_INT);
	}
	elseif (hasRequest('filter_rst')) {
		CProfile::delete('web.usergroup.filter_name');
		CProfile::delete('web.usergroup.filter_users_status');
	}

	$filter = [
		'name' => CProfile::get('web.usergroup.filter_name', ''),
		'users_status' => CProfile::get('web.usergroup.filter_users_status', -1)
	];

	$config = select_config();

	$data = [
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'filter' => $filter,
		'config' => $config
	];

	$data['usergroups'] = API::UserGroup()->get([
		'output' => API_OUTPUT_EXTEND,
		'selectUsers' => API_OUTPUT_EXTEND,
		'search' => [
			'name' => ($filter['name'] === '') ? null : $filter['name']
		],
		'filter' => [
			'users_status' => ($filter['users_status'] == -1) ? null : $filter['users_status']
		],
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	]);

	// sorting & paging
	order_result($data['usergroups'], $sortField, $sortOrder);
	$data['paging'] = getPagingLine($data['usergroups'], $sortOrder, new CUrl('usergrps.php'));

	// render view
	$view = new CView('administration.usergroups.list', $data);
}

$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';
