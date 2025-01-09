<?php declare(strict_types = 0);
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


class CControllerProxyGroupList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_name' =>	'string',
			'filter_state' =>	'in '.implode(',', [-1, ZBX_PROXYGROUP_STATE_OFFLINE, ZBX_PROXYGROUP_STATE_RECOVERING, ZBX_PROXYGROUP_STATE_ONLINE, ZBX_PROXYGROUP_STATE_DEGRADING]),
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'sort' =>			'in '.implode(',', ['name']),
			'sortorder'	=>		'in '.ZBX_SORT_UP.','.ZBX_SORT_DOWN,
			'page' =>			'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXY_GROUPS);
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.proxygroups.filter.name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.proxygroups.filter.state', $this->getInput('filter_state', -1), PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.proxygroups.filter.name');
			CProfile::delete('web.proxygroups.filter.state');
		}

		$filter = [
			'name' => CProfile::get('web.proxygroups.filter.name', ''),
			'state' => CProfile::get('web.proxygroups.filter.state', -1)
		];

		$sort_field = $this->getInput('sort', CProfile::get('web.proxygroups.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.proxygroups.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.proxygroups.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.proxygroups.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		$data = [
			'filter' => $filter,
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'profileIdx' => 'web.proxygroups.filter',
			'active_tab' => CProfile::get('web.proxygroups.filter.active', 1),
			'user' => [
				'can_edit_proxies' => $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)
			]
		];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		$data['proxy_groups'] = API::ProxyGroup()->get([
			'output' => ['proxy_groupid', 'name', 'failover_delay', 'min_online', 'state'],
			'selectProxies' => ['proxyid', 'name', 'state'],
			'search' => [
				'name' => $filter['name'] !== '' ? $filter['name'] : null
			],
			'filter' => [
				'state' => $filter['state'] != -1 ? $filter['state'] : null
			],
			'sortfield' => $sort_field,
			'limit' => $limit,
			'preservekeys' => true
		]);

		CArrayHelper::sort($data['proxy_groups'], [['field' => $sort_field, 'order' => $sort_order]]);

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('proxygroup.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['proxy_groups'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		foreach ($data['proxy_groups'] as &$proxy_group) {
			$proxy_group['proxy_count_total'] = count($proxy_group['proxies']);
			$proxy_group['proxy_count_online'] = 0;

			if (!$proxy_group['proxies']) {
				continue;
			}

			foreach ($proxy_group['proxies'] as $proxy) {
				if ($proxy['state'] == ZBX_PROXY_STATE_ONLINE) {
					$proxy_group['proxy_count_online']++;
				}
			}

			CArrayHelper::sort($proxy_group['proxies'], ['name']);

			$proxy_group['proxies'] = array_slice($proxy_group['proxies'], 0,
				CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE)
			);
		}
		unset($proxy_group);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of proxy groups'));
		$this->setResponse($response);
	}
}
