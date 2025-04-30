<?php
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


class CControllerUsergroupList extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'filter_name' => 'string',
			'filter_user_status' => 'in -1,'.GROUP_STATUS_ENABLED.','.GROUP_STATUS_DISABLED,
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
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_GROUPS);
	}

	protected function doAction() {
		$sort_field = $this->getInput('sort', CProfile::get('web.usergroup.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.usergroup.sortorder', ZBX_SORT_UP));

		CProfile::update('web.usergroup.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.usergroup.sortorder', $sort_order, PROFILE_TYPE_STR);

		// filter
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.usergroup.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.usergroup.filter_user_status', $this->getInput('filter_user_status', -1),
				PROFILE_TYPE_INT
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.usergroup.filter_name');
			CProfile::delete('web.usergroup.filter_user_status');
		}

		// Prepare data for view.
		$filter = [
			'name' => CProfile::get('web.usergroup.filter_name', ''),
			'user_status' => CProfile::get('web.usergroup.filter_user_status', -1)
		];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data = [
			'uncheck' => $this->hasInput('uncheck'),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.usergroup.filter',
			'active_tab' => CProfile::get('web.usergroup.filter.active', 1),
			'allowed_ui_users' => $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS)
		];

		$user_groups = API::UserGroup()->get([
			'output' => ['usrgrpid', 'name', 'debug_mode', 'gui_access', 'users_status'],
			'search' => ['name' => ($filter['name'] !== '') ? $filter['name'] : null],
			'filter' => ['users_status' => ($filter['user_status'] != -1) ? $filter['user_status'] : null],
			'sortfield' => $sort_field,
			'limit' => $limit,
			'preservekeys' => true
		]);

		if ($user_groups) {
			$users = API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'usrgrpids' => array_keys($user_groups),
				'selectUsrgrps' => ['usrgrpid'],
				'getAccess' => true
			]);

			foreach ($user_groups as $usrgrpid => &$user_group) {
				$usergroup_users = [];

				foreach ($users as $user) {
					if (array_key_exists($usrgrpid, array_flip(array_column($user['usrgrps'], 'usrgrpid')))) {
						$usergroup_users[] = array_intersect_key($user,
							array_flip(['userid', 'username', 'name', 'surname', 'gui_access', 'users_status'])
						);
					}
				}

				$user_group['users'] = $usergroup_users;
			}
			unset($user_group);

			$data['usergroups'] = $user_groups;

			// data sort
			CArrayHelper::sort($data['usergroups'], [['field' => $sort_field, 'order' => $sort_order]]);
		}
		else {
			$data['usergroups'] = [];
		}

		// data pager
		$page_num = getRequest('page', 1);
		CPagerHelper::savePage('usergroup.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['usergroups'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		foreach ($data['usergroups'] as &$usergroup) {
			CArrayHelper::sort($usergroup['users'], ['username']);

			$usergroup['user_cnt'] = count($usergroup['users']);
			if ($usergroup['user_cnt'] > CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE)) {
				$usergroup['users'] = array_slice($usergroup['users'], 0, CSettingsHelper::get(
					CSettingsHelper::MAX_IN_TABLE
				));
			}
		}
		unset($usergroup);

		$response = new CControllerResponseData($data);

		$response->setTitle(_('Configuration of user groups'));
		$this->setResponse($response);
	}
}
