<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerPopupDashboardShareEdit extends CController {

	private $dashboard;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'dashboardid' => 'required|db dashboard.dashboardid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD)
				|| !$this->checkAccess(CRoleHelper::ACTIONS_EDIT_DASHBOARDS)) {
			return false;
		}

		$dashboards = API::Dashboard()->get([
			'output' => ['dashboardid', 'private'],
			'selectUsers' => ['userid', 'permission'],
			'selectUserGroups' => ['usrgrpid', 'permission'],
			'dashboardids' => [$this->getInput('dashboardid')],
			'editable' => true
		]);

		$this->dashboard = reset($dashboards);

		return true;
	}

	protected function doAction() {
		if ($this->dashboard) {
			$this->dashboard['users'] = $this->prepareUsers($this->dashboard['users']);
			$this->dashboard['userGroups'] = $this->prepareUserGroups($this->dashboard['userGroups']);

			$this->setResponse(new CControllerResponseData([
				'dashboard' => $this->dashboard,
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			]));
		}
		else {
			error(_('No permissions to referred object or it does not exist!'));

			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => [getMessages()->toString()]])
				]))->disableView()
			);
		}
	}

	/**
	 * Extend dashboard users data.
	 *
	 * @param array $users
	 * @param array $users[]['userid']
	 * @param array $users[]['permission']
	 *
	 * @return array
	 */
	private function prepareUsers(array $users = []) {
		$users = zbx_toHash($users, 'userid');

		$db_users = API::User()->get([
			'output' => ['userid', 'username', 'name', 'surname'],
			'userids' => array_keys($users)
		]);

		$result = [];
		foreach ($db_users as $db_user) {
			$result[] = [
				'id' => $db_user['userid'],
				'name' => getUserFullname($db_user),
				'permission' => $users[$db_user['userid']]['permission']
			];
		}
		CArrayHelper::sort($result, ['name']);

		return array_values($result);
	}

	/**
	 * Extend dashboard user groups data.
	 *
	 * @param array $usrgrps
	 * @param array $usrgrps[]['usrgrpid']
	 * @param array $usrgrps[]['permission']
	 *
	 * @return array
	 */
	private function prepareUserGroups(array $usrgrps = []) {
		$usrgrps = zbx_toHash($usrgrps, 'usrgrpid');

		$db_usrgrps = API::UserGroup()->get([
			'output' => ['usrgrpid', 'name'],
			'usrgrpids' => array_keys($usrgrps)
		]);

		$result = [];
		foreach ($db_usrgrps as $db_usrgrp) {
			$result[] = [
				'usrgrpid' => $db_usrgrp['usrgrpid'],
				'name' => $db_usrgrp['name'],
				'permission' => $usrgrps[$db_usrgrp['usrgrpid']]['permission']
			];
		}
		CArrayHelper::sort($result, ['name']);

		return array_values($result);
	}
}
