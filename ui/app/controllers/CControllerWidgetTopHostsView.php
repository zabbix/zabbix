<?php declare(strict_types = 1);
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


class CControllerWidgetTopHostsView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_TOP_HOSTS);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$data = [
			'name' => $this->getInput('name', $this->getDefaultName()),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$data += self::getData($this->getForm()->getFieldsData());

		$this->setResponse(new CControllerResponseData($data));
	}

	/**
	 * @param array $fields
	 *
	 * @throws Exception
	 *
	 * @return array
	 */
	private static function getData(array $fields): array {
		$configuration = $fields['columns'];

		$groupids = $fields['groupids'] ? getSubGroups($fields['groupids']) : null;
		$hostids = $fields['hostids'] ?: null;

		if (array_key_exists('tags', $fields)) {
			$hosts = API::Host()->get([
				'output' => ['name'],
				'groupids' => $groupids,
				'hostids' => $hostids,
				'evaltype' => $fields['evaltype'],
				'tags' => $fields['tags'],
				'monitored_hosts' => true,
				'preservekeys' => true
			]);

			$hostids = array_keys($hosts);
		}
		else {
			$hosts = null;
		}

		$time_now = time();

		$master_column = $configuration[$fields['column']];
		$master_items_only_numeric_allowed = self::isNumericOnlyColumn($master_column);

		$master_items = self::getItems($master_column['item'], $master_items_only_numeric_allowed, $groupids, $hostids);
		$master_item_values = self::getItemValues($master_items, $master_column, $time_now);

		if (!$master_item_values) {
			return [
				'configuration' => $configuration,
				'rows' => []
			];
		}

		$master_items_only_numeric_present = $master_items_only_numeric_allowed && !array_filter($master_items,
			static function(array $item): bool {
				return !in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
			}
		);

		if ($fields['order'] == CWidgetFormTopHosts::ORDER_TOPN) {
			if ($master_items_only_numeric_present) {
				arsort($master_item_values, SORT_NUMERIC);

				$master_items_min = end($master_item_values);
				$master_items_max = reset($master_item_values);
			}
			else {
				asort($master_item_values, SORT_NATURAL);
			}
		}
		else {
			if ($master_items_only_numeric_present) {
				asort($master_item_values, SORT_NUMERIC);

				$master_items_min = reset($master_item_values);
				$master_items_max = end($master_item_values);
			}
			else {
				arsort($master_item_values, SORT_NATURAL);
			}
		}

		$master_item_values = array_slice($master_item_values, 0, $fields['count'], true);
		$master_items = array_intersect_key($master_items, $master_item_values);

		$master_hostids = [];

		foreach (array_keys($master_item_values) as $itemid) {
			$master_hostids[$master_items[$itemid]['hostid']] = true;
		}

		$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

		$item_values = [];

		foreach ($configuration as $column_index => &$column) {
			if ($column['data'] != CWidgetFieldColumnsList::DATA_ITEM_VALUE) {
				continue;
			}

			$calc_extremes = $column['display'] == CWidgetFieldColumnsList::DISPLAY_BAR
				|| $column['display'] == CWidgetFieldColumnsList::DISPLAY_INDICATORS;

			if ($calc_extremes) {
				if ($column['min'] !== '' && $number_parser->parse($column['min']) == CParser::PARSE_SUCCESS) {
					$column['min'] = $number_parser->calcValue();
				}

				if ($column['max'] !== '' && $number_parser->parse($column['max']) == CParser::PARSE_SUCCESS) {
					$column['max'] = $number_parser->calcValue();
				}
			}

			if (array_key_exists('thresholds', $column)) {
				foreach ($column['thresholds'] as &$threshold) {
					if ($number_parser->parse($threshold['threshold']) == CParser::PARSE_SUCCESS) {
						$threshold['threshold'] = $number_parser->calcValue();
					}
				}
				unset($threshold);
			}

			if ($column_index == $fields['column']) {
				$column_items = $master_items;
				$column_item_values = $master_item_values;

				if ($calc_extremes) {
					if ($column['min'] === '') {
						$column['min'] = $master_items_min;
					}

					if ($column['max'] === '') {
						$column['max'] = $master_items_max;
					}
				}
			}
			else {
				$numeric_only = self::isNumericOnlyColumn($column);
				$column_items = !$calc_extremes || $column['min'] !== '' && $column['max'] !== ''
					? self::getItems($column['item'], $numeric_only, $groupids, array_keys($master_hostids))
					: self::getItems($column['item'], $numeric_only, $groupids, $hostids);

				$column_item_values = self::getItemValues($column_items, $column, $time_now);

				if ($calc_extremes && $column_item_values) {
					if ($column['min'] === '') {
						$column['min'] = min($column_item_values);
					}

					if ($column['max'] === '') {
						$column['max'] = max($column_item_values);
					}
				}
			}

			$item_values[$column_index] = [];

			foreach ($column_item_values as $itemid => $column_item_value) {
				if (array_key_exists($column_items[$itemid]['hostid'], $master_hostids)) {
					$item_values[$column_index][$column_items[$itemid]['hostid']] = [
						'value' => $column_item_value,
						'item' => $column_items[$itemid]
					];
				}
			}
		}
		unset($column);

		$text_columns = [];

		foreach ($configuration as $column_index => $column) {
			if ($column['data'] == CWidgetFieldColumnsList::DATA_TEXT) {
				$text_columns[$column_index] = $column['text'];
			}
		}

		$text_columns = CMacrosResolverHelper::resolveWidgetTopHostsTextColumns($text_columns, $master_items);

		$hostid_to_itemid = array_column($master_items, 'itemid', 'hostid');

		$rows = [];

		foreach (array_keys($master_hostids) as $hostid) {
			$row = [];

			foreach ($configuration as $column_index => $column) {
				switch ($column['data']) {
					case CWidgetFieldColumnsList::DATA_HOST_NAME:
						if ($hosts === null) {
							$hosts = API::Host()->get([
								'output' => ['name'],
								'groupids' => $groupids,
								'hostids' => array_keys($master_hostids),
								'monitored_hosts' => true,
								'preservekeys' => true
							]);
						}

						$row[] = [
							'value' => $hosts[$hostid]['name'],
							'hostid' => $hostid
						];

						break;

					case CWidgetFieldColumnsList::DATA_TEXT:
						$row[] = [
							'value' => $text_columns[$column_index][$hostid_to_itemid[$hostid]]
						];

						break;

					case CWidgetFieldColumnsList::DATA_ITEM_VALUE:
						$row[] = array_key_exists($hostid, $item_values[$column_index])
							? [
								'value' => $item_values[$column_index][$hostid]['value'],
								'item' => $item_values[$column_index][$hostid]['item']
							]
							: null;

						break;
				}
			}

			$rows[] = $row;
		}

		return [
			'configuration' => $configuration,
			'rows' => $rows
		];
	}

	/**
	 * @param array $column
	 *
	 * @return bool
	 */
	private static function isNumericOnlyColumn(array $column): bool {
		return $column['aggregate_function'] != AGGREGATE_NONE
			|| $column['display'] != CWidgetFieldColumnsList::DISPLAY_AS_IS
			|| array_key_exists('thresholds', $column);
	}

	/**
	 * @param string     $name
	 * @param bool       $numeric_only
	 * @param array|null $groupids
	 * @param array|null $hostids
	 *
	 * @return array
	 */
	private static function getItems(string $name, bool $numeric_only, ?array $groupids, ?array $hostids): array {
		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'key_', 'history', 'trends', 'value_type', 'units'],
			'selectValueMap' => ['mappings'],
			'groupids' => $groupids,
			'hostids' => $hostids,
			'monitored' => true,
			'webitems' => true,
			'filter' => [
				'name' => $name,
				'status' => ITEM_STATUS_ACTIVE,
				'value_type' => $numeric_only ? [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64] : null
			],
			'sortfield' => 'key_',
			'preservekeys' => true
		]);

		if ($items) {
			$single_key = reset($items)['key_'];

			$items = array_filter($items,
				static function ($item) use ($single_key): bool {
					return $item['key_'] === $single_key;
				}
			);
		}

		return $items;
	}

	/**
	 * @param array $items
	 * @param array $column
	 * @param int   $time_now
	 *
	 * @return array
	 */
	private static function getItemValues(array $items, array $column, int $time_now): array {
		static $history_period;

		if ($history_period === null) {
			$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		}

		$timeshift = $column['timeshift'] !== '' ? timeUnitToSeconds($column['timeshift']) : 0;

		$time_to = $time_now + $timeshift;

		$time_from = $column['aggregate_function'] != AGGREGATE_NONE
			? $time_to - timeUnitToSeconds($column['aggregate_interval'])
			: $time_to - $history_period;

		$function = $column['aggregate_function'] != AGGREGATE_NONE
			? $column['aggregate_function']
			: AGGREGATE_LAST;

		$interval = $time_to;

		self::addDataSource($items, $time_from, $time_now, $column['history']);

		$result = [];

		if ($column['aggregate_function'] == AGGREGATE_NONE) {
			$items_by_source = ['history' => [], 'trends' => []];

			foreach ($items as $itemid => $item) {
				$items_by_source[$item['source']][$itemid] = $item;
			}

			if ($timeshift != 0) {
				$values = [];

				foreach ($items_by_source['history'] as $itemid => $item) {
					$history = Manager::History()->getValueAt($item, $time_to, 0);

					if (is_array($history)) {
						$values[$itemid] = $history['value'];
					}
				}
			}
			else {
				$values = Manager::History()->getLastValues($items_by_source['history'], 1, $history_period);
				$values = array_column(array_column($values, 0), 'value', 'itemid');
			}

			$result += $values;
			$items = $items_by_source['trends'];
		}

		$values = Manager::History()->getAggregationByInterval($items, $time_from, $time_to, $function, $interval);
		$values = array_column(array_column(array_column($values, 'data'), 0),
			$function == AGGREGATE_COUNT ? 'count' : 'value', 'itemid'
		);

		$result += $values;

		return $result;
	}

	/**
	 * @param array $items
	 * @param int   $time_from
	 * @param int   $time_now
	 * @param int   $data_source
	 *
	 * @return void
	 */
	private static function addDataSource(array &$items, int $time_from, int $time_now, int $data_source): void {
		if ($data_source == CWidgetFieldColumnsList::HISTORY_DATA_HISTORY
				|| $data_source == CWidgetFieldColumnsList::HISTORY_DATA_TRENDS) {
			foreach ($items as &$item) {
				$item['source'] = $data_source == CWidgetFieldColumnsList::HISTORY_DATA_TRENDS
					&& ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64)
						? 'trends'
						: 'history';
			}
			unset($item);

			return;
		}

		static $hk_history_global, $global_history_time, $hk_trends_global, $global_trends_time;

		if ($hk_history_global === null) {
			$hk_history_global = CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL);

			if ($hk_history_global) {
				$global_history_time = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY));
			}
		}

		if ($hk_trends_global === null) {
			$hk_trends_global = CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL);

			if ($hk_history_global) {
				$global_trends_time = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS));
			}
		}

		if ($hk_history_global) {
			foreach ($items as &$item) {
				$item['history'] = $global_history_time;
			}
			unset($item);
		}

		if ($hk_trends_global) {
			foreach ($items as &$item) {
				$item['trends'] = $global_trends_time;
			}
			unset($item);
		}

		if (!$hk_history_global || !$hk_trends_global) {
			$items = CMacrosResolverHelper::resolveTimeUnitMacros($items,
				array_merge($hk_history_global ? [] : ['history'], $hk_trends_global ? [] : ['trends'])
			);

			$processed_items = [];

			foreach ($items as $itemid => $item) {
				if (!$global_trends_time) {
					$item['history'] = timeUnitToSeconds($item['history']);

					if ($item['history'] === null) {
						error(_s('Incorrect value for field "%1$s": %2$s.', 'history',
							_('invalid history storage period')
						));

						continue;
					}
				}

				if (!$hk_trends_global) {
					$item['trends'] = timeUnitToSeconds($item['trends']);

					if ($item['trends'] === null) {
						error(_s('Incorrect value for field "%1$s": %2$s.', 'trends',
							_('invalid trend storage period')
						));

						continue;
					}
				}

				$processed_items[$itemid] = $item;
			}

			$items = $processed_items;
		}

		foreach ($items as &$item) {
			$item['source'] = $item['trends'] == 0 || $time_now - $item['history'] <= $time_from ? 'history' : 'trends';
		}
		unset($item);
	}
}
