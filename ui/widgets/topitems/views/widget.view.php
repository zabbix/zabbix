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


/**
 * Top items widget view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\TopItems\Includes\{
	CWidgetFieldColumnsList,
	WidgetForm
};
use Widgets\TopItems\Widget;

$table = new CTableInfo();

if ($data['error'] !== null) {
	$table->setNoDataMessage($data['error']);
}
else {
	if ($data['show_column_header'] != WidgetForm::COLUMN_HEADER_OFF) {
		$column_title_class = $data['show_column_header'] == WidgetForm::COLUMN_HEADER_VERTICAL
			? ZBX_STYLE_TEXT_VERTICAL
			: null;

		$header = [];

		if ($data['layout'] == WidgetForm::LAYOUT_VERTICAL) {
			$header[] = new CColHeader(_('Items'));

			foreach ($data['rows'][0] as $cell) {
				$hostid = $cell[Widget::CELL_HOSTID];
				$title = $data['db_hosts'][$hostid]['name'];
				['is_view_value_in_row' => $is_view_value] = $cell[Widget::CELL_METADATA];
				$header[] = (new CColHeader(
					(new CSpan($title))
						->addClass($column_title_class)
						->setTitle($title)
				))->setColSpan($is_view_value ? 2 : 1);
			}
		}
		else {
			$header[] = new CColHeader(_('Hosts'));

			foreach ($data['rows'][0] as $cell) {
				['name' => $title, 'is_view_value_in_column' => $is_view_value] = $cell[Widget::CELL_METADATA];
				$header[] = (new CColHeader(
					(new CSpan($title))
						->addClass($column_title_class)
						->setTitle($title)
				))->setColSpan($is_view_value ? 2 : 1);
			}
		}

		$table->setHeader($header);
	}

	foreach ($data['rows'] as $row_index => $data_row) {
		$table_row = [];

		// Table row heading.
		if ($data['layout'] == WidgetForm::LAYOUT_VERTICAL) {
			['name' => $title] = $data_row[0][Widget::CELL_METADATA];
			$table_row[] = new CCol($title);
		}
		else {
			$hostid = $data_row[0][Widget::CELL_HOSTID];
			$table_row[] = new CCol((new CLinkAction($data['db_hosts'][$hostid]['name']))
				->setMenuPopup(CMenuPopupHelper::getHost($hostid)));
		}

		foreach ($data_row as $cell) {
			$table_row = [...$table_row, ...makeTableCellViews($cell, $data)];
		}

		$table->addRow($table_row);
	}
}

(new CWidgetView($data))
	->addItem($table)
	->show();

function makeTableCellViews(array $cell, array $data): array {
	$is_view_value = $data['layout'] == WidgetForm::LAYOUT_VERTICAL
		? $cell[Widget::CELL_METADATA]['is_view_value_in_row']
		: $cell[Widget::CELL_METADATA]['is_view_value_in_column'];

	$column = $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']];
	$itemid = $cell[Widget::CELL_ITEMID];
	$value = $cell[Widget::CELL_VALUE];

	if ($itemid === null || $value === null) {
		return [(new CCol())->setColSpan($is_view_value ? 2 : 1)];
	}

	$formatted_value = makeTableCellViewFormattedValue($cell, $data);
	$trigger = $data['db_item_problem_triggers'][$itemid] ?? null;
	if ($trigger !== null) {
		return makeTableCellViewsTrigger($cell, $trigger, $formatted_value, $is_view_value);
	}

	if ($column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC) {
		return makeTableCellViewsNumeric($cell, $data, $formatted_value, $is_view_value);
	}

	if ($column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_TEXT) {
		return makeTableCellViewsText($cell, $data, $formatted_value, $is_view_value);
	}

	return [(new CCol())->setColSpan($is_view_value ? 2 : 1)];
}

function makeTableCellViewsNumeric(array $cell, array $data, $formatted_value, bool $is_view_value): array {
	$item = $data['db_items'][$cell[Widget::CELL_ITEMID]];
	$value = $cell[Widget::CELL_VALUE];
	$column = $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']];
	$color = $column['base_color'];

	$value_cell = (new CCol(new CDiv($formatted_value)))
		->addClass(ZBX_STYLE_CURSOR_POINTER)
		->addClass(ZBX_STYLE_NOWRAP);

	if ($value !== '') {
		$value_cell->setHint((new CDiv($value))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false);
	}

	switch ($column['display']) {
		case CWidgetFieldColumnsList::DISPLAY_AS_IS:
			$style = $color !== '' ? 'background-color: #'.$color : null;
			$value_cell->addStyle($style);

			if (!$is_view_value) {
				return [$value_cell];
			}

			return [(new CCol())->addStyle($style), $value_cell];

		case CWidgetFieldColumnsList::DISPLAY_SPARKLINE:
			$style = $color !== '' ? 'background-color: #'.$color : null;
			$value_cell->addStyle($style);
			$sparkline_value = $cell[Widget::CELL_SPARKLINE_VALUE] ?? [];
			$sparkline = (new CSparkline())
				->setHeight(20)
				->setColor('#'.$column['sparkline']['color'])
				->setLineWidth($column['sparkline']['width'])
				->setFill($column['sparkline']['fill'])
				->setValue($sparkline_value)
				->setTimePeriodFrom($column['sparkline']['time_period']['from_ts'])
				->setTimePeriodTo($column['sparkline']['time_period']['to_ts']);

			return [new CCol($sparkline), $value_cell];

		case CWidgetFieldColumnsList::DISPLAY_INDICATORS:
		case CWidgetFieldColumnsList::DISPLAY_BAR:
			$bar_gauge = (new CBarGauge())
				->setValue($value)
				->setAttribute('fill', $color !== '' ? '#' . $color : Widget::DEFAULT_FILL)
				->setAttribute('min', isBinaryUnits($item['units'])
					? $column['min_binary']
					: $column['min']
				)
				->setAttribute('max', isBinaryUnits($item['units'])
					? $column['max_binary']
					: $column['max']
				);

			if ($column['display'] == CWidgetFieldColumnsList::DISPLAY_BAR) {
				$bar_gauge->setAttribute('solid', 1);
			}

			if (array_key_exists('thresholds', $column)) {
				foreach ($column['thresholds'] as $threshold) {
					$bar_gauge->addThreshold($threshold['threshold'], '#'.$threshold['color']);
				}
			}

			return [new CCol($bar_gauge), $value_cell];
	}
}

function makeTableCellViewFormattedValue(array $cell, array $data): CSpan {
	$itemid = $cell[Widget::CELL_ITEMID];
	$value = $cell[Widget::CELL_VALUE];
	$column = $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']];
	$color = $column['base_color'];
	$item = $data['db_items'][$itemid];

	if ($item['value_type'] == ITEM_VALUE_TYPE_BINARY) {
		$formatted_value = italic(_('binary value'))
			->addClass($color === '' ? ZBX_STYLE_GREY : null);
	}
	else {
		$formatted_value = formatAggregatedHistoryValue($value, $item,
			$column['aggregate_function'], false, true, [
				'decimals' => $column['decimal_places'],
				'decimals_exact' => true,
				'small_scientific' => false,
				'zero_as_zero' => false
			]
		);
	}

	return (new CSpan($formatted_value))
		->setMenuPopup(
			CMenuPopupHelper::getItem([
				'itemid' => $itemid,
				'context' => 'host',
				'backurl' => (new CUrl('zabbix.php'))
					->setArgument('action', 'dashboard.view')
					->getUrl()
			])
		);
}

function makeTableCellViewsText(array $cell, array $data, $formatted_value, bool $is_view_value): array {
	$value = $cell[Widget::CELL_VALUE];
	$column = $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']];

	$color = '';
	if (array_key_exists('highlights', $column)) {
		foreach ($column['highlights'] as $highlight) {
			if (@preg_match('('.$highlight['pattern'].')', $value)) {
				$color = $highlight['color'];
				break;
			}
		}
	}

	$style = $color !== '' ? 'background-color: #'.$color : null;
	$value_cell = (new CCol(new CDiv($formatted_value)))
		->addStyle($style)
		->addClass(ZBX_STYLE_CURSOR_POINTER)
		->addClass(ZBX_STYLE_NOWRAP);

	if ($value !== '') {
		$value_cell->setHint((new CDiv($value))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false);
	}

	if ($is_view_value) {
		return [(new CCol())->addStyle($style), $value_cell];
	}

	return [$value_cell];
}

function makeTableCellViewsTrigger(array $cell, array $trigger, $formatted_value, bool $is_view_value): array {
	$value = $cell[Widget::CELL_VALUE];

	if ($trigger['problem']['acknowledged'] == EVENT_ACKNOWLEDGED) {
		$formatted_value = [$formatted_value, (new CSpan())->addClass(ZBX_ICON_CHECK)];
	}

	$class = CSeverityHelper::getStyle((int) $trigger['priority']);
	$value_cell = (new CCol(new CDiv($formatted_value)))
		->addClass($class)
		->addClass(ZBX_STYLE_CURSOR_POINTER)
		->addClass(ZBX_STYLE_NOWRAP);

	if ($value !== '') {
		$value_cell->setHint((new CDiv($value))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false);
	}

	if ($is_view_value) {
		return [(new CCol())->addClass($class), $value_cell];
	}

	return [$value_cell];
}
