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
	CColorHelper,
	CColorPicker,
	CHousekeepingHelper,
	CItemHelper,
	CMacrosResolverHelper,
	CParser,
	CSimpleIntervalParser,
	Exception,
	Manager;

/**
 * Class calculates graph data and makes Scatter plot graph.
 */
class CScatterPlotHelper {

	/**
	 * Calculate graph data and draw Scatter plot graph based on given graph configuration.
	 *
	 * @param array $options  Options for graph.
	 *                        array   $options['data_sets']           Graph data set options.
	 *                        string  $options['templateid']          Template id used by the graph.
	 *                        string  $options['override_hostid']     Host id used for overriding hosts.
	 *                        int     $options['data_source']         Data source of graph.
	 *                        array   $options['time_period']         Graph time period used.
	 *                        bool    $options['fix_time_period']     Whether to keep time period fixed.
	 *                        array   $options['axes']                Options for graph X and Y axis.
	 *                        array   $options['grouped_thresholds']  Thresholds used by graph to modify the color
	 *                                                                based on the value of the metric.
	 *                        bool    $options['interpolation']       Apply interpolation to threshold color.
	 *                        array   $options['legend']              Options for graph legend.
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
		self::setMetricNames($metrics, $options['legend']);
		self::applyUnits($metrics, $options['axes']);
		// Apply time periods for each $metric, based on graph/dashboard time as well as metric level time shifts.
		self::getTimePeriods($metrics, $options['time_period']);
		// Find what data source (history or trends) will be used for each metric.
		self::getGraphDataSource($metrics, $errors, $options['data_source'], $width);
		// Load aggregated Data for each dataset.
		self::getMetricsAggregatedData($metrics, $options['grouped_thresholds'], $options['interpolation']);

		$legend = self::getLegend($metrics, $options['legend']);

		$svg_height = max(0, $height - ($legend !== null ? $legend->getHeight() : 0));

		$scatter_plot = (new CScatterPlot([
			'time_period' => $options['time_period'],
			'axes' => $options['axes']
		]))
			->setSize($width, $svg_height)
			->addMetrics($metrics)
			->draw();

		// Add mouse following helper line.
		$scatter_plot->addHelper();

		return [
			'svg' => $scatter_plot,
			'legend' => $legend ?? '',
			'data' => [
				'dims' => [
					'x' => $scatter_plot->getCanvasX(),
					'y' => $scatter_plot->getCanvasY(),
					'w' => $scatter_plot->getCanvasWidth(),
					'h' => $scatter_plot->getCanvasHeight()
				],
				'spp' => $scatter_plot->getCanvasWidth() === 0
					? 0
					: ($options['time_period']['time_to'] - $options['time_period']['time_from'])
					/ $scatter_plot->getCanvasWidth()
			],
			'errors' => $errors
		];
	}

	private static function getMetricsPattern(array &$metrics, array $data_sets, string $templateid,
		string $override_hostid): void {
		$max_metrics = [
			'x_axis_items' => SVG_GRAPH_MAX_NUMBER_OF_METRICS,
			'y_axis_items' => SVG_GRAPH_MAX_NUMBER_OF_METRICS
		];

		foreach ($data_sets as $index => $data_set) {
			if ($max_metrics['x_axis_items'] <= 0 || $max_metrics['y_axis_items'] <= 0) {
				break;
			}

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

			$resolve_macros = $templateid === '' || $override_hostid !== '';

			foreach (['x_axis_items', 'y_axis_items'] as $axis) {
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
					'limit' => $max_metrics[$axis]
				];

				if ($resolve_macros) {
					$options['output'][] = 'name_resolved';

					if ($templateid === '') {
						$options['search']['name_resolved'] = self::processPattern($data_set[$axis]);
					}
					else {
						$options['search']['name'] = self::processPattern($data_set[$axis]);
					}
				}
				else {
					$options['output'][] = 'name';
					$options['search']['name'] = self::processPattern($data_set[$axis]);
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
							'evaltype' => $data_set['host_tags_evaltype'],
							'tags' => $data_set['host_tags'] ?: null,
							'groupids' => $data_set['hostgroupids'] ?: null,
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

				$items[$axis] = [];

				if (array_key_exists('hostids', $options) && $options['hostids']) {
					$items[$axis] = API::Item()->get($options);
				}

				if (!$items[$axis]) {
					continue;
				}

				if ($resolve_macros) {
					$items[$axis] = CArrayHelper::renameObjectsKeys($items[$axis], ['name_resolved' => 'name']);
				}

				unset($data_set[$axis]);

				foreach ($items[$axis] as $item) {
					$items_by_hosts[$item['hostid']][$axis][] = $item;

					$max_metrics[$axis] --;
				}
			}

			$items_by_hosts = array_filter($items_by_hosts,
				static fn ($h) => array_key_exists('x_axis_items', $h) && array_key_exists('y_axis_items', $h)
			);

			$colors = array_key_exists('color', $data_set)
				? CColorPicker::getColorVariations($data_set['color'], count($items_by_hosts))
				: CColorPicker::getPaletteColors($data_set['color_palette'], count($items_by_hosts));

			$data_set = array_diff_key($data_set, array_flip(['x_axis_itemids, y_axis_itemids', 'x_axis_references',
				'y_axis_references', 'color_palette'
			]));

			foreach ($items_by_hosts as $items_by_host) {
				$data_set['color'] = array_shift($colors);
				$metrics[] = [
					'x_axis_items' => $items_by_host['x_axis_items'],
					'y_axis_items' => $items_by_host['y_axis_items'],
					'data_set' => $index,
					'options' => $data_set
				];
			}
		}
	}

	private static function getMetricsItems(array &$metrics, array $data_sets, string $templateid,
			string $override_hostid): void {
		$max_metrics = [
			'x_axis_itemids' => SVG_GRAPH_MAX_NUMBER_OF_METRICS,
			'y_axis_itemids' => SVG_GRAPH_MAX_NUMBER_OF_METRICS
		];

		foreach ($data_sets as $index => $data_set) {
			if ($max_metrics['x_axis_itemids'] <= 0 || $max_metrics['y_axis_itemids'] <= 0) {
				break;
			}

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

			foreach (['x_axis_itemids', 'y_axis_itemids'] as $axis) {
				$result[$axis] = [];

				if ($dataset_override_hostid !== null) {
					$tmp_items = API::Item()->get([
						'output' => ['key_'],
						'itemids' => $data_set[$axis],
						'webitems' => true,
						'preservekeys' => true
					]);

					if ($tmp_items) {
						$keys_index = [];

						foreach ($data_set[$axis] as $item_index => $itemid) {
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

						$data_set[$axis] = [];

						foreach ($items as $item) {
							$data_set[$axis][$keys_index[$item['key_']]] = $item['itemid'];
						}

						ksort($data_set[$axis]);
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
					'itemids' => $data_set[$axis],
					'preservekeys' => true,
					'limit' => $max_metrics[$axis]
				]);

				if (!$db_items) {
					continue;
				}

				$data_set['timeshift'] = ($data_set['timeshift'] !== '')
					? (int) timeUnitToSeconds($data_set['timeshift'])
					: 0;

				$itemids = $data_set[$axis];

				unset($data_set[$axis]);

				foreach ($itemids as $itemid) {
					if (array_key_exists($itemid, $db_items)) {
						$item = $resolve_macros
							? CArrayHelper::renameKeys($db_items[$itemid], ['name_resolved' => 'name'])
							: $db_items[$itemid];

						$result[$axis][] = $item;

						$max_metrics[$axis]--;
					}
				}
			}

			$data_set = array_diff_key($data_set, array_flip(['x_axis_items', 'y_axis_items', 'x_axis_references',
				'y_axis_references', 'hostgroupids', 'hosts', 'host_tags', 'host_tags_evaltype', 'color_palette'
			]));

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
			$metric['x_units'] = $axis_options['x_axis_units'] !== null
				? trim(preg_replace('/\s+/', ' ', $axis_options['x_axis_units']))
				: reset($metric['x_axis_items'])['units'];

			$metric['y_units'] = $axis_options['y_axis_units'] !== null
				? trim(preg_replace('/\s+/', ' ', $axis_options['y_axis_units']))
				: reset($metric['y_axis_items'])['units'];
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
					foreach (['x_axis_items', 'y_axis_items'] as $axis) {
						foreach ($metric[$axis] as &$item) {
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
					foreach (['x_axis_items', 'y_axis_items'] as $axis) {
						foreach ($metric[$axis] as &$item) {
							if ($item['trends'] != 0) {
								$item['trends'] = timeUnitToSeconds(
									CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS)
								);
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

			// If no global history and trend override enabled, resolve 'history' and/or 'trends' values for given
			// $metric.
			if ($to_resolve) {
				$simple_interval_parser = new CSimpleIntervalParser();

				foreach ($metrics as &$metric) {
					foreach (['x_axis_items', 'y_axis_items'] as $axis) {
						foreach ($metric[$axis] as $num => &$item) {
							[$item] = CMacrosResolverHelper::resolveTimeUnitMacros([$item], $to_resolve);

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
				foreach (['x_axis_items', 'y_axis_items'] as $axis) {
					foreach ($metric[$axis] as &$item) {
						/**
						 * History as a data source is used in 2 cases:
						 * 1) if trends are disabled (set to 0) either for particular $metric item or globally;
						 * 2) if period for requested data is newer than the period of keeping history for particular
						 * $metric item.
						 *
						 * Use trends otherwise.
						 */
						$history = $item['history'];
						$trends = $item['trends'];
						$time_from = $metric['time_period']['time_from'];
						$period = $metric['time_period']['time_to'] - $time_from;

						$item['source'] = ($trends == 0 || (time() - $history < $time_from
								&& $period / $width <= ZBX_MAX_TREND_DIFF / ZBX_GRAPH_MAX_SKIP_CELL))
							? 'history'
							: 'trends';
					}
					unset($item);
				}
			}
			unset($metric);
		}
		else {
			foreach ($metrics as &$metric) {
				foreach (['x_axis_items', 'y_axis_items'] as $axis) {
					foreach ($metric[$axis] as &$item) {
						$item['source'] = $data_source == SVG_GRAPH_DATA_SOURCE_HISTORY ? 'history' : 'trends';
					}
					unset($item);
				}
			}
		}

		unset($metric);
	}

	/**
	 * Set metric names from X axis and Y axis items and aggregation function used.
	 */
	private static function setMetricNames(array &$metrics, array $legend_options): void {
		foreach ($metrics as &$metric) {
			$aggregation_name = $legend_options['show_aggregation']
				? CItemHelper::getAggregateFunctionName($metric['options']['aggregate_function']).'('
				: '';

			foreach (['x_axis_items', 'y_axis_items'] as $axis) {
				$name = $aggregation_name;

				$count = 0;

				foreach ($metric[$axis] as $item) {
					if ($count > 0) {
						$name .= ', ';
					}

					$name .= $item['hosts'][0]['name'].NAME_DELIMITER.$item['name'];

					$count++;
				}

				if ($legend_options['show_aggregation']) {
					$name .= ')';
				}
				elseif ($count > 1) {
					$name = '('.$name.')';
				}

				$metric[$axis.'_name'] = $name;
			}
		}
		unset($metric);
	}

	/**
	 * Select aggregated data to show in scatter plot for each metric and adjust color base on the thresholds.
	 */
	private static function getMetricsAggregatedData(array &$metrics, array $grouped_thresholds,
			bool $interpolation): void {
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

			foreach (['x_axis', 'y_axis'] as $axis) {
				$axis_points = [];

				foreach ($result[$axis] as $points) {
					usort($points['data'],
						static function (array $point_a, array $point_b): int {
							return $point_a['clock'] <=> $point_b['clock'];
						}
					);

					foreach ($points['data'] as $point) {
						$axis_points[$point['tick']][] = $point;
					}
				}

				switch ($metric['options']['aggregate_function']) {
					case AGGREGATE_MIN:
						foreach ($axis_points as $tick => $points) {
							$metric_points[$tick][$axis] = min(array_column($points, 'value'));
						}
						break;

					case AGGREGATE_MAX:
						foreach ($axis_points as $tick => $points) {
							$metric_points[$tick][$axis] = max(array_column($points, 'value'));
						}
						break;

					case AGGREGATE_AVG:
						foreach ($axis_points as $tick => $points) {
							$value_sum = 0;
							$num_sum = 0;

							foreach ($points as $point) {
								$value_sum += $point['value'] * $point['num'];
								$num_sum += $point['num'];
							}

							$metric_points[$tick][$axis] = $value_sum / $num_sum;
						}
						break;

					case AGGREGATE_COUNT:
					case AGGREGATE_SUM:
						foreach ($axis_points as $tick => $points) {
							$value = array_sum(array_column($points, 'value'));

							if ($metric['options']['aggregate_function'] == AGGREGATE_SUM || $value !== 0) {
								$metric_points[$tick][$axis] = $value;
							}
						}
						break;

					case AGGREGATE_FIRST:
					case AGGREGATE_LAST:
						foreach ($axis_points as $tick => $points) {
							usort($points, static fn(array $point_a, array $point_b): int =>
								[$point_a['clock'], $point_a['ns']] <=> [$point_b['clock'], $point_b['ns']]);

							$point = $metric['options']['aggregate_function'] == AGGREGATE_FIRST
								? $points[0]
								: $points[count($points) - 1];

							$metric_points[$tick][$axis] = $point['value'];
						}
						break;
				}
			}

			$metric_points = array_filter($metric_points,
				static fn ($point) => array_key_exists('x_axis', $point) && array_key_exists('y_axis', $point)
			);

			if (!$metric_points) {
				continue;
			}

			foreach ($metric_points as &$point) {
				$point['color'] = self::calculatePointColorByThresholds($metric['options']['color'],
					$point['x_axis'], $point['y_axis'], $metric['x_units'], $metric['y_units'], $grouped_thresholds,
					$interpolation
				);
			}
			unset($point);

			ksort($metric_points, SORT_NUMERIC);

			$metric['points'] = $metric_points;
		}
		unset($metric);
	}

	private static function calculatePointColorByThresholds(string $color, $value_x, $value_y, string $units_x,
			string $units_y, array $grouped_thresholds, bool $apply_interpolation): string {
		if (!$grouped_thresholds) {
			return $color;
		}

		$is_binary_x_units = isBinaryUnits($units_x);
		$is_binary_y_units = isBinaryUnits($units_y);

		$x_key = $is_binary_x_units ? 'x_binary' : 'x';
		$y_key = $is_binary_y_units ? 'y_binary' : 'y';

		$prev = null;
		$current = null;

		foreach ($grouped_thresholds as $group => $thresholds) {
			if ($prev !== null) {
				break;
			}

			foreach ($thresholds as $threshold) {
				$current = $threshold;

				if ($group === 'both') {
					if ($value_x < $threshold[$x_key] || $value_y < $threshold[$y_key]) {
						break;
					}
				}
				elseif ($group === 'only_x') {
					if ($value_x < $threshold[$x_key]) {
						break;
					}
				}
				elseif ($group === 'only_y') {
					if ($value_y < $threshold[$y_key]) {
						break;
					}
				}

				$prev = $threshold;
			}
		}

		if ($prev === null) {
			return $color;
		}

		if ($apply_interpolation) {
			$prev_threshold = [
				'x' => array_key_exists($x_key, $prev) ? $prev[$x_key] : $value_x,
				'y' => array_key_exists($y_key, $prev) ? $prev[$y_key] : $value_y
			];

			$current_threshold = [
				'x' => array_key_exists($x_key, $current) ? $current[$x_key] : $value_x,
				'y' => array_key_exists($y_key, $current) ? $current[$y_key] : $value_y
			];

			$x = min($current_threshold['x'], $value_x);
			$y = min($current_threshold['y'], $value_y);

			$position = self::calculateInterpolationPosition($prev_threshold, $current_threshold, $x, $y);

			return CColorHelper::interpolateColor($prev['color'], $current['color'], $position);
		}

		return $prev['color'];
	}

	private static function calculateInterpolationPosition(array $threshold_1, array $threshold_2, float $x,
			float $y): float {
		// Vector from threshold_1 to threshold_2.
		$vx = $threshold_2['x'] - $threshold_1['x'];
		$vy = $threshold_2['y'] - $threshold_1['y'];

		// Vector from threshold_1 to current point.
		$wx = $x - $threshold_1['x'];
		$wy = $y - $threshold_1['y'];

		// Dot product and magnitude squared of v.
		$dot_product = $wx * $vx + $wy * $vy;
		$magnitude_square = $vx * $vx + $vy * $vy;

		if ($magnitude_square == 0) {
			return 0.0;
		}

		// Projection factor = (w·v) / |v|^2.
		return $dot_product / $magnitude_square;
	}

	private static function getLegend(array $metrics, array $legend_options): ?CScatterPlotLegend {
		if ($legend_options['show_legend'] != WidgetForm::LEGEND_ON) {
			return null;
		}

		$items = [];

		foreach ($metrics as $metric) {
			$item = [
				'name' => $metric['x_axis_items_name'].', '.$metric['y_axis_items_name'],
				'color' => $metric['options']['color'],
				'marker' => $metric['options']['marker']
			];

			$items[] = $item;
		}

		return (new CScatterPlotLegend($items))
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
