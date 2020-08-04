<?php
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
 * Base controller for the "Latest data" page and the "Latest data" asynchronous refresh page.
 */
abstract class CControllerLatest extends CController {

	/**
	 * Prepares the latest data based on the given filter and sorting options.
	 *
	 * @param array  $filter                      Item filter options.
	 * @param array  $filter['groupids']          Filter items by host groups.
	 * @param array  $filter['hostids']           Filter items by hosts.
	 * @param string $filter['application']       Filter items by application.
	 * @param string $filter['select']            Filter items by name.
	 * @param int    $filter['show_without_data'] Include items with empty history.
	 * @param string $sort_field                  Sorting field.
	 * @param string $sort_order                  Sorting order.
	 */
	protected function prepareData(array $filter, $sort_field, $sort_order) {
		$config = select_config();

		$multiselect_hostgroup_data = [];
		$multiselect_host_data = [];

		// Sorting options for hosts, applications and items.

		$host_sort_options = [
			'field' => 'name',
			'order' => ($sort_field === 'host') ? $sort_order : 'ASC'
		];
		$application_sort_options = [
			'field' => 'name',
			'order' => ($sort_field === 'name') ? $sort_order : 'ASC'
		];
		$item_sort_options = [
			'field' => 'name',
			'order' => ($sort_field === 'name') ? $sort_order : 'ASC'
		];

		// Select groups for subsequent selection of hosts, applications and items.

		if ($filter['groupids']) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['groupids'],
				'preservekeys' => true
			]);

			if ($groups) {
				$subgroup_names = [];

				foreach ($groups as $group) {
					$subgroup_names[] = $group['name'].'/';

					$multiselect_hostgroup_data[] = [
						'id' => $group['groupid'],
						'name' => $group['name']
					];
				}

				$groups += API::HostGroup()->get([
					'output' => ['groupid'],
					'search' => ['name' => $subgroup_names],
					'startSearch' => true,
					'searchByAny' => true,
					'preservekeys' => true
				]);
			}

			$groupids = array_keys($groups);
		}
		else {
			$groupids = null;
		}

		// Select hosts for subsequent selection of applications and items.

		if ($filter['hostids']) {
			$hosts = API::Host()->get([
				'output' => ['hostid', 'name', 'status'],
				'groupids' => $groupids,
				'hostids' => $filter['hostids'],
				'with_monitored_items' => true,
				'preservekeys' => true
			]);

			$hostids = array_keys($hosts);
		}
		else {
			$hosts = null;
			$hostids = null;
		}

		// Select applications for subsequent selection of items.

		if ($filter['application'] !== '') {
			$applications = API::Application()->get([
				'output' => ['applicationid', 'name'],
				'groupids' => $groupids,
				'hostids' => $hostids,
				'templated' => false,
				'search' => ['name' => $filter['application']],
				'preservekeys' => true
			]);

			$applicationids = array_keys($applications);
		}
		else {
			$applications = null;
			$applicationids = null;
		}

		// Select unlimited items based on filter, requesting minimum data.
		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'value_type'],
			'groupids' => $groupids,
			'hostids' => $hostids,
			'applicationids' => $applicationids,
			'webitems' => true,
			'templated' => false,
			'filter' => [
				'status' => [ITEM_STATUS_ACTIVE]
			],
			'search' => ($filter['select'] === '') ? null : [
				'name' => $filter['select']
			],
			'preservekeys' => true
		]);

		if ($items) {
			if ($hosts === null) {
				$hosts = API::Host()->get([
					'output' => ['hostid', 'name', 'status'],
					'groupids' => $groupids,
					'hostids' => array_keys(array_flip(array_column($items, 'hostid'))),
					'with_monitored_items' => true,
					'preservekeys' => true
				]);
			}

			CArrayHelper::sort($hosts, [$host_sort_options]);
			$hostids = array_keys($hosts);

			$items_of_hosts = [];

			foreach ($items as $itemid => $item) {
				$items_of_hosts[$item['hostid']][$itemid] = $item;
			}

			uksort($items_of_hosts, function($hostid_1, $hostid_2) use ($hostids) {
				return (array_search($hostid_1, $hostids, true) <=> array_search($hostid_2, $hostids, true));
			});

			$select_items = [];

			foreach ($items_of_hosts as $host_items) {
				$select_items += $filter['show_without_data']
					? $host_items
					: Manager::History()->getItemsHavingValues($host_items, ZBX_HISTORY_PERIOD);

				if (count($select_items) > $config['search_limit']) {
					break;
				}
			}

			// Select limited set of items, requesting extended data.
			$items = API::Item()->get([
				'output' => ['itemid', 'type', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
					'value_type', 'units', 'valuemapid', 'description', 'state', 'error'
				],
				'selectApplications' => ['applicationid'],
				'groupids' => $groupids,
				'hostids' => $hostids,
				'applicationids' => $applicationids,
				'itemids' => array_keys($select_items),
				'webitems' => true,
				'preservekeys' => true
			]);

			if ($applications === null) {
				$applications = API::Application()->get([
					'output' => ['applicationid', 'name'],
					'groupids' => $groupids,
					'hostids' => $hostids,
					'itemids' => array_keys($items),
					'templated' => false,
					'preservekeys' => true
				]);
			}

			CArrayHelper::sort($applications, [$application_sort_options]);
			$applicationids = array_keys($applications);

			$applications_size = [];
			$items_grouped = [];

			foreach ($items as $itemid => $item) {
				if (!array_key_exists($item['hostid'], $applications_size)) {
					$applications_size[$item['hostid']] = [];
				}

				$item_applicationids = $item['applications']
					? array_column($item['applications'], 'applicationid')
					: [0];

				foreach ($item_applicationids as $applicationid) {
					if ($applicationid != 0 && !array_key_exists($applicationid, $applications)) {
						continue;
					}

					$items_grouped[$item['hostid']][$applicationid][$itemid] = $item;

					if (array_key_exists($applicationid, $applications_size[$item['hostid']])) {
						$applications_size[$item['hostid']][$applicationid]++;
					}
					else {
						$applications_size[$item['hostid']][$applicationid] = 1;
					}
				}
			}

			$items = [];
			$rows = [];

			uksort($items_grouped, function($hostid_1, $hostid_2) use ($hostids) {
				return (array_search($hostid_1, $hostids, true) <=> array_search($hostid_2, $hostids, true));
			});

			foreach ($items_grouped as $host_items_grouped) {
				uksort($host_items_grouped, function($id_1, $id_2) use ($applicationids, $application_sort_options) {
					if ($id_1 == 0 || $id_2 == 0) {
						return bccomp($id_1, $id_2) * (($application_sort_options['order'] === 'ASC') ? -1 : 1);
					}

					return (array_search($id_1, $applicationids, true) <=> array_search($id_2, $applicationids, true));
				});

				foreach ($host_items_grouped as $applicationid => $application_items) {
					CArrayHelper::sort($application_items, [$item_sort_options]);

					foreach ($application_items as $itemid => $item) {
						unset($item['applications']);

						$items[$itemid] = $item;
						$rows[] = [
							'itemid' => $itemid,
							'applicationid' => $applicationid
						];

						if (count($rows) > $config['search_limit']) {
							break 3;
						}
					}
				}
			}

			// Resolve macros.

			$items = CMacrosResolverHelper::resolveItemKeys($items);
			$items = CMacrosResolverHelper::resolveItemNames($items);
			$items = CMacrosResolverHelper::resolveTimeUnitMacros($items, ['delay', 'history', 'trends']);

			// Choosing max history period for already filtered items having data.
			$history_period = $filter['show_without_data'] ? ZBX_HISTORY_PERIOD : null;

			$history = Manager::History()->getLastValues($items, 2, $history_period);
		}
		else {
			$rows = [];
			$hosts = [];
			$applications = [];
			$applications_size = [];
			$history = [];
		}

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
			'rows' => $rows,
			'hosts' => $hosts,
			'applications' => $applications,
			'applications_size' => $applications_size,
			'items' => $items,
			'history' => $history,
			'multiselect_hostgroup_data' => $multiselect_hostgroup_data,
			'multiselect_host_data' => $multiselect_host_data
		];
	}
}
