<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

// Available GUI access rights.
$gui_access = [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_LDAP, GROUP_GUI_ACCESS_DISABLED];
//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	// group
	'usrgrpid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'],
	'group_groupid' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'gname' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Group name')],
	'gui_access' =>				[T_ZBX_INT, O_OPT, null,	IN($gui_access), 'isset({add}) || isset({update})'],
	'users_status' =>			[T_ZBX_INT, O_OPT, null,	IN([GROUP_STATUS_ENABLED, GROUP_STATUS_DISABLED]),	null],
	'debug_mode' =>				[T_ZBX_INT, O_OPT, null,	IN('1'),	null],
	'userids' =>				[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'groups_rights' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'set_gui_access' =>			[T_ZBX_INT, O_OPT, null,	IN($gui_access),null],
	// actions
	'action' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
									IN('"usergroup.massdisable","usergroup.massdisabledebug","usergroup.massdelete",'.
										'"usergroup.massenable","usergroup.massenabledebug","usergroup.set_gui_access"'
									),
									null
								],
	'add' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_permission' =>			[T_ZBX_STR, O_OPT, null,		 null,	null],
	'new_permission' =>			[T_ZBX_STR, O_OPT, null,		 null,	null],
	'groupids' =>				[T_ZBX_STR, O_OPT, null,		 null,	null],
	'subgroups' =>				[T_ZBX_STR, O_OPT, null,		 null,	null],
	'tag_filters' =>			[T_ZBX_STR, O_OPT, null,		 null,	null],
	'remove_tag_filter' =>		[T_ZBX_STR, O_OPT, null,		 null,	null],
	'tag_filter_groupids' =>	[T_ZBX_STR, O_OPT, null,		 null,	null],
	'tag' =>					[T_ZBX_STR, O_OPT, null,		 null,	null],
	'value' =>					[T_ZBX_STR, O_OPT, null,		 null,	null],
	'tag_filter_subgroups' =>	[T_ZBX_STR, O_OPT, null,		 null,	null],
	'add_tag_filter' =>			[T_ZBX_STR, O_OPT, null,		 null,	null],
	// form
	'form' =>					[T_ZBX_STR, O_OPT, P_SYS,		 null,	null],
	'form_refresh' =>			[T_ZBX_INT, O_OPT, null,		 null,	null],
	// filter
	'filter_set' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_name' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_users_status' =>	[T_ZBX_INT, O_OPT, null,	IN([-1, GROUP_STATUS_ENABLED, GROUP_STATUS_DISABLED]),		null],
	// sort and sortorder
	'sort' =>					[T_ZBX_STR, O_OPT, P_SYS, IN('"name"'),								null],
	'sortorder' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

$form = getRequest('form');

/*
 * Permissions
 */
if (hasRequest('usrgrpid')) {
	$db_user_group = API::UserGroup()->get([
		'output' => ['usrgrpid', 'name', 'gui_access', 'users_status', 'debug_mode'],
		'selectTagFilters' => ['groupid', 'tag', 'value'],
		'usrgrpids' => getRequest('usrgrpid')
	]);

	if (!$db_user_group) {
		access_deny();
	}
}
elseif (hasRequest('action')) {
	if (!hasRequest('group_groupid') || !is_array(getRequest('group_groupid'))) {
		access_deny();
	}
	else {
		$group_users = API::UserGroup()->get([
			'output' => [],
			'usrgrpids' => getRequest('group_groupid')
		]);

		if (count($group_users) != count(getRequest('group_groupid'))) {
			uncheckTableRows(null, zbx_objectValues($group_users, 'usrgrpid'));
		}
	}
}

/*
 * Actions
 */
if (hasRequest('add') || hasRequest('update')) {
	$user_group = [
		'name' => getRequest('gname'),
		'users_status' => getRequest('users_status', GROUP_STATUS_DISABLED),
		'gui_access' => getRequest('gui_access'),
		'debug_mode' => getRequest('debug_mode', GROUP_DEBUG_MODE_DISABLED),
		'userids' => getRequest('userids', []),
		'tag_filters' => getRequest('tag_filters', []),
		'rights' => []
	];

	$groups_rights = applyHostGroupRights(getRequest('groups_rights', []));

	foreach ($groups_rights as $groupid => $group_rights) {
		if ($groupid != 0 && $group_rights['permission'] != PERM_NONE) {
			$user_group['rights'][] = [
				'id' => (string) $groupid,
				'permission' => $group_rights['permission']
			];
		}
	}

	if (hasRequest('add')) {
		$result = (bool) API::UserGroup()->create($user_group);
		show_messages($result, _('Group added'), _('Cannot add group'));
	}
	else {
		$user_group['usrgrpid'] = getRequest('usrgrpid');
		$result = (bool) API::UserGroup()->update($user_group);
		show_messages($result, _('Group updated'), _('Cannot update group'));
	}

	if ($result) {
		uncheckTableRows();
		$form = null;
	}
}
elseif (hasRequest('delete')) {
	$result = (bool) API::UserGroup()->delete([getRequest('usrgrpid')]);
	show_messages($result, _('Group deleted'), _('Cannot delete group'));

	if ($result) {
		uncheckTableRows();
		$form = null;
	}
}
elseif (hasRequest('action')) {
	$action = getRequest('action');
	$result = false;

	switch ($action) {
		case 'usergroup.massdelete':
			if (hasRequest('group_groupid')) {
				$result = (bool) API::UserGroup()->delete(getRequest('group_groupid'));
				show_messages($result, _('Group deleted'), _('Cannot delete group'));
			}
			break;

		case 'usergroup.set_gui_access':
			$user_groups = [];

			foreach ((array) getRequest('group_groupid', getRequest('usrgrpid')) as $usrgrpid) {
				$user_groups[] = [
					'usrgrpid' => $usrgrpid,
					'gui_access' => getRequest('set_gui_access')
				];
			}

			$result = (bool) API::UserGroup()->update($user_groups);
			show_messages($result, _('Frontend access updated'), _('Cannot update frontend access'));
			break;

		case 'usergroup.massenabledebug':
		case 'usergroup.massdisabledebug':
			$user_groups = [];

			foreach ((array) getRequest('group_groupid', getRequest('usrgrpid')) as $usrgrpid) {
				$user_groups[] = [
					'usrgrpid' => $usrgrpid,
					'debug_mode' => ($action === 'usergroup.massenabledebug')
						? GROUP_DEBUG_MODE_ENABLED
						: GROUP_DEBUG_MODE_DISABLED
				];
			}

			$result = (bool) API::UserGroup()->update($user_groups);
			show_messages($result, _('Debug mode updated'), _('Cannot update debug mode'));
			break;

		case 'usergroup.massenable':
		case 'usergroup.massdisable':
			$user_groups = [];

			foreach ((array) getRequest('group_groupid', getRequest('usrgrpid')) as $usrgrpid) {
				$user_groups[] = [
					'usrgrpid' => $usrgrpid,
					'users_status' => ($action === 'usergroup.massenable')
						? GROUP_STATUS_ENABLED
						: GROUP_STATUS_DISABLED
				];
			}

			$result = (bool) API::UserGroup()->update($user_groups);
			$message_success = ($action === 'usergroup.massenable')
				? _n('User group enabled', 'User groups enabled', count($user_groups))
				: _n('User group disabled', 'User groups disabled', count($user_groups));
			$message_failed = ($action === 'usergroup.massenable')
				? _n('Cannot enable user group', 'Cannot enable user groups', count($user_groups))
				: _n('Cannot disable user group', 'Cannot disable user groups', count($user_groups));
			show_messages($result, $message_success, $message_failed);
			break;
	}

	if ($result) {
		uncheckTableRows();
	}
}

/*
 * Display
 */
if ($form !== null) {
	$data = [
		'usrgrpid' => getRequest('usrgrpid', 0),
		'form' => $form,
		'name' => getRequest('gname', ''),
		'users_status' => hasRequest('form_refresh')
			? getRequest('users_status', GROUP_STATUS_DISABLED)
			: GROUP_STATUS_ENABLED,
		'gui_access' => getRequest('gui_access', GROUP_GUI_ACCESS_SYSTEM),
		'debug_mode' => getRequest('debug_mode', GROUP_DEBUG_MODE_DISABLED),
		'users_ms' => [],
		'form_refresh' => getRequest('form_refresh', 0),
		'new_permission' => getRequest('new_permission', PERM_NONE),
		'subgroups' => getRequest('subgroups', 0),
		'tag' => getRequest('tag', ''),
		'value' => getRequest('value', ''),
		'tag_filter_subgroups' => getRequest('tag_filter_subgroups', 0)
	];

	$options = [
		'output' => ['alias', 'name', 'surname'],
		'usrgrpids' => null,
		'userids' => null,
		'preservekeys' => true
	];

	if ($data['usrgrpid'] != 0) {
		// User group exists, but there might be no permissions set yet.
		$db_user_group = reset($db_user_group);
		$data['name'] = getRequest('gname', $db_user_group['name']);
		$data['users_status'] = hasRequest('form_refresh')
			? getRequest('users_status', GROUP_STATUS_DISABLED)
			: $db_user_group['users_status'];
		$data['gui_access'] = getRequest('gui_access', $db_user_group['gui_access']);
		$data['debug_mode'] = hasRequest('form_refresh')
			? getRequest('debug_mode', GROUP_DEBUG_MODE_DISABLED)
			: $db_user_group['debug_mode'];

		if (hasRequest('form_refresh')) {
			$options['userids'] = getRequest('userids', []);
		}
		else {
			$options['usrgrpids'] = $data['usrgrpid'];
		}
	}
	else {
		$options['userids'] = getRequest('userids', []);
	}

	if ($options['usrgrpids'] !== null || $options['userids'] !== []) {
		$users = API::User()->get($options);

		// Prepare data for multiselect. Skip silently deleted users.
		foreach ($users as $user) {
			$data['users_ms'][] = [
				'id' => $user['userid'],
				'name' => getUserFullname($user)
			];
		}
		CArrayHelper::sort($data['users_ms'], ['name']);
	}

	if (hasRequest('form_refresh')) {
		$data['groups_rights'] = getRequest('groups_rights', []);
		$data['tag_filters'] = getRequest('tag_filters', []);
	}
	else {
		$data['tag_filters'] = ($data['usrgrpid'] != 0) ? $db_user_group['tag_filters'] : [];
		$data['groups_rights'] = collapseHostGroupRights(getHostGroupsRights(($data['usrgrpid'] != 0)
			? [$data['usrgrpid']]
			: []
		));
	}

	$permission_groupids = getRequest('groupids', []);

	if (hasRequest('add_permission')) {
		if (!$permission_groupids) {
			show_error_message(_s('Incorrect value for field "%1$s": %2$s.', _('Host groups'), _('cannot be empty')));
		}
		else {
			// Add new permission with submit().
			if ($data['subgroups'] == 1) {
				$groupids = [];
				$groupids_subgroupids = $permission_groupids;
			}
			else {
				$groupids = $permission_groupids;
				$groupids_subgroupids = [];
			}

			$data['groups_rights'] = collapseHostGroupRights(
				applyHostGroupRights($data['groups_rights'], $groupids, $groupids_subgroupids, $data['new_permission'])
			);

			$permission_groupids = [];
			$data['new_permission'] = PERM_NONE;
			$data['subgroups'] = 0;
		}
	}

	if ($permission_groupids) {
		$host_groups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $permission_groupids
		]);
		CArrayHelper::sort($host_groups, ['name']);

		$data['permission_groups'] = CArrayHelper::renameObjectsKeys($host_groups, ['groupid' => 'id']);
	}
	else {
		$data['permission_groups'] = [];
	}

	$tag_filter_groupids = getRequest('tag_filter_groupids', []);

	if (hasRequest('add_tag_filter')) {
		if (!$tag_filter_groupids) {
			show_error_message(_s('Incorrect value for field "%1$s": %2$s.', _('Host groups'), _('cannot be empty')));
		}
		elseif ($data['value'] !== '' && $data['tag'] === '') {
			show_error_message(_s('Incorrect value for field "%1$s": %2$s.', _('Tag'), _('cannot be empty')));
		}
		else {
			// Add new tag filter with submit().
			if ($data['tag_filter_subgroups'] == 1) {
				$tag_filter_groupids = getSubGroups($tag_filter_groupids);
			}

			foreach ($tag_filter_groupids as $groupid) {
				$data['tag_filters'][] = [
					'groupid' => $groupid,
					'tag' => $data['tag'],
					'value' => $data['value']
				];
			}

			$tag_filter_groupids = [];
			$data['tag'] = '';
			$data['value'] = '';
			$data['tag_filter_subgroups'] = 0;
		}
	}
	elseif (hasRequest('remove_tag_filter')) {
		$remove_tag_filter = getRequest('remove_tag_filter');
		if (is_array($remove_tag_filter) && array_key_exists(key($remove_tag_filter), $data['tag_filters'])) {
			unset($data['tag_filters'][key($remove_tag_filter)]);
		}
	}

	$data['tag_filters'] = collapseTagFilters($data['tag_filters']);

	if ($tag_filter_groupids) {
		$host_groups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $tag_filter_groupids
		]);
		CArrayHelper::sort($host_groups, ['name']);

		$data['tag_filter_groups'] = CArrayHelper::renameObjectsKeys($host_groups, ['groupid' => 'id']);
	}
	else {
		$data['tag_filter_groups'] = [];
	}

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
		'config' => $config,
		'profileIdx' => 'web.usergroup.filter',
		'active_tab' => CProfile::get('web.usergroup.filter.active', 1),
		'usergroups' => API::UserGroup()->get([
			'output' => API_OUTPUT_EXTEND,
			'selectUsers' => API_OUTPUT_EXTEND,
			'search' => ['name' => ($filter['name'] !== '') ? $filter['name'] : null],
			'filter' => ['users_status' => ($filter['users_status'] != -1) ? $filter['users_status'] : null],
			'sortfield' => $sortField,
			'limit' => $config['search_limit'] + 1
		])
	];

	// sorting & paging
	order_result($data['usergroups'], $sortField, $sortOrder);
	$data['paging'] = getPagingLine($data['usergroups'], $sortOrder, new CUrl('usergrps.php'));

	// render view
	$view = new CView('administration.usergroups.list', $data);
}

$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';
