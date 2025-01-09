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


class CControllerHostGroupList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'filter_name' =>	'string',
			'sort' =>			'in name',
			'sortorder' =>		'in '.ZBX_SORT_UP.','.ZBX_SORT_DOWN,
			'page' =>			'ge 1'
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
		$sort_field = $this->getInput('sort', CProfile::get('web.hostgroups.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.hostgroups.sortorder', ZBX_SORT_UP));

		CProfile::update('web.hostgroups.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.hostgroups.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.hostgroups.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.hostgroups.filter_name');
		}

		$filter = [
			'name' => CProfile::get('web.hostgroups.filter_name', '')
		];

		$data = [
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.hostgroups.filter',
			'active_tab' => CProfile::get('web.hostgroups.filter.active', 1),
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
		CArrayHelper::sort($groups, [['field' => $sort_field, 'order' => $sort_order]]);

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('hostgroup.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $groups, $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$groupids = array_column($groups, 'groupid');

		$data['groupCounts'] = API::HostGroup()->get([
			'output' => ['groupid'],
			'selectHosts' => API_OUTPUT_COUNT,
			'groupids' => $groupids,
			'preservekeys' => true
		]);

		$limit = CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE) + 1;
		$data['groups'] = API::HostGroup()->get([
			'output' => ['groupid', 'name', 'flags'],
			'selectHosts' => ['hostid', 'name', 'status'],
			'selectGroupDiscoveries' => ['ts_delete', 'status'],
			'selectDiscoveryRules' => ['itemid', 'name'],
			'selectHostPrototypes' => ['hostid'],
			'groupids' => $groupids,
			'limitSelects' => $limit
		]);
		CArrayHelper::sort($data['groups'], [['field' => $sort_field, 'order' => $sort_order]]);

		$host_prototypeids = [];

		foreach ($data['groups'] as &$group) {
			if ($group['discoveryRules']) {
				$editable_discovery_ruleids = API::DiscoveryRule()->get([
					'output' => [],
					'itemids' => array_column($group['discoveryRules'], 'itemid'),
					'editable' => true,
					'preservekeys' => true
				]);

				foreach ($group['discoveryRules'] as &$discovery_rule) {
					$discovery_rule['is_editable'] = array_key_exists($discovery_rule['itemid'],
						$editable_discovery_ruleids
					);
				}
				unset($discovery_rule);

				foreach ($group['hostPrototypes'] as $host_prototype) {
					$host_prototypeids[$host_prototype['hostid']] = true;
				}
			}

			CArrayHelper::sort($group['hosts'], ['name']);
			CArrayHelper::sort($group['discoveryRules'], ['name']);

			$group['discoveryRules'] = array_values($group['discoveryRules']);
		}
		unset($group);

		$host_prototypes = $host_prototypeids
			? API::HostPrototype()->get([
				'output' => ['hostid'],
				'selectDiscoveryRule' => ['itemid'],
				'hostids' => array_keys($host_prototypeids),
				'editable' => true
			])
			: [];

		$data['ldd_rule_to_host_prototype'] = [];

		foreach ($host_prototypes as $value) {
			$data['ldd_rule_to_host_prototype'][$value['discoveryRule']['itemid']][] = $value['hostid'];
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of host groups'));
		$this->setResponse($response);
	}
}
