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

	// Filter idx prefix.
	const FILTER_IDX = 'web.monitoring.latest';

	// Filter fields default values.
	const FILTER_FIELDS_DEFAULT = [
		'groupids' => [],
		'hostids' => [],
		'name' => '',
		'evaltype' => TAG_EVAL_TYPE_AND_OR,
		'tags' => [],
		'show_without_data' => true,
		'show_details' => true,
		'page' => null,
		'sort' => 'name',
		'sortorder' => ZBX_SORT_UP
	];

	/**
	 * Prepare the latest data based on the given filter and sorting options.
	 *
	 * @param array  $filter                       Item filter options.
	 * @param array  $filter['groupids']           Filter items by host groups.
	 * @param array  $filter['hostids']            Filter items by hosts.
	 * @param string $filter['name']               Filter items by name.
	 * @param int    $filter['evaltype']           Filter evaltype.
	 * @param array  $filter['tags']               Filter tags.
	 * @param string $filter['tags'][]['tag']
	 * @param string $filter['tags'][]['value']
	 * @param int    $filter['tags'][]['operator']
	 * @param int    $filter['show_without_data']  Include items with empty history.
	 * @param string $sort_field                   Sorting field.
	 * @param string $sort_order                   Sorting order.
	 *
	 * @return array
	 */
	protected function prepareData(array $filter, $sort_field, $sort_order) {
		// Select groups for subsequent selection of hosts and items.
		$groupids = $filter['groupids'] ? getSubGroups($filter['groupids']) : null;

		// Select hosts for subsequent selection of items.
		$hosts = API::Host()->get([
			'output' => ['hostid', 'name', 'status'],
			'groupids' => $groupids,
			'hostids' => $filter['hostids'] ? $filter['hostids'] : null,
			'monitored_hosts' => true,
			'preservekeys' => true
		]);

		$search_limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);
		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		$select_items_cnt = 0;
		$select_items = [];

		foreach ($hosts as $hostid => $host) {
			if ($select_items_cnt > $search_limit) {
				unset($hosts[$hostid]);
				continue;
			}

			$host_items = API::Item()->get([
				'output' => ['itemid', 'hostid', 'value_type'],
				'hostids' => [$hostid],
				'webitems' => true,
				'evaltype' => $filter['evaltype'],
				'tags' => $filter['tags'] ? $filter['tags'] : null,
				'filter' => [
					'status' => [ITEM_STATUS_ACTIVE]
				],
				'search' => ($filter['name'] === '') ? null : [
					'name' => $filter['name']
				],
				'preservekeys' => true
			]);

			$select_items += $filter['show_without_data']
				? $host_items
				: Manager::History()->getItemsHavingValues($host_items, $history_period);

			$select_items_cnt = count($select_items);
		}

		if ($select_items) {
			$items = API::Item()->get([
				'output' => ['itemid', 'type', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
					'value_type', 'units', 'description', 'state', 'error'
				],
				'selectTags' => ['tag', 'value'],
				'selectValueMap' => ['mappings'],
				'itemids' => array_keys($select_items),
				'webitems' => true,
				'preservekeys' => true
			]);

			if ($sort_field === 'host') {
				$items = array_map(function ($item) use ($hosts) {
					return $item + [
						'host_name' => $hosts[$item['hostid']]['name']
					];
				}, $items);

				CArrayHelper::sort($items, [[
					'field' => 'host_name',
					'order' => $sort_order
				]]);
			}
			else {
				CArrayHelper::sort($items, [[
					'field' => 'name',
					'order' => $sort_order
				]]);
			}
		}
		else {
			$hosts = [];
			$items = [];
		}

		return [
			'hosts' => $hosts,
			'items' => $items
		];
	}

	/**
	 * Extend previously prepared data.
	 *
	 * @param array $prepared_data      Data returned by prepareData method.
	 */
	protected function extendData(array &$prepared_data) {
		$items = CMacrosResolverHelper::resolveItemKeys($prepared_data['items']);
		$items = CMacrosResolverHelper::resolveItemNames($items);
		$items = CMacrosResolverHelper::resolveItemDescriptions($items);
		$items = CMacrosResolverHelper::resolveTimeUnitMacros($items, ['delay', 'history', 'trends']);

		$history = Manager::History()->getLastValues($items, 2,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD))
		);

		$prepared_data['items'] = $items;
		$prepared_data['history'] = $history;
	}

	/**
	 * Get additional data for filters. Selected groups for multiselect, etc.
	 *
	 * @param array $filter  Filter fields values array.
	 *
	 * @return array
	 */
	protected function getAdditionalData($filter): array {
		$data = [];

		if ($filter['groupids']) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['groupids']
			]);
			$data['groups_multiselect'] = CArrayHelper::renameObjectsKeys(array_values($groups), ['groupid' => 'id']);
		}

		if ($filter['hostids']) {
			$hosts = API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $filter['hostids']
			]);
			$data['hosts_multiselect'] = CArrayHelper::renameObjectsKeys(array_values($hosts), ['hostid' => 'id']);
		}

		return $data;
	}

	/**
	 * Clean passed filter fields in input from default values required for HTML presentation. Convert field
	 *
	 * @param array $input  Filter fields values.
	 *
	 * @return array
	 */
	protected function cleanInput(array $input): array {
		if (array_key_exists('filter_reset', $input) && $input['filter_reset']) {
			return array_intersect_key(['filter_name' => ''], $input);
		}

		if (array_key_exists('tags', $input) && $input['tags']) {
			$input['tags'] = array_filter($input['tags'], function($tag) {
				return !($tag['tag'] === '' && $tag['value'] === '');
			});
			$input['tags'] = array_values($input['tags']);
		}

		return $input;
	}

	/**
	 * Get items count for passed filter.
	 *
	 * @param array  $filter                        Filter options.
	 * @param string $filter['name']                Filter items by name.
	 * @param array  $filter['groupids']            Filter items by host groups.
	 * @param array  $filter['hostids']             Filter items by host groups.
	 * @param string $filter['evaltype']            Filter items by tags.
	 * @param string $filter['tags']                Filter items by tag names and values.
	 * @param int    $filter['show_without_data']   Filter items with/without data.
	 *
	 * @return int
	 */
	protected function getCount(array $filter): int {
		$groupids = $filter['groupids'] ? getSubGroups($filter['groupids']) : null;

		$hosts = API::Host()->get([
			'output' => [],
			'groupids' => $groupids,
			'hostids' => $filter['hostids'] ? $filter['hostids'] : null,
			'monitored_hosts' => true,
			'preservekeys' => true
		]);

		$search_limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);
		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		$select_items_cnt = 0;

		foreach (array_keys($hosts) as $hostid) {
			if ($select_items_cnt >= $search_limit) {
				break;
			}

			$host_items = API::Item()->get([
				'output' => ['itemid', 'value_type'],
				'hostids' => [$hostid],
				'webitems' => true,
				'evaltype' => $filter['evaltype'],
				'tags' => $filter['tags'] ? $filter['tags'] : null,
				'filter' => [
					'status' => [ITEM_STATUS_ACTIVE]
				],
				'search' => ($filter['name'] === '') ? null : [
					'name' => $filter['name']
				],
				'preservekeys' => true
			]);

			$host_items = $filter['show_without_data']
				? $host_items
				: Manager::History()->getItemsHavingValues($host_items, $history_period);

			$select_items_cnt += count($host_items);
		}

		return min($select_items_cnt, $search_limit);
	}
}
