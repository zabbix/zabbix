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


namespace Widgets\TopHosts\Actions;

use API,
	CAggFunctionData,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CItemHelper,
	CMacrosResolverHelper,
	CNumberParser,
	CSettingsHelper,
	CSvgGraph,
	Manager;

use Widgets\TopHosts\Widget;
use Widgets\TopHosts\Includes\CWidgetFieldColumnsList;

class WidgetView extends CControllerDashboardWidgetView {

	/** @property int $sparkline_max_samples  Limit of samples when requesting sparkline graph data for time period. */
	protected int $sparkline_max_samples;

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'contents_width'	=> 'int32'
		]);
	}

	protected function doAction(): void {
		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'error' => null,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		if (!$this->fields_values['override_hostid'] && $this->isTemplateDashboard()) {
			$data['configuration'] = $this->fields_values['columns'];
			$data['show_thumbnail'] = false;
			$data['rows'] = [];
		}
		else {
			$data += $this->getData();
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getData(): array {
		$columns = $this->fields_values['columns'];

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
			'output' => ['name', 'maintenance_status', 'maintenance_type', 'maintenanceid'],
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
				'output' => ['name', 'description'],
				'maintenanceids' => $maintenanceids,
				'preservekeys' => true
			])
			: [];

		$has_text_column = false;
		$show_thumbnail = false;
		$item_names = [];
		$items = [];
		$this->sparkline_max_samples = ceil($this->getInput('contents_width') / count($columns));

		foreach ($columns as $column_index => $column) {
			if ($column['data'] == CWidgetFieldColumnsList::DATA_TEXT) {
				$has_text_column = true;
			}
			elseif ($column['data'] == CWidgetFieldColumnsList::DATA_ITEM_VALUE) {
				$item_names[$column_index] = $column['item'];

				if ($column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_BINARY
						&& $column['show_thumbnail'] == 1) {
					$show_thumbnail = true;
				}
			}
		}

		if (!$has_text_column && $item_names) {
			$hosts_with_items = [];

			foreach ($item_names as $column_index => $item_name) {
				$numeric_only = self::isNumericOnlyColumn($columns[$column_index]);
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
				'configuration' => $columns,
				'show_thumbnail' => $show_thumbnail,
				'rows' => []
			];
		}

		$master_column_index = $this->fields_values['column'];
		$master_column = $columns[$master_column_index];
		$master_entities = $hosts;
		$master_entity_values = [];
		$master_sparkline_values = [];

		switch ($master_column['data']) {
			case CWidgetFieldColumnsList::DATA_ITEM_VALUE:
				$numeric_only = self::isNumericOnlyColumn($master_column);
				$master_entities = array_key_exists($master_column_index, $items)
					? $items[$master_column_index]
					: self::getItems($master_column['item'], $numeric_only, $groupids, $hostids);

				$master_entity_values = self::getItemValues($master_entities, $master_column);

				if ($master_column['display'] == CWidgetFieldColumnsList::DISPLAY_SPARKLINE) {
					$config = $master_column + ['contents_width' => $this->sparkline_max_samples];
					$master_sparkline_values = self::getItemSparklineValues($master_entities, $config);
				}

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

		$master_items_only_numeric_present = $master_column['data'] == CWidgetFieldColumnsList::DATA_ITEM_VALUE
			&& ($master_column['aggregate_function'] == AGGREGATE_COUNT
				|| !array_filter($master_entities,
					static function(array $item): bool {
						return !in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
					}
				)
			);

		if ($this->fields_values['order'] == Widget::ORDER_TOP_N) {
			if ($master_items_only_numeric_present) {
				arsort($master_entity_values, SORT_NUMERIC);

				$master_entities_min = end($master_entity_values);
				$master_entities_max = reset($master_entity_values);
			}
			elseif ($master_column['data'] != CWidgetFieldColumnsList::DATA_ITEM_VALUE
					|| $master_column['display_value_as'] != CWidgetFieldColumnsList::DISPLAY_VALUE_AS_BINARY) {
				natcasesort($master_entity_values);
			}
		}
		else {
			if ($master_items_only_numeric_present) {
				asort($master_entity_values, SORT_NUMERIC);

				$master_entities_min = reset($master_entity_values);
				$master_entities_max = end($master_entity_values);
			}
			elseif ($master_column['data'] != CWidgetFieldColumnsList::DATA_ITEM_VALUE
					|| $master_column['display_value_as'] != CWidgetFieldColumnsList::DISPLAY_VALUE_AS_BINARY) {
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

		foreach ($columns as $column_index => &$column) {
			if ($column['data'] == CWidgetFieldColumnsList::DATA_TEXT) {
				$text_columns[$column_index] = $column['text'];
				continue;
			}

			if ($column['data'] != CWidgetFieldColumnsList::DATA_ITEM_VALUE) {
				continue;
			}

			$sparkline_item_values = [];
			$calc_extremes = $column['display'] == CWidgetFieldColumnsList::DISPLAY_BAR
				|| $column['display'] == CWidgetFieldColumnsList::DISPLAY_INDICATORS;

			$column += ['min' => '', 'min_binary' => '', 'max' => '', 'max_binary' => ''];

			if ($column_index == $master_column_index) {
				$column_items = $master_entities;
				$column_item_values = $master_entity_values;
				$sparkline_item_values = $master_sparkline_values;
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

				$column_item_values = self::getItemValues($column_items, $column);

				if ($column['display'] == CWidgetFieldColumnsList::DISPLAY_SPARKLINE) {
					$config = $column + ['contents_width' => $this->sparkline_max_samples];
					$sparkline_item_values = self::getItemSparklineValues($column_items, $config);
				}
			}

			if ($calc_extremes) {
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

				if ($column_index == $master_column_index) {
					if ($column['min'] === '') {
						$column['min'] = $master_entities_min;
						$column['min_binary'] = $column['min'];
					}

					if ($column['max'] === '') {
						$column['max'] = $master_entities_max;
						$column['max_binary'] = $column['max'];
					}
				}
				elseif ($column_item_values) {
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

			if (array_key_exists('thresholds', $column)) {
				foreach ($column['thresholds'] as &$threshold) {
					$number_parser_binary->parse($threshold['threshold']);
					$threshold['threshold_binary'] = $number_parser_binary->calcValue();

					$number_parser->parse($threshold['threshold']);
					$threshold['threshold'] = $number_parser->calcValue();
				}
				unset($threshold);
			}

			$item_values[$column_index] = [];

			foreach ($column_items as $itemid => $item) {
				$hostid = $column_items[$itemid]['hostid'];

				$column_value = [];

				if (array_key_exists($itemid, $column_item_values)) {
					$column_value['value'] = $column_item_values[$itemid];
				}

				if (array_key_exists($itemid, $sparkline_item_values)) {
					$column_value['sparkline_value'] = $sparkline_item_values[$itemid];
				}

				if ($column_value && in_array($hostid, $master_hostids)) {
					$item_values[$column_index][$hostid] = [
						'item' => $column_items[$itemid],
						'is_binary_units' => isBinaryUnits($column_items[$itemid]['units'])
					] + $column_value;
				}
			}
		}
		unset($column);

		$text_columns = CMacrosResolverHelper::resolveWidgetTopHostsTextColumns($text_columns, $master_hostids);

		$rows = [];

		foreach ($master_hostids as $hostid) {
			$row = [];

			foreach ($columns as $column_index => $column) {
				switch ($column['data']) {
					case CWidgetFieldColumnsList::DATA_HOST_NAME:
						$data = [
							'value' => $hosts[$hostid]['name'],
							'hostid' => $hostid,
							'maintenance_status' => $hosts[$hostid]['maintenance_status']
						];

						if ($data['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
							$data['maintenance_type'] = $hosts[$hostid]['maintenance_type'];

							if (array_key_exists($hosts[$hostid]['maintenanceid'], $db_maintenances)) {
								$maintenance = $db_maintenances[$hosts[$hostid]['maintenanceid']];

								$data['maintenance_name'] = $maintenance['name'];
								$data['maintenance_description'] = $maintenance['description'];
							}
							else {
								$data['maintenance_name'] = _('Inaccessible maintenance');
								$data['maintenance_description'] = '';
							}
						}

						$row[] = $data;

						break;

					case CWidgetFieldColumnsList::DATA_TEXT:
						$row[] = ['value' => $text_columns[$column_index][$hostid]];

						break;

					case CWidgetFieldColumnsList::DATA_ITEM_VALUE:
						$row[] = array_key_exists($hostid, $item_values[$column_index])
							? $item_values[$column_index][$hostid]
							: null;

						break;
				}
			}

			$rows[] = [
				'columns' => $row,
				'context' => ['hostid' => $hostid]
			];
		}

		return [
			'configuration' => $columns,
			'show_thumbnail' => $show_thumbnail,
			'rows' => $rows
		];
	}

	/**
	 * Check if column configuration requires selecting numeric items only.
	 *
	 * @param array $column  Column configuration.
	 *
	 * @return bool
	 */
	private static function isNumericOnlyColumn(array $column): bool {
		if ($column['display'] == CWidgetFieldColumnsList::DISPLAY_AS_IS) {
			return CAggFunctionData::requiresNumericItem($column['aggregate_function']);
		}

		return $column['aggregate_function'] != AGGREGATE_COUNT;
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
				'name_resolved' => $name,
				'status' => ITEM_STATUS_ACTIVE,
				'value_type' => $numeric_only ? [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64] : null
			],
			'sortfield' => 'key_',
			'preservekeys' => true
		]);

		if ($items) {
			$processed_hostids = [];

			$items = array_filter($items, static function ($item) use (&$processed_hostids) {
				if (array_key_exists($item['hostid'], $processed_hostids)) {
					return false;
				}

				$processed_hostids[$item['hostid']] = true;

				return true;
			});
		}

		return $items;
	}

	/**
	 * Return sparkline graph item values, applies data function SVG_GRAPH_MISSING_DATA_NONE on points for each item.
	 *
	 * @param array $items   Items required to get sparkline data for.
	 * @param array $column  Column configuration with sparkline configuration data.
	 *
	 * @return array itemid as key, sparkline data array of arrays as value, itemid with no data will be not present.
	 */
	private static function getItemSparklineValues(array $items, array $column): array {
		$result = [];
		$sparkline = $column['sparkline'];
		$items_by_valuetype = self::addDataSource($items, $sparkline['time_period']['from_ts'],
			['history' => $sparkline['history']] + $column
		);
		$items = array_key_exists(ITEM_VALUE_TYPE_FLOAT, $items_by_valuetype)
			? $items_by_valuetype[ITEM_VALUE_TYPE_FLOAT]
			: [];

		if (array_key_exists(ITEM_VALUE_TYPE_UINT64, $items_by_valuetype)) {
			$items = array_merge($items, $items_by_valuetype[ITEM_VALUE_TYPE_UINT64]);
		}

		if (!$items) {
			return $result;
		}

		$itemids_rows = Manager::History()->getGraphAggregationByWidth($items, $sparkline['time_period']['from_ts'],
			$sparkline['time_period']['to_ts'], $column['contents_width']
		);

		foreach ($itemids_rows as $itemid => $rows) {
			if (!$rows['data']) {
				continue;
			}

			$result[$itemid] = [];
			$points = array_column($rows['data'], 'avg', 'clock');
			/**
			 * Postgres may return entries in mixed 'clock' order, getMissingData for calculations
			 * requires order by 'clock'.
			 */
			ksort($points);
			$points += CSvgGraph::getMissingData($points, SVG_GRAPH_MISSING_DATA_NONE);
			ksort($points);

			foreach ($points as $ts => $value) {
				$result[$itemid][] = [$ts, $value];
			}
		}

		return $result;
	}

	private static function getItemValues(array $items, array $column): array {
		static $history_period_s;

		if ($history_period_s === null) {
			$history_period_s = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		}

		$time_from = $column['aggregate_function'] != AGGREGATE_NONE
			? $column['time_period']['from_ts']
			: time() - $history_period_s;

		$items_by_value_type = self::addDataSource($items, $time_from, $column);

		$result = [];

		if ($column['aggregate_function'] != AGGREGATE_NONE) {
			foreach ($items_by_value_type as $value_type => $items) {
				if ($value_type == ITEM_VALUE_TYPE_BINARY) {
					$output = $column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_BINARY
						? ['itemid', 'clock', 'ns']
						: ['itemid', 'value'];

					foreach (array_keys($items) as $itemid) {
						switch ($column['aggregate_function']) {
							case AGGREGATE_LAST:
							case AGGREGATE_FIRST:
								$db_values = API::History()->get([
									'output' => $output,
									'history' => ITEM_VALUE_TYPE_BINARY,
									'itemids' => $itemid,
									'time_from' => $column['time_period']['from_ts'],
									'time_till' => $column['time_period']['to_ts'],
									'sortfield' => ['clock', 'ns'],
									'sortorder' => $column['aggregate_function'] == AGGREGATE_LAST
										? ZBX_SORT_DOWN
										: ZBX_SORT_UP,
									'limit' => 1
								]);

								if ($db_values) {
									$result[$db_values[0]['itemid']] =
										$column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_BINARY
											? [
												'clock' => $db_values[0]['clock'],
												'ns' => $db_values[0]['ns']
											]
											: $db_values[0]['value'];
								}

								break;

							case AGGREGATE_COUNT:
								$db_values = API::History()->get([
									'output' => ['itemid'],
									'history' => ITEM_VALUE_TYPE_BINARY,
									'itemids' => $itemid,
									'time_from' => $column['time_period']['from_ts'],
									'time_till' => $column['time_period']['to_ts']
								]);

								if ($db_values) {
									$result[$db_values[0]['itemid']] = count($db_values);
								}

								break;
						}
					}
				}
				else {
					$values = Manager::History()->getAggregatedValues($items, $column['aggregate_function'], $time_from,
						$column['time_period']['to_ts']
					);

					$result += array_column($values, 'value', 'itemid');
				}
			}
		}
		else {
			$items_by_source = ['history' => [], 'trends' => []];

			foreach ($items_by_value_type as $value_type => $items) {
				if ($value_type == ITEM_VALUE_TYPE_BINARY) {
					$output = $column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_BINARY
						? ['itemid', 'clock', 'ns']
						: ['itemid', 'value'];

					foreach (array_keys($items) as $itemid) {
						$db_values = API::History()->get([
							'output' => $output,
							'history' => ITEM_VALUE_TYPE_BINARY,
							'itemids' => $itemid,
							'sortfield' => ['clock', 'ns'],
							'sortorder' => ZBX_SORT_DOWN,
							'limit' => 1
						]);

						if ($db_values) {
							$result[$db_values[0]['itemid']] =
								$column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_BINARY
									? [
										'clock' => $db_values[0]['clock'],
										'ns' => $db_values[0]['ns']
									]
									: $db_values[0]['value'];
						}
					}
				}
				else {
					foreach ($items as $itemid => $item) {
						$items_by_source[$item['source']][$itemid] = $item;
					}

					if ($items_by_source['history']) {
						$values = Manager::History()->getLastValues($items_by_source['history'], 1, $history_period_s);

						$result += array_column(array_column($values, 0), 'value', 'itemid');
					}

					if ($items_by_source['trends']) {
						$values = Manager::History()->getAggregatedValues($items_by_source['trends'], AGGREGATE_LAST,
							$time_from
						);

						$result += array_column($values, 'value', 'itemid');
					}
				}
			}
		}

		return $result;
	}

	private static function addDataSource(array $items, int $time, array $column): array {
		$items_by_history_type = [];

		if ($column['history'] == CWidgetFieldColumnsList::HISTORY_DATA_AUTO) {
			$items = CItemHelper::addDataSource($items, $time);
		}
		else {
			foreach ($items as &$item) {
				$item['source'] = $column['history'] == CWidgetFieldColumnsList::HISTORY_DATA_TRENDS
					? 'trends'
					: 'history';
			}
			unset($item);
		}

		foreach ($items as &$item) {
			if (!in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
				$item['source'] = 'history';
			}

			$items_by_history_type[$item['value_type']][$item['itemid']] = $item;
		}
		unset($item);

		return $items_by_history_type;
	}
}
