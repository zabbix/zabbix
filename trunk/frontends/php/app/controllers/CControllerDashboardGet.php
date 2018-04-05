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
 * Controller to get dashboard data.
 */
class CControllerDashboardGet extends CController {

	private $dashboard;

	protected function checkInput() {
		$fields = [
			'dashboardid' => 'required|db dashboard.dashboardid',
			'editable' => 'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseData([
				'main_block' => CJs::encodeJson(['messages' => [getMessages()->toString()]])
			]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		$dashboards = API::Dashboard()->get([
			'output' => ['dashboardid', 'private'],
			'selectUsers' => ['userid', 'permission'],
			'selectUserGroups' => ['usrgrpid', 'permission'],
			'dashboardids' => $this->getInput('dashboardid'),
			'editable' => (bool) $this->getInput('editable', false)
		]);

		if (!$dashboards) {
			return false;
		}

		$this->dashboard = $dashboards[0];

		return true;
	}

	protected function doAction() {
		$this->dashboard['users'] = $this->prepareUsers($this->dashboard['users']);
		$this->dashboard['userGroups'] = $this->prepareUserGroups($this->dashboard['userGroups']);

		$this->setResponse(new CControllerResponseData([
			'main_block' => CJs::encodeJson(['data' => $this->dashboard])
		]));
	}

	/**
	 * Extend dashboard users data
	 *
	 * @param array $users
	 *
	 * @return array
	 */
	private function prepareUsers(array $users) {
		$users = zbx_toHash($users, 'userid');

		$db_users = API::User()->get([
			'output' => ['userid', 'alias', 'name', 'surname'],
			'userids' => array_keys($users)
		]);

		$result = [];
		foreach ($db_users as $db_user) {
			$result[] = [
				'id'   => $db_user['userid'],
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
	 *
	 * @return array
	 */
	private function prepareUserGroups(array $usrgrps) {
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
