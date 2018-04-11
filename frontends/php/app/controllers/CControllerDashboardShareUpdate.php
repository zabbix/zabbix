<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
class CControllerDashboardShareUpdate extends CController {
	const EMPTY_USER = 'empty_user';
	const EMPTY_GROUP = 'empty_group';

	protected function checkInput() {
		$fields = [
			'dashboardid' => 'required|db dashboard.dashboardid',
			'private' => 'db dashboard.private|in 0,1',
			'users' => 'array',
			'userGroups' => 'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseData([
				'main_block' => CJs::encodeJson(['errors' => getMessages()->toString()])
			]));
		}

		return $ret;
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

		$result = (bool) API::Dashboard()->update($dashboard);

		$response = [
			'result' => $result,
			'errors' => (($messages = getMessages()) !== null) ? $messages->toString() : ''
		];
		$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($response)]));
	}
}
