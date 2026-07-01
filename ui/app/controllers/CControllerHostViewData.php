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

	protected array $allowed_data_fields = ['hostid', 'data_actions', 'name', 'status', 'maintenance', 'maintenanceid',
		'maintenance_type', 'maintenance_status', 'interface', 'availability', 'active_available', 'status',
		'items_count', 'problems', 'graphs', 'dashboards', 'httpTests', 'tags', 'custom_text'];

	protected function init(): void {
		parent::init();

		$this->addValidationRules(['sort_field' => 'string|in name,status']);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS);
	}

	protected function getData(): array {
		$data_fields = $this->getDataFields();
		$options = $this->getInput('options');
		$filter = $this->getInput('filter', []);
		$page = $this->getInput('page', 1);

		$sort_field = $this->getInput('sort_field', CControllerHost::DEFAULT_SORT);
		$sort_order = $this->getInput('sort_order', CControllerHost::DEFAULT_SORTORDER);

		$limit = (int) CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		if ($filter['tags']) {
			$filter['tags'] = array_filter($filter['tags'], static fn(array $tag) => $tag && $tag['tag'] != '');
		}

		$groupids = $filter['groupids'] ? getSubGroups($filter['groupids']) : null;

		$hosts = API::Host()->get([
			'output' => ['hostid', $sort_field],
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'] ?: null,
			'inheritedTags' => true,
			'groupids' => $groupids,
			'severities' => $filter['severities'] ?: null,
			'withProblemsSuppressed' => $filter['severities']
				? ($options['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE ? null : false)
				: null,
			'search' => [
				'name' => $filter['name'] === '' ? null : $filter['name'],
				'ip' => $filter['ip'] === '' ? null : $filter['ip'],
				'dns' => $filter['dns'] === '' ? null : $filter['dns']
			],
			'filter' => [
				'status' => $filter['status'] == -1 ? null : $filter['status'],
				'port' => $filter['port'] === '' ? null : $filter['port'],
				'maintenance_status' => $filter['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON
					? null
					: HOST_MAINTENANCE_STATUS_OFF
			],
			'sortfield' => $sort_field,
			'limit' => $limit,
			'preservekeys' => true
		]);

		order_result($hosts, $sort_field, $sort_order);

		$view_curl = (new CUrl())->setArgument('action', 'host.view');
		$paging_arguments = array_filter(array_intersect_key($filter, CControllerHost::FILTER_FIELDS_DEFAULT));
		array_map([$view_curl, 'setArgument'], array_keys($paging_arguments), $paging_arguments);

		// Split the result array and create paging.
		$this->paging = $this->paginate($hosts, $page, $sort_order);

		// Get additional data to limited host amount.
		$hosts = API::Host()->get([
			'output' => $data_fields,
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

		order_result($hosts, $sort_field, $sort_order);

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
			'suppressed' => array_key_exists('show_suppressed', $options)
				&& $options['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE ? null : false,
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
						static fn (array $host_interface) => $host_interface['type'] == $interface_type
					);

					if ($host_interfaces) {
						$host['interface'] = getHostInterface(reset($host_interfaces));
						break;
					}
				}
			}

			$host['maintenance'] = null;
			if ($host['status'] == HOST_STATUS_MONITORED && $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON
					&& array_key_exists('maintenanceid', $host) && $host['maintenanceid'] != 0
					&& array_key_exists($host['maintenanceid'], $maintenances)) {
				$host['maintenance'] = $maintenances[$host['maintenanceid']] + [
					'type' => $host['maintenance_type'],
					'status' => $host['maintenance_status']
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

			CArrayHelper::sort($host['tags'], ['tag', 'value']);
			$host['tags'] = CTagHelper::getTagsList($host);
		}
		unset($host);

		$custom_text = $this->extractCustomText($options);
		$this->flattenColumnOptions($options);

		if ($custom_text) {
			$this->resolveCustomText($hosts, $custom_text);
		}

		$output = [
			'filter_counters' => $this->getFilterCounters(),
			'data_fields' => $data_fields,
			'rows' => array_values(array_map(static fn (array $host) => [[], $host], $hosts)),
			'allowed_ui_latest_data' => $this->checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA)
		];

		$debug_mode = CWebUser::$data['debug_mode'] ?? GROUP_DEBUG_MODE_DISABLED;

		if ($debug_mode == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$output['debug'] = CProfiler::getInstance()->make()->toString();
		}

		return $output;
	}

	private function getFilterCounters(): array {
		$filter_counters = [];

		if (CViewHelper::loadLayoutMode() == ZBX_LAYOUT_KIOSKMODE) {
			return $filter_counters;
		}

		$profile = (new CTabFilterProfile('web.monitoring.hosts', CControllerHost::FILTER_FIELDS_DEFAULT))->read();

		$filters = $profile->getTabsWithDefaults();

		$user_configs = $this->getUserConfigs('web.monitoring.hosts.datatable');

		foreach ($filters as $index => $tabfilter) {
			$tabfilter = CControllerHost::sanitizeFilter($tabfilter);

			$column_options = $this->getColumnOptions($user_configs, $index);

			$filter_counters[$index] = $tabfilter['filter_show_counter']
				? $this->getCount($tabfilter, $column_options)
				: 0;
		}

		return $filter_counters;
	}

	/**
	 * Get host list results count for passed filter.
	 *
	 * @param array  $filter                        Filter options.
	 *        string $filter['name']                Filter hosts by name.
	 *        array  $filter['groupids']            Filter hosts by host groups.
	 *        string $filter['ip']                  Filter hosts by IP.
	 *        string $filter['dns']	                Filter hosts by DNS.
	 *        string $filter['port']                Filter hosts by port.
	 *        string $filter['status']              Filter hosts by status.
	 *        string $filter['evaltype']            Filter hosts by tags.
	 *        string $filter['tags']                Filter hosts by tag names and values.
	 *        string $filter['severities']          Filter problems on hosts by severities.
	 *        string $filter['show_suppressed']     Filter suppressed problems.
	 *        int    $filter['maintenance_status']  Filter hosts by maintenance.
	 * @param array  $column_options                Column options.
	 *
	 * @return int
	 */
	private function getCount(array $filter, array $column_options): int {
		$groupids = $filter['groupids'] ? getSubGroups($filter['groupids']) : null;

		$show_suppressed = array_key_exists('show_suppressed', $column_options)
			&& $column_options['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE;

		return (int) API::Host()->get([
			'countOutput' => true,
			'groupids' => $groupids,
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'] ?: null,
			'inheritedTags' => true,
			'severities' => $filter['severities'] ?: null,
			'withProblemsSuppressed' => $filter['severities']
				? ($show_suppressed ? null : false)
				: null,
			'search' => [
				'name' => $filter['name'] === '' ? null : $filter['name'],
				'ip' => $filter['ip'] === '' ? null : $filter['ip'],
				'dns' => $filter['dns'] === '' ? null : $filter['dns']
			],
			'filter' => [
				'status' => $filter['status'] == -1 ? null : $filter['status'],
				'port' => $filter['port'] === '' ? null : $filter['port'],
				'maintenance_status' => $filter['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON
					? null
					: HOST_MAINTENANCE_STATUS_OFF
			],
			'limit' => (int) CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
		]);
	}

	protected function resolveCustomText(array &$hosts, array $custom_text): void {
		$custom_text = CMacrosResolverHelper::resolveHostMacros($custom_text, array_keys($hosts));

		foreach ($custom_text as $key => $values) {
			foreach ($values as $hostid => $value) {
				$hosts[$hostid]['custom_text'][$key] = $value;
			}
		}
	}
}
