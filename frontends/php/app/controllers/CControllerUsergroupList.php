<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
			'sort' => 'in name',
			'sortorder' => 'in ' . ZBX_SORT_DOWN . ',' . ZBX_SORT_UP,
			'filter_set' => 'in 1',
			'filter_rst' => 'in 1',
			'filter_user_status' => 'in -1,' . GROUP_STATUS_ENABLED . ',' . GROUP_STATUS_DISABLED,
			/* 'page' => 'ge 1' */
			'uncheck' => 'in 1',
			'filter_name' => 'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	/**
	 * Updates use profile key values based on current input.
	 */
	protected function updateUserProfile() {
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.usergroup.filter_user_status', $this->getInput('filter_user_status', -1), PROFILE_TYPE_INT);
			CProfile::update('web.usergroup.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.usergroup.filter_user_status');
			CProfile::delete('web.usergroup.filter_name');
		}

		CProfile::update('web.usergroup.sort',
			$this->getInput('sort', CProfile::get('web.usergroup.sort', 'name')), PROFILE_TYPE_STR
		);
		CProfile::update('web.usergroup.sortorder',
			$this->getInput('sortorder', CProfile::get('web.usergroup.sortorder', ZBX_SORT_UP)), PROFILE_TYPE_STR
		);
	}

	/**
	 * @param array &$paging  Field the paginator will be created in.
	 * @param array &$filter  Outputs the filter value being used for selection.
	 *
	 * @return array  Sorted and paginated user groups.
	 */
	protected function selectUserGroups(&$paging, &$filter) {
		$config = select_config();

		$filter = [
			'name' => CProfile::get('web.usergroup.filter_name', ''),
			'user_status' => CProfile::get('web.usergroup.filter_user_status', -1)
		];

		$options = [
			'output' => API_OUTPUT_EXTEND,
			'selectUsers' => API_OUTPUT_EXTEND,
			'limit' => $config['search_limit'] + 1
		];

		if ($filter['name'] !== '') {
			$options['search'] = ['name' => $filter['name']];
		}

		if ($filter['user_status'] != -1) {
			$options['filter'] = ['users_status' => $filter['user_status']];
		}

		$usergroups = API::UserGroup()->get($options);

		CArrayHelper::sort($usergroups, [[
			'field' => CProfile::get('web.usergroup.sort', 'name'),
			'order' => CProfile::get('web.usergroup.sortorder', ZBX_SORT_UP)
		]]);

		$paging = getPagingLine($usergroups, CProfile::get('web.usergroup.sortorder', ZBX_SORT_UP),
			(new CUrl('zabbix.php'))->setArgument('action', 'usergroup.list')
		);

		foreach ($usergroups as &$usergroup) {
			order_result($usergroup['users'], 'alias');

			$usergroup['user_ctn'] = count($usergroup['users']);
			if ($usergroup['user_ctn'] > $config['max_in_table']) {
				array_splice($usergroup['users'], $config['max_in_table'], $usergroup['user_ctn'], [null]);
			}
		}
		unset($usergroup);

		return $usergroups;
	}

	protected function doAction() {
		$this->updateUserProfile();

		$data = [
			'uncheck' => $this->hasInput('uncheck'),
			'is_filter_visible' => CProfile::get('web.usergroup.filter.active', 1),
			'sort' => CProfile::get('web.usergroup.sort', 'name', PROFILE_TYPE_STR),
			'sortorder' => CProfile::get('web.usergroup.sortorder', ZBX_SORT_UP),
			'profileIdx' => 'web.usergroup.filter',
			'usergroups' => $this->selectUserGroups($paging, $filter),
			'paging' => $paging,
			'filter' => $filter,
		];

		$response = new CControllerResponseData($data);

		$response->setTitle(_('Configuration of user groups'));
		$this->setResponse($response);
	}
}
