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
 * Controller to get dashboard data
 *
 */
class CControllerDashboardGet extends CController
{
	protected function checkInput() {
		$fields = [
			'dashboardid' => 'db dashboard.dashboardid',
			'editable'    => 'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData([
					'main_block' => CJs::encodeJson(['error' => _('Input data are invalid or don\'t exist!')])
				])
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() < USER_TYPE_ZABBIX_USER) {
			return false;
		}

		$dashboards = API::Dashboard()->get([
			'output' => [],
			'dashboardids' => $this->getInput('dashboardid'),
			'editable' => (boolean) $this->getInput('editable', false)
		]);

		if (!$dashboards) {
			return false;
		}

		return true;
	}

	protected function doAction()
	{
		$this->setResponse(new CControllerResponseData([
			'main_block' => CJs::encodeJson(['data' => $this->getDashboard()])
		]));
	}

	/**
	 * Get dashboard data from API
	 *
	 * @return array|null
	 */
	private function getDashboard() {
		$dashboards = API::Dashboard()->get([
			'output' => ['dashboardid', 'name', 'private'],
			'selectUsers' => ['userid', 'permission'],
			'selectUserGroups' => ['usrgrpid', 'permission'],
			'dashboardids' => $this->getInput('dashboardid')
		]);

		if ($dashboards) {
			$dashboard = $dashboards[0];
			$dashboard['users'] = $this->prepareUsers($dashboard['users']);
			$dashboard['user_groups'] = $this->prepareUserGroups($dashboard['userGroups']);
			unset($dashboard['userGroups']);
			return $dashboard;
		}
		return null;
	}

	/**
	 * Extend dashboard users data
	 *
	 * @param array $dashboard_users
	 * @return array
	 */
	private function prepareUsers(array $dashboard_users) {
		$userids = [];
		foreach ($dashboard_users as $data) {
			$userids[$data['userid']] = $data['permission'];
		}

		$users = API::User()->get([
			'output' => ['userid', 'alias', 'name', 'surname'],
			'userids' => array_keys($userids)
		]);

		$result = [];
		foreach ($users as $user) {
			$result[] = [
				'id'   => $user['userid'],
				'name' => getUserFullname($user),
				'permission' => $userids[$user['userid']]
			];
		}

		return $result;
	}

	/**
	 * Extend dashboard user groups data
	 *
	 * @param array $dashboard_user_groups
	 * @return array
	 */
	private function prepareUserGroups(array $dashboard_user_groups)
	{
		$groupids = [];
		foreach ($dashboard_user_groups as $data) {
			$groupids[$data['usrgrpid']] = $data['permission'];
		}

		$groups = API::UserGroup()->get([
			'output' => ['usrgrpid', 'name'],
			'usrgrpids' => array_keys($groupids)
		]);
		$result = [];
		foreach ($groups as $user_group) {
			$result[] = [
				'usrgrpid' => $user_group['usrgrpid'],
				'name' => $user_group['name'],
				'permission' => $groupids[$user_group['usrgrpid']]['permission']
			];
		}
		return $result;
	}
}
