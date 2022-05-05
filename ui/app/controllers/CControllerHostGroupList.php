<?php declare(strict_types = 1);
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


class CControllerHostGroupList extends CController {

	protected function init(): void {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'filter_name' =>	'string',
			'sort' =>			'in name',
			'sortorder' =>		'in '.ZBX_SORT_UP.','.ZBX_SORT_DOWN
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOST_GROUPS);
	}

	protected function doAction(): void {
		$sort_field = $this->getInput('sort', CProfile::get('web.hostgroups.php.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.hostgroups.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.hostgroups.php.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.hostgroups.php.sortorder', $sort_order, PROFILE_TYPE_STR);

		// filter
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.groups.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.groups.filter_name');
		}

		$filter = [
			'name' => CProfile::get('web.groups.filter_name', '')
		];

		$data = [
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.groups.filter',
			'active_tab' => CProfile::get('web.groups.filter.active', 1),
			'config' => [
				'max_in_table' => CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE)
			],
			'allowed_ui_conf_hosts' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
		];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$groups = API::HostGroup()->get([
			'output' => ['groupid', $sort_field],
			'search' => [
				'name' => $filter['name'] === '' ? null : $filter['name']
			],
			'editable' => true,
			'sortfield' => $sort_field,
			'limit' => $limit
		]);
		order_result($groups, $sort_field, $sort_order);

		// pager
		if (hasRequest('page')) {
			$page_num = getRequest('page', 1);
		}
		else {
			$page_num = CPagerHelper::loadPage('hostgroup.list');
		}

		CPagerHelper::savePage('hostgroup.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $groups, $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$groupids = array_column($groups, 'groupid');

		// get host count
		$data['groupCounts'] = API::HostGroup()->get([
			'output' => ['groupid'],
			'groupids' => $groupids,
			'selectHosts' => API_OUTPUT_COUNT,
			'preservekeys' => true
		]);

		// get host groups
		$limit = CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE) + 1;
		$data['groups'] = API::HostGroup()->get([
			'output' => ['groupid', 'name', 'flags'],
			'groupids' => $groupids,
			'selectHosts' => ['hostid', 'name', 'status'],
			'selectGroupDiscovery' => ['ts_delete'],
			'selectDiscoveryRule' => ['itemid', 'name'],
			'limitSelects' => $limit
		]);
		order_result($data['groups'], $sort_field, $sort_order);

		foreach ($data['groups'] as &$group) {
			order_result($group['hosts'], 'name');
		}
		unset($group);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Host groups'));
		$this->setResponse($response);
	}
}

