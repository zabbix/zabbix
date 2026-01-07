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


class CControllerHostViewData extends CControllerDataTable {

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS);
	}

	/**
	 * @throws APIException
	 * @throws Exception
	 */
	protected function getData(): array {
		$columns = $this->getInput('columns');
		$fields = $this->extractFields($columns);
		$context_popup_data = array_merge(...array_column($columns, 'context_popup_data'));

		$filter = $this->getInput('filter', []);
		$page = $this->getInput('page', 1);

		$sort_field = $this->getInput('sort_field', $filter['sort'] ?? 'name');
		$sort_order = $this->getInput('sort_order', $filter['sortorder'] ?? ZBX_SORT_UP);

		$limit = (int) CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		if ($filter['tags']) {
			$filter['tags'] = array_filter($filter['tags'], function (array $tag) {
				return !($tag['tag'] === '' && $tag['value'] === '');
			});
		}

		$groupids = $filter['groupids'] ? getSubGroups($filter['groupids']) : null;

		$hosts = API::Host()->get([
			'output' => ['hostid', 'name', 'status'],
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'] ?: null,
			'inheritedTags' => true,
			'groupids' => $groupids,
			'severities' => $filter['severities'] ?: null,
			'withProblemsSuppressed' => null,
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name'],
				'ip' => ($filter['ip'] === '') ? null : $filter['ip'],
				'dns' => ($filter['dns'] === '') ? null : $filter['dns']
			],
			'filter' => [
				'status' => ($filter['status'] == -1) ? null : $filter['status'],
				'port' => ($filter['port'] === '') ? null : $filter['port'],
				'maintenance_status' => ($filter['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON)
					? null
					: HOST_MAINTENANCE_STATUS_OFF
			],
			'sortfield' => 'name',
			'limit' => $limit,
			'preservekeys' => true
		]);

		// Sort for paging so we know which IDs go to which page.
		CArrayHelper::sort($hosts, [['field' => $sort_field, 'order' => $sort_order]]);

		$view_curl = (new CUrl())->setArgument('action', 'host.view');
		$paging_arguments = array_filter(array_intersect_key($filter, CControllerHost::FILTER_FIELDS_DEFAULT));
		array_map([$view_curl, 'setArgument'], array_keys($paging_arguments), $paging_arguments);

		// Split the result array and create paging.
		$this->paging = $this->paginate($hosts, $page, $sort_order);

		// Get additional data to limited host amount.
		$hosts = API::Host()->get([
			'output' => $fields,
			'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'port', 'main', 'type', 'useip', 'available', 'error',
				'details'
			],
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectHttpTests' => API_OUTPUT_COUNT,
			'selectTags' => ['tag', 'value'],
			'selectInheritedTags' => ['tag', 'value'],
			'hostids' => array_keys($hosts),
			'preservekeys' => true
		]);
		// Re-sort the results again.
		CArrayHelper::sort($hosts, [['field' => $sort_field, 'order' => $sort_order]]);

		CTagHelper::mergeOwnAndInheritedTags($hosts);

		$interfaceids = [];
		$hostids = [];

		foreach ($hosts as $host) {
			$hostids[] = $host['hostid'];
			$interfaceids = array_merge($interfaceids, array_column($host['interfaces'], 'interfaceid'));
		}

		$interface_enabled_items_count = getEnabledItemsCountByInterfaceIds($interfaceids);
		$active_item_count_by_hostid = getEnabledItemTypeCountByHostId(ITEM_TYPE_ZABBIX_ACTIVE, $hostids);

		$maintenanceids = [];

		// Select triggers and problems to calculate the number of problems for each host.
		$triggers = API::Trigger()->get([
			'output' => [],
			'selectHosts' => ['hostid'],
			'hostids' => array_keys($hosts),
			'skipDependent' => true,
			'monitored' => true,
			'preservekeys' => true
		]);

		$problems = API::Problem()->get([
			'output' => ['eventid', 'objectid', 'severity'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'objectids' => array_keys($triggers),
			'suppressed' => ($context_popup_data['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE) ? null : false,
			'symptom' => false
		]);

		$items_count = API::Item()->get([
			'countOutput' => true,
			'groupCount' => true,
			'hostids' => array_keys($hosts),
			'webitems' =>true,
			'monitored' => true
		]);
		$items_count = $items_count ? array_column($items_count, 'rowscount', 'hostid') : [];

		// Group all problems per host per severity.
		$host_problems = [];
		foreach ($problems as $problem) {
			foreach ($triggers[$problem['objectid']]['hosts'] as $trigger_host) {
				$host_problems[$trigger_host['hostid']][$problem['severity']][$problem['eventid']] = true;
			}
		}

		if ($hosts) {
			$dashboard_count = API::HostDashboard()->get([
				'countOutput' => true,
				'groupCount' => true,
				'hostids' => array_keys($hosts)
			]);

			$dashboard_count = array_column($dashboard_count, 'rowscount', 'hostid');
		}

		foreach ($hosts as &$host) {
			foreach ($host['interfaces'] as &$interface) {
				$interfaceid = $interface['interfaceid'];
				$interface['has_enabled_items'] = array_key_exists($interfaceid, $interface_enabled_items_count)
					&& $interface_enabled_items_count[$interfaceid] > 0;
			}
			unset($interface);

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

			$host['items_count'] = array_key_exists($host['hostid'], $items_count) ? $items_count[$host['hostid']] : 0;
			$host['dashboards'] = $dashboard_count[$host['hostid']];

			CArrayHelper::sort($host['interfaces'], [['field' => 'main', 'order' => ZBX_SORT_DOWN]]);

			if ($host['status'] == HOST_STATUS_MONITORED && $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$maintenanceids[$host['maintenanceid']] = true;
			}

			// Fill empty arrays for hosts without problems.
			if (!array_key_exists($host['hostid'], $host_problems)) {
				$host_problems[$host['hostid']] = [];
			}

			// Count the number of problems (as value) per severity (as key).
			for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
				$host['problem_count'][$severity] = array_key_exists($severity, $host_problems[$host['hostid']])
					? count($host_problems[$host['hostid']][$severity])
					: 0;
			}
		}
		unset($host);

		$maintenances = [];

		if ($maintenanceids) {
			$maintenances = API::Maintenance()->get([
				'output' => ['name', 'description'],
				'maintenanceids' => array_keys($maintenanceids),
				'preservekeys' => true
			]);
		}

		order_result($hosts, $sort_field, $sort_order);

		foreach ($hosts as &$host) {
			$host['interface'] = null;
			if ($host['interfaces']) {
				foreach (CItemGeneral::INTERFACE_TYPES_BY_PRIORITY as $interface_type) {
					$host_interfaces = array_filter($host['interfaces'],
						static fn (array $host_interface) => $host_interface['type'] == $interface_type);
					if ($host_interfaces) {
						$host['interface'] = getHostInterface(reset($host_interfaces));
						break;
					}
				}
			}

			$host['maintenance'] = null;
			if ($host['status'] == HOST_STATUS_MONITORED && $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON
					&& isset($host['maintenanceid']) && array_key_exists($host['maintenanceid'], $maintenances)) {
				$host['maintenance'] = $maintenances[$host['maintenanceid']] + [
					'type' => $host['maintenance_type'],
					'status' => $host['maintenance_status'],
				];
			}

			$host['availability'] = getHostAvailabilityTable($host['interfaces'])->toString();

			CArrayHelper::sort($host['tags'], ['tag', 'value']);
			$host['tags'] = array_values($host['tags']);

			$total_problem_count = 0;

			$problems = $this->checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS)
				? new CLink('',
					(new CUrl('zabbix.php'))
						->setArgument('action', 'problem.view')
						->setArgument('severities', $filter['severities'])
						->setArgument('hostids', [$host['hostid']])
						->setArgument('filter_set', '1')
				)
				: (new CDiv())->addClass(ZBX_STYLE_DISABLED);

			// Fill the severity icons by problem count and style and calculate the total number of problems.
			foreach ($host['problem_count'] as $severity => $count) {
				if (($count > 0 && $filter['severities'] && in_array($severity, $filter['severities']))
					|| (!$filter['severities'] && $count > 0)) {
					$total_problem_count += $count;

					$problems->addItem((new CSpan($count))
						->addClass(ZBX_STYLE_PROBLEM_ICON_LIST_ITEM)
						->addClass(CSeverityHelper::getStatusStyle($severity))
						->setAttribute('title', CSeverityHelper::getName($severity))
					);
				}
			}

			if ($total_problem_count == 0) {
				$problems->addItem(_('Problems'));
			}
			else {
				$problems->addClass(ZBX_STYLE_PROBLEM_ICON_LINK);
			}

			$host['problems'] = $problems->toString();
		}
		unset($host);

		return [
			'fields' => $fields,
			'columns' => $columns,
			'rows' => array_values(array_map(static fn (array $host) => [[], $host], $hosts)),
			'allowed_ui_latest_data' => $this->checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA)
		];
	}
}
