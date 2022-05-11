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


class CControllerUsergroupList extends CController {

	protected function init() {
		$this->disableSIDValidation();
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
			'usergroups' => API::UserGroup()->get([
				'output' => ['usrgrpid', 'name', 'debug_mode', 'gui_access', 'users_status'],
				'selectUsers' => ['userid', 'username', 'name', 'surname', 'gui_access', 'users_status'],
				'search' => ['name' => ($filter['name'] !== '') ? $filter['name'] : null],
				'filter' => ['users_status' => ($filter['user_status'] != -1) ? $filter['user_status'] : null],
				'sortfield' => $sort_field,
				'limit' => $limit
			]),
			'allowed_ui_users' => $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS)
		];

		// data sort and pager
		CArrayHelper::sort($data['usergroups'], [['field' => $sort_field, 'order' => $sort_order]]);

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
