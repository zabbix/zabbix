<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerHostListData extends CControllerDataTable {

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
	}

	protected function getData(): array {
		$columns = $this->getInput('columns');
		$filter = $this->getInput('filter', []);
		$page = $this->getInput('page', (int) CPagerHelper::loadPage('host.list'));
		$sort_field = $this->getInput('sort_field');
		$sort_order = $this->getInput('sort_order');

		CArrayHelper::sort($filter['tags'], ['tag', 'value', 'operator']);

		$filter['groups'] = $filter['groups']
			? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => array_column($filter['groups'], 'id'),
				'preservekeys' => true
			]), ['groupid' => 'id'])
			: [];

		$filter_groupids = $filter['groups'] ? array_keys($filter['groups']) : null;

		if ($filter_groupids) {
			$filter_groupids = getSubGroups($filter_groupids);
		}

		$filter['templates'] = $filter['templates']
			? CArrayHelper::renameObjectsKeys(API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => $filter['templates'],
				'preservekeys' => true
			]), ['templateid' => 'id'])
			: [];

		switch ($filter['monitored_by']) {
			case ZBX_MONITORED_BY_ANY:
				$proxyids = null;
				$proxy_groupids = null;
				break;

			case ZBX_MONITORED_BY_SERVER:
				$proxyids = 0;
				$proxy_groupids = 0;
				break;

			case ZBX_MONITORED_BY_PROXY:
				$proxyids = $filter['proxyids'] ?: array_keys(API::Proxy()->get([
					'output' => [],
					'preservekeys' => true
				]));
				$proxy_groupids = 0;
				break;

			case ZBX_MONITORED_BY_PROXY_GROUP:
				$proxyids = 0;
				$proxy_groupids = $filter['proxy_groupids'] ?: array_keys(API::ProxyGroup()->get([
					'output' => [],
					'preservekeys' => true
				]));
				break;
		}

		// Select hosts.
		$limit = (int) CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$hosts = API::Host()->get([
			'output' => ['hostid', $sort_field],
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'] ?: null,
			'inheritedTags' => true,
			'groupids' => $filter_groupids,
			'templateids' => $filter['templates'] ? array_keys($filter['templates']) : null,
			'proxyids' => $proxyids,
			'proxy_groupids' => $proxy_groupids,
			'editable' => true,
			'sortfield' => $sort_field,
			'limit' => $limit,
			'search' => [
				'name' => $filter['host'] === '' ? null : $filter['host'],
				'ip' => $filter['ip'] === '' ? null : $filter['ip'],
				'dns' => $filter['dns'] === '' ? null : $filter['dns']
			],
			'filter' => [
				'port' => $filter['port'] === '' ? null : $filter['port'],
				'status' => $filter['status'] == -1 ? null : $filter['status']
			]
		]);

		$this->paging = $this->paginate($hosts, $page, $sort_order);

		$fields = $this->extractFields($columns);

		$hostids = array_column($hosts, 'hostid');
		$active_item_count_by_hostid = getEnabledItemTypeCountByHostId(ITEM_TYPE_ZABBIX_ACTIVE, $hostids);

		$hosts = API::Host()->get([
			'output' => $fields,
			'selectParentTemplates' => ['templateid', 'name'],
			'selectInterfaces' => ['interfaceid', 'main', 'type', 'useip',  'ip', 'dns', 'port', 'available', 'error',
				'details'
			],
			'selectItems' => API_OUTPUT_COUNT,
			'selectDiscoveryRules' => API_OUTPUT_COUNT,
			'selectTriggers' => API_OUTPUT_COUNT,
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectHttpTests' => API_OUTPUT_COUNT,
			'selectDiscoveryRule' => ['itemid', 'name', 'lifetime_type', 'enabled_lifetime_type'],
			'selectDiscoveryData' => ['parent_hostid', 'status', 'ts_delete', 'ts_disable', 'disable_source'],
			'selectTags' => ['tag', 'value'],
			'selectInheritedTags' => ['tag', 'value'],
			'hostids' => $hostids,
			'preservekeys' => true
		]);

		// Selecting linked templates to templates linked to hosts.
		$templateids = [];
		$interfaceids = [];
		$proxyids = [];
		$proxy_groupids = [];
		$maintenanceids = [];

		foreach ($hosts as $host) {
			$templateids = array_merge($templateids, array_column($host['parentTemplates'], 'templateid'));
			$interfaceids = array_merge($interfaceids, array_column($host['interfaces'], 'interfaceid'));

			if ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY) {
				$proxyids[$host['proxyid']] = true;
			}
			elseif ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
				$proxy_groupids[$host['proxy_groupid']] = true;

				if ($host['assigned_proxyid'] != 0) {
					$proxyids[$host['assigned_proxyid']] = true;
				}
			}

			if (isset($host['maintenanceid']) && $host['status'] == HOST_STATUS_MONITORED
					&& $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$maintenanceids[$host['maintenanceid']] = true;
			}
		}

		$templates = API::Template()->get([
			'output' => ['templateid', 'name'],
			'selectParentTemplates' => ['templateid', 'name'],
			'templateids' => $templateids,
			'preservekeys' => true
		]);

		$templateids = array_keys(array_flip($templateids));
		if ($templateids) {
			foreach ($templates as $template) {
				$templateids = array_merge($templateids, array_column($template['parentTemplates'], 'templateid'));
			}

			$writable_templateids = array_column(API::Template()->get([
				'output' => ['templateid'],
				'templateids' => $templateids,
				'editable' => true,
				'preservekeys' => true
			]), 'templateid');

			foreach ($templates as &$template) {
				$template['editable'] = in_array($template['templateid'], $writable_templateids);

				foreach ($template['parentTemplates'] as &$parent_template) {
					$parent_template['editable'] = in_array($parent_template['templateid'], $writable_templateids);
				}
				unset($parent_template);
			}
			unset($template);
		}

		$proxies = $proxyids
			? API::Proxy()->get([
				'output' => ['name'],
				'proxyids' => array_keys($proxyids),
				'preservekeys' => true
			])
			: [];
		$proxy_groups = $proxy_groupids
			? API::ProxyGroup()->get([
				'output' => ['name'],
				'proxy_groupids' => array_keys($proxy_groupids),
				'preservekeys' => true
			])
			: [];

		$maintenances = $maintenanceids
			? API::Maintenance()->get([
				'output' => ['name', 'description'],
				'maintenanceids' => array_keys($maintenanceids),
				'preservekeys' => true
			])
			: [];

		$interface_enabled_items_count = getEnabledItemsCountByInterfaceIds($interfaceids);

		order_result($hosts, $sort_field, $sort_order);

		foreach ($hosts as &$host) {
			$host['discovery'] = [
				'data' => $host['discoveryData'],
				'rule' => $host['discoveryRule'],
				'editable' => $host['is_discovery_rule_editable'] ?? false
			];

			$host['maintenance'] = null;
			if ($host['status'] == HOST_STATUS_MONITORED && $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON
					&& isset($host['maintenanceid']) && array_key_exists($host['maintenanceid'], $maintenances)) {
				$host['maintenance'] = $maintenances[$host['maintenanceid']] + [
					'type' => $host['maintenance_type'],
					'status' => $host['maintenance_status']
				];
			}

			CArrayHelper::sort($host['parentTemplates'], [['field' => 'name', 'order' => ZBX_SORT_UP]]);
			$host['templates'] = array_values(array_map(
				static fn (array $template) => $templates[$template['templateid']], $host['parentTemplates']));

			// Sort interfaces to be listed starting with one selected as 'main'.
			CArrayHelper::sort($host['interfaces'], [['field' => 'main', 'order' => ZBX_SORT_DOWN]]);

			foreach ($host['interfaces'] as &$interface) {
				$interfaceid = $interface['interfaceid'];
				$interface['has_enabled_items'] = array_key_exists($interfaceid, $interface_enabled_items_count)
					&& $interface_enabled_items_count[$interfaceid] > 0;
			}
			unset($interface);

			$host['interface'] = null;

			if ($host['interfaces']) {
				foreach (CItemGeneral::INTERFACE_TYPES_BY_PRIORITY as $interface_type) {
					$host_interfaces = array_filter($host['interfaces'], function(array $host_interface) use ($interface_type) {
						return ($host_interface['type'] == $interface_type);
					});

					if ($host_interfaces) {
						$host['interface'] = getHostInterface(reset($host_interfaces));
						break;
					}
				}
			}

			// Add active checks interface if host have items with type ITEM_TYPE_ZABBIX_ACTIVE (7).
			if (array_key_exists($host['hostid'], $active_item_count_by_hostid)
					&& $active_item_count_by_hostid[$host['hostid']] > 0) {
				$host['interfaces'][] = [
					'type' => INTERFACE_TYPE_AGENT_ACTIVE,
					'available' => $host['active_available'],
					'has_enabled_items' => true,
					'error' => ''
				];
			}
			unset($host['active_available']);

			$disable_source = $host['status'] == HOST_STATUS_NOT_MONITORED && $host['discoveryData']
				? $host['discoveryData']['disable_source']
				: '';
			$host['disabled_by_lld'] = $disable_source == ZBX_DISABLE_SOURCE_LLD;

			$host['availability'] = getHostAvailabilityTable($host['interfaces'])->toString();

			$host['info_icons'] = [];
			if ($host['flags'] == ZBX_FLAG_DISCOVERY_CREATED
					&& $host['discoveryData']['status'] == ZBX_LLD_STATUS_LOST) {
				$current_time = time();

				$indicator = getLldLostEntityIndicator($current_time, $host['discoveryData']['ts_delete'],
					$host['discoveryData']['ts_disable'], $disable_source, $host['status'] == HOST_STATUS_NOT_MONITORED,
					_('host')
				);
				if ($indicator) {
					$host['info_icons'][] = $indicator->toString();
				}
			}

			$host['proxy'] = $host['proxyid']
				? array_merge($proxies[$host['proxyid']], ['proxyid' => $host['proxyid']])
				: null;
			$host['proxy_group'] = $host['proxy_groupid']
				? array_merge($proxy_groups[$host['proxy_groupid']], ['proxy_groupid' => $host['proxy_groupid']])
				: null;
			$host['assigned_proxy'] = $host['assigned_proxyid']
				? array_merge($proxies[$host['assigned_proxyid']], ['proxyid' => $host['assigned_proxyid']])
				: null;
		}
		unset($host);

		CTagHelper::mergeOwnAndInheritedTags($hosts, true);

		return [
			'fields' => $fields,
			'columns' => $columns,
			'rows' => array_values(array_map(static fn (array $host) => [[], $host], $hosts)),
			'max_in_table' => (int) CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE),
			'can_edit_proxies' => CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES),
			'can_edit_proxy_groups' => CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXY_GROUPS)
		];
	}
}
