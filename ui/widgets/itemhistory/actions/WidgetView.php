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


namespace Widgets\ItemHistory\Actions;

use API,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CItemHelper,
	CNumberParser;

use	Widgets\ItemHistory\Includes\CWidgetFieldColumnsList;

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'has_custom_time_period' => 'in 1'
		]);
	}

	protected function doAction(): void {
		$name = $this->widget->getDefaultName();

		$data = [
			'name' => $this->getInput('name', $name),
			'info' => $this->makeWidgetInfo(),
			'columns' => [],
			'item_values' => [],
			'layout' => $this->fields_values['layout'],
			'show_lines' => $this->fields_values['show_lines'],
			'show_column_header' => $this->fields_values['show_column_header'],
			'show_thumbnail' => false,
			'show_timestamp' => $this->fields_values['show_timestamp'],
			'sortorder' => $this->fields_values['sortorder'],
			'error' => null,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		if (!$this->fields_values['override_hostid'] && $this->isTemplateDashboard()) {
			$this->setResponse(new CControllerResponseData($data));

			return;
		}

		$columns = $this->fields_values['columns'];
		$db_items = [];

		if ($columns) {
			if ($this->fields_values['override_hostid']) {
				$db_item_keys = API::Item()->get([
					'output' => ['key_'],
					'itemids' => array_column($columns, 'itemid'),
					'webitems' => true,
					'preservekeys' => true
				]);

				if ($db_item_keys) {
					$db_items = API::Item()->get([
						'output' => ['key_', 'value_type', 'units', 'valuemapid', 'history', 'trends'],
						'selectValueMap' => ['mappings'],
						'hostids' => $this->fields_values['override_hostid'],
						'filter' => [
							'key_' => array_column($db_item_keys, 'key_')
						],
						'webitems' => true,
						'preservekeys' => true
					]);

					$itemid_by_key = $db_items
						? array_combine(array_column($db_items, 'key_'), array_keys($db_items))
						: [];

					foreach ($columns as &$column) {
						$column['itemid'] = array_key_exists($column['itemid'], $db_item_keys)
							? $itemid_by_key[$db_item_keys[$column['itemid']]['key_']] ?? null
							: null;
					}
					unset($column);
				}
			}
			else {
				$db_items = API::Item()->get([
					'output' => ['key_', 'value_type', 'units', 'valuemapid', 'history', 'trends'],
					'selectValueMap' => ['mappings'],
					'itemids' => array_column($columns, 'itemid'),
					'webitems' => true,
					'preservekeys' => true
				]);

				foreach ($columns as &$column) {
					if (!array_key_exists($column['itemid'], $db_items)) {
						$column['itemid'] = null;
					}
				}
				unset($column);
			}

			$columns = $db_items
				? array_filter($columns, static function($column) {
					return $column['itemid'] !== null;
				})
				: [];
		}

		if (!$columns) {
			$this->setResponse(new CControllerResponseData($data));

			return;
		}

		$item_values_by_source = $this->getItemValuesByDataSource($columns, $db_items);
		$item_values = [];

		$number_parser = new CNumberParser([
			'with_size_suffix' => true,
			'with_time_suffix' => true,
			'is_binary_size' => false
		]);

		$number_parser_binary = new CNumberParser([
			'with_size_suffix' => true,
			'with_time_suffix' => true,
			'is_binary_size' => true
		]);

		$show_thumbnail = false;

		foreach ($columns as $index => &$column) {
			if (array_key_exists('show_thumbnail', $column) && $column['show_thumbnail'] == 1) {
				$show_thumbnail = true;
			}

			if (array_key_exists($column['itemid'], $item_values_by_source[$column['history']])) {
				$column_item_values = $item_values_by_source[$column['history']][$column['itemid']];

				$value_type_number = in_array($column['item_value_type'],
					[ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]
				);

				if ($value_type_number) {
					$column['has_binary_units'] = isBinaryUnits($db_items[$column['itemid']]['units']);

					if ($column['display'] == CWidgetFieldColumnsList::DISPLAY_BAR
							|| $column['display'] == CWidgetFieldColumnsList::DISPLAY_INDICATORS) {

						$values = array_column($column_item_values, 'value');

						if (!array_key_exists('min', $column) || $column['min'] === '') {
							$column['min'] = min($values);
							$column['min_binary'] = $column['min'];
						}

						if ($column['min'] !== '') {
							$number_parser_binary->parse($column['min']);
							$column['min_binary'] = $number_parser_binary->calcValue();

							$number_parser->parse($column['min']);
							$column['min'] = $number_parser->calcValue();
						}

						if (!array_key_exists('max', $column) || $column['max'] === '') {
							$column['max'] = max($values);
							$column['max_binary'] = $column['max'];
						}

						if ($column['max'] !== '') {
							$number_parser_binary->parse($column['max']);
							$column['max_binary'] = $number_parser_binary->calcValue();

							$number_parser->parse($column['max']);
							$column['max'] = $number_parser->calcValue();
						}
					}

					if (array_key_exists('thresholds', $column)) {
						foreach ($column['thresholds'] as &$threshold) {
							$number_parser_binary->parse($threshold['threshold']);
							$threshold['threshold_binary'] = $number_parser_binary->calcValue();

							$number_parser->parse($threshold['threshold']);
							$threshold['threshold'] = $number_parser->calcValue();
						}
						unset($threshold);
					}
				}

				foreach ($column_item_values as $item_value) {
					$item_values[] = array_merge($item_value, [
						'column_index' => $index,
						'formatted_value' => $value_type_number
							? formatHistoryValue($item_value['value'], $db_items[$column['itemid']], false)
							: ''
					]);
				}
			}
		}
		unset($column);

		CArrayHelper::sort($item_values, [
			['field' => 'clock', 'order' => ZBX_SORT_DOWN],
			['field' => 'ns', 'order' => ZBX_SORT_DOWN]
		]);

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $name),
			'info' => $this->makeWidgetInfo(),
			'columns' => $columns,
			'item_values' => $item_values,
			'layout' => $this->fields_values['layout'],
			'show_lines' => $this->fields_values['show_lines'],
			'show_column_header' => $this->fields_values['show_column_header'],
			'show_thumbnail' => $show_thumbnail,
			'show_timestamp' => (bool) $this->fields_values['show_timestamp'],
			'sortorder' => $this->fields_values['sortorder'],
			'error' => null,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	private function getItemValuesByDataSource(array &$columns_config, array $items): array {
		$time_from = $this->fields_values['time_period']['from_ts'];
		$time_to = $this->fields_values['time_period']['to_ts'];

		$items_by_source = $this->addDataSourceAndPrepareColumns($columns_config, $items, $time_from);

		$result = [
			CWidgetFieldColumnsList::HISTORY_DATA_HISTORY => [],
			CWidgetFieldColumnsList::HISTORY_DATA_TRENDS => []
		];

		foreach ($items_by_source[CWidgetFieldColumnsList::HISTORY_DATA_HISTORY] as $value_type => $items) {
			$itemids = array_keys($items);

			switch ($value_type) {
				case ITEM_VALUE_TYPE_LOG:
					$output = ['itemid', 'value', 'clock', 'ns', 'timestamp'];
					break;
				case ITEM_VALUE_TYPE_BINARY:
					$output = ['itemid', 'clock', 'ns'];
					break;
				default:
					$output = ['itemid', 'value', 'clock', 'ns'];
					break;
			}

			$db_items_values = API::History()->get([
				'output' => $output,
				'history' => $value_type,
				'itemids' => $itemids,
				'time_from' => $time_from,
				'time_till' => $time_to,
				'sortfield' => ['clock', 'ns'],
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => $this->fields_values['show_lines'] * count($itemids)
			]);

			foreach ($db_items_values as $db_item_value) {
				$result[CWidgetFieldColumnsList::HISTORY_DATA_HISTORY][$db_item_value['itemid']][] = $db_item_value
					+ ['key_' => $items[$db_item_value['itemid']]['key_']];
			}
		}

		foreach ($items_by_source[CWidgetFieldColumnsList::HISTORY_DATA_TRENDS] as $items) {
			$itemids = array_keys($items);

			$db_items_trends = API::Trend()->get([
				'output' => ['itemid', 'value_avg', 'clock'],
				'itemids' => $itemids,
				'time_from' => $time_from,
				'time_till' => $time_to,
				'sortfield' => 'clock',
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => $this->fields_values['show_lines'] * count($itemids)
			]);

			foreach ($db_items_trends as $db_item_trend) {
				$result[CWidgetFieldColumnsList::HISTORY_DATA_TRENDS][$db_item_trend['itemid']][] = [
					'itemid' => $db_item_trend['itemid'],
					'value' => $db_item_trend['value_avg'],
					'clock' => $db_item_trend['clock'],
					'ns' => 0,
					'key_' => $items[$db_item_trend['itemid']]['key_']
				];
			}
		}

		return $result;
	}

	private function addDataSourceAndPrepareColumns(array &$columns, array $items, int $time): array {
		$items_with_source = [
			CWidgetFieldColumnsList::HISTORY_DATA_TRENDS => [],
			CWidgetFieldColumnsList::HISTORY_DATA_HISTORY => []
		];

		foreach ($columns as &$column) {
			$itemid = $column['itemid'];
			$item = $items[$itemid];
			$column['item_value_type'] = $item['value_type'];

			if (in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
				if ($column['history'] == CWidgetFieldColumnsList::HISTORY_DATA_AUTO) {
					[$item] = CItemHelper::addDataSource([$item], $time);

					$column['history'] = $item['source'] === 'history'
						? CWidgetFieldColumnsList::HISTORY_DATA_HISTORY
						: CWidgetFieldColumnsList::HISTORY_DATA_TRENDS;
				}
			}
			else {
				$column['history'] = CWidgetFieldColumnsList::HISTORY_DATA_HISTORY;
			}

			$items_with_source[$column['history']][$item['value_type']][$itemid] = $item;
		}
		unset($column);

		return $items_with_source;
	}

	/**
	 * Make widget specific info to show in widget's header.
	 */
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
}
