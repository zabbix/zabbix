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


class CControllerUserroleList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'filter_name' => 'string',
			'filter_set' => 'in 1',
			'filter_rst' => 'in 1',
			'sort' => 'in name',
			'sortorder' => 'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'page' => 'ge 1',
			'uncheck' => 'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_ROLES);
	}

	protected function doAction() {
		$sort_field = $this->getInput('sort', CProfile::get('web.userrole.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.userrole.sortorder', ZBX_SORT_UP));

		CProfile::update('web.userrole.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.userrole.sortorder', $sort_order, PROFILE_TYPE_STR);

		// filter
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.userrole.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.userrole.filter_name');
		}

		// Prepare data for view.
		$filter = ['name' => CProfile::get('web.userrole.filter_name', '')];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data = [
			'uncheck' => $this->hasInput('uncheck'),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.userrole.filter',
			'active_tab' => CProfile::get('web.userrole.filter.active', 1),
			'roles' => API::Role()->get([
				'output' => ['roleid', 'name', 'type', 'readonly'],
				'selectUsers' => ['userid', 'username'],
				'search' => ['name' => ($filter['name'] !== '') ? $filter['name'] : null],
				'sortfield' => $sort_field,
				'limit' => $limit,
				'preservekeys' => true
			]),
			'allowed_ui_users' => $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS)
		];

		// Data sort and pager.
		CArrayHelper::sort($data['roles'], [['field' => $sort_field, 'order' => $sort_order]]);

		$page_num = getRequest('page', 1);
		CPagerHelper::savePage('userrole.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['roles'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);
		$userids = [];

		foreach ($data['roles'] as &$role) {
			CArrayHelper::sort($role['users'], ['username']);

			$role['user_cnt'] = count($role['users']);
			if ($role['user_cnt'] > CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE)) {
				$role['users'] = array_slice($role['users'], 0, CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE));
			}

			$userids = array_merge($userids, array_column($role['users'], 'userid'));
			$role['users'] = [];
		}
		unset($role);

		$users = API::User()->get([
			'output' => ['userid', 'username', 'name', 'surname', 'gui_access', 'users_status', 'roleid'],
			'userids' => $userids,
			'getAccess' => true
		]);

		foreach ($users as &$user) {
			$data['roles'][$user['roleid']]['users'][] = $user;
		}
		unset($user);

		foreach ($data['roles'] as &$role) {
			CArrayHelper::sort($role['users'], ['username']);
		}
		unset($role);

		$response = new CControllerResponseData($data);

		$response->setTitle(_('Configuration of user roles'));
		$this->setResponse($response);
	}
}
