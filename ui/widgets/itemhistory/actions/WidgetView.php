<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


namespace Widgets\ItemHistory\Actions;

use API,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CItemHelper,
	CNumberParser,
	CSettingsHelper,
	Manager;

use	Widgets\ItemHistory\Includes\CWidgetFieldColumnsList;

class WidgetView extends CControllerDashboardWidgetView {

	protected function doAction(): void {
		$error = null;

		$name = $this->widget->getDefaultName();

		$data = [
			'name' => $this->getInput('name', $name),
			'layout' => $this->fields_values['layout'],
			'columns' => [],
			'item_values' => [],
			'show_thumbnail' => false,
			'show_lines' => $this->fields_values['show_lines'],
			'sortorder' => $this->fields_values['sortorder'],
			'show_timestamp' => $this->fields_values['show_timestamp'],
			'show_column_header' => $this->fields_values['show_column_header'],
			'error' => $error,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$override_hostid = $this->fields_values['override_hostid'] ? $this->fields_values['override_hostid'][0] : '';

		if ($override_hostid === '' && $this->isTemplateDashboard()) {
			$data['error'] = _('No data.');

			$this->setResponse(new CControllerResponseData($data));

			return;
		}

		$columns = $this->fields_values['columns'];

		$itemids = array_column($columns, 'itemid');
		$db_items = [];

		if ($columns) {
			if (!$this->isTemplateDashboard()) {
				$db_items = API::Item()->get([
					'output' => ['itemid', 'value_type', 'units', 'valuemapid', 'history', 'trends'],
					'selectValueMap' => ['mappings'],
					'hostids' => $override_hostid !== '' ? [$override_hostid] : null,
					'itemids' => $itemids,
					'webitems' => true,
					'preservekeys' => true
				]);
			}
			else {
				$db_item_keys = API::Item()->get([
					'output' => ['key_'],
					'itemids' => $itemids,
					'webitems' => true,
					'preservekeys' => true
				]);

				if ($db_item_keys) {
					$db_items = API::Item()->get([
						'output' => ['itemid', 'key_', 'value_type', 'units', 'valuemapid', 'history', 'trends'],
						'selectValueMap' => ['mappings'],
						'hostids' => [$override_hostid],
						'filter' => [
							'key_' => array_keys(array_column($db_item_keys, null, 'key_'))
						],
						'webitems' => true,
						'preservekeys' => true
					]);

					if ($db_items) {
						$itemid_per_key = array_column($db_items, 'itemid', 'key_');

						foreach ($columns as &$column) {
							if (array_key_exists($column['itemid'], $db_item_keys)
									&& array_key_exists($db_item_keys[$column['itemid']]['key_'], $itemid_per_key)) {
								$column['itemid'] = $itemid_per_key[$db_item_keys[$column['itemid']]['key_']];
							}
						}
						unset($column);
					}
				}
			}
		}

		if (!$db_items) {
			$data['error'] = _('No permissions to referred object or it does not exist!');

			$this->setResponse(new CControllerResponseData($data));

			return;
		}

		$columns = array_filter($columns, static function($column) use ($db_items) {
			return array_key_exists($column['itemid'], $db_items);
		});

		if (!$columns) {
			$this->setResponse(new CControllerResponseData($data));

			return;
		}

		$item_values_by_source = $this->getItemValuesByDataSource($db_items, $columns);
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
			'layout' => $this->fields_values['layout'],
			'columns' => $columns,
			'item_values' => $item_values,
			'show_lines' => $this->fields_values['show_lines'],
			'show_thumbnail' => $show_thumbnail,
			'sortorder' => $this->fields_values['sortorder'],
			'show_timestamp' => (bool) $this->fields_values['show_timestamp'],
			'show_column_header' => $this->fields_values['show_column_header'],
			'error' => $error,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	private function getItemValuesByDataSource(array $items, array &$columns_config): array {
		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		$time_from = time() - $history_period;

		$items_by_source = $this->addDataSourceAndPrepareColumns($items, $columns_config, $time_from);

		$result = [
			CWidgetFieldColumnsList::HISTORY_DATA_HISTORY => [],
			CWidgetFieldColumnsList::HISTORY_DATA_TRENDS => [],
			'binary_items' => []
		];

		if ($items_by_source[CWidgetFieldColumnsList::HISTORY_DATA_HISTORY]) {
			foreach ($items_by_source[CWidgetFieldColumnsList::HISTORY_DATA_HISTORY] as $item) {
				$result[CWidgetFieldColumnsList::HISTORY_DATA_HISTORY] += Manager::History()->getLastValues(
					[$item], $this->fields_values['show_lines'], $history_period
				);
			}
		}

		if ($items_by_source[CWidgetFieldColumnsList::HISTORY_DATA_TRENDS]) {
			$db_trends = Manager::History()->getAggregatedValues(
				$items_by_source[CWidgetFieldColumnsList::HISTORY_DATA_TRENDS], AGGREGATE_LAST, $time_from
			);

			foreach ($db_trends as $db_trend) {
				$result[CWidgetFieldColumnsList::HISTORY_DATA_TRENDS][$db_trend['itemid']][] = $db_trend + ['ns' => 0];
			}
		}

		if ($items_by_source['binary_items']) {
			$db_binary_items_values = API::History()->get([
				'history' => ITEM_VALUE_TYPE_BINARY,
				'itemids' => array_keys($items_by_source['binary_items']),
				'output' => ['itemid', 'clock', 'ns'],
				'limit' => $this->fields_values['show_lines']
			]) ?: [];

			foreach ($db_binary_items_values as $binary_items_value) {
				$result['binary_items'][$binary_items_value['itemid']][] = $binary_items_value;
			}
		}

		return $result;
	}

	private function addDataSourceAndPrepareColumns(array $items, array &$columns_config, int $time): array {
		$items_with_source = [
			CWidgetFieldColumnsList::HISTORY_DATA_TRENDS => [],
			CWidgetFieldColumnsList::HISTORY_DATA_HISTORY => [],
			'binary_items' => []
		];

		foreach ($columns_config as &$column) {
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
				else {
					$item['source'] = $column['history'] == CWidgetFieldColumnsList::HISTORY_DATA_HISTORY
						? 'history'
						: 'trends';
				}

				$items_with_source[$column['history']][$itemid] = $item;
			}
			else {
				if ($item['value_type'] == ITEM_VALUE_TYPE_BINARY) {
					$column['history'] = 'binary_items';
					$items_with_source['binary_items'][$itemid] = true;
				}
				else {
					$column['history'] = CWidgetFieldColumnsList::HISTORY_DATA_HISTORY;
					$item['source'] = 'history';
					$items_with_source[CWidgetFieldColumnsList::HISTORY_DATA_HISTORY][$itemid] = $item;
				}
			}
		}
		unset($column);

		return $items_with_source;
	}
}
