<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CSvgGraphHelper {

	/**
	 * Calculate graph data and draw SVG graph based on given graph configuration.
	 *
	 * @param array $options				Options for graph.
	 * @param array $options[data_sets]		Graph data set options.
	 * @param array $options[problems]		Graph problems options.
	 * @param array $options[overrides]		Graph problems options.
	 * @param array $options[data_source]	Data source of graph.
	 * @param array $options[time_period]	Graph time period used.
	 * @param array $options[left_y_axis]	Options for graph left Y axis.
	 * @param array $options[right_y_axis]	Options for graph right Y axis.
	 * @param array $options[x_axis]		Options for graph X axis.
	 * @param array $options[show_legend]	Options for graph legend.
	 *
	 * @return array
	 */
	public static function get(array $options = []) {
		$metrics = [];
		$errors = [];
		$problems = null;

		// Find which metrics will be shown in graph and calculate time periods and display options.
		self::getMetrics($metrics, $options['data_sets']);
		// Apply overrides for previously selected $metrics.
		self::applyOverrides($metrics, $options['overrides']);
		// Apply time periods for each $metric, based on graph/dashboard time as well as metric level timeshifts.
		self::getTimePeriods($metrics, $options['time_period']);
		// Find what data source (history or trends) will be used for each metric.
		self::getGraphDataSource($metrics, $errors, $options['data_source']);
		// Load Data for each metric.
		self::getMetricsData($metrics, $errors);

		// Get problems to display in graph.
		if (array_key_exists('problems', $options)) {
			$options['problems']['itemids_only'] = (array_key_exists('graph_item_problems_only', $options['problems'])
					&& $options['problems']['graph_item_problems_only'] == SVG_GRAPH_SELECTED_ITEM_PROBLEMS)
				? array_keys($metrics)
				: null;

			$problems = self::getProblems($options['problems'], $options['time_period']);
		}

		// Clear unneeded data.
		unset($options['data_sets'], $options['overrides'], $options['problems'], $options['time_period']);

		// Draw SVG graph using what's left in $options as well as newly made arrays $metrics and $problems.
		return [
			// Demo SVG.
			'svg' => (new CTag('svg', true))
						->setAttribute('viewBox', '0 0 500 100')
						->addItem(
							(new CTag('polyline', true))
								->setAttribute('fill', 'none')
								->setAttribute('stroke', '#0074d9')
								->setAttribute('stroke-width', 2)
								->setAttribute('points', '00,120 20,60 40,80 60,20 80,80 100,80 120,60 140,100 160,90 180,80 200, 110 220, 10 240, 70 260, 100 280, 100 300, 40 320, 0 340, 100 360, 100 380, 120 400, 60 420, 70 440, 80')
						),
			'errors' => $errors
		];
	}

	protected static function getMetricsData(array &$metrics = [], array &$errors = []) {
		foreach ($metrics as &$metric) {
			// Select data for metric from history or trends
			$metric['data'] = [];
		}
		unset($metric);
	}

	protected static function getGraphDataSource(array &$metrics = [], array &$errors = [], $data_source) {
		$simple_interval_parser = new CSimpleIntervalParser();
		$config = select_config();

		foreach ($metrics as &$metric) {
			/**
			 * If data source is not specified, calculate it automatically. Otherwise, set given $data_source to each
			 * $metric.
			 */
			if ($data_source == SVG_GRAPH_DATA_SOURCE_AUTO) {
				$to_resolve = [];

				/**
				 * First, if global configuration setting "Override item history period" is enabled, override globally
				 * specified "Data storage period" value to each metric's custom history storage duration, converting it
				 * to seconds. If "Override item history period" is disabled, item level field 'history' will be used
				 * later but now we are just storing the field name 'history' in array $to_resolve.
				 *
				 * Do the same with trends.
				 */
				if ($config['hk_history_global']) {
					$metric['history'] = timeUnitToSeconds($config['hk_history']);
				}
				else {
					$to_resolve[] = 'history';
				}

				if ($config['hk_trends_global']) {
					$metric['trends'] = timeUnitToSeconds($config['hk_trends']);
				}
				else {
					$to_resolve[] = 'trends';
				}

				/**
				 * If no global history and trend override enabled, resolve 'history' and/or 'trends' values for given
				 * $metric and convert its values to seconds.
				 */
				if ($to_resolve) {
					$metric = CMacrosResolverHelper::resolveTimeUnitMacros([$metric], $to_resolve)[0];

					if (!$config['hk_history_global']) {
						if ($simple_interval_parser->parse($metric['history']) != CParser::PARSE_SUCCESS) {
							$errors[] = _s('Incorrect value for field "%1$s": %2$s.', 'history',
								_('invalid history storage period')
							);
						}
						$metric['history'] = timeUnitToSeconds($metric['history']);
					}

					if (!$config['hk_trends_global']) {
						if ($simple_interval_parser->parse($metric['trends']) != CParser::PARSE_SUCCESS) {
							$errors[] = _s('Incorrect value for field "%1$s": %2$s.', 'trends',
								_('invalid trend storage period')
							);
						}
						$metric['trends'] = timeUnitToSeconds($metric['trends']);
					}
				}

				/**
				 * History as a data source is used in 2 cases:
				 * 1) if trends are disabled (set to 0) either for particular $metric item or globally;
				 * 2) if period for requested data is newer than the period of keeping history for particular $metric
				 *	  item.
				 *
				 * Use trends otherwise.
				 */
				$metric['source'] = ($metric['trends'] == 0
						|| $metric['time_period']['time_from'] >= (time() - $metric['history']))
					? SVG_GRAPH_DATA_SOURCE_HISTORY
					: SVG_GRAPH_DATA_SOURCE_TRENDS;
			}
			else {
				$metric['source'] = $data_source;
			}
		}
	}

	protected static function getProblems(array $problem_options = [], array $time_period) {
		$options = [
			'output' => ['objectid', 'name', 'severity', 'clock'],
			'severities' => $problem_options['severities'],
			'time_from'	=> $time_period['time_from'],
			'time_till'	=> $time_period['time_to'],
			'preservekeys' => true
		];

		if ($problem_options['itemids_only'] !== null) {
			$options['objectids'] = $problem_options['itemids_only'];
		}

		if (array_key_exists('problem_name', $problem_options)) {
			$options['search']['name'] = $problem_options['problem_name'];
		}

		if (array_key_exists('problem_hosts', $problem_options)) {
			$options['hostids'] = array_keys(API::Host()->get([
				'output' => [],
				'searchWildcardsEnabled' => true,
				'preservekeys' => true,
				'search' => [
					'name' => self::processPattern($problem_options['problem_hosts'])
				]
			]));
		}

		if (array_key_exists('evaltype', $problem_options)) {
			$options['evaltype'] = $problem_options['evaltype'];
		}

		if (array_key_exists('tags', $problem_options)) {
			$options['tags'] = $problem_options['tags'];
		}

		return API::Problem()->get($options);
	}

	protected static function getMetrics(array &$metrics = [], array $data_sets = []) {
		$data_set_num = 0;
		$metrics = [];

		do {
			$data_set = $data_sets[$data_set_num];
			$data_set_num++;

			// TODO miks: still not clear how valid data set looks like. Fix this if needed.
			if (!array_key_exists('hosts', $data_set)) {
				$data_set['hosts'] = '*';
			}
			if (!array_key_exists('items', $data_set)) {
				$data_set['items'] = '*';
			}

			if ($data_set['hosts'] === '' || $data_set['items'] === '') {
				continue;
			}

			// Find hosts.
			if ($data_set['hosts'] !== '') {
				$matching_hosts = API::Host()->get([
					'output' => [],
					'searchWildcardsEnabled' => true,
					'search' => [
						'name' => self::processPattern($data_set['hosts'])
					],
					'sortfield' => 'name',
					'sortorder' => ZBX_SORT_UP,
					'preservekeys' => true
				]);

				if ($matching_hosts) {
					$matching_items = API::Item()->get([
						'output' => ['itemid', 'name', 'history', 'trends', 'value_type'],
						'hostids' => array_keys($matching_hosts),
						'selectHosts' => ['hostid', 'name'],
						'searchWildcardsEnabled' => true,
						'search' => [
							'name' => self::processPattern($data_set['items'])
						],
						'filter' => [
							'value_type' => [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT]
						],
						'sortfield' => 'name',
						'sortorder' => ZBX_SORT_UP,
						'limit' => SVG_GRAPH_MAX_NUMBER_OF_METRICS - count($metrics),
						'preservekeys' => true
					]);

					unset($data_set['hosts'], $data_set['items']);

					foreach ($matching_items as $item) {
						$metrics[$item['itemid']] = $item + ['options' => $data_set];
					}
				}
			}
		}
		while (SVG_GRAPH_MAX_NUMBER_OF_METRICS > count($metrics) && array_key_exists($data_set_num, $data_sets));

		CArrayHelper::sort($metrics, ['name']);

		return $metrics;
	}

	protected static function applyOverrides(array &$metrics = [], array $overrides = []) {
		foreach ($overrides as $override) {
			// TODO miks: still not clear how valid override looks like. Fix this if needed.
			if (!array_key_exists('hosts', $override)) {
				$override['hosts'] = '*';
			}
			if (!array_key_exists('items', $override)) {
				$override['items'] = '*';
			}

			if ($override['hosts'] === '' || $override['items'] === '') {
				continue;
			}

			$hosts_patterns = self::processPattern($override['hosts']);
			$items_patterns = self::processPattern($override['items']);

			unset($override['hosts'], $override['items']);

			foreach ($metrics as &$metric) {
				// If '*' used, apply options to all metrics.
				$host_matches = ($hosts_patterns === null);
				$item_matches = ($items_patterns === null);

				/**
				 * Find if host and item names matches one of given patterns.
				 *
				 * It currently checks if at least one of host pattern and at least one of item pattern matches,
				 * without checking relation between matching host and item.
				 */
				$host_pattern_num = 0;
				while (!$host_matches && array_key_exists($host_pattern_num, $hosts_patterns)) {
					$re = '/^'.str_replace('\*', '.*', preg_quote($hosts_patterns[$host_pattern_num], '/')).'$/i';
					$host_matches = (strpos($hosts_patterns[$host_pattern_num], '*') === false)
						? ($metric['hosts'][0]['name'] === $hosts_patterns[$host_pattern_num])
						: preg_match($re, $metric['hosts'][0]['name']);

					$host_pattern_num++;
				}

				$item_pattern_num = 0;
				while (!$item_matches && array_key_exists($item_pattern_num, $item_matches)) {
					$re = '/^'.str_replace('\*', '.*', preg_quote($item_matches[$item_pattern_num], '/')).'$/i';
					$item_matches = (strpos($item_matches[$item_pattern_num], '*') === false)
						? ($metric['name'] === $item_matches[$item_pattern_num])
						: preg_match($re, $metric['name']);

					$item_pattern_num++;
				}

				// Apply override options to matching metrics.
				if ($host_matches && $item_matches) {
					$metric['options'] = $override + $metric['options'];
				}
			}
			unset($metric);
		}
	}

	protected static function getTimePeriods(array &$metrics = [], array $options) {
		foreach ($metrics as &$metric) {
			$metric['time_period'] = $options;

			if ($metric['options']['timeshift'] !== '') {
				$timeshift = (int) timeUnitToSeconds($metric['options']['timeshift'], true);
				if ($timeshift) {
					$metric['time_period']['time_from'] = bcadd($metric['time_period']['time_from'], $timeshift, 0);
					$metric['time_period']['time_to'] = bcadd($metric['time_period']['time_to'], $timeshift, 0);
				}
			}
		}
		unset($metric);
	}

	/**
	 * Make array of patterns from given comma separated patterns string.
	 *
	 * @param string   $patterns		String containing comma separated patterns.
	 *
	 * @return array   Returns array of patterns or NULL if '*' used, thus all database records are valid.
	 */
	protected static function processPattern($patterns) {
		$patterns = explode(',', $patterns);
		$patterns = array_keys(array_flip($patterns));

		foreach ($patterns as &$pattern) {
			$pattern = trim($pattern);
			if ($pattern === '*') {
				$patterns = null;
				break;
			}
		}
		unset($pattern);

		return $patterns;
	}
}
