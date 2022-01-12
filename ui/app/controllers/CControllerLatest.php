<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
		'show_tags' => SHOW_TAGS_3,
		'tag_name_format' => TAG_NAME_FULL,
		'tag_priority' => '',
		'show_without_data' => 1,
		'show_details' => 1,
		'page' => null,
		'sort' => 'name',
		'sortorder' => ZBX_SORT_UP,
		'subfilter_hostids' => [],
		'subfilter_tagnames' => [],
		'subfilter_tags' => [],
		'subfilter_data' => []
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
			'output' => ['hostid', 'name', 'status', 'maintenanceid', 'maintenance_status', 'maintenance_type'],
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
		$items = CMacrosResolverHelper::resolveItemDescriptions($items);
		$items = CMacrosResolverHelper::resolveTimeUnitMacros($items, ['delay', 'history', 'trends']);

		$history = Manager::History()->getLastValues($items, 2,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD))
		);

		$hosts_on_page = array_intersect_key($prepared_data['hosts'],
			array_column($prepared_data['items'], 'hostid', 'hostid')
		);

		$maintenanceids = [];

		foreach ($hosts_on_page as $host) {
			if ($host['status'] == HOST_STATUS_MONITORED &&	$host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$maintenanceids[$host['maintenanceid']] = true;
			}
		}

		$db_maintenances = [];

		if ($maintenanceids) {
			$db_maintenances = API::Maintenance()->get([
				'output' => ['name', 'description'],
				'maintenanceids' => array_keys($maintenanceids),
				'preservekeys' => true
			]);
		}

		$prepared_data['maintenances'] = $db_maintenances;
		$prepared_data['items'] = $items;
		$prepared_data['history'] = $history;
	}

	/**
	 * Get additional data for filters. Selected groups for multiselect, etc.
	 *
	 * @param array $filter
	 * @param array $filter['groupids']  Groupids from filter to select additional data.
	 * @param array $filter['hostids']   Hostids from filter to select additional data.
	 *
	 * @return array
	 */
	protected function getAdditionalData(array $filter): array {
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
	 * Clean and convert passed filter input fields from default values required for HTML presentation.
	 *
	 * @param array $input
	 * @param int   $input['filter_reset']     Either the reset button was pressed.
	 * @param array $input['tags']             Filter field tags.
	 * @param array $input['tags'][]['tag']    Filter field tag name.
	 * @param array $input['tags'][]['value']  Filter field tag value.
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
	 * @param array  $filter['subfilter_hostids']	Host subfilter.
	 * @param array  $filter['subfilter_tagnames']	Tagname subfilter.
	 * @param array  $filter['subfilter_tags']      Tags subfilter.
	 * @param array  $filter['subfilter_data']      Data subfilter.
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

		if (array_key_exists('subfilter_hostids', $filter) && $filter['subfilter_hostids']) {
			$hosts = array_intersect_key($hosts, array_flip($filter['subfilter_hostids']));
		}

		if (array_key_exists('subfilter_tagnames', $filter) && $filter['subfilter_tagnames']
				|| array_key_exists('subfilter_tags', $filter) && $filter['subfilter_tags']) {
			$filter['evaltype'] = TAG_EVAL_TYPE_AND_OR;

			$filter['tags'] = [];
			if (array_key_exists('subfilter_tagnames', $filter)) {
				foreach ($filter['subfilter_tagnames'] as $tagname) {
					$filter['tags'][] = [
						'tag' => $tagname,
						'operator' => TAG_OPERATOR_EXISTS
					];
				}
			}
			if (array_key_exists('subfilter_tags', $filter)) {
				foreach ($filter['subfilter_tags'] as $tagname => $values) {
					foreach ($values as $value) {
						$filter['tags'][] = [
							'tag' => $tagname,
							'value' => $value,
							'operator' => TAG_OPERATOR_EQUAL
						];
					}
				}
			}
		}

		$subfilter_data = -1;
		if ($filter['show_without_data']
				&& array_key_exists('subfilter_data', $filter)
				&& $filter['subfilter_data'] && count($filter['subfilter_data']) != 2) {
			$subfilter_data = reset($filter['subfilter_data']);
		}

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

			if ($subfilter_data != -1) {
				$items_having_values = Manager::History()->getItemsHavingValues($host_items, $history_period);

				switch ($subfilter_data) {
					// Items without data only.
					case 0:
						$host_items = array_diff_key($host_items, $items_having_values);
						break;

					// Items having data only.
					case 1:
						$host_items = $items_having_values;
						break;
				}
			}

			$select_items_cnt += count($host_items);
		}

		return min($select_items_cnt, $search_limit);
	}

	/**
	 * Prepare subfilter fields from filter.
	 *
	 * @param array  $filter
	 * @param array  $filter['subfilter_hostids']   Selected host subfilter parameters.
	 * @param array  $filter['subfilter_tagnames']  Selected tagname subfilter parameters.
	 * @param array  $filter['subfilter_tags']      Selected tags subfilter parameters.
	 * @param array  $filter['subfilter_data']      Selected data subfilter parameters.
	 *
	 * @return array
	 */
	protected static function getSubfilterFields(array $filter): array {
		$subfilters = [];

		foreach (['subfilter_hostids', 'subfilter_tagnames', 'subfilter_tags', 'subfilter_data'] as $subfilter_key) {
			if (!array_key_exists($subfilter_key, $filter)) {
				continue;
			}

			if ($subfilter_key === 'subfilter_tags') {
				$tmp_tags = [];
				foreach ($filter[$subfilter_key] as $tag => $tag_values) {
					$tmp_tags[urldecode($tag)] = array_flip($tag_values);
				}
				$subfilters[$subfilter_key] = $tmp_tags;
				unset($tmp_tags);
			}
			else {
				$subfilters[$subfilter_key] = array_flip($filter[$subfilter_key]);
			}
		}

		return CArrayHelper::renameKeys($subfilters, [
			'subfilter_hostids' => 'hostids',
			'subfilter_tagnames' => 'tagnames',
			'subfilter_tags' => 'tags',
			'subfilter_data' => 'data'
		]);
	}

	/**
	 * Find what subfilters are available based on items selected using the main filter.
	 *
	 * @param array  $subfilters                                       Selected subfilters.
	 * @param array  $prepared_data                                    [IN/OUT] Result of items matching primary filter.
	 * @param array  $prepared_data['hosts']                           [IN] Selected hosts from database.
	 * @param string $prepared_data['hosts'][]['name']                 [IN] Host name.
	 * @param array  $prepared_data['items']                           [IN/OUT] Selected items from database.
	 * @param string $prepared_data['items'][]['hostid']               [IN] Item hostid.
	 * @param string $prepared_data['items'][]['itemid']               [IN] Item itemid.
	 * @param array  $prepared_data['items'][]['tags']                 [IN] Item tags array.
	 * @param string $prepared_data['items'][]['tags'][]['tag']        [IN] Tag name.
	 * @param string $prepared_data['items'][]['tags'][]['value']      [IN] Tag value.
	 * @param array  $prepared_data['items'][]['matching_subfilters']  [OUT] Flag for each of subfilter group showing
	 *                                                                 either item fits its subfilter requirements.
	 * @param bool   $prepared_data['items'][]['has_data']             [OUT] Flag either item has data.
	 * @param array  $filter                                           Filter parameters.
	 * @param int    $filter['show_without_data']                      "Show items without data" filter checkbox value.
	 *
	 * @return array
	 */
	protected static function getSubfilters(array $subfilters, array &$prepared_data, array $filter): array {
		$subfilter_options = self::getSubfilterOptions($prepared_data, $subfilters);
		$prepared_data['items'] = self::getItemMatchings($prepared_data['items'], $subfilters);

		/*
		 * Calculate how many additional items would match the filtering results after selecting each of provided host
		 * subfilters. So item MUST match all subfilters except the tested one.
		 */
		foreach ($prepared_data['items'] as $item) {
			// Hosts subfilter.
			$item_matches = true;
			foreach ($item['matching_subfilters'] as $filter_name => $match) {
				if ($filter_name === 'hostids') {
					continue;
				}
				$item_matches &= $match;
			}

			if ($item_matches) {
				$subfilter_options['hostids'][$item['hostid']]['count']++;
			}

			// Calculate the counters of tag existence subfilter options.
			foreach ($item['tags'] as $tag) {
				$item_matches = true;
				foreach ($item['matching_subfilters'] as $filter_name => $match) {
					if ($filter_name === 'tagnames') {
						continue;
					}
					$item_matches &= $match;
				}

				if ($item_matches) {
					$subfilter_options['tagnames'][$tag['tag']]['count']++;
				}
			}

			// Calculate the same for the tag/value pair subfilter options.
			foreach ($item['tags'] as $tag) {
				$item_matches = true;
				foreach ($item['matching_subfilters'] as $filter_name => $match) {
					if ($filter_name === 'tags') {
						continue;
					}
					$item_matches &= $match;
				}

				if ($item_matches) {
					$subfilter_options['tags'][$tag['tag']][$tag['value']]['count']++;
				}
			}

			// Data subfilter. Enabled only when 'Show without data' checkbox is on.
			if ($filter['show_without_data']) {
				$data_key = (int) $item['has_data'];
				$subfilter_options['data'][$data_key]['count']++;
			}
		}

		// No need to show data subfilter if all selected items fits into same group.
		if (!$subfilter_options['data'][0]['count'] || !$subfilter_options['data'][1]['count']) {
			$subfilter_options['data'] = [];
		}

		return $subfilter_options;
	}

	/**
	 * Collect available options of subfilter from existing items and hosts selected by primary filter.
	 *
	 * @param array $data
	 * @param array $data['hosts']                         Hosts selected by primary filter.
	 * @param array $data['hosts'][<hostid>]['name']       Name of the host selected by primary filter.
	 * @param array $data['items']                         Items selected by primary filter.
	 * @param array $data['items'][]['tags']               Item tags.
	 * @param array $data['items'][]['tags'][]['tag']      Item tag name.
	 * @param array $data['items'][]['tags'][]['value']    Item tag value.
	 * @param array $subfilter
	 * @param array $subfilter['hostids']                  Selected subfilter hosts.
	 * @param array $subfilter['tagnames']                 Selected subfilter names.
	 * @param array $subfilter['tags']                     Selected subfilter tags.
	 * @param array $subfilter['data']                     Selected subfilter data options.
	 *
	 * @return array
	 */
	protected static function getSubfilterOptions(array $data, array $subfilter): array {
		$subfilter_options = [
			'hostids' => [],
			'tagnames' => [],
			'tags' => [],
			'data' => []
		];

		foreach ($data['hosts'] as $hostid => $host) {
			$subfilter_options['hostids'][$hostid] = [
				'name' => $host['name'],
				'selected' => array_key_exists($hostid, $subfilter['hostids']),
				'count' => 0
			];
		}

		foreach ($data['items'] as $item) {
			foreach ($item['tags'] as $tag) {
				if (!array_key_exists($tag['tag'], $subfilter_options['tagnames'])) {
					$subfilter_options['tagnames'][$tag['tag']] = [
						'name' => $tag['tag'],
						'selected' => array_key_exists($tag['tag'], $subfilter['tagnames']),
						'count' => 0
					];

					$subfilter_options['tags'][$tag['tag']] = [];
				}

				$subfilter_options['tags'][$tag['tag']][$tag['value']] = [
					'name' => $tag['value'],
					'selected' => (array_key_exists($tag['tag'], $subfilter['tags'])
						&& array_key_exists($tag['value'], $subfilter['tags'][$tag['tag']])
					),
					'count' => 0
				];
			}
		}

		$subfilter_options['data'] = [
			1 => [
				'name' => _('With data'),
				'selected' => array_key_exists(1, $subfilter['data']),
				'count' => 0
			],
			0 => [
				'name' => _('Without data'),
				'selected' => array_key_exists(0, $subfilter['data']),
				'count' => 0
			]
		];

		// Sort subfilters by values.
		CArrayHelper::sort($subfilter_options['hostids'], ['name']);
		CArrayHelper::sort($subfilter_options['tagnames'], ['name']);
		uksort($subfilter_options['tags'], 'strnatcmp');
		array_walk($subfilter_options['tags'], function (&$tag_values) {
			CArrayHelper::sort($tag_values, ['name']);
		});

		return $subfilter_options;
	}

	/**
	 * Calculate which items retrieved using the primary filter matches selected subfilter options. Results are added to
	 * the array stored with 'matching_subfilters' key for each retrieved item. Additionally 'has_data' flag is added to
	 * each of retrieved item to indicate either particular item has data.
	 *
	 * @param array  $items
	 * @param string $items[]['hostid']                           Item hostid.
	 * @param string $items[]['itemid']                           Item itemid.
	 * @param array  $items[]['tags']                             Items tags.
	 * @param array  $items[]['tags'][]['tag']                    Items tag name.
	 * @param array  $items[]['tags'][]['value']                  Items tag value.
	 * @param array  $subfilter
	 * @param array  $subfilter['hostids']                        Selected subfilter hosts.
	 * @param array  $subfilter['tagnames']                       Selected subfilter tagnames.
	 * @param array  $subfilter['tags']                           Selected subfilter tags.
	 * @param array  $subfilter['data']                           Selected subfilter data options.
	 *
	 * @return array
	 */
	protected static function getItemMatchings(array $items, array $subfilter): array {
		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		$with_data = Manager::History()->getItemsHavingValues($items, $history_period);
		$with_data = array_flip(array_keys($with_data));

		foreach ($items as &$item) {
			$match_hosts = (!$subfilter['hostids'] || array_key_exists($item['hostid'], $subfilter['hostids']));
			$match_tagnames = (!$subfilter['tagnames']
				|| (bool) array_intersect_key($subfilter['tagnames'], array_flip(array_column($item['tags'], 'tag')))
			);

			if ($subfilter['tags']) {
				$match_tags = false;
				foreach ($item['tags'] as $tag) {
					if (array_key_exists($tag['tag'], $subfilter['tags'])
							&& array_key_exists($tag['value'], $subfilter['tags'][$tag['tag']])) {
						$match_tags = true;
						break;
					}
				}
			}
			else {
				$match_tags = true;
			}

			$item['has_data'] = array_key_exists($item['itemid'], $with_data);
			$match_data = (!$subfilter['data']
				|| array_key_exists(0, $subfilter['data']) && !$item['has_data']
				|| array_key_exists(1, $subfilter['data']) && $item['has_data']
			);

			$item['matching_subfilters'] = [
				'hostids' => $match_hosts,
				'tagnames' => $match_tagnames,
				'tags' => $match_tags,
				'data' => $match_data
			];
		}
		unset($item);

		return $items;
	}

	/**
	 * Unset items not matching selected subfilters.
	 *
	 * @param array $items
	 * @param array $items['matching_subfilters']    Contains flags either items matches all selected subfilters.
	 *
	 * @return array
	 */
	protected static function applySubfilters(array $items): array {
		return array_filter($items, function ($item) {
			return array_sum($item['matching_subfilters']) == count($item['matching_subfilters']);
		});
	}
}
