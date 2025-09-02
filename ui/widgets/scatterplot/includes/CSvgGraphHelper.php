<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


namespace Widgets\ScatterPlot\Includes;

use API,
	CArrayHelper,
	CColorPicker,
	CHousekeepingHelper,
	CItemHelper,
	CMacrosResolverHelper,
	CMathHelper,
	CParser,
	CSimpleIntervalParser,
	CSvgGraph,
	Exception,
	Manager;

/**
 * Class calculates graph data and makes SVG graph.
 */
class CSvgGraphHelper
{

	/**
	 * Calculate graph data and draw SVG graph based on given graph configuration.
	 *
	 * @param array $options  Options for graph.
	 *                        array  $options['data_sets']        Graph data set options.
	 *                        int    $options['data_source']      Data source of graph.
	 *                        array  $options['time_period']      Graph time period used.
	 *                        bool   $options['fix_time_period']  Whether to keep time period fixed.
	 *                        array  $options['left_y_axis']      Options for graph left Y axis.
	 *                        array  $options['right_y_axis']     Options for graph right Y axis.
	 *                        array  $options['x_axis']           Options for graph X axis.
	 *                        array  $options['legend']           Options for graph legend.
	 *                        int    $options['legend_lines']     Number of lines in the legend.
	 *                        array  $options['problems']         Graph problems options.
	 *                        array  $options['overrides']        Graph override options.
	 *
	 * @param int   $width
	 * @param int   $height
	 *
	 * @throws Exception
	 *
	 * @return array
	 */
	public static function get(array $options, int $width, int $height): array {
		$metrics = [];
		$errors = [];

		// Find which metrics will be shown in graph and calculate time periods and display options.
		self::getMetricsPattern($metrics, $options['data_sets'], $options['templateid'], $options['override_hostid']);
		self::getMetricsItems($metrics, $options['data_sets'], $options['templateid'], $options['override_hostid']);
		self::sortByDataset($metrics);
		self::applyUnits($metrics, $options['axes']);
		// Apply time periods for each $metric, based on graph/dashboard time as well as metric level time shifts.
		self::getTimePeriods($metrics, $options['time_period']);
		// Find what data source (history or trends) will be used for each metric.
		self::getGraphDataSource($metrics, $errors, $options['data_source'], $width);
		// Load aggregated Data for each dataset.
		self::getMetricsAggregatedData($metrics, $width, $options['thresholds']);

		$legend = self::getLegend($metrics, $options['legend']);

		$svg_height = max(0, $height - ($legend !== null ? $legend->getHeight() : 0));

		$graph = (new СScatterPlot([
			'time_period' => $options['time_period'],
			'axes' => $options['axes']
		]))
			->setSize($width, $svg_height)
			->addMetrics($metrics)
			->draw();

		// Add mouse following helper line.
		$graph->addHelper();

		return [
			'svg' => $graph,
			'legend' => $legend ?? '',
			'data' => [
				'dims' => [
					'x' => $graph->getCanvasX(),
					'y' => $graph->getCanvasY(),
					'w' => $graph->getCanvasWidth(),
					'h' => $graph->getCanvasHeight()
				],
				'spp' => $graph->getCanvasWidth() === 0
					? 0
					: ($options['time_period']['time_to'] - $options['time_period']['time_from'])
					/ $graph->getCanvasWidth()
			],
			'errors' => $errors
		];
	}

	private static function getMetricsPattern(array &$metrics, array $data_sets, string $templateid,
		string $override_hostid): void {
		$max_metrics = SVG_GRAPH_MAX_NUMBER_OF_METRICS;

		foreach ($data_sets as $index => $data_set) {
			if ($data_set['dataset_type'] == CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM) {
				continue;
			}

			if ($templateid === '') {
				if (!$data_set['hosts'] || !$data_set['x_axis_items'] || !$data_set['y_axis_items']) {
					continue;
				}
			}
			else {
				if (!$data_set['x_axis_items'] || !$data_set['y_axis_items']) {
					continue;
				}
			}

			$data_set['timeshift'] = ($data_set['timeshift'] !== '')
				? (int)timeUnitToSeconds($data_set['timeshift'])
				: 0;

			$items_by_hosts = [];
			$host_colors = [];

			foreach (['x_axis_items', 'y_axis_items'] as $key) {
				$resolve_macros = $templateid === '' || $override_hostid !== '';

				$options = [
					'output' => ['itemid', 'hostid', 'history', 'trends', 'units', 'value_type'],
					'selectHosts' => ['name'],
					'webitems' => true,
					'filter' => [
						'value_type' => [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT]
					],
					'searchWildcardsEnabled' => true,
					'searchByAny' => true,
					'sortfield' => 'name',
					'sortorder' => ZBX_SORT_UP,
					'limit' => $max_metrics
				];

				if ($resolve_macros) {
					$options['output'][] = 'name_resolved';

					if ($templateid === '') {
						$options['search']['name_resolved'] = self::processPattern($data_set[$key]);
					}
					else {
						$options['search']['name'] = self::processPattern($data_set[$key]);
					}
				}
				else {
					$options['output'][] = 'name';
					$options['search']['name'] = self::processPattern($data_set[$key]);
				}

				if ($templateid === '') {
					if ($data_set['override_hostid']) {
						$options['hostids'] = $data_set['override_hostid'];
					}
					else {
						$hosts = API::Host()->get([
							'output' => [],
							'search' => [
								'name' => self::processPattern($data_set['hosts'])
							],
							'searchWildcardsEnabled' => true,
							'searchByAny' => true,
							'preservekeys' => true
						]);

						if ($hosts) {
							$options['hostids'] = array_keys($hosts);
						}
					}
				}
				else {
					$options['hostids'] = $override_hostid !== '' ? $override_hostid : $templateid;
				}

				$items[$key] = [];

				if (array_key_exists('hostids', $options) && $options['hostids']) {
					$items[$key] = API::Item()->get($options);
				}

				if (!$items[$key]) {
					continue;
				}

				if ($resolve_macros) {
					$items[$key] = CArrayHelper::renameObjectsKeys($items[$key], ['name_resolved' => 'name']);
				}

				unset($data_set[$key]);

				$colors = array_key_exists('color', $data_set)
					? CColorPicker::getColorVariations($data_set['color'], count($items[$key]))
					: CColorPicker::getPaletteColors($data_set['color_palette'], count($items[$key]));

				foreach ($items[$key] as $item) {
					if (!array_key_exists($item['hostid'], $host_colors)) {
						$host_colors[$item['hostid']] = array_shift($colors);
					}

					$items_by_hosts[$item['hostid']][$key][] = $item;
				}
			}

			foreach ($items_by_hosts as $hostid => $items_by_host) {
				$data_set['color'] = $host_colors[$hostid];
				$metrics[] = [
					'x_axis_items' => $items_by_host['x_axis_items'],
					'y_axis_items' => $items_by_host['y_axis_items'],
					'data_set' => $index,
					'options' => $data_set
				];
			}

			unset($data_set['x_axis_itemids'], $data_set['y_axis_itemids']);
		}
	}

	private static function getMetricsItems(array &$metrics, array $data_sets, string $templateid,
			string $override_hostid): void {
		$max_metrics = SVG_GRAPH_MAX_NUMBER_OF_METRICS;

		foreach ($data_sets as $index => $data_set) {
			if ($data_set['dataset_type'] == CWidgetFieldDataSet::DATASET_TYPE_PATTERN_ITEM) {
				continue;
			}

			if (!$data_set['x_axis_itemids'] || !$data_set['y_axis_itemids']) {
				continue;
			}

			$dataset_override_hostid = null;

			if ($templateid === '') {
				if ($data_set['override_hostid']) {
					$dataset_override_hostid = $data_set['override_hostid'][0];
				}
			}
			elseif ($override_hostid !== '') {
				$dataset_override_hostid = $override_hostid;
			}

			$result = [];

			$resolve_macros = $templateid === '' || $override_hostid !== '';

			foreach (['x_axis_itemids', 'y_axis_itemids'] as $key) {
				$result[$key] = [];

				if ($dataset_override_hostid !== null) {
					$tmp_items = API::Item()->get([
						'output' => ['key_'],
						'itemids' => $data_set[$key],
						'webitems' => true,
						'preservekeys' => true
					]);

					if ($tmp_items) {
						$keys_index = [];

						foreach ($data_set[$key] as $item_index => $itemid) {
							if (array_key_exists($itemid, $tmp_items)) {
								$keys_index[$tmp_items[$itemid]['key_']] = $item_index;
							}
						}

						$items = API::Item()->get([
							'output' => ['itemid', 'key_'],
							'hostids' => [$dataset_override_hostid],
							'webitems' => true,
							'filter' => [
								'key_' => array_keys($keys_index)
							]
						]);

						if (!$items) {
							continue;
						}

						$data_set[$key] = [];

						foreach ($items as $item) {
							$data_set[$key][$keys_index[$item['key_']]] = $item['itemid'];
						}

						ksort($data_set[$key]);
					}
				}

				$db_items = API::Item()->get([
					'output' => ['itemid', 'hostid', $resolve_macros ? 'name_resolved' : 'name', 'history', 'trends',
						'units', 'value_type'
					],
					'selectHosts' => ['name'],
					'webitems' => true,
					'filter' => [
						'value_type' => [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT]
					],
					'itemids' => $data_set[$key],
					'preservekeys' => true,
					'limit' => $max_metrics
				]);

				if (!$db_items) {
					continue;
				}

				$data_set['timeshift'] = ($data_set['timeshift'] !== '')
					? (int) timeUnitToSeconds($data_set['timeshift'])
					: 0;

				$itemids = $data_set[$key];

				unset($data_set[$key]);

				foreach ($itemids as $itemid) {
					if (array_key_exists($itemid, $db_items)) {
						$item = $resolve_macros
							? CArrayHelper::renameKeys($db_items[$itemid], ['name_resolved' => 'name'])
							: $db_items[$itemid];

						$result[$key][] = $item;
					}
				}
			}

			if ($result['x_axis_itemids'] && $result['y_axis_itemids']) {
				$data_set['color'] = '#'.$data_set['color'];
				$metrics[] = [
					'x_axis_items' => $result['x_axis_itemids'],
					'y_axis_items' => $result['y_axis_itemids'],
					'data_set' => $index,
					'options' => $data_set
				];
			}
		}
	}

	private static function sortByDataset(array &$metrics): void {
		usort($metrics, static function (array $a, array $b): int {
			return $a['data_set'] <=> $b['data_set'];
		});
	}

	/**
	 * Apply static units for each metric if selected.
	 */
	private static function applyUnits(array &$metrics, array $axis_options): void {
		foreach ($metrics as &$metric) {
			if ($axis_options['x_axis_units'] !== null) {
				$metric['units'] = trim(preg_replace('/\s+/', ' ', $axis_options['left_y_units']));
			}

			if ($axis_options['y_axis_units'] !== null) {
				$metric['units'] = trim(preg_replace('/\s+/', ' ', $axis_options['right_y_units']));
			}
		}
		unset($metric);
	}

	/**
	 * Apply time period for each metric.
	 */
	private static function getTimePeriods(array &$metrics, array $options): void {
		foreach ($metrics as &$metric) {
			$metric['time_period'] = $options;

			if ($metric['options']['timeshift'] != 0) {
				$metric['time_period']['time_from'] += $metric['options']['timeshift'];
				$metric['time_period']['time_to'] += $metric['options']['timeshift'];
			}
		}
		unset($metric);
	}

	/**
	 * Calculate what data source must be used for each metric.
	 */
	private static function getGraphDataSource(array &$metrics, array &$errors, int $data_source, int $width): void {
		/**
		 * If data source is not specified, calculate it automatically. Otherwise, set given $data_source to each
		 * $metric.
		 */
		if ($data_source == SVG_GRAPH_DATA_SOURCE_AUTO) {
			/**
			 * First, if global configuration setting "Override item history period" is enabled, override globally
			 * specified "Data storage period" value to each metric custom history storage duration, converting it
			 * to seconds. If "Override item history period" is disabled, item level field 'history' will be used
			 * later, but now we are just storing the field name 'history' in array $to_resolve.
			 *
			 * Do the same with trends.
			 */
			$to_resolve = [];

			if (CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL)) {
				foreach ($metrics as &$metric) {
					foreach (['x_axis_items', 'y_axis_items'] as $key) {
						foreach ($metric[$key] as &$item) {
							if ($item['history'] != 0) {
								$item['history'] = timeUnitToSeconds(
									CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY)
								);
							}
						}
						unset($item);
					}
				}
				unset($metric);
			}
			else {
				$to_resolve[] = 'history';
			}

			if (CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL)) {
				foreach ($metrics as &$metric) {
					foreach (['x_axis_items', 'y_axis_items'] as $key) {
						foreach ($metric[$key] as &$item) {
							if ($item['trends'] != 0) {
								$item['trends'] = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS));
							}
						}
						unset($item);
					}
				}
				unset($metric);
			}
			else {
				$to_resolve[] = 'trends';
			}

			// If no global history and trend override enabled, resolve 'history' and/or 'trends' values for given $metric.
			if ($to_resolve) {
				foreach ($metrics as &$metric) {
					foreach (['x_axis_items', 'y_axis_items'] as $key) {
						foreach ($metric[$key] as &$item) {
							[$item] = CMacrosResolverHelper::resolveTimeUnitMacros([$item], $to_resolve);
						}
						unset($item);
					}
				}
				unset($metric);

				$simple_interval_parser = new CSimpleIntervalParser();

				foreach ($metrics as &$metric) {
					foreach (['x_axis_items', 'y_axis_items'] as $key) {
						foreach ($metric[$key] as $num => &$item) {
							// Convert its values to seconds.
							if (!CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL)) {
								if ($simple_interval_parser->parse($item['history']) != CParser::PARSE_SUCCESS) {
									$errors[] = _s('Incorrect value for field "%1$s": %2$s.', 'history',
										_('invalid history storage period')
									);
									unset($item[$num]);
								}
								else {
									$item['history'] = timeUnitToSeconds($item['history']);
								}
							}

							if (!CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL)) {
								if ($simple_interval_parser->parse($item['trends']) != CParser::PARSE_SUCCESS) {
									$errors[] = _s('Incorrect value for field "%1$s": %2$s.', 'trends',
										_('invalid trend storage period')
									);
									unset($item[$num]);
								}
								else {
									$item['trends'] = timeUnitToSeconds($item['trends']);
								}
							}
						}
						unset($item);
					}
				}
				unset($metric);
			}

			foreach ($metrics as &$metric) {
				foreach (['x_axis_items', 'y_axis_items'] as $key) {
					foreach ($metric[$key] as &$item) {
						/**
						 * History as a data source is used in 2 cases:
						 * 1) if trends are disabled (set to 0) either for particular $metric item or globally;
						 * 2) if period for requested data is newer than the period of keeping history for particular $metric
						 *    item.
						 *
						 * Use trends otherwise.
						 */
						$history = $item['history'];
						$trends = $item['trends'];
						$time_from = $metric['time_period']['time_from'];
						$period = $metric['time_period']['time_to'] - $time_from;

						$item['source'] = ($trends == 0 || (time() - $history < $time_from
								&& $period / $width <= ZBX_MAX_TREND_DIFF / ZBX_GRAPH_MAX_SKIP_CELL))
							? SVG_GRAPH_DATA_SOURCE_HISTORY
							: SVG_GRAPH_DATA_SOURCE_TRENDS;
					}
					unset($item);
				}
			}
		}
		else {
			foreach ($metrics as &$metric) {
				foreach (['x_axis_items', 'y_axis_items'] as $key) {
					foreach ($metric[$key] as &$item) {
						$item['source'] = $data_source;
					}
					unset($item);
				}
			}
		}

		unset($metric);
	}

	/**
	 * Select aggregated data to show in graph for each metric.
	 */
	private static function getMetricsAggregatedData(array &$metrics, int $width, array $thresholds): void {
		foreach ($metrics as &$metric) {
			$aggregation_name = CItemHelper::getAggregateFunctionName($metric['options']['aggregate_function']);

			foreach (['x_axis_items', 'y_axis_items'] as $key) {
				$name = $aggregation_name.'(';

				$count = 0;

				foreach ($metric[$key] as &$item) {
					if ($count > 0) {
						$name .= ', ';
					}

					$name .= $item['hosts'][0]['name'].NAME_DELIMITER.$item['name'];

					$item['source'] = ($item['source'] == SVG_GRAPH_DATA_SOURCE_HISTORY) ? 'history' : 'trends';

					$count++;
				}
				unset($item);

				$name .= ')';
				$metric[$key.'_name'] = $name;
			}
		}
		unset($metric);

		foreach ($metrics as &$metric) {
			$aggregate_interval = timeUnitToSeconds($metric['options']['aggregate_interval'], true);

			$metric['options']['aggregate_interval'] = (int) $aggregate_interval;

			$result['x_axis'] = Manager::History()->getAggregationByInterval(
				$metric['x_axis_items'], $metric['time_period']['time_from'], $metric['time_period']['time_to'],
				$metric['options']['aggregate_function'], $metric['options']['aggregate_interval']
			);

			if (!$result['x_axis']) {
				continue;
			}

			$result['y_axis'] = Manager::History()->getAggregationByInterval(
				$metric['y_axis_items'], $metric['time_period']['time_from'], $metric['time_period']['time_to'],
				$metric['options']['aggregate_function'], $metric['options']['aggregate_interval']
			);

			if (!$result['y_axis']) {
				continue;
			}

			$metric_points = [];

			$period = $metric['time_period']['time_to'] - $metric['time_period']['time_from'];
			$approximation_tick_delta = ($period / $metric['options']['aggregate_interval']) > $width
				? ceil($period / $width)
				: 0;

			foreach (['x_axis', 'y_axis'] as $key) {
				foreach ($result[$key] as $points) {
					$tick = 0;
					usort($points['data'],
						static function (array $point_a, array $point_b): int {
							return $point_a['clock'] <=> $point_b['clock'];
						}
					);

					foreach ($points['data'] as $point) {
						if ($point['tick'] > ($tick + $approximation_tick_delta)) {
							$tick = $point['tick'];
						}

						$metric_points[$tick][$key] = $point;
					}
				}

				switch ($metric['options']['aggregate_function']) {
					case AGGREGATE_MIN:
						foreach ($metric_points as $tick => $points) {
							$metric['points'][$tick][$key] = min(array_column($points, 'value'));
						}
						break;

					case AGGREGATE_MAX:
						foreach ($metric_points as $tick => $points) {
							$metric['points'][$tick][$key] = max(array_column($points, 'value'));
						}
						break;

					case AGGREGATE_AVG:
						foreach ($metric_points as $tick => $points) {
							$value_sum = 0;
							$num_sum = 0;

							foreach ($points as $point) {
								$value_sum += $point['value'] * $point['num'];
								$num_sum += $point['num'];
							}

							$metric['points'][$tick][$key] = $value_sum / $num_sum;
						}
						break;

					case AGGREGATE_COUNT:
					case AGGREGATE_SUM:
						foreach ($metric_points as $tick => $points) {
							$metric['points'][$tick][$key] = array_sum(array_column($points, 'value'));
						}
						break;

					case AGGREGATE_FIRST:
					case AGGREGATE_LAST:
						foreach ($metric_points as $tick => $points) {
							usort($points,
								static fn(array $point_a, array $point_b): int => [$point_a['clock'], $point_a['ns']] <=> [$point_b['clock'], $point_b['ns']]
							);

							$point = $metric['options']['aggregate_function'] == AGGREGATE_FIRST
								? $points[0]
								: $points[count($points) - 1];

							$metric['points'][$tick][$key] = $point['value'];
						}
						break;
				}

				$y_units = '';
				$x_units = '';

				foreach ($metric['y_axis_items'] as $item) {
					$y_units = $item['units'];

					break;
				}

				foreach ($metric['x_axis_items'] as $item) {
					$x_units = $item['units'];

					break;
				}
			}

			foreach ($metric['points'] as $tick => &$point) {
				$point['color'] = $metric['options']['color'];

				if (array_key_exists('x_axis',$point) && array_key_exists('y_axis', $point)) {
					self::calculatePointColorByThresholds($point['color'],
						$point['x_axis'], $point['y_axis'], $x_units, $y_units,
						$thresholds
					);
				}
				else {
					unset($metric['points'][$tick]);
				}
			}
			unset($point);

			ksort($metric['points'], SORT_NUMERIC);

			if (!$metric['points']) {
				unset($metric);
			}
		}
	}

	private static function calculatePointColorByThresholds(string &$color, $value_x, $value_y, string $units_x,
			string $units_y, array $thresholds): void {
		$is_binary_x_units = isBinaryUnits($units_x);
		$is_binary_y_units = isBinaryUnits($units_y);

		foreach ($thresholds as $threshold) {
			if (array_key_exists('x_axis_threshold', $threshold) && array_key_exists('y_axis_threshold', $threshold)) {
				$threshold_value_x = $is_binary_x_units
					? $threshold['x_axis_threshold_binary']
					: $threshold['x_axis_threshold'];

				$threshold_value_y = $is_binary_y_units
					? $threshold['y_axis_threshold_binary']
					: $threshold['y_axis_threshold'];

				if ($value_x > $threshold_value_x && $value_y > $threshold_value_y) {
					$color = $threshold['color'];

					break;
				}
			}
			elseif (array_key_exists('x_axis_threshold', $threshold)) {
				$threshold_value_x = $is_binary_x_units
					? $threshold['x_axis_threshold_binary']
					: $threshold['x_axis_threshold'];

				if ($value_x > $threshold_value_x) {
					$color = $threshold['color'];
				}
			}
			elseif (array_key_exists('y_axis_threshold', $threshold)) {
				$threshold_value_y = $is_binary_y_units
					? $threshold['y_axis_threshold_binary']
					: $threshold['y_axis_threshold'];

				if ($value_y > $threshold_value_y) {
					$color = $threshold['color'];

					break;
				}
			}
		}
	}

	private static function getLegend(array $metrics, array $legend_options): ?CSvgGraphLegend {
		if ($legend_options['show_legend'] != WidgetForm::LEGEND_ON) {
			return null;
		}

		$items = [];

		foreach ($metrics as $metric) {
			$item = [
				'name' => $metric['x_axis_items_name'].', '.$metric['y_axis_items_name'],
				'color' => $metric['options']['color']
			];

			$items[] = $item;
		}

		return (new CSvgGraphLegend($items))
			->setColumnsCount($legend_options['legend_columns'])
			->setLinesCount($legend_options['legend_lines'])
			->setLinesMode($legend_options['legend_lines_mode']);
	}

	/**
	 * Prepare an array to be used for hosts/items filtering.
	 *
	 * @param array $patterns  Array of strings containing hosts/items patterns.
	 *
	 * @return array|mixed  Returns array of patterns.
	 *                      Returns NULL if array contains '*' (so any possible host/item search matches).
	 */
	private static function processPattern(array $patterns): ?array {
		return in_array('*', $patterns, true) ? null : $patterns;
	}
}
