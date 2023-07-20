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
			'usrgrpid' =>					'db usrgrp.usrgrpid',
			'form_refresh' =>				'int32'
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

		$data['group_rights'] = $this->getGroupRights();
		$data['templategroup_rights'] = $this->getTemplategroupRights();
		$data['tag_filters'] = $this->getTagFilters();

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
	 * Returns the sorted list of the unique tag filters and group names.
	 *
	 * @return array
	 */
	private function getTagFilters() {
		if ($this->hasInput('tag_filters')) {
			return collapseTagFilters($this->getInput('tag_filters'));
		}

		return collapseTagFilters($this->hasInput('usrgrpid') ? $this->user_group['tag_filters'] : []);
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
