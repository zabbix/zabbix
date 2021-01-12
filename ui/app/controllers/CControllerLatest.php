<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
	 * Prepare the latest data based on the given filter and sorting options.
	 *
	 * @param array  $filter                       Item filter options.
	 * @param array  $filter['groupids']           Filter items by host groups.
	 * @param array  $filter['hostids']            Filter items by hosts.
	 * @param string $filter['application']        Filter items by application.
	 * @param string $filter['select']             Filter items by name.
	 * @param int    $filter['show_without_data']  Include items with empty history.
	 * @param string $sort_field                   Sorting field.
	 * @param string $sort_order                   Sorting order.
	 *
	 * @return array
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

		$hosts = API::Host()->get([
			'output' => ['hostid', 'name', 'status'],
			'groupids' => $groupids,
			'hostids' => $filter['hostids'] ? $filter['hostids'] : null,
			'monitored_hosts' => true,
			'preservekeys' => true
		]);

		CArrayHelper::sort($hosts, [$host_sort_options]);
		$hostids = array_keys($hosts);
		$hostids_index = array_flip($hostids);

		$applications = [];

		$select_hosts = [];
		$select_items = [];

		foreach ($hosts as $hostid => $host) {
			if ($filter['application'] !== '') {
				$host_applications = API::Application()->get([
					'output' => ['applicationid', 'name'],
					'hostids' => [$hostid],
					'search' => ['name' => $filter['application']],
					'preservekeys' => true
				]);

				$host_applicationids = array_keys($host_applications);

				$applications += $host_applications;
			}
			else {
				$host_applicationids = null;
			}

			$host_items = API::Item()->get([
				'output' => ['itemid', 'hostid', 'value_type'],
				'hostids' => [$hostid],
				'applicationids' => $host_applicationids,
				'webitems' => true,
				'filter' => [
					'status' => [ITEM_STATUS_ACTIVE]
				],
				'search' => ($filter['select'] === '') ? null : [
					'name' => $filter['select']
				],
				'preservekeys' => true
			]);

			$select_hosts[$hostid] = true;

			$select_items += $filter['show_without_data']
				? $host_items
				: Manager::History()->getItemsHavingValues($host_items, ZBX_HISTORY_PERIOD);

			if (count($select_items) > $config['search_limit']) {
				break;
			}
		}

		if ($select_items) {
			$items = API::Item()->get([
				'output' => ['itemid', 'type', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
					'value_type', 'units', 'valuemapid', 'description', 'state', 'error'
				],
				'selectApplications' => ['applicationid'],
				'itemids' => array_keys($select_items),
				'webitems' => true,
				'preservekeys' => true
			]);

			if ($filter['application'] === '') {
				$applications = API::Application()->get([
					'output' => ['applicationid', 'name'],
					'hostids' => array_keys($select_hosts),
					'preservekeys' => true
				]);
			}

			CArrayHelper::sort($applications, [$application_sort_options]);
			$applicationids = array_keys($applications);
			$applicationids_index = array_flip($applicationids);

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

			$applications_index = [];
			$items = [];
			$rows = [];

			uksort($items_grouped, function($hostid_1, $hostid_2) use ($hostids_index) {
				return ($hostids_index[$hostid_1] <=> $hostids_index[$hostid_2]);
			});

			foreach ($items_grouped as $hostid => $host_items_grouped) {
				uksort($host_items_grouped,
					function($id_1, $id_2) use ($applicationids_index, $application_sort_options) {
						if ($id_1 == 0 || $id_2 == 0) {
							return bccomp($id_1, $id_2) * (($application_sort_options['order'] === 'ASC') ? -1 : 1);
						}

						return ($applicationids_index[$id_1] <=> $applicationids_index[$id_2]);
					}
				);

				foreach ($host_items_grouped as $applicationid => $application_items) {
					CArrayHelper::sort($application_items, [$item_sort_options]);

					$applications_index[$hostid][$applicationid] = [
						'start' => count($rows)
					];

					foreach ($application_items as $itemid => $item) {
						unset($item['applications']);

						$applications_index[$hostid][$applicationid]['end'] = count($rows);
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
		}
		else {
			$rows = [];
			$hosts = [];
			$applications = [];
			$applications_size = [];
			$applications_index = [];
			$items = [];
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
			'applications_index' => $applications_index,
			'items' => $items,
			'multiselect_hostgroup_data' => $multiselect_hostgroup_data,
			'multiselect_host_data' => $multiselect_host_data
		];
	}

	/**
	 * Extend previously prepared data.
	 *
	 * @param array $prepared_data      Data returned by prepareData method.
	 * @param int   $show_without_data  Include items with empty history.
	 */
	protected function extendData(array &$prepared_data, $show_without_data) {
		$items = array_intersect_key($prepared_data['items'],
			array_flip(array_column($prepared_data['rows'], 'itemid'))
		);

		// Resolve macros.

		$items = CMacrosResolverHelper::resolveItemKeys($items);
		$items = CMacrosResolverHelper::resolveItemNames($items);
		$items = CMacrosResolverHelper::resolveTimeUnitMacros($items, ['delay', 'history', 'trends']);

		$history = Manager::History()->getLastValues($items, 2, ZBX_HISTORY_PERIOD);

		$prepared_data['items'] = $items;
		$prepared_data['history'] = $history;
	}

	/**
	 * Add collapsed data from user profile.
	 *
	 * @param array $prepared_data  Data returned by prepareData method.
	 */
	protected function addCollapsedDataFromProfile(array &$prepared_data) {
		$collapsed_index = [];
		$collapsed_all = true;

		foreach ($prepared_data['rows'] as $row) {
			$hostid = $prepared_data['items'][$row['itemid']]['hostid'];
			$applicationid = $row['applicationid'];

			if (array_key_exists($hostid, $collapsed_index)
					&& array_key_exists($applicationid, $collapsed_index[$hostid])) {
				continue;
			}

			$collapsed = $applicationid
				? (CProfile::get('web.latest.toggle', null, $applicationid) !== null)
				: (CProfile::get('web.latest.toggle_other', null, $hostid) !== null);

			$collapsed_index[$hostid][$applicationid] = $collapsed;
			$collapsed_all = $collapsed_all && $collapsed;
		}

		$prepared_data['collapsed_index'] = $collapsed_index;
		$prepared_data['collapsed_all'] = $collapsed_all;
	}
}
