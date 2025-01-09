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
 * Item history widget view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\ItemHistory\Widget;

use Widgets\ItemHistory\Includes\{
	WidgetForm,
	CWidgetFieldColumnsList
};

$table = (new CTableInfo())->addClass($data['show_thumbnail'] ? 'show-thumbnail' : null);

if ($data['error'] !== null) {
	$table->setNoDataMessage($data['error']);
}
else {
	$is_layout_vertical = $data['layout'] == WidgetForm::LAYOUT_VERTICAL;

	if ($data['show_column_header'] != WidgetForm::COLUMN_HEADER_OFF) {
		$table_header = [];

		$column_title_class = $data['show_column_header'] == WidgetForm::COLUMN_HEADER_VERTICAL
			? ZBX_STYLE_TEXT_VERTICAL
			: null;

		if ($data['show_timestamp']) {
			$table_header[] = (new CColHeader(
				(new CSpan(_x('Timestamp', 'compact table header')))->addClass($column_title_class)
			))
				->addClass(ZBX_STYLE_CELL_WIDTH)
				->addClass(ZBX_STYLE_NOWRAP);
		}

		if ($is_layout_vertical) {
			foreach ($data['columns'] as $column) {
				$table_header[] = (new CColHeader(
					(new CSpan($column['name']))
						->addClass($column_title_class)
						->setTitle($column['name'])
				))->setColSpan(2);
			}
		}
		else {
			$table_header[] = (new CColHeader(
				(new CSpan(_x('Name', 'compact table header')))->addClass($column_title_class)
			))
				->addClass(ZBX_STYLE_NOWRAP)
				->addClass(ZBX_STYLE_CELL_WIDTH);

			$table_header[] = (new CColHeader(
				(new CSpan(_x('Value', 'compact table header')))->addClass($column_title_class)
			))->setColSpan(2);
		}

		$table->setHeader($table_header);
	}

	$rows = [];
	$row = [];
	$clock = 0;

	foreach ($data['item_values'] as $item_value) {
		$column_index = $is_layout_vertical ? $item_value['column_index'] : 0;

		if ($item_value['clock'] != $clock || array_key_exists($column_index, $row)) {
			if ($row) {
				$rows[] = $row;
			}

			$clock = $item_value['clock'];
			$row = [];

			if (count($rows) == $data['show_lines']) {
				break;
			}
		}

		$row[$column_index] = $item_value;
	}

	if ($row) {
		$rows[] = $row;
	}

	if ($data['sortorder'] == WidgetForm::NEW_VALUES_BOTTOM) {
		$rows = array_reverse($rows);
	}

	if ($is_layout_vertical) {
		$column_indexes = array_keys($data['columns']);
		$column_count = count($data['columns']);

		foreach ($rows as $row) {
			$table_row = [];

			if ($data['show_timestamp']) {
				$table_row[] = (new CCol(
					zbx_date2str(DATE_TIME_FORMAT_SECONDS, getRowClock($data['columns'], $row))
				))->addClass(ZBX_STYLE_NOWRAP);
			}

			foreach ($column_indexes as $index) {
				if (array_key_exists($index, $row)) {
					$table_row = array_merge($table_row,
						makeValueCell($data['columns'][$index], $row[$index], $column_count > 1, 'has-broadcast-data')
					);
				}
				else {
					$table_row[] = (new CCol())->setColSpan(2);
				}
			}

			$table->addRow($table_row);
		}
	}
	else {
		foreach ($rows as $row) {
			$clock = getRowClock($data['columns'], $row);
			$item_value = $row[0];

			$table
				->setHeadingColumn(0)
				->addRow(
					(new CRow([
						$data['show_timestamp']
							? (new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $clock)))->addClass(ZBX_STYLE_NOWRAP)
							: null,
						(new CCol($data['columns'][$item_value['column_index']]['name']))->addClass(ZBX_STYLE_NOWRAP),
						...makeValueCell($data['columns'][$item_value['column_index']], $item_value)
					]))
						->addClass('has-broadcast-data')
						->setAttribute('data-itemid', $item_value['itemid'])
						->setAttribute('data-key_', $item_value['key_'])
						->setAttribute('data-clock', $clock.'.'.$item_value['ns'])
				);
		}
	}
}

$view = new CWidgetView($data);

if ($data['info']) {
	$view->setVar('info', $data['info']);
}

$view
	->addItem($table)
	->show();

function getRowClock(array $columns, array $row): string | int {
	foreach ($row as $item_value) {
		$column = $columns[$item_value['column_index']];

		if ($column['item_value_type'] == ITEM_VALUE_TYPE_LOG
				&& array_key_exists('local_time', $column) && $column['local_time'] != 0) {
			return $item_value['timestamp'];
		}
	}

	return reset($row)['clock'];
}

function makeValueCell(array $column, array $item_value, bool $text_wordbreak = false,
		string $cell_class = null): array {
	$color = $column['base_color'];

	switch ($column['item_value_type']) {
		case ITEM_VALUE_TYPE_FLOAT:
		case ITEM_VALUE_TYPE_UINT64:
			if ($column['display'] == CWidgetFieldColumnsList::DISPLAY_BAR
					|| $column['display'] == CWidgetFieldColumnsList::DISPLAY_INDICATORS) {
				$bar_gauge = (new CBarGauge())
					->setValue($item_value['value'])
					->setAttribute('fill', $color !== '' ? '#'.$color : Widget::DEFAULT_FILL)
					->setAttribute('min',  $column['has_binary_units'] ? $column['min_binary'] : $column['min'])
					->setAttribute('max',  $column['has_binary_units'] ? $column['max_binary'] : $column['max']);

				if ($column['display'] == CWidgetFieldColumnsList::DISPLAY_BAR) {
					$bar_gauge->setAttribute('solid', 1);
				}

				if (array_key_exists('thresholds', $column)) {
					foreach ($column['thresholds'] as $threshold) {
						$bar_gauge->addThreshold($threshold['threshold'], '#'.$threshold['color']);
					}
				}

				return [
					(new CCol($bar_gauge))
						->addClass($cell_class)
						->setAttribute('data-itemid', $item_value['itemid'])
						->setAttribute('data-key_', $item_value['key_'])
						->setAttribute('data-clock', $item_value['clock'].'.'.$item_value['ns']),
					(new CCol(
						(new CSpan($item_value['formatted_value']))->setHint(
							(new CDiv($item_value['value']))->addClass(ZBX_STYLE_HINTBOX_WRAP)
						)
					))
						->addClass($cell_class)
						->setAttribute('data-itemid', $item_value['itemid'])
						->setAttribute('data-key_', $item_value['key_'])
						->setAttribute('data-clock', $item_value['clock'].'.'.$item_value['ns'])
						->addStyle('width: 0;')
						->addClass(ZBX_STYLE_NOWRAP)
				];
			}
			else {
				if (array_key_exists('thresholds', $column)) {
					foreach ($column['thresholds'] as $threshold) {
						$threshold_value = $column['has_binary_units']
							? $threshold['threshold_binary']
							: $threshold['threshold'];

						if ($item_value['value'] < $threshold_value) {
							break;
						}

						$color = $threshold['color'];
					}
				}

				return [
					(new CCol(
						(new CSpan($item_value['formatted_value']))->setHint(
							(new CDiv($item_value['value']))->addClass(ZBX_STYLE_HINTBOX_WRAP)
						)
					))
						->addClass($cell_class)
						->setAttribute('data-itemid', $item_value['itemid'])
						->setAttribute('data-key_', $item_value['key_'])
						->setAttribute('data-clock', $item_value['clock'].'.'.$item_value['ns'])
						->setAttribute('bgcolor', $color !== '' ? '#'.$color : null)
						->setColSpan(2)
				];
			}

		case ITEM_VALUE_TYPE_LOG:
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			if (array_key_exists('highlights', $column)) {
				foreach ($column['highlights'] as $highlight) {
					if (@preg_match('('.$highlight['pattern'].')', $item_value['value'])) {
						$color = $highlight['color'];
						break;
					}
				}
			}

			$cell = new CCol();

			switch ($column['display']) {
				case CWidgetFieldColumnsList::DISPLAY_AS_IS:
					$cell
						->addItem(
							(new CSpan(zbx_nl2br($item_value['value'])))->setHint(
								(new CDiv($item_value['value']))->addClass(ZBX_STYLE_HINTBOX_WRAP)
							)
						)
						->addClass($text_wordbreak ? ZBX_STYLE_WORDBREAK : ZBX_STYLE_NOWRAP);
					break;

				case CWidgetFieldColumnsList::DISPLAY_SINGLE_LINE:
					$single_line_value = [mb_substr($item_value['value'], 0, $column['max_length'])];

					if (strlen($item_value['value']) > $column['max_length']) {
						$single_line_value[] = HELLIP();
					}

					$cell
						->addItem(
							(new CSpan($single_line_value))->setHint(
								(new CDiv($item_value['value']))->addClass(ZBX_STYLE_HINTBOX_WRAP)
							)
						)
						->addClass(ZBX_STYLE_NOWRAP);
					break;

				case CWidgetFieldColumnsList::DISPLAY_HTML:
					$cell->addItem(
						new CJsScript($item_value['value'])
					);
					break;
			}

			return [
				$cell
					->addClass($column['monospace_font'] ? ZBX_STYLE_MONOSPACE_FONT : null)
					->addClass($cell_class)
					->setAttribute('data-itemid', $item_value['itemid'])
					->setAttribute('data-key_', $item_value['key_'])
					->setAttribute('data-clock', $item_value['clock'].'.'.$item_value['ns'])
					->setAttribute('bgcolor', $color !== '' ? '#'.$color : null)
					->setColSpan(2)
			];

		case ITEM_VALUE_TYPE_BINARY:
			return [
				(new CCol(
					(new CButton(null, _('Show')))
						->addClass($column['show_thumbnail'] ? 'btn-thumbnail' : ZBX_STYLE_BTN_LINK)
						->addClass('js-show-binary')
						->setAttribute('data-alt', $column['name'])
				))
					->addClass($cell_class)
					->setAttribute('bgcolor', $color !== '' ? '#'.$color : null)
					->setAttribute('data-itemid', $item_value['itemid'])
					->setAttribute('data-key_', $item_value['key_'])
					->setAttribute('data-clock', $item_value['clock'].'.'.$item_value['ns'])
					->setColSpan(2)
			];

		default:
			return [];
	}
}
