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

	protected function prepareData($filter, $sortField, $sortOrder) {
		$applications = [];
		$items = [];
		$child_groups = [];

		// multiselect host groups
		$multiselect_hostgroup_data = [];
		if ($filter['groupids']) {
			$filterGroups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['groupids'],
				'preservekeys' => true
			]);

			if ($filterGroups) {
				foreach ($filterGroups as $group) {
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
		$filterSet = ($filter['select'] !== '' || $filter['application'] !== '' || $filter['groupids'] || $filter['hostids']);
		if ($filterSet) {
			$groupids = null;
			if ($child_groups) {
				$groups = $filterGroups;
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

			$sortFields = ($sortField === 'host') ? [['field' => 'name', 'order' => $sortOrder]] : ['name'];
			CArrayHelper::sort($hosts, $sortFields);

			$hostIds = array_keys($hosts);

			$applications = null;

			// if an application filter is set, fetch the applications and then use them to filter items
			if ($filter['application'] !== '') {
				$applications = API::Application()->get([
					'output' => API_OUTPUT_EXTEND,
					'hostids' => $hostIds,
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
				if (!$filter['showWithoutData']) {
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
				if ($sortField === 'name') {
					$sortFields = [['field' => 'name_expanded', 'order' => $sortOrder], 'itemid'];
				}
				elseif ($sortField === 'lastclock') {
					$sortFields = [['field' => 'lastclock', 'order' => $sortOrder], 'name_expanded', 'itemid'];
				}
				else {
					$sortFields = ['name_expanded', 'itemid'];
				}
				CArrayHelper::sort($items, $sortFields);

				if ($applications) {
					foreach ($applications as &$application) {
						$application['hostname'] = $hosts[$application['hostid']]['name'];
						$application['item_cnt'] = 0;
					}
					unset($application);

					// by default order by application name and application id
					$sortFields = ($sortField === 'host') ? [['field' => 'hostname', 'order' => $sortOrder]] : [];
					array_push($sortFields, 'name', 'applicationid');
					CArrayHelper::sort($applications, $sortFields);
				}
			}
		}

		// multiselect hosts
		$multiselect_host_data = [];
		if ($filter['hostids']) {
			$filterHosts = API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $filter['hostids']
			]);

			foreach ($filterHosts as $host) {
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
			'filterSet' => $filterSet,
		];
	}
}
