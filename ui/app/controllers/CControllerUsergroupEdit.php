<?php declare(strict_types = 0);
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


class CControllerUsergroupEdit extends CController {

	/**
	 * @var array  User group data from database.
	 */
	private $user_group = [];

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'usrgrpid' =>				'db usrgrp.usrgrpid',
			'name' =>					'db usrgrp.name',
			'userids' =>				'array_db users.userid',
			'gui_access' =>				'db usrgrp.gui_access|in '.implode(',', [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_LDAP, GROUP_GUI_ACCESS_DISABLED]),
			'users_status' =>			'db usrgrp.users_status|in '.GROUP_STATUS_ENABLED.','.GROUP_STATUS_DISABLED,
			'debug_mode' =>				'db usrgrp.debug_mode|in '.GROUP_DEBUG_MODE_ENABLED.','.GROUP_DEBUG_MODE_DISABLED,
			'ms_hostgroup_right' =>		'array',
			'hostgroup_right' =>		'array',
			'ms_templategroup_right' =>	'array',
			'templategroup_right' =>	'array',
			'tag_filters' =>			'array',
			'form_refresh' =>			'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_GROUPS)) {
			return false;
		}

		if ($this->hasInput('usrgrpid')) {
			$user_groups = API::UserGroup()->get([
				'output' => ['name', 'gui_access', 'users_status', 'debug_mode', 'userdirectoryid', 'mfa_status',
					'mfaid'
				],
				'selectTagFilters' => ['groupid', 'tag', 'value'],
				'usrgrpids' => $this->getInput('usrgrpid'),
				'editable' => true
			]);

			if (!$user_groups) {
				return false;
			}

			$this->user_group = $user_groups[0];
		}

		return true;
	}

	protected function doAction() {
		$db_defaults = DB::getDefaults('usrgrp');
		$mfa_config_status = CAuthenticationHelper::get(CAuthenticationHelper::MFA_STATUS);
		$data = [
			'usrgrpid' => 0,
			'name' => $db_defaults['name'],
			'gui_access' => $db_defaults['gui_access'],
			'userdirectoryid' => 0,
			'users_status' => $db_defaults['users_status'],
			'debug_mode' => $db_defaults['debug_mode'],
			'form_refresh' => 0,
			'group_mfa_status' => $mfa_config_status ? GROUP_MFA_ENABLED : GROUP_MFA_DISABLED,
			'mfaid' => 0
		];

		if ($this->hasInput('usrgrpid')) {
			$data['usrgrpid'] = $this->user_group['usrgrpid'];
			$data['name'] = $this->user_group['name'];
			$data['gui_access'] = $this->user_group['gui_access'];
			$data['users_status'] = $this->user_group['users_status'];
			$data['debug_mode'] = $this->user_group['debug_mode'];
			$data['userdirectoryid'] = $this->user_group['userdirectoryid'];
			$data['group_mfa_status'] = $this->user_group['mfa_status'];
			$data['mfaid'] = $this->user_group['mfaid'];
		}

		$this->getInputs($data, ['name', 'gui_access', 'users_status', 'debug_mode', 'form_refresh']);

		$host_groups = API::HostGroup()->get([
			'output' => ['groupid', 'name']
		]);

		$data['hostgroup_rights'] = $this->getGroupRights($host_groups);
		$data['templategroup_rights'] = $this->getTemplategroupRights();

		// Get the sorted list of unique tag filters and hostgroup names.
		$data['tag_filters'] = collapseTagFilters($this->hasInput('usrgrpid') ? $this->user_group['tag_filters'] : []);

		if ($this->hasInput('tag_filters')) {
			foreach ($this->getInput('tag_filters') as $tag_filter) {
				$groupid = $tag_filter['groupid'];

				if (in_array($groupid, array_column($host_groups, 'groupid'))) {
					$key = array_search($groupid, array_column($host_groups, 'groupid'));
					$name = $key !== false ? $host_groups[$key]['name'] : '';

					$data['tag_filters'][$groupid] = [
						'groupid' => $groupid,
						'name' => $name,
						'tags' => $tag_filter['tags']
					];
				}
			}
		}

		CArrayHelper::sort($data['tag_filters'], ['name']);

		$tag_filters_badges = $data['tag_filters'];

		foreach ($tag_filters_badges as $key => $group) {
			$tags = $group['tags'];

			if (!$tags || (count($tags) == 1 && $tags[key($tags)]['tag'] === '')) {
				unset($tag_filters_badges[$key]);
			}
		}

		$data['tag_filters_badges'] = makeTags($tag_filters_badges, true, 'groupid');
		$data['users_ms'] = $this->getUsersMs();
		$data['can_update_group'] = (!$this->hasInput('usrgrpid') || granted2update_group($this->getInput('usrgrpid')));

		if ($data['can_update_group']) {
			$userdirectories = API::UserDirectory()->get([
				'output' => ['userdirectoryid', 'name'],
				'filter' => ['idp_type' => IDP_TYPE_LDAP]
			]);
			CArrayHelper::sort($userdirectories, ['name']);
			$data['userdirectories'] = array_column($userdirectories, 'name', 'userdirectoryid');

			$data['ldap_status'] = CAuthenticationHelper::get(CAuthenticationHelper::LDAP_AUTH_ENABLED);
		}

		$mfas = API::Mfa()->get([
			'output' => ['mfaid', 'name'],
			'sortfield' => ['name']
		]);
		$data['mfas'] = array_column($mfas, 'name', 'mfaid');
		$data['mfa_config_status'] = $mfa_config_status;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of user groups'));
		$this->setResponse($response);
	}

	/**
	 * Returns the sorted list of permissions to the host groups.
	 *
	 * @param array $host_groups  Optional array of host groups to process.
	 *                            If not provided, will use the host groups from the input.
	 *
	 * @return array
	 */
	private function getGroupRights(array $host_groups = []): array {
		if ($this->hasInput('ms_hostgroup_right') && $this->hasInput('hostgroup_right')) {
			$new_hostgroup_rights = $this->processNewRights($host_groups, 'ms_hostgroup_right', 'hostgroup_right');

			if ($new_hostgroup_rights) {
				CArrayHelper::sort($new_hostgroup_rights, ['name']);

				return $this->sortGroupRights($new_hostgroup_rights);
			}
		}

		$group_rights = getHostGroupsRights($this->hasInput('usrgrpid') ? [$this->user_group['usrgrpid']] : []);

		CArrayHelper::sort($group_rights, ['name']);

		return $this->sortGroupRights($group_rights);
	}

	/**
	 * Returns the sorted list of permissions to the template groups.
	 *
	 * @return array
	 */
	private function getTemplategroupRights(): array {
		$template_groups = API::TemplateGroup()->get([
			'output' => ['groupid', 'name']
		]);

		if ($this->hasInput('ms_templategroup_right') && $this->hasInput('templategroup_right')) {
			$new_templategroup_rights = $this->processNewRights(
				$template_groups, 'ms_templategroup_right', 'templategroup_right'
			);

			if ($new_templategroup_rights) {
				CArrayHelper::sort($new_templategroup_rights, ['name']);

				return $this->sortGroupRights($new_templategroup_rights);
			}
		}

		$group_rights = getTemplateGroupsRights($this->hasInput('usrgrpid') ? [$this->user_group['usrgrpid']] : []);

		CArrayHelper::sort($group_rights, ['name']);

		return $this->sortGroupRights($group_rights);
	}

	/**
	 * Formats the new host or template group rights from the input suitable for providing in the response.
	 *
	 * @param array  $groups          An array of host or template groups.
	 * @param string $groupid_key     The key in the input for the group IDs.
	 * @param string $permission_key  The key in the input for the permissions.
	 *
	 * @return array
	 */
	function processNewRights(array $groups, string $groupid_key, string $permission_key): array {
		$new_rights = [];
		$this->getInputs($new_rights, [$groupid_key, $permission_key]);

		$groupids = $new_rights[$groupid_key]['groupids'] ?? [];
		$permissions = $new_rights[$permission_key]['permission'] ?? [];

		$group_rights = [];
		$unique_rights = [];

		foreach ($groupids as $index => $group) {
			foreach ($group as $groupid) {
				$permission = $permissions[$index] ?? PERM_DENY;

				if ($groupid != 0 && in_array($groupid, array_column($groups, 'groupid'))) {
					$unique_rights[$groupid] = array_key_exists($groupid, $unique_rights)
						? min($unique_rights[$groupid], $permission)
						: $permission;
				}
			}
		}

		foreach($unique_rights as $groupid => $permission) {
			$key = array_search($groupid, array_column($groups, 'groupid'));
			$name = $key !== false ? $groups[$key]['name'] : '';

			$group_rights[$groupid] = [
				'permission' => $permission,
				'name' => $name
			];
		}

		return $group_rights;
	}

	/**
	 * Returns the sorted host or template group rights by permission type.
	 *
	 * @return array
	 */
	private function sortGroupRights(array $group_rights): array {
		$sorted_group_rights = [];

		foreach ($group_rights as $id => $right) {
			if ($right['permission'] == PERM_NONE) {
				continue;
			}

			$right['groupid'] = $id;
			$permission = $right['permission'];

			if (!array_key_exists($permission, $sorted_group_rights)) {
				$sorted_group_rights[$permission] = [];
			}

			$sorted_group_rights[$permission][] = $right;
		}

		return $sorted_group_rights;
	}

	/**
	 * Returns all needed users formatted for multiselector.
	 *
	 * @return array
	 */
	private function getUsersMs() {
		$options = [
			'output' => ['userid', 'username', 'name', 'surname', 'userdirectoryid']
		];

		if ($this->hasInput('usrgrpid') && !$this->hasInput('form_refresh')) {
			$options['usrgrpids'] = $this->getInput('usrgrpid');
		}
		else {
			$options['userids'] = $this->getInput('userids', []);
		}

		$users = (array_key_exists('usrgrpids', $options) || $options['userids'] !== [])
			? API::User()->get($options)
			: [];

		$users_ms = [];
		foreach ($users as $user) {
			$users_ms[] = ['id' => $user['userid'], 'name' => getUserFullname($user),
				'disabled' => (bool) $user['userdirectoryid']
			];
		}

		CArrayHelper::sort($users_ms, ['name']);

		return $users_ms;
	}
}
