<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

			'ms_hostgroup_right' =>		'array',
			'hostgroup_right' =>		'array',
			'ms_templategroup_right' =>	'array',
			'templategroup_right' =>	'array',
			'ms_tag_filter' =>			'array',
			'tag_filter' =>			    'array',

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
				'output' => ['name', 'gui_access', 'users_status', 'debug_mode', 'userdirectoryid'],
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
		$data = [
			'usrgrpid' => 0,
			'name' => $db_defaults['name'],
			'gui_access' => $db_defaults['gui_access'],
			'userdirectoryid' => 0,
			'users_status' => $db_defaults['users_status'],
			'debug_mode' => $db_defaults['debug_mode'],
			'form_refresh' => 0
		];

		if ($this->hasInput('usrgrpid')) {
			$data['usrgrpid'] = $this->user_group['usrgrpid'];
			$data['name'] = $this->user_group['name'];
			$data['gui_access'] = $this->user_group['gui_access'];
			$data['users_status'] = $this->user_group['users_status'];
			$data['debug_mode'] = $this->user_group['debug_mode'];
			$data['userdirectoryid'] = $this->user_group['userdirectoryid'];
		}

		$data['hostgroup_rights'] = $this->getGroupRights();
		$data['templategroup_rights'] = $this->getTemplategroupRights();

		// Get the sorted list of unique tag filters and hostgroup names.
		$data['tag_filters'] = collapseTagFilters($this->hasInput('usrgrpid') ? $this->user_group['tag_filters'] : []);

		$host_groups = API::HostGroup()->get(['output' => ['groupid', 'name']]);
		$template_groups = API::TemplateGroup()->get(['output' => ['groupid', 'name']]);

		$new_hostgroup_rights = $this->processNewGroupRights($host_groups, 'ms_hostgroup_right', 'hostgroup_right');
		$new_templategroup_rights = $this->processNewGroupRights(
			$template_groups, 'ms_templategroup_right', 'templategroup_right'
		);

		if (count($new_hostgroup_rights) > 0) {
			$data['hostgroup_rights'] = $this->sortGroupRights($new_hostgroup_rights);
		}

		if (count($new_templategroup_rights) > 0) {
			$data['templategroup_rights'] = $this->sortGroupRights($new_templategroup_rights);
		}

		$new_tag_filters = [];
		$this->getInputs($new_tag_filters, ['ms_tag_filter', 'tag_filter']);

		$tag_filters_groupIds = $new_tag_filters['ms_tag_filter']['groupids'] ?? [];
		$tags = $new_tag_filters['tag_filter']['tag'] ?? [];
		$values = $new_tag_filters['tag_filter']['value'] ?? [];

		$formatted_new_tag_filters = [];

		foreach ($tag_filters_groupIds as $index => $group) {
			foreach ($group as $groupId) {
				$tag = $tags[$index] ?? null;
				$value = $values[$index] ?? null;

				if ($groupId !== '0'&& $tag !== null && $value !== null) {
					$key = array_search($groupId, array_column($host_groups, 'groupid'));
					$name = $key !== false ? $host_groups[$key]['name'] : '';

					$formatted_new_tag_filters[] = [
						'groupid' => $groupId,
						'tag' => $tag,
						'value' => $value,
						'name' => $name
					];
				}
			}
		}

		if (count($formatted_new_tag_filters) > 0) {
			$data['tag_filters'] = $formatted_new_tag_filters;
		}

		$data['users_ms'] = $this->getUsersMs();

		$data['can_update_group'] = (
			!$this->hasInput('usrgrpid')
			|| granted2update_group($this->getInput('usrgrpid'))
		);

		if ($data['can_update_group']) {
			$userdirectories = API::UserDirectory()->get([
				'output' => ['userdirectoryid', 'name'],
				'filter' => ['idp_type' => IDP_TYPE_LDAP]
			]);
			CArrayHelper::sort($userdirectories, ['name']);
			$data['userdirectories'] = array_column($userdirectories, 'name', 'userdirectoryid');
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of user groups'));
		$this->setResponse($response);
	}

	/**
	 * Returns the sorted list of permissions to the host groups.
	 *
	 * @return array
	 */
	private function getGroupRights() {
		$group_rights = collapseGroupRights(
			getHostGroupsRights($this->hasInput('usrgrpid') ? [$this->user_group['usrgrpid']] : [])
		);

		return $this->sortGroupRights($group_rights);
	}

	/**
	 * Returns the sorted list of permissions to the template groups.
	 *
	 * @return array
	 */
	private function getTemplategroupRights() {
		$group_rights = collapseGroupRights(
			getTemplateGroupsRights($this->hasInput('usrgrpid') ? [$this->user_group['usrgrpid']] : [])
		);

		return $this->sortGroupRights($group_rights);
	}

	/**
	 * Returns host or template group rights formatted for providing in response.
	 *
	 * @return array
	 */
	function processNewGroupRights($groups, $groupId_key, $permission_key) {
		$new_rights = [];
		$this->getInputs($new_rights, [$groupId_key, $permission_key]);

		$groupIds = $new_rights[$groupId_key]['groupids'] ?? [];
		$permissions = $new_rights[$permission_key]['permission'] ?? [];

		$group_rights = [];

		foreach ($groupIds as $index => $group) {
			foreach ($group as $groupId) {
				$permission = $permissions[$index] ?? PERM_DENY;

				if ($groupId !== '0') {
					$key = array_search($groupId, array_column($groups, 'groupid'));
					$name = $key !== false ? $groups[$key]['name'] : '';

					$group_rights[$groupId] = [
						'permission' => $permission,
						'name' => $name
					];
				}
			}
		}

		return $group_rights;
	}

	/**
	 * Returns the sorted host or template group rights by permission type.
	 *
	 * @return array
	 */
	private function sortGroupRights($group_rights) {
		$sorted_group_rights = [];

		foreach ($group_rights as $id => $right) {
			if ($right['permission'] == PERM_NONE) {
				continue;
			}

			switch ($right['permission']) {
				case PERM_DENY:
					$group = PERM_DENY;
					break;
				case PERM_READ:
					$group = PERM_READ;
					break;
				case PERM_READ_WRITE:
					$group = PERM_READ_WRITE;
					break;
				default:
					$group = PERM_DENY;
			}

			if (!isset($sorted_group_rights[$group])) {
				$sorted_group_rights[$group] = [];
			}

			$sorted_group_rights[$group][$id] = $right;
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
			'output' => ['userid', 'username', 'name', 'surname']
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
			$users_ms[] = ['id' => $user['userid'], 'name' => getUserFullname($user)];
		}

		CArrayHelper::sort($users_ms, ['name']);

		return $users_ms;
	}
}
