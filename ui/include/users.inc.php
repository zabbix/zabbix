<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Find user theme or get default theme.
 *
 * @param array $userData
 *
 * @return string
 */
function getUserTheme($userData) {
	return isset($userData['theme']) && $userData['theme'] != THEME_DEFAULT
		? $userData['theme']
		: CSettingsHelper::getPublic(CSettingsHelper::DEFAULT_THEME);
}

/**
 * Get user type name.
 *
 * @param int $userType
 *
 * @return string|array
 */
function user_type2str($userType = null) {
	$userTypes = [
		USER_TYPE_ZABBIX_USER => _('User'),
		USER_TYPE_ZABBIX_ADMIN => _('Admin'),
		USER_TYPE_SUPER_ADMIN => _('Super admin')
	];

	if ($userType === null) {
		return $userTypes;
	}
	elseif (isset($userTypes[$userType])) {
		return $userTypes[$userType];
	}
	else {
		return _('Unknown');
	}
}

/**
 * Get user authentication name.
 *
 * @param int $authType
 *
 * @return string
 */
function user_auth_type2str($authType) {
	if ($authType === null) {
		$authType = getUserGuiAccess(CWebUser::$data['userid']);
	}

	$authUserType = [
		GROUP_GUI_ACCESS_SYSTEM => _('System default'),
		GROUP_GUI_ACCESS_INTERNAL => _x('Internal', 'user type'),
		GROUP_GUI_ACCESS_LDAP => _('LDAP'),
		GROUP_GUI_ACCESS_DISABLED => _('Disabled')
	];

	return isset($authUserType[$authType]) ? $authUserType[$authType] : _('Unknown');
}

/**
 * Get users ids by groups ids.
 *
 * @param array $userGroupIds
 *
 * @return array
 */
function get_userid_by_usrgrpid($userGroupIds) {
	zbx_value2array($userGroupIds);

	$userIds = [];

	$dbUsers = DBselect(
		'SELECT DISTINCT u.userid'.
		' FROM users u,users_groups ug'.
		' WHERE u.userid=ug.userid'.
			' AND '.dbConditionInt('ug.usrgrpid', $userGroupIds)
	);
	while ($user = DBFetch($dbUsers)) {
		$userIds[$user['userid']] = $user['userid'];
	}

	return $userIds;
}

/**
 * Check if group has permissions for update.
 *
 * @param array $userGroupIds
 *
 * @return bool
 */
function granted2update_group($userGroupIds) {
	zbx_value2array($userGroupIds);

	$users = get_userid_by_usrgrpid($userGroupIds);

	return !isset($users[CWebUser::$data['userid']]);
}

/**
 * Gets user full name in format "username (name surname)". If both name and surname exist, returns translated string.
 *
 * @param array  $userData
 * @param string $userData['username']
 * @param string $userData['name']
 * @param string $userData['surname']
 *
 * @return string
 */
function getUserFullname($userData) {
	if (!zbx_empty($userData['surname'])) {
		if (!zbx_empty($userData['name'])) {
			return $userData['username'].' '._xs('(%1$s %2$s)', 'user fullname', $userData['name'],
				$userData['surname']
			);
		}

		$fullname = $userData['surname'];
	}
	else {
		$fullname = zbx_empty($userData['name']) ? '' : $userData['name'];
	}

	return zbx_empty($fullname) ? $userData['username'] : $userData['username'].' ('.$fullname.')';
}

/**
 * Returns the list of permissions to the host groups for selected user groups.
 *
 * @param array $usrgrpids		An array of user group IDs.
 *
 * @return array
 */
function getHostGroupsRights(array $usrgrpids = []) {
	$groups_rights = [
		'0' => [
			'permission' => PERM_NONE,
			'name' => '',
			'grouped' => '1'
		]
	];

	$host_groups = API::HostGroup()->get(['output' => ['groupid', 'name']]);

	foreach ($host_groups as $host_group) {
		$groups_rights[$host_group['groupid']] = [
			'permission' => PERM_NONE,
			'name' => $host_group['name']
		];
	}

	if ($usrgrpids) {
		$db_rights = DBselect(
			'SELECT r.id AS groupid,'.
				'CASE WHEN MIN(r.permission)='.PERM_DENY.' THEN '.PERM_DENY.' ELSE MAX(r.permission) END AS permission'.
			' FROM rights r'.
				' WHERE '.dbConditionInt('r.groupid', $usrgrpids).
			' GROUP BY r.id'
		);

		while ($db_right = DBfetch($db_rights)) {
			if (array_key_exists($db_right['groupid'], $groups_rights)) {
				$groups_rights[$db_right['groupid']]['permission'] = $db_right['permission'];
			}
		}
	}

	return $groups_rights;
}

/**
 * Returns the list of permissions to the template groups for selected user groups.
 *
 * @param array $usrgrpids  An array of user group IDs.
 *
 * @return array
 */
function getTemplateGroupsRights(array $usrgrpids = []) {
	$groups_rights = [
		'0' => [
			'permission' => PERM_NONE,
			'name' => '',
			'grouped' => '1'
		]
	];

	$template_groups = API::TemplateGroup()->get(['output' => ['groupid', 'name']]);

	foreach ($template_groups as $template_group) {
		$groups_rights[$template_group['groupid']] = [
			'permission' => PERM_NONE,
			'name' => $template_group['name']
		];
	}

	if ($usrgrpids) {
		$db_rights = DBselect(
			'SELECT r.id AS groupid,'.
			'CASE WHEN MIN(r.permission)='.PERM_DENY.' THEN '.PERM_DENY.' ELSE MAX(r.permission) END AS permission'.
			' FROM rights r'.
			' WHERE '.dbConditionInt('r.groupid', $usrgrpids).
			' GROUP BY r.id'
		);

		while ($db_right = DBfetch($db_rights)) {
			if (array_key_exists($db_right['groupid'], $groups_rights)) {
				$groups_rights[$db_right['groupid']]['permission'] = $db_right['permission'];
			}
		}
	}

	return $groups_rights;
}

/**
 * Returns the sorted list of permissions to the host groups in collapsed form.
 *
 * @param array  $groups_rights
 * @param string $groups_rights[<groupid>]['name']
 * @param int    $groups_rights[<groupid>]['permission']
 *
 * @return array
 */
function collapseGroupRights(array $groups_rights) {
	$groups = [];

	foreach ($groups_rights as $groupid => $group_rights) {
		$groups[$group_rights['name']] = $groupid;
	}

	CArrayHelper::sort($groups_rights, [['field' => 'name', 'order' => ZBX_SORT_DOWN]]);

	$permissions = [];

	foreach ($groups_rights as $groupid => $group_rights) {
		if ($groupid == 0) {
			continue;
		}

		$permissions[$group_rights['permission']] = true;

		$parent_group_name = $group_rights['name'];

		do {
			$pos = strrpos($parent_group_name, '/');
			$parent_group_name = ($pos === false) ? '' : substr($parent_group_name, 0, $pos);

			if (array_key_exists($parent_group_name, $groups)) {
				$parent_group_rights = &$groups_rights[$groups[$parent_group_name]];

				if ($parent_group_rights['permission'] == $group_rights['permission']) {
					$parent_group_rights['grouped'] = '1';
					unset($groups_rights[$groupid]);
				}
				unset($parent_group_rights);

				break;
			}
		}
		while ($parent_group_name !== '');
	}

	if (count($permissions) == 1) {
		$groups_rights = array_slice($groups_rights, -1);
		$groups_rights[0]['permission'] = key($permissions);
	}

	CArrayHelper::sort($groups_rights, [['field' => 'name', 'order' => ZBX_SORT_UP]]);

	return $groups_rights;
}

/**
 * Returns the sorted list of the unique tag filters.
 *
 * @param array  $tag_filters
 *
 * @return array
 */
function uniqTagFilters(array $tag_filters) {
	CArrayHelper::sort($tag_filters, ['groupid', 'tag', 'value']);

	$prev_tag_filter = null;

	foreach ($tag_filters as $key => $tag_filter) {
		if ($prev_tag_filter !== null && $prev_tag_filter['groupid'] == $tag_filter['groupid']
				&& ($prev_tag_filter['tag'] === '' || $prev_tag_filter['tag'] === $tag_filter['tag'])
				&& ($prev_tag_filter['value'] === '' || $prev_tag_filter['value'] === $tag_filter['value'])) {
			unset($tag_filters[$key]);
		}
		else {
			$prev_tag_filter = $tag_filter;
		}
	}

	return $tag_filters;
}

/**
 * Returns the sorted list of the unique tag filters and group names. Combines the tags belonging to the same group.
 * The list will be enriched by group names. Empty tag filters are labeled "All tags".
 *
 * @param array  $tag_filters
 * @param string $tag_filters[]['groupid']
 * @param string $tag_filters[]['tag']
 * @param string $tag_filters[]['value']
 *
 * @return array
 */
function collapseTagFilters(array $tag_filters) {
	$tag_filters = uniqTagFilters($tag_filters);

	$groupids = [];

	foreach ($tag_filters as $tag_filter) {
		$groupids[$tag_filter['groupid']] = true;
	}

	$groups = API::HostGroup()->get([
		'output' => ['name'],
		'groupids' => array_keys($groupids),
		'preservekeys' => true
	]);

	$combined_tag_filters = [];

	foreach ($tag_filters as $tag_filter) {
		$groupid = $tag_filter['groupid'];
		$name = array_key_exists($tag_filter['groupid'], $groups) ? $groups[$tag_filter['groupid']]['name'] : '';
		$tag = ['tag' => $tag_filter['tag'], 'value' => $tag_filter['value']];

		if (!array_key_exists($groupid, $combined_tag_filters)) {
			$combined_tag_filters[$groupid] = [
				'groupid' => $groupid,
				'name' => $name,
				'tags' => []
			];
		}

		$combined_tag_filters[$groupid]['tags'][] = $tag;

		CArrayHelper::sort($combined_tag_filters[$groupid]['tags'], ['tag', 'value']);
	}

	CArrayHelper::sort($combined_tag_filters, ['name']);

	return $combined_tag_filters;
}

/**
 * Get textual representation of given permission.
 *
 * @param string $perm  Numerical value of permission.
 *                          Possible values are:
 *                           3 - PERM_READ_WRITE,
 *                           2 - PERM_READ,
 *                           0 - PERM_DENY,
 *                          -1 - PERM_NONE;
 *
 * @return string
 */
function permissionText($perm) {
	switch ($perm) {
		case PERM_READ_WRITE:
			return _('Read-write');
		case PERM_READ:
			return _('Read');
		case PERM_DENY:
			return _('Deny');
		case PERM_NONE:
			return _('None');
	}
}

/**
 * Formats host or template group rights for writing in the database.
 * Filters out duplicates, and applies the most strict permission type for duplicates.
 *
 * @param array  $rights          An array of host or template group rights.
 * @param string $groupid_key     The key in the rights array for the group IDs.
 * @param string $permission_key  The key in the rights array for the permissions.
 *
 * @return array
 */
function processRights(array $rights, string $groupid_key, string $permission_key): array {
	$groupids = $rights[$groupid_key]['groupids'] ?? [];
	$permissions = $rights[$permission_key]['permission'] ?? [];

	$processed_rights = [];
	$unique_rights = [];

	foreach ($groupids as $index => $group) {
		foreach ($group as $groupid) {
			$permission = $permissions[$index] ?? PERM_DENY;

			if ($groupid != 0) {
				// If duplicates submitted, saves the one with most strict permission type.
				$unique_rights[$groupid] = array_key_exists($groupid, $unique_rights)
					? min($unique_rights[$groupid], $permission)
					: $permission;
			}
		}
	}

	foreach ($unique_rights as $groupid => $permission) {
		$processed_rights[] = [
			'id' => (string) $groupid,
			'permission' => $permission
		];
	}

	return $processed_rights;
}

/**
 * Checks if the groups specified in the $rights parameter exist in the provided $db_groups.
 *
 * @param array  $rights      Host or template groups submitted for permission update/creation.
 * @param array  $db_groups   Array of host or template groups fetched from the database.
 * @param string $group_name  Key in the $rights array for the list of groupids
 *                            ('ms_hostgroup_right' or 'ms_templategroup_right').
 *
 * @return bool
 */
function checkGroupsExist(array $rights, array $db_groups, string $group_name): bool {
	if (!array_key_exists($group_name, $rights)
			|| !array_key_exists('groupids', $rights[$group_name])
			|| !is_array($rights[$group_name]['groupids'])) {

		return true;
	}

	$all_groupids = array_merge(...$rights[$group_name]['groupids']);
	$existing_groupids = array_column($db_groups, 'groupid');

	foreach ($all_groupids as $groupid) {
		if (!in_array($groupid, $existing_groupids)) {

			return false;
		}
	}

	return true;
}
