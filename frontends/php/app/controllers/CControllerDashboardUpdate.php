<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


/**
 * Controller to update dashboard
 */
class CControllerDashboardUpdate extends CController {
	const EMPTY_USER = 'empty_user';
	const EMPTY_GROUP = 'empty_group';

	protected function checkInput() {
		$fields = [
			'dashboardid' =>	'required|db dashboard.dashboardid',
			'private' =>		'db dashboard.private|in 0,1',
			'users' =>			'array',
			'userGroups' =>		'array'
		];

		$ret = $this->validateInput($fields) && $this->checkUsers() && $this->checkUserGroups();

		if (!$ret) {
			$this->setResponse(new CControllerResponseData([
				'main_block' => CJs::encodeJson(['error' => _('Input data are invalid or don\'t exist!')])
			]));
		}

		return $ret;
	}

	/**
	 * Check users.
	 *
	 * @return bool
	 */
	private function checkUsers() {
		$users = $this->getInput('users', []);
		if (!is_array($users)) {
			return false;
		}
		foreach ($users as $key => $user) {
			if ($key !== self::EMPTY_USER && (!isset($user['userid']) || !isset($user['permission']))) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check user groups.
	 *
	 * @return bool
	 */
	private function checkUserGroups() {
		$usrgrps = $this->getInput('userGroups', []);
		if (!is_array($usrgrps)) {
			return false;
		}
		foreach ($usrgrps as $key => $group) {
			if ($key !== self::EMPTY_GROUP && (!isset($group['usrgrpid']) || !isset($group['permission']))) {
				return false;
			}
		}
		return true;
	}

	protected function checkPermissions() {
		return (bool) API::Dashboard()->get([
			'output' => [],
			'dashboardids' => $this->getInput('dashboardid'),
			'editable' => true
		]);
	}

	protected function doAction() {
		$dashboard = ['dashboardid' => $this->getInput('dashboardid')];

		if ($this->hasInput('private')) {
			$dashboard['private'] = $this->getInput('private');
		}
		if ($this->hasInput('users')) {
			$users = $this->getInput('users');
			// empty user needed to always POST the users param
			// if users is empty array (excluding empty user) then API delete all users
			unset($users[self::EMPTY_USER]);
			$dashboard['users'] = $users;
		}
		if ($this->hasInput('userGroups')) {
			$groups = $this->getInput('userGroups');
			// empty user group needed to always POST the userGroups param
			// if userGroups is empty array (excluding empty group) then API delete all userGroups
			unset($groups[self::EMPTY_GROUP]);
			$dashboard['userGroups'] = $groups;
		}

		$result = API::Dashboard()->update($dashboard);

		$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson(['result' => $result])]));
	}
}
