<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace Widgets\PieChart\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CHousekeepingHelper,
	CMacrosResolverHelper,
	CParser,
	CSimpleIntervalParser,
	Manager;

use Widgets\PieChart\Includes\{
	CWidgetFieldDataSet,
	WidgetForm
};

class WidgetView extends CControllerDashboardWidgetView {

	private const LEGEND_AGGREGATION_ON = 1;
	private const MERGE_SECTORS_ON = 1;
	private const SHOW_TOTAL_ON = 1;
	private const SHOW_UNITS_ON = 1;
	private const VALUE_BOLD_ON = 1;

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'has_custom_time_period' => 'in 1',
			'with_config' => 'in 1'
		]);
	}

	protected function doAction(): void {
		$has_custom_time_period = $this->hasInput('has_custom_time_period');

		$pie_chart_options = [
			'data_sets' => array_values($this->fields_values['ds']),
			'data_source' => $this->fields_values['source'],
			'time_period' => [
				'time_from' => $this->fields_values['time_period']['from_ts'],
				'time_to' => $this->fields_values['time_period']['to_ts']
			],
			'templateid' => $this->getInput('templateid', ''),
			'override_hostid' => $this->fields_values['override_hostid']
				? $this->fields_values['override_hostid'][0]
				: '',
			'merge_sectors' => [
				'merge' => $this->fields_values['merge'],
				'percent' => $this->fields_values['merge'] == self::MERGE_SECTORS_ON
					? $this->fields_values['merge_percent']
					: null,
				'color' => $this->fields_values['merge'] == self::MERGE_SECTORS_ON
					? '#'.$this->fields_values['merge_color']
					: null
			],
			'total_value' => [
				'total_show' => $this->fields_values['total_show'],
				'decimal_places' => $this->fields_values['total_show'] == self::SHOW_TOTAL_ON
					? $this->fields_values['decimal_places']
					: null
			],
			'units' => [
				'units_show' => $this->fields_values['units_show'],
				'units_value' => $this->fields_values['units_show'] == self::SHOW_UNITS_ON
					? $this->fields_values['units']
					: null
			],
			'legend_aggregation_show' => $this->fields_values['legend_aggregation'] == self::LEGEND_AGGREGATION_ON
		];

		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'info' => $this->makeWidgetInfo(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'vars' => []
		];

		$metrics = $this->getData($pie_chart_options);

		if ($metrics['errors']) {
			error($metrics['errors']);
		}

		$svg_data = $this->getSVGData($metrics['sectors'], $metrics['total_value']);

		$data['vars']['sectors'] = $svg_data['svg_sectors'];
		$data['vars']['all_sectorids'] = $metrics['all_sectorids'];
		$data['vars']['total_value'] = $svg_data['svg_total_value'];
		$data['vars']['legend'] = $this->getLegend($metrics['sectors']);
		if ($this->hasInput('with_config')) {
			$data['vars']['config'] = $this->getConfig();
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getData($options): array {
		$metrics = [];
		$errors = [];
		$total_value = [];
		$all_sectorids = [];

		self::getItems($metrics, $options['data_sets'], $options['templateid'], $options['override_hostid']);
		self::getChartDataSource($metrics, $errors, $options['data_source'], $options['time_period']);
		self::getMetricsData($metrics, $options['time_period'], $options['legend_aggregation_show'],
			$options['templateid'], $options['override_hostid']);
		self::getSectorsData($metrics, $total_value, $options['merge_sectors'], $options['total_value'],
			$options['units'], $options['templateid'], $options['override_hostid'], $all_sectorids);

		return [
			'sectors' => $metrics,
			'all_sectorids' => $all_sectorids,
			'total_value' => $total_value,
			'errors' => $errors
		];
	}

	private function makeWidgetInfo(): array {
		$info = [];

		if ($this->hasInput('has_custom_time_period')) {
			$info[] = [
				'icon' => ZBX_ICON_TIME_PERIOD,
				'hint' => relativeDateToText($this->fields_values['time_period']['from'],
					$this->fields_values['time_period']['to']
				)
			];
		}

		return $info;
	}

	private static function getItems(array &$metrics, array $data_sets, string $templateid,
			string $override_hostid): void {
		$metrics = [];
		$max_metrics = 50;

		foreach ($data_sets as $index => $data_set) {
			if ($max_metrics === 0) {
				break;
			}

			if ($data_set['dataset_type'] == CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM) {
				$ds_metrics = self::getMetricsSingleItemDS($data_set, $max_metrics, $override_hostid);
			}
			else {
				$ds_metrics = self::getMetricsPatternItemDS($data_set, $max_metrics, $templateid, $override_hostid);
			}

			foreach ($ds_metrics as $ds_metric) {
				$ds_metric['data_set'] = $index;
				$metrics[] = $ds_metric;
				$max_metrics--;
			}
		}
	}

	private static function getMetricsSingleItemDS(array $data_set, int $max_metrics, string $override_hostid): array {
		$metrics = [];
		$ds_items = [];

		if (!$data_set['itemids'] || count($data_set['color']) !== count($data_set['itemids'])
				|| count($data_set['type']) !== count($data_set['itemids'])) {
			return $metrics;
		}

		foreach ($data_set['itemids'] as $key => $item) {
			$ds_items[$item] = [
				'color' => $data_set['color'][$key],
				'type' => $data_set['type'][$key]
			];
		}

		unset($data_set['itemids'], $data_set['color'], $data_set['type']);

		if ($override_hostid !== '') {
			// Host dashboard (view).
			$tmp_items = API::Item()->get([
				'output' => ['itemid', 'key_'],
				'itemids' => array_keys($ds_items),
				'webitems' => true
			]);

			if ($tmp_items) {
				$items = API::Item()->get([
					'output' => ['itemid', 'key_'],
					'hostids' => [$override_hostid],
					'webitems' => true,
					'filter' => [
						'key_' => array_column($tmp_items, 'key_')
					]
				]);

				if (!$items) {
					$ds_items = [];
				}
				else {
					$old_item_keys = array_combine(array_column($tmp_items, 'itemid'), array_column($tmp_items, 'key_'));
					$new_itemids = array_combine(array_column($items, 'key_'), array_column($items, 'itemid'));

					foreach ($ds_items as $key => $item) {
						unset($ds_items[$key]);
						$new_id = $new_itemids[$old_item_keys[$key]];
						$ds_items[$new_id] = $item;
					}
				}
			}
		}

		$items_db = API::Item()->get([
			'output' => ['itemid', 'hostid', 'name', 'history', 'trends', 'units', 'value_type'],
			'selectHosts' => ['name'],
			'webitems' => true,
			'filter' => [
				'value_type' => [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT]
			],
			'itemids' => array_keys($ds_items),
			'preservekeys' => true,
			'limit' => $max_metrics
		]);

		foreach ($ds_items as $itemid => $ds_item) {
			if (array_key_exists($itemid, $items_db)) {
				$data_set['color'] = '#' . $ds_item['color'];
				$data_set['type'] = $ds_item['type'];

				$metrics[] = $items_db[$itemid] + ['options' => $data_set];
			}
		}

		return $metrics;
	}

	private static function getMetricsPatternItemDS(array $data_set, int $max_metrics, string $templateid,
			string $override_hostid): array {
		$metrics = [];

		if (($templateid === '' && (!$data_set['hosts'] || !$data_set['items']))
			|| ($templateid !== '' && !$data_set['items'])
		) {
			return $metrics;
		}

		if ($override_hostid === '' && $templateid === '') {
			$hosts = API::Host()->get([
				'output' => [],
				'search' => [
					'name' => self::processPattern($data_set['hosts'])
				],
				'searchWildcardsEnabled' => true,
				'searchByAny' => true,
				'preservekeys' => true
			]);

			$hostids = array_keys($hosts);
		}
		else {
			$hostids = $override_hostid !== '' ? [$override_hostid] : [$templateid];
		}

		if (!$hostids) {
			return $metrics;
		}

		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'name', 'history', 'trends', 'units', 'value_type'],
			'selectHosts' => ['name'],
			'webitems' => true,
			'hostids' => $hostids,
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

		$colors = getColorVariations('#' . $data_set['color'], count($items));

		unset($data_set['hosts'], $data_set['items'], $data_set['color']);

		foreach ($items as $item) {
			$data_set['color'] = array_shift($colors);
			$metrics[] = $item + ['options' => $data_set];
		}

		return $metrics;
	}

	private static function getChartDataSource(array &$metrics, array &$errors, int $data_source,
			array $time_period): void {
		/**
		 * If data source is not specified, calculate it automatically. Otherwise, set given $data_source to each
		 * $metric.
		 */
		if ($data_source == WidgetForm::DATA_SOURCE_AUTO) {
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
				 *    item.
				 *
				 * Use trends otherwise.
				 */
				$history = $metric['history'];
				$trends = $metric['trends'];
				$time_from = $time_period['time_from'];

				$metric['source'] = ($trends == 0 || (time() - $history < $time_from))
					? WidgetForm::DATA_SOURCE_HISTORY
					: WidgetForm::DATA_SOURCE_TRENDS;
			}
		}
		else {
			foreach ($metrics as &$metric) {
				$metric['source'] = $data_source;
			}
		}

		unset($metric);
	}

	private static function getMetricsData(array &$metrics, array $time_period, bool $legend_aggregation_show,
			string $templateid, string $override_hostid): void {
		$dataset_metrics = [];

		foreach ($metrics as $metric_num => &$metric) {
			$dataset_num = $metric['data_set'];

			if ($metric['options']['dataset_aggregation'] == AGGREGATE_NONE) {
				if ($legend_aggregation_show) {
					$name = self::aggr_fnc2str($metric['options']['aggregate_function']).
						'('.$metric['hosts'][0]['name'].NAME_DELIMITER.$metric['name'].')';
				}
				else {
					$name = $metric['hosts'][0]['name'].NAME_DELIMITER.$metric['name'];
				}
			}
			else {
				$name = $metric['options']['data_set_label'] !== ''
					? $metric['options']['data_set_label']
					: _('Data set').' #'.($dataset_num + 1);
			}

			$item = [
				'itemid' => $metric['itemid'],
				'value_type' => $metric['value_type'],
				'source' => ($metric['source'] == WidgetForm::DATA_SOURCE_HISTORY) ? 'history' : 'trends'
			];

			if (!array_key_exists($dataset_num, $dataset_metrics)) {
				$metric['name'] = $name;
				$metric['items'] = [$item];
				$metric['value'] = null;

				if ($metric['options']['dataset_aggregation'] != AGGREGATE_NONE) {
					$dataset_metrics[$dataset_num] = $metric_num;
				}
			}
			else {
				$metrics[$dataset_metrics[$dataset_num]]['items'][] = $item;
				unset($metrics[$metric_num]);
			}
		}
		unset($metric);

		foreach ($metrics as &$metric) {
			if ($templateid !== '' && $override_hostid === '') {
				continue;
			}

			$results = Manager::History()->getAggregationByInterval(
				$metric['items'], $time_period['time_from'], $time_period['time_to'],
				$metric['options']['aggregate_function'], $time_period['time_to']
			);

			if (!$results) {
				continue;
			}

			$values = [];

			foreach ($results as $result) {
				if ($metric['options']['aggregate_function'] == AGGREGATE_COUNT) {
					$values[] = $result['data'][0]['count'];
				}
				else {
					$values[] = $result['data'][0]['value'];
				}
			}

			switch($metric['options']['dataset_aggregation']) {
				case AGGREGATE_MAX:
					$metric['value'] = max($values);
					break;
				case AGGREGATE_MIN:
					$metric['value'] = min($values);
					break;
				case AGGREGATE_AVG:
					$metric['value'] = array_sum($values) / count($values);
					break;
				case AGGREGATE_COUNT:
					$metric['value'] = count($values);
					break;
				case AGGREGATE_SUM:
					$metric['value'] = array_sum($values);
					break;
				default:
					$metric['value'] = $values[0];
			}
		}
	}

	private static function getSectorsData(array &$metrics, array &$total_value, array $merge_sectors,
			array $total_config, array $units_config, string $templateid, string $override_hostid,
			array &$all_sectorids): void {
		$has_total = false;
		$chart_units = null;
		$raw_total_value = null;
		$others_value = 0;
		$below_threshold_sectors = [];
		$sectors = [];

		if ($templateid !== '' && $override_hostid === '') {
			foreach ($metrics as $metric) {
				$is_total = ($metric['options']['dataset_aggregation'] == AGGREGATE_NONE
					&& $metric['options']['type'] == CWidgetFieldDataSet::ITEM_TYPE_TOTAL);

				$sectors[] = [
					'id' => $metric['data_set'].'_'.$metric['itemid'],
					'name' => $metric['name'],
					'color' => $metric['options']['color'],
					'value' => null,
					'units' => '',
					'is_total' => $is_total
				];

				$all_sectorids[] = $metric['data_set'].'_'.$metric['itemid'];
			}
		}
		else {
			foreach ($metrics as &$metric) {
				$is_total = ($metric['options']['dataset_aggregation'] == AGGREGATE_NONE
					&& $metric['options']['type'] == CWidgetFieldDataSet::ITEM_TYPE_TOTAL);

				if ($is_total) {
					$raw_total_value = $metric['value'] !== null ? abs($metric['value']) : null;
					$has_total = true;
				}
				elseif (!$has_total && $metric['value'] !== null) {
					if ($raw_total_value === null) {
						$raw_total_value = 0;
					}
					$raw_total_value += abs($metric['value']);
				}

				if ($units_config['units_show'] == self::SHOW_UNITS_ON && $units_config['units_value'] !== '') {
					$metric['units'] = $units_config['units_value'];
				}

				if ($chart_units === null || $is_total) {
					$chart_units = $metric['units'];
				}

				$sectors[] = [
					'id' => $metric['data_set'].'_'.$metric['itemid'],
					'name' => $metric['name'],
					'color' => $metric['options']['color'],
					'value' => $metric['value'],
					'units' => $metric['units'],
					'is_total' => $is_total
				];

				$all_sectorids[] = $metric['data_set'].'_'.$metric['itemid'];
			}
			unset($metric);

			if ($merge_sectors['merge'] == self::MERGE_SECTORS_ON) {
				$all_sectorids[] = 'other';
			}

			foreach ($sectors as $key => $sector) {
				if ($sector['value'] == 0 || $raw_total_value == 0) {
					$percentage = 0;
				}
				else {
					$percentage = (abs($sector['value']) / $raw_total_value) * 100;
				}

				if ($merge_sectors['merge'] == self::MERGE_SECTORS_ON
						&& $percentage < $merge_sectors['percent']) {
					$others_value += abs($sector['value'] ?? 0);

					$below_threshold_sectors[] = $key;
				}
			}

			if (count($below_threshold_sectors) >= 2) {
				foreach ($below_threshold_sectors as $sector_key) {
					unset($sectors[$sector_key]);
				}

				$sectors[] = [
					'id' => 'other',
					'name' => _('Other'),
					'color' => $merge_sectors['color'],
					'value' => $others_value,
					'units' => $chart_units,
					'is_total' => false
				];
			}
		}

		foreach ($sectors as &$sector) {
			$formatted_value = convertUnitsRaw([
				'value' => $sector['value'],
				'units' => $sector['units'],
				'zero_as_zero' => false
			]);
			unset($formatted_value['is_numeric']);
			unset($sector['units']);

			$sector['formatted_value'] = $formatted_value;
		}
		unset($sector);

		$metrics = $sectors;

		$formatted_total_value = convertUnitsRaw([
			'value' => $raw_total_value,
			'units' => $chart_units,
			'power' => $units_config['units_show'] == self::SHOW_UNITS_ON ? null : 0,
			'decimals' => $total_config['decimal_places'],
			'decimals_exact' => true,
			'small_scientific' => false,
			'zero_as_zero' => false
		]);
		unset($formatted_total_value['is_numeric']);

		$total_value['value'] = $raw_total_value;

		if ($raw_total_value !== null && $raw_total_value !== 0) {
			$total_value['formatted_value'] = $formatted_total_value;
		}
	}

	/**
	 * Prepare an array to be used for hosts/items filtering.
	 *
	 * @param array  $patterns  Array of strings containing hosts/items patterns.
	 *
	 * @return array|mixed  Returns array of patterns.
	 *                      Returns NULL if array contains '*' (so any possible host/item search matches).
	 */
	private static function processPattern(array $patterns): ?array {
		return in_array('*', $patterns, true) ? null : $patterns;
	}

	private static function aggr_fnc2str($calc_fnc) {
		switch ($calc_fnc) {
			case AGGREGATE_NONE:
				return _('none');
			case AGGREGATE_MIN:
				return _('min');
			case AGGREGATE_MAX:
				return _('max');
			case AGGREGATE_AVG:
				return _('avg');
			case AGGREGATE_COUNT:
				return _('count');
			case AGGREGATE_SUM:
				return _('sum');
			case AGGREGATE_FIRST:
				return _('first');
			case AGGREGATE_LAST:
				return _('last');
		}
	}

	private function getConfig(): array {
		$config = [
			'draw_type' => $this->fields_values['draw_type'],
			'space' => $this->fields_values['space']
		];

		if ($this->fields_values['draw_type'] == WidgetForm::DRAW_TYPE_DOUGHNUT) {
			$config['width'] = $this->fields_values['width'];

			if ($this->fields_values['total_show'] == self::SHOW_TOTAL_ON) {
				$config['total_value'] = [
					'show' => true,
					'is_custom_size' => $this->fields_values['value_size_type'] == WidgetForm::VALUE_SIZE_CUSTOM,
					'is_bold' =>  $this->fields_values['value_bold'] == self::VALUE_BOLD_ON,
					'color' => '#'.$this->fields_values['value_color'],
					'units_show' => $this->fields_values['units_show'] == self::SHOW_UNITS_ON
				];

				if ($this->fields_values['value_size_type'] == WidgetForm::VALUE_SIZE_CUSTOM) {
					$config['total_value']['size'] = $this->fields_values['value_size'];
				}
			}
			else {
				$config['total_value'] = [
					'show' => false
				];
			}
		}

		return $config;
	}

	private function getLegend(array $sectors): array {
		$legend['data'] = [];

		foreach ($sectors as $sector) {
			$legend['data'][] = [
				'id' => $sector['id'],
				'name' => $sector['name'],
				'color' => $sector['color'],
				'is_total' => $sector['is_total']
			];
		}

		if ($this->fields_values['legend'] == WidgetForm::LEGEND_ON) {
			$legend['show'] = true;
			$legend['lines'] = $this->fields_values['legend_lines'];
			$legend['columns'] = $this->fields_values['legend_columns'];
		}
		else {
			$legend['show'] = false;
		}

		return $legend;
	}

	private function getSVGData(array $sectors, array $total_value): array {
		if ($total_value['value'] === null || $total_value['value'] === 0) {
			return [
				'svg_sectors' => [],
				'svg_total_value' => $total_value
			];
		}

		$sector_total_value = 0;
		$non_total_sectors = [];
		$has_total_item = false;

		$sectors = array_filter($sectors, function ($sector) {
			return $sector['value'] != 0;
		});

		// Move total sector to the end.
		foreach ($sectors as $key => $sector) {
			if ($sector['is_total']) {
				$has_total_item = true;
				$sectors[] = $sector;
				unset($sectors[$key]);
				break;
			}
		}

		$svg_sectors = array_values($sectors);

		foreach ($svg_sectors as &$sector) {
			$sector['percent_of_total'] = (abs($sector['value']) / $total_value['value']) * 100;

			if (!$sector['is_total']) {
				$sector_total_value += abs($sector['value']);
				$non_total_sectors[] = $sector;
			}
		}
		unset($sector);

		if ($has_total_item) {
			if ($sector_total_value === $total_value['value']) {
				// Sectors use the full total value, no remaining space for the total sector.
				array_pop($svg_sectors);
			}
			elseif ($sector_total_value < $total_value['value']) {
				// Sectors use less than total value, the remaining of the total sector will be displayed.
				$svg_sectors[count($svg_sectors) - 1]['percent_of_total'] =
					($total_value['value'] - $sector_total_value) * 100 / $total_value['value'];
			}
			else {
				// Sectors use more than total value.
				$current_value = 0;
				$remaining_value = $total_value['value'];
				$sectors_to_keep = [];

				foreach ($non_total_sectors as &$sector) {
					if (($current_value + abs($sector['value'])) <= $remaining_value) {
						// There is enough space for this sector.
						$sectors_to_keep[] = $sector;
						$current_value += abs($sector['value']);
						$remaining_value -= abs($sector['value']);
					}
					elseif (abs($sector['value']) >= $remaining_value && $current_value < $total_value['value']) {
						// This sector needs to be cut, to fit.
						$sector['percent_of_total'] = ($remaining_value / $total_value['value']) * 100;
						$sectors_to_keep[] = $sector;
						break;
					}
					else {
						// This sector doesn't fit.
						break;
					}
				}
				unset($sector);

				$svg_sectors = $sectors_to_keep;
			}
		}

		foreach($svg_sectors as &$sector) {
			unset($sector['value']);
		}
		unset($sector);

		return [
			'svg_sectors' => $svg_sectors,
			'svg_total_value' => $total_value['formatted_value']
		];
	}
}
