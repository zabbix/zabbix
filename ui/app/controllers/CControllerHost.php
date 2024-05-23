<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


/**
 * Base controller for the "Monitoring->Hosts" page and the "Monitoring->Hosts" asynchronous refresh page.
 */
abstract class CControllerHost extends CController {

	/**
	 * Prepares the host list based on the given filter and sorting options.
	 *
	 * @param array  $filter                        Filter options.
	 * @param string $filter['name']                Filter hosts by name.
	 * @param array  $filter['groupids']            Filter hosts by host groups.
	 * @param string $filter['ip']                  Filter hosts by IP.
	 * @param string $filter['dns']	                Filter hosts by DNS.
	 * @param string $filter['port']                Filter hosts by port.
	 * @param string $filter['status']              Filter hosts by status.
	 * @param string $filter['evaltype']            Filter hosts by tags.
	 * @param string $filter['tags']                Filter hosts by tag names and values.
	 * @param string $filter['severities']          Filter problems on hosts by severities.
	 * @param string $filter['show_suppressed']     Filter suppressed problems.
	 * @param int    $filter['maintenance_status']  Filter hosts by maintenance.
	 * @param int    $filter['page']                Page number.
	 * @param string $sort						    Sorting field.
	 * @param string $sortorder                     Sorting order.
	 *
	 * @return array
	 */
	protected function prepareData(array $filter, string $sort, string $sortorder): array {
		$child_groups = [];

		// Multiselect host groups.
		$multiselect_hostgroup_data = [];
		if ($filter['groupids']) {
			$filter_groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['groupids'],
				'preservekeys' => true
			]);

			if ($filter_groups) {
				foreach ($filter_groups as $group) {
					$multiselect_hostgroup_data[] = [
						'id' => $group['groupid'],
						'name' => $group['name']
					];

					$child_groups[] = $group['name'].'/';
				}
			}
			else {
				$filter['groupids'] = [];
			}
		}

		$groupids = null;

		if ($child_groups) {
			$filter_groups += API::HostGroup()->get([
				'output' => ['groupid'],
				'search' => ['name' => $child_groups],
				'startSearch' => true,
				'searchByAny' => true,
				'preservekeys' => true
			]);

			$groupids = array_keys($filter_groups);
		}

		$config = select_config();

		$hosts = API::Host()->get([
			'output' => ['hostid', 'name', 'status'],
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'],
			'inheritedTags' => true,
			'groupids' => $groupids,
			'severities' => $filter['severities'] ? $filter['severities'] : null,
			'withProblemsSuppressed' => $filter['severities']
				? (($filter['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE) ? null : false)
				: null,
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
			'limit' => $config['search_limit'] + 1,
			'preservekeys' => true
		]);

		// Sort for paging so we know which IDs go to which page.
		CArrayHelper::sort($hosts, [['field' => $sort, 'order' => $sortorder]]);

		$view_curl = (new CUrl('zabbix.php'))->setArgument('action', 'host.view');

		// Split result array and create paging.
		$paging = CPagerHelper::paginate($filter['page'], $hosts, $sortorder, $view_curl);

		// Get additional data to limited host amount.
		$hosts = API::Host()->get([
			'output' => ['hostid', 'name', 'status', 'maintenance_status', 'maintenanceid', 'maintenance_type',
				'available', 'snmp_available', 'jmx_available', 'ipmi_available', 'error', 'ipmi_error', 'snmp_error',
				'jmx_error'
			],
			'selectInterfaces' => ['ip', 'dns', 'port', 'main', 'type', 'useip'],
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectScreens' => API_OUTPUT_COUNT,
			'selectHttpTests' => API_OUTPUT_COUNT,
			'selectTags' => ['tag', 'value'],
			'selectInheritedTags' => ['tag', 'value'],
			'hostids' => array_keys($hosts),
			'preservekeys' => true
		]);
		// Re-sort the results again.
		CArrayHelper::sort($hosts, [['field' => $sort, 'order' => $sortorder]]);

		$maintenanceids = [];

		// Select triggers and problems to calculate number of problems for each host.
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
			'objectids' => array_keys($triggers),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'suppressed' => ($filter['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE) ? null : false
		]);

		// Group all problems per host per severity.
		$host_problems = [];
		foreach ($problems as $problem) {
			foreach ($triggers[$problem['objectid']]['hosts'] as $trigger_host) {
				$host_problems[$trigger_host['hostid']][$problem['severity']][$problem['eventid']] = true;
			}
		}

		foreach ($hosts as &$host) {
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

			// Merge host tags with template tags, and skip duplicate tags and values.
			if (!$host['inheritedTags']) {
				$tags = $host['tags'];
			}
			elseif (!$host['tags']) {
				$tags = $host['inheritedTags'];
			}
			else {
				$tags = $host['tags'];

				foreach ($host['inheritedTags'] as $template_tag) {
					foreach ($tags as $host_tag) {
						// Skip tags with same name and value.
						if ($host_tag['tag'] === $template_tag['tag']
								&& $host_tag['value'] === $template_tag['value']) {
							continue 2;
						}
					}
					$tags[] = $template_tag;
				}
			}

			$host['tags'] = $tags;
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

		$tags = makeTags($hosts, true, 'hostid', ZBX_TAG_COUNT_DEFAULT, $filter['tags']);

		foreach ($hosts as &$host) {
			$host['tags'] = $tags[$host['hostid']];
		}
		unset($host);

		return [
			'paging' => $paging,
			'view_curl' => $view_curl,
			'hosts' => $hosts,
			'maintenances' => $maintenances,
			'multiselect_hostgroup_data' => $multiselect_hostgroup_data,
			'filter' => $filter,
			'config' => $config
		];
	}
}
