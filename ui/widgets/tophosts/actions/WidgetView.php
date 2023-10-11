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


namespace Widgets\TopHosts\Actions;

use API,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CHousekeepingHelper,
	CMacrosResolverHelper,
	CNumberParser,
	CSettingsHelper,
	Manager;

use Widgets\TopHosts\Widget;

use Zabbix\Widgets\Fields\CWidgetFieldColumnsList;

class WidgetView extends CControllerDashboardWidgetView {

	protected function doAction(): void {
		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		// Editing template dashboard?
		if ($this->isTemplateDashboard() && !$this->fields_values['override_hostid']) {
			$data['error'] = _('No data.');
		}
		else {
			$data += $this->getData();
			$data['error'] = null;
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getData(): array {
		$configuration = $this->fields_values['columns'];

		$groupids = !$this->isTemplateDashboard() && $this->fields_values['groupids']
			? getSubGroups($this->fields_values['groupids'])
			: null;

		if ($this->isTemplateDashboard()) {
			$hostids = $this->fields_values['override_hostid'];
		}
		else {
			$hostids = $this->fields_values['hostids'] ?: null;
		}

		$tags_exist = array_key_exists('tags', $this->fields_values);
		$maintenance_status = $this->fields_values['maintenance'] == HOST_MAINTENANCE_STATUS_OFF
			? HOST_MAINTENANCE_STATUS_OFF
			: null;

		$hosts = API::Host()->get([
			'output' => ['name', 'maintenance_status', 'maintenanceid'],
			'groupids' => $groupids,
			'hostids' => $hostids,
			'evaltype' => $tags_exist ? $this->fields_values['evaltype'] : null,
			'tags' => $tags_exist ? $this->fields_values['tags'] : null,
			'filter' => ['maintenance_status' => $maintenance_status],
			'monitored_hosts' => true,
			'preservekeys' => true
		]);

		$hostids = array_keys($hosts);
		$maintenanceids = array_filter(array_column($hosts, 'maintenanceid', 'maintenanceid'));

		$db_maintenances = $maintenanceids && $maintenance_status === null
			? API::Maintenance()->get([
				'output' => ['name', 'maintenance_type', 'description'],
				'maintenanceids' => $maintenanceids,
				'preservekeys' => true
			])
			: [];

		$db_maintenances = CArrayHelper::renameObjectsKeys($db_maintenances,
			['name' => 'maintenance_name', 'description' => 'maintenance_description']
		);

		$has_text_column = false;
		$item_names = [];
		$items = [];

		foreach ($configuration as $column_index => $column) {
			switch ($column['data']) {
				case CWidgetFieldColumnsList::DATA_TEXT:
					$has_text_column = true;
					break 2;

				case CWidgetFieldColumnsList::DATA_ITEM_VALUE:
					$item_names[$column_index] = $column['item'];
					break;
			}
		}

		if (!$has_text_column && $item_names) {
			$hosts_with_items = [];

			foreach ($item_names as $column_index => $item_name) {
				$numeric_only = self::isNumericOnlyColumn($configuration[$column_index]);
				$items[$column_index] = self::getItems($item_name, $numeric_only, $groupids, $hostids);

				foreach ($items[$column_index] as $item) {
					$hosts_with_items[$item['hostid']] = true;
				}
			}

			$hostids = array_keys($hosts_with_items);
			$hosts = array_intersect_key($hosts, $hosts_with_items);
		}

		if (!$hostids) {
			return [
				'configuration' => $configuration,
				'rows' => []
			];
		}

		$time_now = time();

		$master_column_index = $this->fields_values['column'];
		$master_column = $configuration[$master_column_index];
		$master_entities = $hosts;
		$master_entity_values = [];
		$master_items_only_numeric_allowed = false;

		switch ($master_column['data']) {
			case CWidgetFieldColumnsList::DATA_ITEM_VALUE:
				$master_items_only_numeric_allowed = self::isNumericOnlyColumn($master_column);
				$master_entities = array_key_exists($master_column_index, $items)
					? $items[$master_column_index]
					: self::getItems($master_column['item'], $master_items_only_numeric_allowed, $groupids, $hostids);
				$master_entity_values = self::getItemValues($master_entities, $master_column, $time_now);
				break;

			case CWidgetFieldColumnsList::DATA_HOST_NAME:
				$master_entity_values = array_column($master_entities, 'name', 'hostid');
				break;

			case CWidgetFieldColumnsList::DATA_TEXT:
				$master_entity_values = CMacrosResolverHelper::resolveWidgetTopHostsTextColumns(
					[$master_column_index => $master_column['text']], $hostids
				)[$master_column_index];

				foreach ($master_entity_values as $key => $value) {
					if ($value === '') {
						unset($master_entity_values[$key], $master_entities[$key]);
					}
				}

				break;
		}

		$master_items_only_numeric_present = $master_items_only_numeric_allowed && !array_filter($master_entities,
			static function(array $item): bool {
				return !in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
			}
		);

		if ($this->fields_values['order'] == Widget::ORDER_TOP_N) {
			if ($master_items_only_numeric_present) {
				arsort($master_entity_values, SORT_NUMERIC);

				$master_entities_min = end($master_entity_values);
				$master_entities_max = reset($master_entity_values);
			}
			else {
				natcasesort($master_entity_values);
			}
		}
		else {
			if ($master_items_only_numeric_present) {
				asort($master_entity_values, SORT_NUMERIC);

				$master_entities_min = reset($master_entity_values);
				$master_entities_max = end($master_entity_values);
			}
			else {
				natcasesort($master_entity_values);
				$master_entity_values = array_reverse($master_entity_values, true);
			}
		}

		$show_lines = $this->isTemplateDashboard() ? 1 : $this->fields_values['show_lines'];
		$master_entity_values = array_slice($master_entity_values, 0, $show_lines, true);
		$master_entities = array_intersect_key($master_entities, $master_entity_values);

		$master_hostids = [];

		foreach (array_keys($master_entity_values) as $entity) {
			$master_hostids[$master_entities[$entity]['hostid']] = true;
		}

		if (count($master_hostids) < $show_lines) {
			foreach ($hostids as $hostid) {
				if (!array_key_exists($hostid, $master_hostids)) {
					$master_hostids[$hostid] = true;
				}

				if (count($master_hostids) == $show_lines) {
					break;
				}
			}
		}

		$master_hostids = array_keys($master_hostids);

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

		$item_values = [];
		$text_columns = [];

		foreach ($configuration as $column_index => &$column) {
			if ($column['data'] == CWidgetFieldColumnsList::DATA_TEXT) {
				$text_columns[$column_index] = $column['text'];
				continue;
			}

			if ($column['data'] != CWidgetFieldColumnsList::DATA_ITEM_VALUE) {
				continue;
			}

			$calc_extremes = $column['display'] == CWidgetFieldColumnsList::DISPLAY_BAR
				|| $column['display'] == CWidgetFieldColumnsList::DISPLAY_INDICATORS;

			if ($column_index == $master_column_index) {
				$column_items = $master_entities;
				$column_item_values = $master_entity_values;
			}
			else {
				$numeric_only = self::isNumericOnlyColumn($column);

				if (!$calc_extremes || ($column['min'] !== '' && $column['max'] !== '')) {
					$column_items = self::getItems($column['item'], $numeric_only, $groupids, $master_hostids);
				}
				else {
					$column_items = array_key_exists($column_index, $items)
						? $items[$column_index]
						: self::getItems($column['item'], $numeric_only, $groupids, $hostids);
				}

				$column_item_values = self::getItemValues($column_items, $column, $time_now);
			}

			if ($calc_extremes && ($column['min'] !== '' || $column['max'] !== '')) {
				if ($column['min'] !== '') {
					$number_parser_binary->parse($column['min']);
					$column['min_binary'] = $number_parser_binary->calcValue();

					$number_parser->parse($column['min']);
					$column['min'] = $number_parser->calcValue();
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

			if ($column_index == $master_column_index) {
				if ($calc_extremes) {
					if ($column['min'] === '') {
						$column['min'] = $master_entities_min;
						$column['min_binary'] = $column['min'];
					}

					if ($column['max'] === '') {
						$column['max'] = $master_entities_max;
						$column['max_binary'] = $column['max'];
					}
				}
			}
			else {
				if ($calc_extremes && $column_item_values) {
					if ($column['min'] === '') {
						$column['min'] = min($column_item_values);
						$column['min_binary'] = $column['min'];
					}

					if ($column['max'] === '') {
						$column['max'] = max($column_item_values);
						$column['max_binary'] = $column['max'];
					}
				}
			}

			$item_values[$column_index] = [];

			foreach ($column_item_values as $itemid => $column_item_value) {
				if (in_array($column_items[$itemid]['hostid'], $master_hostids)) {
					$item_values[$column_index][$column_items[$itemid]['hostid']] = [
						'value' => $column_item_value,
						'item' => $column_items[$itemid],
						'is_binary_units' => isBinaryUnits($column_items[$itemid]['units'])
					];
				}
			}
		}
		unset($column);

		$text_columns = CMacrosResolverHelper::resolveWidgetTopHostsTextColumns($text_columns, $master_hostids);

		$rows = [];

		foreach ($master_hostids as $hostid) {
			$row = [];

			foreach ($configuration as $column_index => $column) {
				switch ($column['data']) {
					case CWidgetFieldColumnsList::DATA_HOST_NAME:
						$data = [
							'value' => $hosts[$hostid]['name'],
							'hostid' => $hostid,
							'maintenance_status' => $hosts[$hostid]['maintenance_status']
						];

						if ($data['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
							$data = array_merge($data, $db_maintenances[$hosts[$hostid]['maintenanceid']]);
						}

						$row[] = $data;

						break;

					case CWidgetFieldColumnsList::DATA_TEXT:
						$row[] = ['value' => $text_columns[$column_index][$hostid]];

						break;

					case CWidgetFieldColumnsList::DATA_ITEM_VALUE:
						$row[] = array_key_exists($hostid, $item_values[$column_index])
							? [
								'value' => $item_values[$column_index][$hostid]['value'],
								'item' => $item_values[$column_index][$hostid]['item'],
								'is_binary_units' => $item_values[$column_index][$hostid]['is_binary_units']
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

	private static function isNumericOnlyColumn(array $column): bool {
		return $column['aggregate_function'] != AGGREGATE_NONE
			|| $column['display'] != CWidgetFieldColumnsList::DISPLAY_AS_IS
			|| array_key_exists('thresholds', $column);
	}

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
