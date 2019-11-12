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


abstract class CControllerLatest extends CController {

	protected function prepareData($filter, $sort_field, $sort_order) {
		$applications = [];
		$items = [];
		$child_groups = [];

		// multiselect host groups
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

		// we'll only display the values if the filter is set
		$filter_set = ($filter['select'] !== '' || $filter['application'] !== '' || $filter['groupids'] || $filter['hostids']);

		if ($filter_set) {
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

			$hosts = API::Host()->get([
				'output' => ['name', 'hostid', 'status'],
				'hostids' => $filter['hostids'],
				'groupids' => $groupids,
				'with_monitored_items' => true,
				'preservekeys' => true
			]);
		}
		else {
			$hosts = [];
		}

		if ($hosts) {
			foreach ($hosts as &$host) {
				$host['item_cnt'] = 0;
			}
			unset($host);

			$sort_fields = ($sort_field === 'host') ? [['field' => 'name', 'order' => $sort_order]] : ['name'];
			CArrayHelper::sort($hosts, $sort_fields);

			$applications = null;

			// if an application filter is set, fetch the applications and then use them to filter items
			if ($filter['application'] !== '') {
				$applications = API::Application()->get([
					'output' => API_OUTPUT_EXTEND,
					'hostids' => array_keys($hosts),
					'search' => ['name' => $filter['application']],
					'preservekeys' => true
				]);
			}

			$items = API::Item()->get([
				'hostids' => array_keys($hosts),
				'output' => ['itemid', 'name', 'type', 'value_type', 'units', 'hostid', 'state', 'valuemapid', 'status',
					'error', 'trends', 'history', 'delay', 'key_', 'flags', 'description'
				],
				'selectApplications' => ['applicationid'],
				'selectItemDiscovery' => ['ts_delete'],
				'applicationids' => ($applications !== null) ? zbx_objectValues($applications, 'applicationid') : null,
				'webitems' => true,
				'filter' => [
					'status' => [ITEM_STATUS_ACTIVE]
				],
				'preservekeys' => true
			]);

			// if the applications haven't been loaded when filtering, load them based on the retrieved items to avoid
			// fetching applications from hosts that may not be displayed
			if ($applications === null) {
				$applications = API::Application()->get([
					'output' => API_OUTPUT_EXTEND,
					'hostids' => array_keys(array_flip(zbx_objectValues($items, 'hostid'))),
					'search' => ['name' => $filter['application']],
					'preservekeys' => true
				]);
			}
		}

		if ($items) {
			// macros
			$items = CMacrosResolverHelper::resolveItemKeys($items);
			$items = CMacrosResolverHelper::resolveItemNames($items);
			$items = CMacrosResolverHelper::resolveTimeUnitMacros($items, ['delay', 'history', 'trends']);

			// filter items by name
			foreach ($items as $key => $item) {
				if (($filter['select'] !== '')) {
					$haystack = mb_strtolower($item['name_expanded']);
					$needle = mb_strtolower($filter['select']);

					if (mb_strpos($haystack, $needle) === false) {
						unset($items[$key]);
					}
				}
			}

			if ($items) {
				// get history
				$history = Manager::History()->getLastValues($items, 2, ZBX_HISTORY_PERIOD);

				// filter items without history
				if (!$filter['show_without_data']) {
					foreach ($items as $key => $item) {
						if (!isset($history[$item['itemid']])) {
							unset($items[$key]);
						}
					}
				}
			}

			if ($items) {
				// add item last update date for sorting
				foreach ($items as &$item) {
					if (isset($history[$item['itemid']])) {
						$item['lastclock'] = $history[$item['itemid']][0]['clock'];
					}
				}
				unset($item);

				// sort
				if ($sort_field === 'name') {
					$sort_fields = [['field' => 'name_expanded', 'order' => $sort_order], 'itemid'];
				}
				elseif ($sort_field === 'lastclock') {
					$sort_fields = [['field' => 'lastclock', 'order' => $sort_order], 'name_expanded', 'itemid'];
				}
				else {
					$sort_fields = ['name_expanded', 'itemid'];
				}
				CArrayHelper::sort($items, $sort_fields);

				if ($applications) {
					foreach ($applications as &$application) {
						$application['hostname'] = $hosts[$application['hostid']]['name'];
						$application['item_cnt'] = 0;
					}
					unset($application);

					// by default order by application name and application id
					$sort_fields = ($sort_field === 'host') ? [['field' => 'hostname', 'order' => $sort_order]] : [];
					array_push($sort_fields, 'name', 'applicationid');
					CArrayHelper::sort($applications, $sort_fields);
				}
			}
		}

		// multiselect hosts
		$multiselect_host_data = [];
		if ($filter['hostids']) {
			$filter_hosts = API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $filter['hostids']
			]);

			foreach ($filter_hosts as $host) {
				$multiselect_host_data[] = [
					'id' => $host['hostid'],
					'name' => $host['name']
				];
			}
		}

		return [
			'hosts' => $hosts,
			'items' => $items,
			'applications' => $applications,
			'history' => isset($history) ? $history : null,
			'multiselect_hostgroup_data' => $multiselect_hostgroup_data,
			'multiselect_host_data' => $multiselect_host_data,
			'filter_set' => $filter_set,
		];
	}
}
