<?php
declare(strict_types=1);

/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	 * @param string $filter['show_suppressed']     Filter supressed problems.
	 * @param int    $filter['maintenance_status']  Filter hosts by maintenance.
	 * @param string $sort						    Sorting field.
	 * @param string $sortorder                     Sorting order.
	 */
	protected function prepareData(array $filter, $sort, $sortorder) {
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
			$groups = $filter_groups;

			foreach ($child_groups as $child_group) {
				$child_groups = API::HostGroup()->get([
					'output' => ['groupid'],
					'search' => ['name' => $child_group],
					'startSearch' => true,
					'preservekeys' => true
				]);

				$groups = array_replace($groups, $child_groups);
			}

			$groupids = array_keys($groups);
		}

		$config = select_config();

		$hosts = API::Host()->get([
			'output' => [$sort],
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'],
			'groupids' => $groupids,
			'severities' => $filter['severities'] ? $filter['severities'] : null,
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
					: HOST_MAINTENANCE_STATUS_OFF,
			],
			'sortfield' => $sort,
			'limit' => $config['search_limit'] + 1,
			'preservekeys' => true
		]);

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
			'hostids' => array_keys($hosts),
			'preservekeys' => true
		]);
		CArrayHelper::sort($hosts, [['field' => $sort, 'order' => $sortorder]]);

		$maintenanceids = [];

		foreach ($hosts as $key => &$host) {
			CArrayHelper::sort($host['interfaces'], [
				['field' => 'main', 'order' => ZBX_SORT_DOWN]
			]);

			if ($host['status'] == HOST_STATUS_MONITORED && $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$maintenanceids[$host['maintenanceid']] = true;
			}

			$host['problems'] = API::Problem()->get([
				'output' => ['severity'],
				'hostids' => $host['hostid'],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'suppressed' => ($filter['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_FALSE) ? false : null
			]);

			$host['problem_total_count'] = count($host['problems']);

			if ($host['problems'] && $filter['severities']) {
				foreach ($host['problems'] as $i => $problem) {
					if (!in_array($problem['severity'], $filter['severities'])) {
						unset($host['problems'][$i]);
					}
				}
			}
		}
		unset($host);

		$tags = makeTags($hosts, true, 'hostid', ZBX_TAG_COUNT_DEFAULT, $filter['tags']);

		$maintenances = [];

		if ($maintenanceids) {
			$maintenances = API::Maintenance()->get([
				'output' => ['name', 'description'],
				'maintenanceids' => array_keys($maintenanceids),
				'preservekeys' => true
			]);
		}

		foreach ($hosts as &$host) {
			$host['tags'] = $tags[$host['hostid']];
		}
		unset($host);

		return [
			'hosts' => $hosts,
			'maintenances' => $maintenances,
			'multiselect_hostgroup_data' => $multiselect_hostgroup_data,
			'filter' => $filter,
			'config' => $config
		];
	}
}
