<?php declare(strict_types = 1);
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
 * Common methods for "charts.view" and "charts.view.json" actions.
 */
abstract class CControllerCharts extends CController {
	// Number of subfilter values per row.
	const SUBFILTERS_VALUES_PER_ROW = 100;

	// Number of tag value rows allowed to be included in subfilter.
	const SUBFILTERS_TAG_VALUE_ROWS = 20;

	/**
	 * Fetches all host graphs based on hostid and graph name or item name used in graph.
	 *
	 * @param array  $hostids  Limit returned graphs to these hosts.
	 * @param string $name     Graphs or items in them should contain this string in their name.
	 *
	 * @return array
	 */
	protected function getHostGraphs(array $hostids, string $name): array {
		$graphs = API::Graph()->get([
			'output' => ['graphid', 'name'],
			'hostids' => $hostids,
			'search' => $name !== '' ? ['name' => $name] : null,
			'selectItems' => ['itemid'],
			'preservekeys' => true
		]);

		$graph_items = [];
		foreach ($graphs as $graph) {
			foreach ($graph['items'] as $item) {
				$graph_items[$item['itemid']] = $item;
			}
		}

		$filter_items = [];
		if ($name !== '') {
			$filter_items = API::Item()->get([
				'output' => ['itemid'],
				'hostids' => $hostids,
				'search' => ['name' => $name],
				'preservekeys' => true
			]);

			if ($filter_items) {
				$graphs += API::Graph()->get([
					'output' => ['graphid', 'name'],
					'hostids' => $hostids,
					'itemids' => array_keys($filter_items),
					'selectItems' => ['itemid'],
					'preservekeys' => true
				]);
			}
		}

		$items = [];
		if ($graph_items || $filter_items) {
			$items = API::Item()->get([
				'output' => ['itemid', 'name'],
				'hostids' => $hostids,
				'itemids' => array_keys($graph_items + $filter_items),
				'selectTags' => ['tag', 'value'],
				'preservekeys' => true
			]);
		}

		return $this->addTagsToGraphs($graphs, $items);
	}

	private function addTagsToGraphs(array $graphs, array $items): array {
		foreach ($graphs as &$graph) {
			$graph['tags'] = [];
			$tags = [];

			foreach ($graph['items'] as $item) {
				if (!array_key_exists($item['itemid'], $items)) {
					continue;
				}

				foreach ($items[$item['itemid']]['tags'] as $tag) {
					$tags[$tag['tag']][$tag['value']] = true;
				}
			}

			foreach ($tags as $tag_name => $tag) {
				foreach (array_keys($tag) as $tag_value) {
					$graph['tags'][] = [
						'tag' => $tag_name,
						'value' => $tag_value
					];
				}
			}
		}
		unset($graph);

		return $graphs;
	}

	/**
	 * Fetches all host graphs based on hostid and graph name or item name used in graph.
	 *
	 * @param array  $hostids  Limit returned graphs to these hosts.
	 * @param string $name     Graphs or items in them should contain this string in their name.
	 *
	 * @return array
	 */
	protected function getSimpleGraphs(array $hostids, string $name): array {
		return API::Item()->get([
			'output' => ['itemid', 'name'],
			'hostids' => $hostids,
			'search' => $name !== '' ? ['name' => $name] : null,
			// TODO VM: filter by tags
			'selectTags' => ['tag', 'value'],
			'preservekeys' => true
		]);
	}

	/**
	 * Prepares graph display details (graph dimensions, URL and sBox-ing flag).
	 *
	 * @param array $graphids  Graph IDs for which details need to be prepared.
	 *
	 * @return array
	 */
	protected function getCharts(array $graphs): array {
		$charts = [];

		foreach ($graphs as $graph) {
			if (array_key_exists('graphid', $graph)) {
				$chart = [
					'chartid' => 'graph_'.$graph['graphid'],
					'graphid' => $graph['graphid'],
					'dimensions' => getGraphDims($graph['graphid'])
				];

				if (in_array($chart['dimensions']['graphtype'], [GRAPH_TYPE_PIE, GRAPH_TYPE_EXPLODED])) {
					$chart['sbox'] = false;
					$chart['src'] = 'chart6.php';
				}
				else {
					$chart['sbox'] = true;
					$chart['src'] = 'chart2.php';
				}
			}
			else {
				$chart = [
					'chartid' => 'item_'.$graph['itemid'],
					'itemid' => $graph['itemid'],
					'dimensions' => getGraphDims(),
					'sbox' => true,
					'src' => 'chart.php'
				];
			}

			$charts[] = $chart;
		}

		return $charts;
	}

	/**
	 * Prepare subfilter fields from filter.
	 *
	 * @param array  $filter
	 * @param array  $filter['subfilter_tagnames']  Selected tagname subfilter parameters.
	 * @param array  $filter['subfilter_tags']      Selected tags subfilter parameters.
	 *
	 * @return array
	 */
	protected static function getSubfilterFields(array $filter): array {
		$subfilters = [];

		$subfilter_keys = ['subfilter_tagnames', 'subfilter_tags'];

		foreach ($subfilter_keys as $key) {
			if (!array_key_exists($key, $filter)) {
				continue;
			}

			if ($key === 'subfilter_tags') {
				$tmp_tags = [];
				foreach ($filter[$key] as $tag => $tag_values) {
					$tmp_tags[urldecode($tag)] = array_flip($tag_values);
				}
				$subfilters[$key] = $tmp_tags;
				unset($tmp_tags);
			}
			else {
				$subfilters[$key] = array_flip($filter[$key]);
			}
		}

		return CArrayHelper::renameKeys($subfilters, [
			'subfilter_tagnames' => 'tagnames',
			'subfilter_tags' => 'tags'
		]);
	}

	/**
	 * Find what subfilters are available based on items selected using the main filter.
	 *
	 * @param array  $graphs                           [IN/OUT] Result of host/simple graphs matching primary filter.
	 * @param string $graphs[]['graphid']              [IN] Host graph graphid.
	 * @param string $graphs[]['itemid']               [IN] Simple graph itemid.
	 * @param array  $graphs[]['tags']                 [IN] Item tags array.
	 * @param string $graphs[]['tags'][]['tag']        [IN] Tag name.
	 * @param string $graphs[]['tags'][]['value']      [IN] Tag value.
	 * @param array  $graphs[]['matching_subfilters']  [OUT] Flag for each of subfilter group showing either item fits
	 *                                                 fits its subfilter requirements.
	 * @param bool   $graphs[]['has_data']             [OUT] Flag either item has data.
	 * @param array  $subfilters                       Selected subfilters.
	 *
	 * @return array
	 */
	protected static function getSubfilters(array &$graphs, array $subfilters): array {
		$subfilter_options = self::getSubfilterOptions($graphs, $subfilters);
		$subfilters = self::clearSubfilters($subfilters, $subfilter_options);
		$graphs = self::getGraphMatchings($graphs, $subfilters);

		/*
		 * Calculate how many additional items would match the filtering results after selecting each of provided host
		 * subfilters. So item MUST match all subfilters except the tested one.
		 */
		foreach ($graphs as $graph) {
			// Calculate the counters of tag existence subfilter options.
			foreach ($graph['tags'] as $tag) {
				$graph_matches = true;
				foreach ($graph['matching_subfilters'] as $filter_name => $match) {
					if ($filter_name === 'tagnames') {
						continue;
					}
					if (!$match) {
						$graph_matches = false;
						break;
					}
				}

				if ($graph_matches) {
					$subfilter_options['tagnames'][$tag['tag']]['count']++;
				}
			}

			// Calculate the same for the tag/value pair subfilter options.
			foreach ($graph['tags'] as $tag) {
				$graph_matches = true;
				foreach ($graph['matching_subfilters'] as $filter_name => $match) {
					if ($filter_name === 'tags') {
						continue;
					}
					if (!$match) {
						$graph_matches = false;
						break;
					}
				}

				if ($graph_matches) {
					$subfilter_options['tags'][$tag['tag']][$tag['value']]['count']++;
				}
			}
		}

		return $subfilter_options;
	}

	/**
	 * Collect available options of subfilter from existing items and hosts selected by primary filter.
	 *
	 * @param array $graphs                       Host/Simple graphs selected by primary filter.
	 * @param array $graphs[]['tags']             Item tags.
	 * @param array $graphs[]['tags'][]['tag']    Item tag name.
	 * @param array $graphs[]['tags'][]['value']  Item tag value.
	 * @param array $subfilter
	 * @param array $subfilter['tagnames']        Selected subfilter names.
	 * @param array $subfilter['tags']            Selected subfilter tags.
	 *
	 * @return array
	 */
	protected static function getSubfilterOptions(array $graphs, array $subfilter): array {
		$subfilter_options = [
			'tagnames' => [],
			'tags' => []
		];

		foreach ($graphs as $graph) {
			foreach ($graph['tags'] as $tag) {
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

		// Sort subfilters by values.
		CArrayHelper::sort($subfilter_options['tagnames'], ['name']);
		uksort($subfilter_options['tags'], 'strnatcmp');
		array_walk($subfilter_options['tags'], function (&$tag_values) {
			CArrayHelper::sort($tag_values, ['name']);
		});

		return $subfilter_options;
	}

	protected static function clearSubfilters(array $subfilter, array $subfilter_options): array {
		foreach (array_keys($subfilter['tagnames']) as $tagname) {
			if (!array_key_exists($tagname, $subfilter_options['tagnames'])) {
				unset($subfilter['tagnames'][$tagname]);
			}
		}

		foreach ($subfilter['tags'] as $tag => $values) {
			if (!array_key_exists($tag, $subfilter_options['tags'])) {
				unset($subfilter['tags'][$tag]);
				continue;
			}

			foreach (array_keys($values) as $value) {
				if (!array_key_exists($value, $subfilter_options['tags'][$tag])) {
					unset($subfilter['tags'][$tag][$value]);
				}
			}
		}

		return $subfilter;
	}

	/**
	 * Calculate which items retrieved using the primary filter matches selected subfilter options. Results are added to
	 * the array stored with 'matching_subfilters' key for each retrieved item. Additionally 'has_data' flag is added to
	 * each of retrieved item to indicate either particular item has data.
	 *
	 * @param array  $graphs
	 * @param string $graphs[]['graphid']                          Item hostid.
	 * @param string $graphs[]['itemid']                           Item itemid.
	 * @param array  $graphs[]['tags']                             Items tags.
	 * @param array  $graphs[]['tags'][]['tag']                    Items tag name.
	 * @param array  $graphs[]['tags'][]['value']                  Items tag value.
	 * @param array  $subfilter
	 * @param array  $subfilter['tagnames']                       Selected subfilter tagnames.
	 * @param array  $subfilter['tags']                           Selected subfilter tags.
	 *
	 * @return array
	 */
	protected static function getGraphMatchings(array $graphs, array $subfilter): array {
		foreach ($graphs as &$graph) {
			$match_tagnames = (!$subfilter['tagnames']
				|| (bool) array_intersect_key($subfilter['tagnames'], array_flip(array_column($graph['tags'], 'tag')))
			);

			if ($subfilter['tags']) {
				$match_tags = false;
				foreach ($graph['tags'] as $tag) {
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

			$graph['matching_subfilters'] = [
				'tagnames' => $match_tagnames,
				'tags' => $match_tags
			];
		}
		unset($graph);

		return $graphs;
	}

	/**
	 * Unset items not matching selected subfilters.
	 *
	 * @param array $graphs
	 * @param array $graphs['matching_subfilters']    Contains flags either items matches all selected subfilters.
	 *
	 * @return array
	 */
	protected static function applySubfilters(array $graphs): array {
		return array_filter($graphs, function ($graph) {
			return array_sum($graph['matching_subfilters']) == count($graph['matching_subfilters']);
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
	public static function getMostSevereSubfilters(array $subfilters): array {
		if (self::SUBFILTERS_VALUES_PER_ROW >= count($subfilters)) {
			return $subfilters;
		}

		// All selected subfilters always must be included.
		$most_severe = array_filter($subfilters, function ($elmnt) {
			return $elmnt['selected'];
		});

		// Add first non-selected subfilter values in case if limit is not exceeded.
		$remaining = self::SUBFILTERS_VALUES_PER_ROW - count($most_severe);
		if ($remaining > 0) {
			$subfilters = array_diff_key($subfilters, $most_severe);
			$most_severe += array_slice($subfilters, 0, $remaining, true);
		}

		CArrayHelper::sort($most_severe, ['name']);

		return $most_severe;
	}

	/**
	 * Make subset of most severe tag value subfilters to reduce the space used by subfilter.
	 *
	 * @param array $tags
	 * @param bool  $tags[<tagname>][<tagvalue>]['selected']  Flag indicating if tag value is selected.
	 *
	 * @return array
	 */
	public static function getMostSevereTagValueSubfilters(array $tags): array {
		if (self::SUBFILTERS_TAG_VALUE_ROWS >= count($tags)) {
			return $tags;
		}

		// All selected subfilters always must be included.
		$most_severe = array_filter($tags, function ($tag) {
			return (bool) array_sum(array_column($tag, 'selected'));
		});

		// Add first non-selected subfilter values in case if limit is not exceeded.
		$remaining = self::SUBFILTERS_TAG_VALUE_ROWS - count($most_severe);
		if ($remaining > 0) {
			$tags = array_diff_key($tags, $most_severe);
			$most_severe += array_slice($tags, 0, $remaining, true);
		}

		uksort($most_severe, 'strnatcmp');

		return $most_severe;
	}
}
