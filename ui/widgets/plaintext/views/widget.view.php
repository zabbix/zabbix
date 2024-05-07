<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Plain text widget view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\PlainText\Widget;
use Widgets\PlainText\Includes\{
	WidgetForm,
	CWidgetFieldColumnsList
};

$table = new CTableInfo();

if ($data['error'] !== null) {
	$table->setNoDataMessage($data['error']);
}
else {
	$columns_names_at_top = $data['layout'] == WidgetForm::LAYOUT_HORIZONTAL && count($data['columns']) > 1;

	if ($data['show_column_header'] != WidgetForm::COLUMN_HEADER_OFF) {
		$table_header = $data['show_timestamp']
			? [
				(new CColHeader(
					(new CSpan(_x('Timestamp', 'compact table header')))
						->addClass($data['show_column_header'] == WidgetForm::COLUMN_HEADER_VERTICAL
							? ZBX_STYLE_TEXT_VERTICAL
							: null
						)
				))->addClass(ZBX_STYLE_CELL_WIDTH)
			]
			: [];

		$column_names = $data['layout'] == WidgetForm::LAYOUT_HORIZONTAL
			? array_column($data['columns'], 'name')
			: [_x('Name', 'compact table header'), _x('Value', 'compact table header')];

		foreach ($column_names as $column_name) {
			$table_header[] = (new CSpan($column_name))
				->addClass($data['show_column_header'] == WidgetForm::COLUMN_HEADER_VERTICAL
					? ZBX_STYLE_TEXT_VERTICAL
					: null
				)->setTitle($column_name);
		}

		$table->setHeader($table_header);
	}

	$history_values = [];

	foreach ($data['columns'] as $columnid => $column) {
		foreach ($column['item_values'] as $item_value) {
			$history_item = [
				'columnid' => $columnid,
				'clock' => $item_value['clock'],
				'ns' => $item_value['ns'],
				'local_time' => false
			];

			$color = $column['base_color'];

			switch ($column['item_value_type']) {
				case ITEM_VALUE_TYPE_FLOAT:
				case ITEM_VALUE_TYPE_UINT64:
					if ($column['display'] != CWidgetFieldColumnsList::DISPLAY_BAR
							|| $column['display'] != CWidgetFieldColumnsList::DISPLAY_INDICATORS) {
						if (array_key_exists('threshold', $column)) {
							foreach ($column['thresholds'] as $threshold) {
								if ($item_value['value'] < $threshold['threshold']) {
									break;
								}

								$color = $threshold['color'];
							}
						}

						$history_item['value'] = (new CCol())
							->addStyle($color !== '' ? 'background-color: #' . $color : null)
							->addItem(
								(new CPre(formatHistoryValue($item_value['value'], $data['items'][$column['itemid']],
									false
								)))
							);

						break;
					}

					$bar_gauge = (new CBarGauge())
						->setValue($item_value['value'])
						->setAttribute('fill', $color !== '' ? '#' . $color : Widget::DEFAULT_FILL)
						->setAttribute('min',  $column['min'])
						->setAttribute('max',  $column['max']);

					if ($column['display'] == CWidgetFieldColumnsList::DISPLAY_BAR) {
						$bar_gauge->setAttribute('solid', 1);
					}

					if (array_key_exists('thresholds', $column)) {
						foreach ($column['thresholds'] as $threshold) {
							$bar_gauge->addThreshold($threshold['threshold'], '#'.$threshold['color']);
						}
					}

					$history_item['value'][] = new CCol($bar_gauge);
					$history_item['value'][] = (new CCol())
						->addStyle('width: 0;')
						->addItem(
							(new CPre($item_value['value']))
						);

					break;

				case ITEM_VALUE_TYPE_LOG:
					$history_item['local_time'] = (bool) $column['local_time'];
				case ITEM_VALUE_TYPE_STR:
				case ITEM_VALUE_TYPE_TEXT:
					if (array_key_exists('highlights', $column)) {
						foreach ($column['highlights'] as $highlight) {
							if (preg_match($highlight['pattern'], $item_value['value'])) {
								$color = $highlight['color'];
								break;
							}
						}
					}

					$value = $column['display'] == CWidgetFieldColumnsList::DISPLAY_SINGLE_LINE
						? substr($item_value['value'], 0, $column['max_length'])
						: $item_value['value'];

					$history_item['value'] = (new CCol())
						->addStyle($color !== '' ? 'background-color: #' . $color : null)
						->addClass($column['display'] == CWidgetFieldColumnsList::DISPLAY_SINGLE_LINE
							? ZBX_STYLE_NOWRAP
							: null
						)
						->addClass($column['monospace_font'] ? ZBX_STYLE_MONOSPACE_FONT : null)
						->addItem($column['display'] != CWidgetFieldColumnsList::DISPLAY_HTML
							? new CPre($value)
							: new CJsScript($value)
						);

					break;

				case ITEM_VALUE_TYPE_BINARY:
					$history_item['value'] = (new CCol((new CButtonLink(_('Show')))
						->setHint(
							italic($item_value['value'])->addClass(ZBX_STYLE_GREY)
						)
						->onMouseover('')
					))
						->addStyle('height: 56px;vertical-align: middle; text-align: center;');
			}

			$history_values[] = $history_item;
		}
	}

	$sort_order = $data['sortorder'] == WidgetForm::NEW_VALUES_TOP
		? ZBX_SORT_DOWN
		: ZBX_SORT_UP;

	CArrayHelper::sort($history_values, [
		['field' => 'clock', 'order' => $sort_order],
		['field' => 'ns', 'order' => $sort_order]
	]);

	sdff($history_values, '/home/test/work/logs/zabbix.log');

	if (!$columns_names_at_top) {
		foreach($history_values as $history_item) {
			$table_row = $data['show_timestamp']
				? [
					(new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $history_item['clock'])))
						->addClass(ZBX_STYLE_NOWRAP)
				]
				: [];

			if ($data['layout'] == WidgetForm::LAYOUT_VERTICAL) {
				$table->setHeadingColumn($data['show_timestamp'] ? 1 : 0);
				$table_row[] = $data['columns'][$history_item['columnid']]['name'];
			}

			$table_row[] = $history_item['value'];
			$table->addRow($table_row);
		}
	}
	else {
		$clock = 0;
		$row_values = [];

		do {
			$history_item = array_shift($history_values);

			if (($history_item === null && $row_values)
					|| ($history_item !== null && (
						($clock != 0 && $history_item['clock'] != $clock)
							|| array_key_exists($history_item['columnid'], $row_values)))) {
				$table_row = $data['show_timestamp']
					? [
						(new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $clock)))
							->addClass(ZBX_STYLE_NOWRAP)
					]
					: [];

				foreach (array_keys($data['columns']) as $columnid) {
					$table_row[] = array_key_exists($columnid, $row_values)
						? $row_values[$columnid]
						: '';
				}

				$table->addRow($table_row);
				$row_values = [];
			}

			if ($history_item !== null) {
				$clock = $history_item['clock'];
				$row_values[$history_item['columnid']] = $history_item['value'];
			}
		} while ($history_item !== null && $table->getNumRows() < $data['show_lines']);
	}
}

(new CWidgetView($data))
	->addItem($table)
	->show();
