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
		return true;
	}

	protected function doAction() {
		$editable_dashboard = (bool) API::Dashboard()->get([
			'output' => [],
			'dashboardids' => $this->getInput('dashboardid'),
			'editable' => true
		]);

		$msg_box_title = null;
		if ($editable_dashboard) {
			$dashboard = ['dashboardid' => $this->getInput('dashboardid')];

			if ($this->hasInput('private')) {
				$dashboard['private'] = $this->getInput('private');
			}
			if ($this->hasInput('users')) {
				$users = $this->getInput('users');
				/**
				 * Empty user needed to always POST the 'users' parameter.
				 * If 'users' parameter is empty array (excluding empty user) then API deletes all users.
				 */
				unset($users[self::EMPTY_USER]);
				$dashboard['users'] = $users;
			}
			if ($this->hasInput('userGroups')) {
				$groups = $this->getInput('userGroups');
				/**
				 * Empty user group always needs POST the 'userGroups' parameter.
				 * If 'userGroups' is empty array (excluding empty group) the API deletes all user groups.
				 */
				unset($groups[self::EMPTY_GROUP]);
				$dashboard['userGroups'] = $groups;
			}

			$result = (bool) API::Dashboard()->update($dashboard);

			if ($result) {
				$msg_box_title = _('Dashboard updated');
			}
		}
		else {
			error(_('No permissions to referred object or it does not exist!'));
			$result = false;
		}

		$response = [
			'result' => $result
		];

		if (($messages = getMessages($result, $msg_box_title)) !== null) {
			$response[$result ? 'messages' : 'errors'] = $messages->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($response)]));
	}
}
