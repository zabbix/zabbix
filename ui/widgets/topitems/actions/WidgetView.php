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


namespace Widgets\TopItems\Actions;

use API,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CItemHelper,
	CNumberParser,
	CSettingsHelper,
	CWidgetsData,
	CSvgGraph,
	Manager;

use Widgets\TopItems\Includes\{
	WidgetForm,
	CWidgetFieldColumnsList
};

use Widgets\TopItems\Widget;
use Zabbix\Widgets\CWidgetField;

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
			$data['is_template_dashboard'] = $this->isTemplateDashboard();
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getData(): array {
		$db_hosts = $this->getHosts();

		if (!$db_hosts) {
			return ['error' => _('No data.')];
		}

		$db_items = [];
		$column_tables = [];

		$columns = $this->getPreparedColumns();
		$this->sparkline_max_samples = ceil($this->getInput('contents_width') / count($columns));

		foreach ($columns as $column_index => $column) {
			$db_column_items = $this->getItems($column, array_keys($db_hosts));
			if (!$db_column_items) {
				continue;
			}

			// Each column has different aggregation function and time period.
			$db_values = self::getItemValues($db_column_items, $column);

			if ($column['display'] == CWidgetFieldColumnsList::DISPLAY_SPARKLINE) {
				$config = $column + ['contents_width' => $this->sparkline_max_samples];
				$db_sparkline_values = self::getItemSparklineValues($db_column_items, $config);
			}
			else {
				$db_sparkline_values = [];
			}

			$db_items += $db_column_items;

			$table = self::makeColumnizedTable($db_column_items, $column, $db_values, $db_sparkline_values);

			// Each pattern result must be ordered before applying limit.
			$this->applyItemOrdering($table, $db_hosts);
			$this->applyItemOrderingLimit($table);

			$column_tables[$column_index] = $table;
		}

		$table = self::concatenateTables($column_tables);

		if (!$table) {
			return ['error' => _('No data.')];
		}

		$this->applyHostOrdering($table, $db_hosts);
		$this->applyHostOrderingLimit($table);
		$this->applyItemOrdering($table, $db_hosts);

		self::calculateExtremes($columns, $table);
		self::calculateValueViews($columns, $table);

		// Remove hostids.
		$table = array_values($table);

		$db_item_problem_triggers = [];
		if ($this->fields_values['problems'] != WidgetForm::PROBLEMS_NONE) {
			$db_item_problem_triggers = $this->getProblemTriggers(array_keys($db_items));
		}

		$data = [
			'error' => null,
			'layout' => $this->fields_values['layout'],
			'show_column_header' => $this->fields_values['show_column_header'],
			'configuration' => $columns,
			'rows' => $this->fields_values['layout'] == WidgetForm::LAYOUT_VERTICAL
				? self::transposeTable($table)
				: $table,
			'db_hosts' => $db_hosts,
			'db_items' => $db_items,
			'db_item_problem_triggers' => $db_item_problem_triggers
		];

		return $data;
	}

	private function getHosts(): array {
		$groupids = !$this->isTemplateDashboard() && $this->fields_values['groupids']
			? getSubGroups($this->fields_values['groupids'])
			: null;

		if ($this->isTemplateDashboard()) {
			$hostids = $this->fields_values['override_hostid'];
		}
		else {
			$hostids = $this->fields_values['hostids'] ?: null;
		}

		$tags = !$this->isTemplateDashboard() && $this->fields_values['host_tags']
			? $this->fields_values['host_tags']
			: null;

		$evaltype = !$this->isTemplateDashboard()
			? $this->fields_values['host_tags_evaltype']
			: null;

		$options = [
			'output' => ['name', 'hostid'],
			'groupids' => $groupids,
			'hostids' => $hostids,
			'tags' => $tags,
			'evaltype' => $evaltype,
			'monitored_hosts' => true,
			'with_monitored_items' => true,
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT),
			'preservekeys' => true
		];

		$db_hosts = API::Host()->get($options);
		if ($db_hosts === false) {
			return [];
		}

		return $db_hosts;
	}

	/**
	 * Inserts default column configuration that selects all items, if no columns declared.
	 * Parses min, max values if declared.
	 */
	private function getPreparedColumns(): array {
		$default = [
			'column_index' => 0,
			'items' => ['*'],
			'item_tags_evaltype' => TAG_EVAL_TYPE_AND_OR,
			'item_tags' => [],
			'base_color' => '',
			'display_value_as' => CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC,
			'display' => CWidgetFieldColumnsList::DISPLAY_AS_IS,
			'sparkline' => CWidgetFieldColumnsList::SPARKLINE_DEFAULT,
			'min' => '',
			'max' => '',
			'highlights' => [],
			'thresholds' => [],
			'decimal_places' => CWidgetFieldColumnsList::DEFAULT_DECIMAL_PLACES,
			'aggregate_function' => AGGREGATE_NONE,
			'time_period' => [
				CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
					CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
				)
			],
			'history' => CWidgetFieldColumnsList::HISTORY_DATA_AUTO
		];

		$result = [];
		if (!$this->fields_values['columns']) {
			$result[] = $default;
		}
		else {
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

			foreach ($this->fields_values['columns'] as $column_index => $column) {
				$column += $default;
				$column['sparkline'] += $default['sparkline'];
				$column['column_index'] = $column_index;

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

				$result[] = $column;
			}
		}

		return $result;
	}

	private function getItems(array $column, array $hostids): array {
		$search_field = $this->isTemplateDashboard() ? 'name' : 'name_resolved';
		$numeric_only = $column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC;
		$options = [
			'output' => [
				'itemid', 'hostid', 'name_resolved', 'value_type', 'units', 'valuemapid', 'history', 'trends', 'key_'
			],
			'selectValueMap' => ['mappings'],
			'hostids' => $hostids,
			'monitored' => true,
			'webitems' => true,
			'searchWildcardsEnabled' => true,
			'searchByAny' => true,
			'search' => [
				$search_field => in_array('*', $column['items'], true)
					? null
					: $column['items']
			],
			'filter' => [
				'status' => ITEM_STATUS_ACTIVE,
				'value_type' => $numeric_only ? [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64] : null
			],
			'preservekeys' => true
		];

		if (array_key_exists('item_tags', $column) && $column['item_tags']) {
			$options['tags'] = $column['item_tags'];
			$options['evaltype'] = $column['item_tags_evaltype'];
		}

		return CArrayHelper::renameObjectsKeys(API::Item()->get($options), ['name_resolved' => 'name']);
	}

	private static function getItemValues(array $items, array $column): array {
		static $history_period_s;

		if ($history_period_s === null) {
			$history_period_s = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		}

		$time_from = $column['aggregate_function'] != AGGREGATE_NONE
			? $column['time_period']['from_ts']
			: time() - $history_period_s;

		$items = self::addDataSource($items, $time_from, $column);

		$result = [];

		if ($column['aggregate_function'] != AGGREGATE_NONE) {
			$values = Manager::History()->getAggregatedValues($items, $column['aggregate_function'], $time_from,
				$column['time_period']['to_ts']
			);

			$result += array_column($values, 'value', 'itemid');
		}
		else {
			$items_by_source = ['history' => [], 'trends' => []];

			foreach (self::addDataSource($items, $time_from, $column) as $itemid => $item) {
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

		return $result;
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

		$items = self::addDataSource($items, $sparkline['time_period']['from_ts'],
			['history' => $sparkline['history']] + $column
		);

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

	private static function makeColumnizedTable(array $db_items, array $column, array $db_values,
			array $db_sparkline_values): array {
		$columns_map = [];
		foreach ($db_items as $itemid => $db_item) {
			$value_type_group = match ((int) $db_item['value_type']) {
				ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT => 'numeric',
				ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG => 'text',
				ITEM_VALUE_TYPE_BINARY => 'binary'
			};

			$columns_map[$db_item['name']][$value_type_group][$db_item['key_']][$db_item['hostid']] = $itemid;
		}

		$result_columns = [];
		foreach ($columns_map as $name => $column_values) {
			foreach ($column_values as $value_type => $type_values) {
				usort($type_values, fn (array $left, array $right) => count($right) <=> count($left));
				$type_values = array_values($type_values);

				$columns = [];
				$values_size = count($type_values);
				foreach (array_keys($type_values) as $value_index) {
					$result = [];
					for ($next_index = $value_index; $next_index < $values_size; $next_index++) {
						if (!array_intersect_key($result, $type_values[$next_index])) {
							$result += $type_values[$next_index];
							$type_values[$next_index] = [];
						}
					}

					if ($result) {
						$columns[] = $result;
					}
				}

				$result_columns[$name][$value_type] = $columns;
			}
		}

		$table_column_index = -1;
		$hostids = array_keys(array_column($db_items, 'hostid', 'hostid'));
		$table = [];
		foreach ($result_columns as $name => $column_value_types) {
			foreach ($column_value_types as $hosts_columns) {
				foreach ($hosts_columns as $itemids) {
					$table_column_index += 1;

					foreach ($hostids as $hostid) {
						$itemid = $itemids[$hostid] ?? null;
						$value = array_key_exists($itemid, $db_values) ? $db_values[$itemid] : null;
						$sparkline_value = array_key_exists($itemid, $db_sparkline_values)
							? $db_sparkline_values[$itemid]
							: null;
						$table[$hostid][$table_column_index] = [
							Widget::CELL_HOSTID => $hostid,
							Widget::CELL_ITEMID => $itemid,
							Widget::CELL_VALUE => $value,
							Widget::CELL_SPARKLINE_VALUE => $sparkline_value,
							Widget::CELL_METADATA => [
								'name' => $name,
								'column_index' => $column['column_index']
							]
						];
					}
				}
			}
		}

		return $table;
	}

	private function applyItemOrdering(array &$table, array $db_hosts): void {
		if (!$table) {
			return;
		}

		$this->applyItemOrderingByName($table);
		if ($this->fields_values['item_ordering_order_by'] == WidgetForm::ORDERBY_ITEM_VALUE) {
			$this->applyItemOrderingByValue($table);
		}
		elseif ($this->fields_values['item_ordering_order_by'] == WidgetForm::ORDERBY_HOST) {
			$this->applyItemOrderingByHost($table, $db_hosts);
		}
	}

	private function applyItemOrderingLimit(array &$table): void {
		foreach ($table as &$row) {
			$row = array_slice($row, 0, $this->fields_values['item_ordering_limit']);
		}
		unset($row);
	}

	private static function concatenateTables(array $tables): array {
		$result_hostids = [];
		foreach ($tables as $table) {
			$result_hostids += array_flip(array_keys($table));
		}
		$result_hostids = array_keys($result_hostids);

		$result = [];
		foreach ($result_hostids as $hostid) {
			foreach ($tables as $table) {
				$result_row = $result[$hostid] ?? [];

				if (!array_key_exists($hostid, $table)) {
					$first_row = reset($table);
					$cells = [];
					foreach ($first_row as $cell) {
						$cells[] = [
							Widget::CELL_HOSTID => $hostid,
							Widget::CELL_ITEMID => null,
							Widget::CELL_VALUE => null,
							Widget::CELL_SPARKLINE_VALUE => null,
							Widget::CELL_METADATA => &$cell[Widget::CELL_METADATA]
						];
					}
				}
				else {
					$cells = $table[$hostid];
				}

				$result[$hostid] = [...$result_row, ...$cells];
			}
		}

		return $result;
	}

	private function applyHostOrdering(array &$table, array $db_hosts): void {
		if (!$table) {
			return;
		}

		$this->orderHostsByName($table, $db_hosts);
		if ($this->fields_values['host_ordering_order_by'] == WidgetForm::ORDERBY_ITEM_VALUE) {
			$this->orderHostsByItemValue($table);
		}
	}

	private function applyHostOrderingLimit(array &$table): void {
		$result = [];
		$limit = $this->fields_values['host_ordering_limit'];
		foreach ($table as $hostid => $row) {
			if (--$limit < 0) {
				break;
			}

			$result[$hostid] = $row;
		}

		$table = $result;
	}

	private static function calculateValueViews(array $columns, array &$table): void {
		if (!$table) {
			return;
		}

		$columns_with_view_values = [];
		$width = count($table[array_key_first($table)]);
		for ($i = 0; $i < $width; $i++) {
			foreach ($table as [$i => $cell]) {
				['column_index' => $column_index] = $cell[Widget::CELL_METADATA];
				$column = $columns[$column_index];

				if ($column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC
						&& $column['display'] != CWidgetFieldColumnsList::DISPLAY_AS_IS
						&& $cell[Widget::CELL_VALUE] !== null) {
					$columns_with_view_values[] = $i;
				}
			}
		}

		$rows_with_view_values = [];
		foreach ($table as $hostid => $row) {
			foreach ($row as $cell) {
				['column_index' => $column_index] = $cell[Widget::CELL_METADATA];
				$column = $columns[$column_index];

				if ($column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC
						&& $column['display'] != CWidgetFieldColumnsList::DISPLAY_AS_IS
						&& $cell[Widget::CELL_VALUE] !== null) {
					$rows_with_view_values[] = $hostid;
				}
			}
		}

		$rows_with_view_values = array_flip($rows_with_view_values);
		$columns_with_view_values = array_flip($columns_with_view_values);
		foreach ($table as $hostid => &$row) {
			foreach ($row as $table_column_index => &$cell) {
				$cell[Widget::CELL_METADATA]['is_view_value_in_column'] = array_key_exists($table_column_index, $columns_with_view_values);
				$cell[Widget::CELL_METADATA]['is_view_value_in_row'] = array_key_exists($hostid, $rows_with_view_values);
			}
		}
	}

	private static function calculateExtremes(array &$columns, array $table): void {
		$column_min = [];
		$column_max = [];

		foreach ($table as $row) {
			foreach ($row as $cell) {
				$column_index = $cell[Widget::CELL_METADATA]['column_index'];
				$value = $cell[Widget::CELL_VALUE];
				if ($value === null) {
					continue;
				}

				if (!array_key_exists($column_index, $column_min) || $column_min[$column_index] > $value) {
					$column_min[$column_index] = $value;
				}

				if (!array_key_exists($column_index, $column_max) || $column_max[$column_index] < $value) {
					$column_max[$column_index] = $value;
				}
			}
		}

		foreach ($columns as $column_index => &$column) {
			if ($column['min'] === '') {
				$column['min'] = $column_min[$column_index] ?? '';
				$column['min_binary'] = $column['min'];
			}

			if ($column['max'] === '') {
				$column['max'] = $column_max[$column_index] ?? '';
				$column['max_binary'] = $column['max'];
			}
		}
		unset($column);
	}

	private function getProblemTriggers(array $itemids): array {
		$db_triggers = getTriggersWithActualSeverity([
			'output' => ['triggerid', 'priority', 'value'],
			'selectItems' => ['itemid'],
			'itemids' => $itemids,
			'only_true' => true,
			'monitored' => true,
			'preservekeys' => true
		], ['show_suppressed' => $this->fields_values['problems'] == WidgetForm::PROBLEMS_ALL]);

		$itemid_to_triggerids = [];
		foreach ($db_triggers as $triggerid => $db_trigger) {
			foreach ($db_trigger['items'] as $item) {
				if (!array_key_exists($item['itemid'], $itemid_to_triggerids)) {
					$itemid_to_triggerids[$item['itemid']] = [];
				}
				$itemid_to_triggerids[$item['itemid']][] = $triggerid;
			}
		}

		$result = [];
		foreach ($itemids as $itemid) {
			if (array_key_exists($itemid, $itemid_to_triggerids)) {
				$max_priority = -1;
				$max_priority_triggerid = -1;
				foreach ($itemid_to_triggerids[$itemid] as $triggerid) {
					$trigger = $db_triggers[$triggerid];

					if ($trigger['priority'] > $max_priority) {
						$max_priority_triggerid = $triggerid;
						$max_priority = $trigger['priority'];
					}
				}
				$result[$itemid] = $db_triggers[$max_priority_triggerid];
			}
		}

		return $result;
	}

	private static function reorderTableColumns(array &$table, array $index_map): void {
		foreach ($table as &$row) {
			$new_row = [];
			foreach ($index_map as $new_index) {
				$new_row[] = $row[$new_index];
			}
			$row = $new_row;
		}

		unset($row);
	}

	/**
	 * Table columns are mutually ordered by maximum or minimum value it has across hosts.
	 */
	private function applyItemOrderingByValue(array &$table): void {
		// Find max/min value for column across all hosts.
		$first_row = reset($table);
		$column_max = array_fill_keys(array_keys($first_row), null);
		$column_min = array_fill_keys(array_keys($first_row), null);
		foreach ($table as $row) {
			foreach ($row as $column_index => $cell) {
				$value = $cell[Widget::CELL_VALUE];
				if ($value === null) {
					continue;
				}

				if ($column_max[$column_index] === null) {
					$column_max[$column_index] = $value;
				}
				elseif ($value > $column_max[$column_index]) {
					$column_max[$column_index] = $value;
				}

				if ($column_min[$column_index] === null) {
					$column_min[$column_index] = $value;
				}
				elseif ($column_min[$column_index] > $value) {
					$column_min[$column_index] = $value;
				}
			}
		}

		$ordering_row_values = $this->fields_values['item_ordering_order'] == WidgetForm::ORDER_TOP_N
			? $column_max
			: $column_min;

		if ($this->fields_values['item_ordering_order'] == WidgetForm::ORDER_TOP_N) {
			arsort($ordering_row_values);
		}
		else {
			asort($ordering_row_values);
		}

		$index_map = array_keys($ordering_row_values);

		self::reorderTableColumns($table, $index_map);
	}

	/**
	 * If a column is found, it's values are used to order host rows.
	 */
	private function orderHostsByItemValue(array &$table): bool {
		$patterns = self::castWildcards($this->fields_values['host_ordering_item']);
		if (!$patterns) {
			return false;
		}

		$column_names = [];
		foreach ($table as $row) {
			foreach ($row as $cell) {
				$column_names[] = $cell[Widget::CELL_METADATA]['name'];
			}
			break;
		}

		$ordering_column_options = [];
		foreach ($patterns as ['regex' => $regex, 'pattern' => $pattern]) {
			foreach ($column_names as $index => $column_name) {
				if ($column_name === $pattern || preg_match($regex, $column_name)) {
					$ordering_column_options[] = [$index, $column_name];
				}
			}
		}

		if (!$ordering_column_options) {
			return false;
		}

		usort($ordering_column_options, fn (array $left, array $right) => strnatcasecmp($left[1], $right[1]));
		$ordering_column_index = $ordering_column_options[0][0];

		$table_column = array_column($table, $ordering_column_index);
		$ordering_values = [];
		foreach ($table_column as $cell) {
			$hostid = $cell[Widget::CELL_HOSTID];
			$value = $cell[Widget::CELL_VALUE];

			$ordering_values[$hostid] = $value;
		}

		if ($this->fields_values['host_ordering_order'] == WidgetForm::ORDER_TOP_N) {
			arsort($ordering_values);
		}
		else {
			asort($ordering_values);
		}

		$result = [];
		foreach (array_keys($ordering_values) as $hostid) {
			$result[$hostid] = $table[$hostid];
		}

		$table = $result;

		return true;
	}

	private function orderHostsByName(array &$table, array $db_hosts): void {
		uksort($table, function (string $hostid_left, string $hostid_right) use (&$db_hosts) {
			$name_left = $db_hosts[$hostid_left]['name'];
			$name_right = $db_hosts[$hostid_right]['name'];

			return $this->fields_values['host_ordering_order'] == WidgetForm::ORDER_TOP_N
				? strnatcasecmp($name_left, $name_right)
				: strnatcasecmp($name_right, $name_left);
		});
	}

	private static function addDataSource(array $items, int $time, array $column): array {
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
		}

		unset($item);

		return $items;
	}

	private static function transposeTable(array $rows): array {
		$transposed = [];

		foreach ($rows as $rowidx => $row) {
			foreach ($row as $colidx => $cell) {
				foreach ($cell as $elementidx => $element) {
					$transposed[$colidx][$rowidx][$elementidx] = $element;
				}
			}
		}

		return $transposed;
	}

	private static function castWildcards(array $patterns): array {
		$result = [];

		foreach ($patterns as $pattern) {
			$pattern = preg_quote($pattern, '/');
			$result[] = [
				'regex' => '/^'.strtr($pattern, ['\\*' => '.*?']).'$/',
				'pattern' => $pattern
			];
		}

		return $result;
	}

	private function applyItemOrderingByHost(array &$table, array $db_hosts): bool {
		$patterns = self::castWildcards($this->fields_values['item_ordering_host']);
		if (!$patterns) {
			return false;
		}

		$table_host_names = [];
		foreach (array_keys($table) as $hostid) {
			$table_host_names[$hostid] = $db_hosts[$hostid]['name'];
		}

		$ordering_hosts = [];
		foreach ($patterns as ['regex' => $regex, 'pattern' => $pattern]) {
			foreach ($table_host_names as $hostid => $host_name) {
				if ($host_name === $pattern || preg_match($regex, $host_name)) {
					$ordering_hosts[] = [$hostid, $host_name];
				}
			}
		}

		if (!$ordering_hosts) {
			return false;
		}

		usort($ordering_hosts, fn (array $left, array $right) => strnatcasecmp($left[1], $right[1]));
		$ordering_hostid = $ordering_hosts[0][0];

		$ordering_row_values = array_column($table[$ordering_hostid], Widget::CELL_VALUE);

		if ($this->fields_values['item_ordering_order'] == WidgetForm::ORDER_TOP_N) {
			arsort($ordering_row_values);
		}
		else {
			asort($ordering_row_values);
		}

		$index_map = array_keys($ordering_row_values);

		self::reorderTableColumns($table, $index_map);

		return true;
	}

	private function applyItemOrderingByName(array &$table): void {
		$column_names = [];
		foreach ($table as $row) {
			foreach ($row as $cell) {
				$column_names[] = $cell[Widget::CELL_METADATA]['name'];
			}
			break;
		}

		if ($this->fields_values['item_ordering_order'] == WidgetForm::ORDER_TOP_N) {
			uasort($column_names, fn (string $name_left, string $name_right) => strnatcasecmp($name_left, $name_right));
		}
		else {
			uasort($column_names, fn (string $name_left, string $name_right) => strnatcasecmp($name_right, $name_left));
		}

		$index_map = array_keys($column_names);

		self::reorderTableColumns($table, $index_map);
	}
}
