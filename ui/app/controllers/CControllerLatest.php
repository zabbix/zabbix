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
	public const FILTER_IDX = 'web.monitoring.latest';

	// Number of tag value rows allowed to be included in subfilter.
	public const SUBFILTERS_TAG_VALUE_ROWS = 10;

	// Number of tag value rows when tag values subfilter is expanded.
	private const SUBFILTERS_TAG_VALUE_ROWS_EXPANDED = 200;

	// Filter fields default values.
	public const FILTER_FIELDS_DEFAULT = [
		'groupids' => [],
		'hostids' => [],
		'name' => '',
		'evaltype' => TAG_EVAL_TYPE_AND_OR,
		'tags' => [],
		'show_tags' => SHOW_TAGS_3,
		'tag_name_format' => TAG_NAME_FULL,
		'tag_priority' => '',
		'show_details' => 0,
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
			'hostids' => $filter['hostids'] ?: null,
			'preservekeys' => true
		]);

		$search_limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);
		$select_items_cnt = 0;
		$select_items = [];

		foreach ($hosts as $hostid => $host) {
			if ($select_items_cnt > $search_limit) {
				unset($hosts[$hostid]);
				continue;
			}

			$select_items += API::Item()->get([
				'output' => ['itemid', 'hostid', 'value_type'],
				'hostids' => [$hostid],
				'webitems' => true,
				'evaltype' => $filter['evaltype'],
				'tags' => $filter['tags'] ?: null,
				'filter' => [
					'status' => [ITEM_STATUS_ACTIVE]
				],
				'search' => ($filter['name'] === '') ? null : [
					'name' => $filter['name']
				],
				'preservekeys' => true
			]);

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
		if (array_key_exists('tags', $input) && $input['tags']) {
			$input['tags'] = array_filter($input['tags'], function ($tag) {
				return !($tag['tag'] === '' && $tag['value'] === '');
			});
			$input['tags'] = array_values($input['tags']);
		}

		return $input;
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
		$tags = [];

		foreach ($filter['subfilter_tags'] as $tag => $tag_values) {
			$tags[urldecode($tag)] = array_flip($tag_values);
		}

		return [
			'hostids' => array_flip($filter['subfilter_hostids']),
			'tagnames' => array_flip($filter['subfilter_tagnames']),
			'tags' => $tags,
			'data' => array_flip($filter['subfilter_data'])
		];
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
	 *
	 * @return array
	 */
	protected static function getSubfilters(array $subfilters, array &$prepared_data): array {
		$subfilter_options = self::getSubfilterOptions($prepared_data, $subfilters);
		$prepared_data['items'] = self::getItemMatchings($prepared_data['items'], $subfilters);

		/*
		 * Calculate how many additional items would match the filtering results after selecting each of provided host
		 * subfilters. So item MUST match all subfilters except the tested one.
		 */
		$matching_items_by_tagnames = [];
		$matching_items_by_tags = [];

		foreach ($prepared_data['items'] as $item) {
			// Hosts subfilter.
			$item_matches = true;
			foreach ($item['matching_subfilters'] as $filter_name => $match) {
				if ($filter_name === 'hostids') {
					continue;
				}
				if (!$match) {
					$item_matches = false;
					break;
				}
			}

			if ($item_matches) {
				$subfilter_options['hostids'][$item['hostid']]['count']++;
			}

			// Calculate the counters of tag existence subfilter options.
			$item_matches = true;
			foreach ($item['matching_subfilters'] as $filter_name => $match) {
				if ($filter_name === 'tagnames') {
					continue;
				}
				if (!$match) {
					$item_matches = false;
					break;
				}
			}

			if ($item_matches) {
				foreach ($item['tags'] as $tag) {
					if (is_array($item['matching_subfilters']['tagnames'])
							&& array_key_exists($tag['tag'], $item['matching_subfilters']['tagnames'])) {
						$matching_items_by_tagnames[$item['itemid']] = true;
					}

					$subfilter_options['tagnames'][$tag['tag']]['items'][$item['itemid']] = true;
				}
			}

			// Calculate the same for the tag/value pair subfilter options.
			$item_matches = true;
			foreach ($item['matching_subfilters'] as $filter_name => $match) {
				if ($filter_name === 'tags') {
					continue;
				}
				if (!$match) {
					$item_matches = false;
					break;
				}
			}

			if ($item_matches) {
				foreach ($item['tags'] as $tag) {
					if (is_array($item['matching_subfilters']['tags'])
							&& array_key_exists($tag['tag'], $item['matching_subfilters']['tags'])
							&& array_key_exists($tag['value'], $item['matching_subfilters']['tags'][$tag['tag']])) {
						$matching_items_by_tags[$item['itemid']] = true;
					}

					$subfilter_options['tags'][$tag['tag']][$tag['value']]['items'][$item['itemid']] = true;
				}
			}

			// Data subfilter.
			if ($subfilters['data']) {
				$subfilter_options['data'][$item['has_data'] ? 1 : 0]['count']++;
			}
		}

		array_walk($subfilter_options['tagnames'], function (&$tag) use ($matching_items_by_tagnames) {
			if (!$tag['selected'] && $tag['items']) {
				$tag['count'] = count(array_diff_key($tag['items'], $matching_items_by_tagnames));
			}
			else {
				$tag['count'] = count($tag['items']);
			}

			unset($tag['items']);
		});

		array_walk($subfilter_options['tags'], function (&$tag_values) use ($matching_items_by_tags) {
			array_walk($tag_values, function (&$tag) use ($matching_items_by_tags) {
				if (!$tag['selected'] && $tag['items']) {
					$tag['count'] = count(array_diff_key($tag['items'], $matching_items_by_tags));
				}
				else {
					$tag['count'] = count($tag['items']);
				}

				unset($tag['items']);
			});
		});

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
			'tags' => []
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
						'items' => [],
						'count' => 0
					];

					$subfilter_options['tags'][$tag['tag']] = [];
				}

				$subfilter_options['tags'][$tag['tag']][$tag['value']] = [
					'name' => $tag['value'],
					'selected' => (array_key_exists($tag['tag'], $subfilter['tags'])
						&& array_key_exists($tag['value'], $subfilter['tags'][$tag['tag']])
					),
					'items' => [],
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
		if ($subfilter['data']) {
			$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
			$with_data = Manager::History()->getItemsHavingValues($items, $history_period);
			$with_data = array_flip(array_keys($with_data));
		}

		foreach ($items as &$item) {
			$match_hosts = (!$subfilter['hostids'] || array_key_exists($item['hostid'], $subfilter['hostids']));
			$match_tagnames = $subfilter['tagnames']
				? array_intersect_key($subfilter['tagnames'], array_flip(array_column($item['tags'], 'tag')))
				: true;

			if ($subfilter['tags']) {
				$match_tags = [];
				foreach ($item['tags'] as $tag) {
					if (array_key_exists($tag['tag'], $subfilter['tags'])
							&& array_key_exists($tag['value'], $subfilter['tags'][$tag['tag']])) {
						$match_tags[$tag['tag']][$tag['value']] = true;
					}
				}
			}
			else {
				$match_tags = true;
			}

			$item['matching_subfilters'] = [
				'hostids' => $match_hosts,
				'tagnames' => $match_tagnames,
				'tags' => $match_tags
			];

			if ($subfilter['data']) {
				$item['has_data'] = array_key_exists($item['itemid'], $with_data);
				$item['matching_subfilters']['data'] = array_key_exists(0, $subfilter['data']) && !$item['has_data']
					|| array_key_exists(1, $subfilter['data']) && $item['has_data'];
			}
		}
		unset($item);

		return $items;
	}

	/**
	 * Returns array of items matching selected subfilters.
	 *
	 * @param array      $items
	 * @param array      $items[]['matching_subfilters']
	 * @param bool       $items[]['matching_subfilters']['hostids']   (optional) TRUE if item matches host subfilter.
	 * @param array|bool $items[]['matching_subfilters']['tagnames']  (optional) TRUE if item matches tagname subfilter
	 *                                                                or array of exactly matching tagnames.
	 * @param array|bool $items[]['matching_subfilters']['tags']      (optional) TRUE if item matches tagname/value
	 *                                                                subfilter or array of exactly matching
	 *                                                                tagname/value pairs.
	 * @param bool       $items[]['matching_subfilters']['data']      (optional) TRUE if item matches data subfilter.
	 *
	 * @return array
	 */
	protected static function applySubfilters(array $items): array {
		return array_filter($items, function ($item) {
			$matches = array_intersect_key($item['matching_subfilters'],
				array_flip(['hostids', 'tagnames', 'tags', 'data'])
			);

			if (array_key_exists('tagnames', $matches)) {
				$matches['tagnames'] = (bool) $matches['tagnames'];
			}

			if (array_key_exists('tags', $matches)) {
				$matches['tags'] = (bool) $matches['tags'];
			}

			return (count(array_unique($matches)) == 1)
				? current($matches)
				: false;
		});
	}

	/**
	 * Make subset of most severe subfilters to reduce the space used by subfilter.
	 *
	 * @param array  $subfilters
	 * @param string $subfilters[<subfilter option>]['name']      Option name.
	 * @param bool   $subfilters[<subfilter option>]['selected']  Flag indicating if option is selected.
	 *
	 * @return array
	 */
	public static function getTopPrioritySubfilters(array $subfilters): array {
		if (SUBFILTER_VALUES_PER_GROUP < count($subfilters)) {
			// All selected subfilters must always be included.
			$top_priority_fields = array_filter($subfilters, function ($field) {
				return $field['selected'];
			});

			// Add first non-selected subfilter values in case limit is not exceeded.
			$remaining = SUBFILTER_VALUES_PER_GROUP - count($top_priority_fields);
			if ($remaining > 0) {
				$subfilters = array_diff_key($subfilters, $top_priority_fields);
				CArrayHelper::sort($subfilters, ['name']);
				$top_priority_fields += array_slice($subfilters, 0, $remaining, true);
			}
		}
		else {
			$top_priority_fields = $subfilters;
		}

		CArrayHelper::sort($top_priority_fields, ['name']);

		return $top_priority_fields;
	}

	/**
	 * Make subset of most severe tag value subfilters to reduce the space used by subfilter.
	 *
	 * @param array $tags
	 * @param bool  $tags[<tagname>][<tagvalue>]['selected']  Flag indicating if tag value is selected.
	 *
	 * @return array
	 */
	public static function getTopPriorityTagValueSubfilters(array $tags): array {
		$top_priority_fields = [];

		// All selected subfilters must always be included.
		foreach ($tags as $tag => $values) {
			if ((bool) array_sum(array_column($values, 'selected'))) {
				$values = array_filter($values, function ($field) {
					return ($field['selected'] || $field['count'] != 0);
				});

				$values_count = count($values);
				$values = self::getTopPrioritySubfilters($values);

				$top_priority_fields[] = [
					'name' => $tag,
					'values' => $values,
					'trimmed' => ($values_count > count($values))
				];
				unset($tags[$tag]);
			}
		}

		// Add first non-selected subfilter values in case limit is not exceeded.
		if (self::SUBFILTERS_TAG_VALUE_ROWS_EXPANDED > count($top_priority_fields)) {
			$tags_names = array_keys($tags);
			uasort($tags_names, 'strnatcasecmp');

			do {
				if (($tag_name = array_shift($tags_names)) === null) {
					break;
				}

				$tag_values = array_filter($tags[$tag_name], function ($field) {
					return ($field['selected'] || $field['count'] != 0);
				});

				if ($tag_values) {
					$tag_values_count = count($tag_values);
					$tag_values = self::getTopPrioritySubfilters($tag_values);

					$top_priority_fields[] = [
						'name' => $tag_name,
						'values' => $tag_values,
						'trimmed' => ($tag_values_count > count($tag_values))
					];
				}
			} while (self::SUBFILTERS_TAG_VALUE_ROWS_EXPANDED > count($top_priority_fields));
		}

		CArrayHelper::sort($top_priority_fields, ['name']);

		return $top_priority_fields;
	}
}
