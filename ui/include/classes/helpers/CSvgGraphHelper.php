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
 * Class calculates graph data and makes SVG graph.
 */
class CSvgGraphHelper {

	/**
	 * Calculate graph data and draw SVG graph based on given graph configuration.
	 *
	 * @param array  $options                     Options for graph.
	 * @param array  $options['data_sets']        Graph data set options.
	 * @param int    $options['data_source']      Data source of graph.
	 * @param bool   $options['dashboard_time']   True if dashboard time is used.
	 * @param array  $options['time_period']      Graph time period used.
	 * @param array  $options['left_y_axis']      Options for graph left Y axis.
	 * @param array  $options['right_y_axis']     Options for graph right Y axis.
	 * @param array  $options['x_axis']           Options for graph X axis.
	 * @param array  $options['legend']           Options for graph legend.
	 * @param int    $options['legend_lines']     Number of lines in the legend.
	 * @param array  $options['problems']         Graph problems options.
	 * @param array  $options['overrides']        Graph override options.
	 *
	 * @return array
	 */
	public static function get(array $options, $width, $height) {
		$metrics = [];
		$errors = [];

		// Find which metrics will be shown in graph and calculate time periods and display options.
		self::getMetrics($metrics, $options['data_sets']);
		// Apply overrides for previously selected $metrics.
		self::applyOverrides($metrics, $options['overrides']);
		// Apply time periods for each $metric, based on graph/dashboard time as well as metric level timeshifts.
		self::getTimePeriods($metrics, $options['time_period']);
		// Find what data source (history or trends) will be used for each metric.
		self::getGraphDataSource($metrics, $errors, $options['data_source'], $width);
		// Load Data for each metric.
		self::getMetricsData($metrics, $width);
		// Load aggregated Data for each dataset.
		self::getMetricsAggregatedData($metrics);

		// Legend single line height is 18. Value should be synchronized with $svg-legend-line-height in 'screen.scss'.
		$legend_height = ($options['legend'] == SVG_GRAPH_LEGEND_TYPE_SHORT) ? $options['legend_lines'] * 18 : 0;

		// Draw SVG graph.
		$graph = (new CSvgGraph([
			'time_period' => $options['time_period'],
			'x_axis' => $options['x_axis'],
			'left_y_axis' => $options['left_y_axis'],
			'right_y_axis' => $options['right_y_axis']
		]))
			->setSize($width, $height - $legend_height)
			->addMetrics($metrics);

		// Get problems to display in graph.
		if ($options['problems']['show_problems'] == SVG_GRAPH_PROBLEMS_SHOW) {
			$options['problems']['itemids'] =
				($options['problems']['graph_item_problems'] == SVG_GRAPH_SELECTED_ITEM_PROBLEMS)
					? array_unique(zbx_objectValues($metrics, 'itemid'))
					: null;

			$problems = self::getProblems($options['problems'], $options['time_period']);
			$graph->addProblems($problems);
		}

		if ($legend_height > 0) {
			$labels = [];

			foreach ($metrics as $metric) {
				$labels[] = [
					'name' => $metric['name'],
					'color' => $metric['options']['color']
				];
			}

			$legend = (new CSvgGraphLegend($labels))
				->setAttribute('style', 'height: '.$legend_height.'px')
				->toString();
		}
		else {
			$legend = '';
		}

		// Draw graph.
		$graph->draw();

		// SBox available only for graphs without overriten relative time.
		if ($options['dashboard_time']) {
			$graph->addSBox();
		}

		// Add mouse following helper line.
		$graph->addHelper();

		return [
			'svg' => $graph,
			'legend' => $legend,
			'data' => [
				'dims' => [
					'x' => $graph->getCanvasX(),
					'y' => $graph->getCanvasY(),
					'w' => $graph->getCanvasWidth(),
					'h' => $graph->getCanvasHeight()
				],
				'spp' => (int) ($options['time_period']['time_to'] - $options['time_period']['time_from']) / $graph->getCanvasWidth()
			],
			'errors' => $errors
		];
	}

	/**
	 * Select aggregated data to show in graph for each metric.
	 */
	protected static function getMetricsAggregatedData(array &$metrics = []) {
		$dataset_metrics = [];

		foreach ($metrics as $metric_num => &$metric) {
			if ($metric['options']['aggregate_function'] == AGGREGATE_NONE) {
				continue;
			}

			$dataset_num = $metric['data_set'];

			if ($metric['options']['aggregate_grouping'] == GRAPH_AGGREGATE_BY_ITEM) {
				$name = $metric['hosts'][0]['name'].NAME_DELIMITER.$metric['name'];
			}
			else {
				$name = 'Dataset #'.($dataset_num + 1);
			}

			$item = [
				'itemid' => $metric['itemid'],
				'value_type' => $metric['value_type'],
				'source' => ($metric['source'] == SVG_GRAPH_DATA_SOURCE_HISTORY) ? 'history' : 'trends'
			];

			if (!array_key_exists($dataset_num, $dataset_metrics)) {
				$metric = array_merge($metric, [
					'name' => graph_item_aggr_fnc2str($metric['options']['aggregate_function']).'('.$name.')',
					'items' => [],
					'points' => []
				]);
				$metric['options']['aggregate_interval'] =
					(int) timeUnitToSeconds($metric['options']['aggregate_interval'], true);

				if ($metric['options']['aggregate_grouping'] == GRAPH_AGGREGATE_BY_DATASET) {
					$dataset_metrics[$dataset_num] = $metric_num;
				}

				$metric['items'][] = $item;
			}
			else {
				$metrics[$dataset_metrics[$dataset_num]]['items'][] = $item;
				unset($metrics[$metric_num]);
			}
		}
		unset($metric);

		foreach ($metrics as &$metric) {
			if ($metric['options']['aggregate_function'] == AGGREGATE_NONE) {
				continue;
			}

			$result = Manager::History()->getAggregationByInterval(
				$metric['items'], $metric['time_period']['time_from'], $metric['time_period']['time_to'],
				$metric['options']['aggregate_function'], $metric['options']['aggregate_interval']
			);

			$metric_points = [];

			if ($result) {
				foreach ($result as $itemid => $points) {
					foreach ($points['data'] as $point) {
						$metric_points[$point['tick']]['itemid'][] = $point['itemid'];
						$metric_points[$point['tick']]['clock'][] = $point['clock'];

						if (array_key_exists('count', $point)) {
							$metric_points[$point['tick']]['count'][] = $point['count'];
						}
						if (array_key_exists('value', $point)) {
							$metric_points[$point['tick']]['value'][] = $point['value'];
						}
					}
				}
				ksort($metric_points, SORT_NUMERIC);

				switch ($metric['options']['aggregate_function']) {
					case AGGREGATE_MIN:
						foreach ($metric_points as $tick => $point) {
							$metric['points'][] = ['clock' => $tick, 'value' => min($point['value'])];
						}
						break;
					case AGGREGATE_MAX:
						foreach ($metric_points as $tick => $point) {
							$metric['points'][] = ['clock' => $tick, 'value' => max($point['value'])];
						}
						break;
					case AGGREGATE_AVG:
						foreach ($metric_points as $tick => $point) {
							$metric['points'][] = [
								'clock' => $tick,
								'value' => CMathHelper::safeAvg($point['value'])
							];
						}
						break;
					case AGGREGATE_COUNT:
						foreach ($metric_points as $tick => $point) {
							$metric['points'][] = ['clock' => $tick, 'value' => array_sum($point['count'])];
						}
						break;
					case AGGREGATE_SUM:
						foreach ($metric_points as $tick => $point) {
							$metric['points'][] = ['clock' => $tick, 'value' => array_sum($point['value'])];
						}
						break;
					case AGGREGATE_FIRST:
						foreach ($metric_points as $tick => $point) {
							if ($point['clock']) {
								$metric['points'][] = [
									'clock' => $tick,
									'value' => $point['value'][array_search(min($point['clock']), $point['clock'])]
								];
							}
						}
						break;
					case AGGREGATE_LAST:
						foreach ($metric_points as $tick => $point) {
							if ($point['clock']) {
								$metric['points'][] = [
									'clock' => $tick,
									'value' => $point['value'][array_search(max($point['clock']), $point['clock'])]
								];
							}
						}
						break;
				}
			}
		}
	}

	/**
	 * Select data to show in graph for each metric.
	 */
	protected static function getMetricsData(array &$metrics, $width) {
		// To reduce number of requests, group metrics by time range.
		$tr_groups = [];
		foreach ($metrics as $metric_num => &$metric) {
			if ($metric['options']['aggregate_function'] != AGGREGATE_NONE) {
				continue;
			}

			$metric['name'] = $metric['hosts'][0]['name'].NAME_DELIMITER.$metric['name'];
			$metric['points'] = [];

			$key = $metric['time_period']['time_from'].$metric['time_period']['time_to'];
			if (!array_key_exists($key, $tr_groups)) {
				$tr_groups[$key] = [
					'time' => [
						'from' => $metric['time_period']['time_from'],
						'to' => $metric['time_period']['time_to']
					]
				];
			}

			$tr_groups[$key]['items'][$metric_num] = [
				'itemid' => $metric['itemid'],
				'value_type' => $metric['value_type'],
				'source' => ($metric['source'] == SVG_GRAPH_DATA_SOURCE_HISTORY) ? 'history' : 'trends'
			];
		}
		unset($metric);

		// Request data.
		foreach ($tr_groups as $tr_group) {
			$results = Manager::History()->getGraphAggregationByWidth($tr_group['items'], $tr_group['time']['from'],
				$tr_group['time']['to'], $width
			);

			if ($results) {
				foreach ($tr_group['items'] as $metric_num => $item) {
					$metric = &$metrics[$metric_num];

					// Collect and sort data points.
					if (array_key_exists($item['itemid'], $results)) {
						$points = [];
						foreach ($results[$item['itemid']]['data'] as $point) {
							$points[] = ['clock' => $point['clock'], 'value' => $point['avg']];
						}
						usort($points, [__CLASS__, 'sortByClock']);
						$metric['points'] = $points;

						unset($metric['source'], $metric['history'], $metric['trends']);
					}
				}
				unset($metric);
			}
		}
	}

	/**
	 * Calculate what data source must be used for each metric.
	 */
	protected static function getGraphDataSource(array &$metrics, array &$errors, $data_source, $width) {
		/**
		 * If data source is not specified, calculate it automatically. Otherwise, set given $data_source to each
		 * $metric.
		 */
		if ($data_source == SVG_GRAPH_DATA_SOURCE_AUTO) {
			/**
			 * First, if global configuration setting "Override item history period" is enabled, override globally
			 * specified "Data storage period" value to each metric's custom history storage duration, converting it
			 * to seconds. If "Override item history period" is disabled, item level field 'history' will be used
			 * later but now we are just storing the field name 'history' in array $to_resolve.
			 *
			 * Do the same with trends.
			 */
			$to_resolve = [];

			if (CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL)) {
				foreach ($metrics as &$metric) {
					$metric['history'] = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY));
				}
				unset($metric);
			}
			else {
				$to_resolve[] = 'history';
			}

			if (CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL)) {
				foreach ($metrics as &$metric) {
					$metric['trends'] = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS));
				}
				unset($metric);
			}
			else {
				$to_resolve[] = 'trends';
			}

			// If no global history and trend override enabled, resolve 'history' and/or 'trends' values for given $metric.
			if ($to_resolve) {
				$metrics = CMacrosResolverHelper::resolveTimeUnitMacros($metrics, $to_resolve);
				$simple_interval_parser = new CSimpleIntervalParser();

				foreach ($metrics as $num => &$metric) {
					// Convert its values to seconds.
					if (!CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL)) {
						if ($simple_interval_parser->parse($metric['history']) != CParser::PARSE_SUCCESS) {
							$errors[] = _s('Incorrect value for field "%1$s": %2$s.', 'history',
								_('invalid history storage period')
							);
							unset($metrics[$num]);
						}
						else {
							$metric['history'] = timeUnitToSeconds($metric['history']);
						}
					}

					if (!CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL)) {
						if ($simple_interval_parser->parse($metric['trends']) != CParser::PARSE_SUCCESS) {
							$errors[] = _s('Incorrect value for field "%1$s": %2$s.', 'trends',
								_('invalid trend storage period')
							);
							unset($metrics[$num]);
						}
						else {
							$metric['trends'] = timeUnitToSeconds($metric['trends']);
						}
					}
				}
				unset($metric);
			}

			foreach ($metrics as &$metric) {
				/**
				 * History as a data source is used in 2 cases:
				 * 1) if trends are disabled (set to 0) either for particular $metric item or globally;
				 * 2) if period for requested data is newer than the period of keeping history for particular $metric
				 *	  item.
				 *
				 * Use trends otherwise.
				 */
				$history = $metric['history'];
				$trends = $metric['trends'];
				$time_from = $metric['time_period']['time_from'];
				$period = $metric['time_period']['time_to'] - $time_from;

				$metric['source'] = ($trends == 0 || (time() - $history < $time_from
						&& $period / $width <= ZBX_MAX_TREND_DIFF / ZBX_GRAPH_MAX_SKIP_CELL))
					? SVG_GRAPH_DATA_SOURCE_HISTORY
					: SVG_GRAPH_DATA_SOURCE_TRENDS;
			}
			unset($metric);
		}
		else {
			foreach ($metrics as &$metric) {
				$metric['source'] = $data_source;
			}
			unset($metric);
		}
	}

	/**
	 * Find problems at given time period that matches specified problem options.
	 */
	protected static function getProblems(array $problem_options, array $time_period) {
		$options = [
			'output' => ['objectid', 'name', 'severity', 'clock', 'r_eventid'],
			'select_acknowledges' => ['action'],
			'problem_time_from' => $time_period['time_from'],
			'problem_time_till' => $time_period['time_to'],
			'preservekeys' => true
		];

		// Find triggers involved.
		if ($problem_options['problemhosts'] !== '') {
			$options['hostids'] = array_keys(API::Host()->get([
				'output' => [],
				'searchWildcardsEnabled' => true,
				'searchByAny' => true,
				'search' => [
					'name' => self::processPattern($problem_options['problemhosts'])
				],
				'preservekeys' => true
			]));

			// Return if no hosts found.
			if (!$options['hostids']) {
				return [];
			}
		}

		$options['objectids'] = array_keys(API::Trigger()->get([
			'output' => [],
			'hostids' => array_key_exists('hostids', $options) ? $options['hostids'] : null,
			'itemids' => $problem_options['itemids'],
			'monitored' => true,
			'preservekeys' => true
		]));

		unset($options['hostids']);

		// Return if no triggers found.
		if (!$options['objectids']) {
			return [];
		}

		// Add severity filter.
		$filter_severities = implode(',', $problem_options['severities']);
		$all_severities = implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1));

		if ($filter_severities !== '' && $filter_severities !== $all_severities) {
			$options['severities'] = $problem_options['severities'];
		}

		// Add problem name filter.
		if ($problem_options['problem_name'] !== '') {
			$options['searchWildcardsEnabled'] = true;
			$options['search']['name'] = $problem_options['problem_name'];
		}

		// Add tags filter.
		if ($problem_options['tags']) {
			$options['evaltype'] = $problem_options['evaltype'];
			$options['tags'] = $problem_options['tags'];
		}

		$events = API::Event()->get($options);

		// Find end time for each problem.
		if ($events) {
			$r_events = API::Event()->get([
				'output' => ['clock'],
				'eventids' => zbx_objectValues($events, 'r_eventid'),
				'preservekeys' => true
			]);
		}

		// Add recovery times for each problem event.
		foreach ($events as &$event) {
			$event['r_clock'] = array_key_exists($event['r_eventid'], $r_events)
				? $r_events[$event['r_eventid']]['clock']
				: 0;
		}
		unset($event);

		CArrayHelper::sort($events, [['field' => 'clock', 'order' => ZBX_SORT_DOWN]]);

		return $events;
	}

	/**
	 * Select metrics from given data set options. Apply data set options to each selected metric.
	 */
	protected static function getMetrics(array &$metrics, array $data_sets) {
		$max_metrics = SVG_GRAPH_MAX_NUMBER_OF_METRICS;

		foreach ($data_sets as $index => $data_set) {
			if (!$data_set['hosts'] || !$data_set['items']) {
				continue;
			}

			if ($max_metrics == 0) {
				break;
			}

			// Find hosts.
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
				$items = API::Item()->get([
					'output' => [
						'itemid', 'name', 'history', 'trends', 'units', 'value_type'],
					'selectHosts' => ['name'],
					'hostids' => array_keys($hosts),
					'webitems' => true,
					'filter' => [
						'value_type' => [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT]
					],
					'search' => [
						'name' => self::processPattern($data_set['items'])
					],
					'searchWildcardsEnabled' => true,
					'searchByAny' => true,
					'sortfield' => 'name',
					'sortorder' => ZBX_SORT_UP,
					'limit' => $max_metrics
				]);

				if (!$items) {
					continue;
				}

				unset($data_set['hosts'], $data_set['items']);

				// The bigger transparency level, the less visible the metric is.
				$data_set['transparency'] = 10 - (int) $data_set['transparency'];

				$data_set['timeshift'] = ($data_set['timeshift'] !== '')
					? (int) timeUnitToSeconds($data_set['timeshift'])
					: 0;

				$colors = getColorVariations('#'.$data_set['color'], count($items));

				foreach ($items as $item) {
					$data_set['color'] = array_shift($colors);
					$metrics[] = $item + ['data_set' => $index, 'options' => $data_set];
					$max_metrics--;
				}
			}
		}
	}

	/**
	 * Apply overrides for each pattern matchig metric.
	 */
	protected static function applyOverrides(array &$metrics = [], array $overrides = []) {
		foreach ($overrides as $override) {
			if ($override['hosts'] === '' || $override['items'] === '') {
				continue;
			}

			// Convert timeshift to seconds.
			if (array_key_exists('timeshift', $override)) {
				$override['timeshift'] = ($override['timeshift'] !== '')
					? (int) timeUnitToSeconds($override['timeshift'])
					: 0;
			}

			$hosts_patterns = self::processPattern($override['hosts']);
			$items_patterns = self::processPattern($override['items']);

			unset($override['hosts'], $override['items']);

			$metrics_matched = [];

			foreach ($metrics as $metric_num => $metric) {
				// If '*' used, apply options to all metrics.
				$host_matches = ($hosts_patterns === null);
				$item_matches = ($items_patterns === null);

				/**
				 * Find if host and item names matches one of given patterns.
				 *
				 * It currently checks if at least one of host pattern and at least one of item pattern matches,
				 * without checking relation between matching host and item.
				 */
				if ($hosts_patterns !== null) {
					for ($hosts_pattern = reset($hosts_patterns); !$host_matches && $hosts_pattern !== false;
							$hosts_pattern = next($hosts_patterns)) {
						$pattern = '/^'.str_replace('\*', '.*', preg_quote($hosts_pattern, '/')).'$/i';
						$host_matches = (bool) preg_match($pattern, $metric['hosts'][0]['name']);
					}
				}

				if ($items_patterns !== null && $host_matches) {
					for ($items_pattern = reset($items_patterns); !$item_matches && $items_pattern !== false;
							$items_pattern = next($items_patterns)) {
						$pattern = '/^'.str_replace('\*', '.*', preg_quote($items_pattern, '/')).'$/i';
						$item_matches = (bool) preg_match($pattern, $metric['name']);
					}
				}

				/**
				 * We need to know total amount of matched metrics to calculate variations of colors. That's why we
				 * first collect matching metrics and than override existing metric options.
				 */
				if ($host_matches && $item_matches) {
					$metrics_matched[] = $metric_num;
				}
			}

			// Apply override options to matching metrics.
			if ($metrics_matched) {
				$colors = (array_key_exists('color', $override) && $override['color'] !== '')
					? getColorVariations('#'.$override['color'], count($metrics_matched))
					: null;

				if (array_key_exists('transparency', $override)) {
					// The bigger transparency level, the less visible the metric is.
					$override['transparency'] = 10 - (int) $override['transparency'];
				}

				foreach ($metrics_matched as $metric_num) {
					$metric = &$metrics[$metric_num];

					if ($colors !== null) {
						$override['color'] = array_shift($colors);
					}
					$metric['options'] = $override + $metric['options'];

					// Fix missing options if draw type has changed.
					switch ($metric['options']['type']) {
						case SVG_GRAPH_TYPE_POINTS:
							if (!array_key_exists('pointsize', $metric['options'])) {
								$metric['options']['pointsize'] = SVG_GRAPH_DEFAULT_POINTSIZE;
							}
							break;

						case SVG_GRAPH_TYPE_LINE:
						case SVG_GRAPH_TYPE_STAIRCASE:
							if (!array_key_exists('fill', $metric['options'])) {
								$metric['options']['fill'] = SVG_GRAPH_DEFAULT_FILL;
							}
							if (!array_key_exists('missingdatafunc', $metric['options'])) {
								$metric['options']['missingdatafunc'] = SVG_GRAPH_MISSING_DATA_NONE;
							}
							if (!array_key_exists('width', $metric['options'])) {
								$metric['options']['width'] = SVG_GRAPH_DEFAULT_WIDTH;
							}
							break;
					}
				}
				unset($metric);
			}
		}
	}

	/**
	 * Apply time period for each metric.
	 */
	protected static function getTimePeriods(array &$metrics, array $options) {
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
	 * Prepare an array to be used for hosts/items filtering.
	 *
	 * @param array  $patterns  Array of strings containing hosts/items patterns.
	 *
	 * @return array|mixed  Returns array of patterns.
	 *                      Returns NULL if array contains '*' (so any possible host/item search matches).
	 */
	protected static function processPattern(array $patterns) {
		return in_array('*', $patterns) ? null : $patterns;
	}

	/*
	 * Sort data points by clock field.
	 * Do not use this function directly. It serves as value_compare_func function for usort.
	 */
	protected static function sortByClock($a, $b) {
		if ($a['clock'] == $b['clock']) {
			return 0;
		}

		return ($a['clock'] < $b['clock']) ? -1 : 1;
	}
}
