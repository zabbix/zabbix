<?php
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 * @var array $data
 */

$headers = [];

foreach ($data['configuration'] as $column_config) {
	if ($column_config['data'] == CWidgetFieldColumnsList::DATA_ITEM_VALUE
			&& $column_config['display'] == CWidgetFieldColumnsList::DISPLAY_AS_IS) {
		$headers[] = (new CColHeader($column_config['name']))->addClass(ZBX_STYLE_CENTER);
	}
	else {
		$headers[] = $column_config['name'];
	}
}

$table = (new CTableInfo())->setHeader($headers);

foreach ($data['rows'] as $columns) {
	$row = [];

	foreach ($columns as $i => $column) {
		if ($column === null) {
			$row[] = '';

			continue;
		}

		$column_config = $data['configuration'][$i];

		switch ($column_config['data']) {
			case CWidgetFieldColumnsList::DATA_HOST_NAME:
				$cell = (new CLinkAction($column['value']))->setMenuPopup(CMenuPopupHelper::getHost($column['hostid']));
				break;

			case CWidgetFieldColumnsList::DATA_TEXT:
				$cell = new CDiv($column['value']);
				break;

			case CWidgetFieldColumnsList::DATA_ITEM_VALUE:
				if ($column_config['display'] == CWidgetFieldColumnsList::DISPLAY_AS_IS) {
					$cell = (new CDiv(formatHistoryValue($column['value'], $column['item'])))
						->addClass('item-value')
						->addClass(ZBX_STYLE_CURSOR_POINTER)
						->setHint(
							(new CDiv($column['value']))->addClass(ZBX_STYLE_HINTBOX_WRAP)
						);

					break;
				}

				$cell = (new CBarGauge())
					->setValue($column['value'])
					->addClass('item-value')
					->setHint(
						(new CDiv(formatHistoryValue($column['value'], $column['item'])))
							->addClass(ZBX_STYLE_HINTBOX_WRAP)
					);

				if (array_key_exists('thresholds', $column_config)) {
					foreach ($column_config['thresholds'] as $threshold) {
						$cell->addThreshold($threshold['threshold'], '#'.$threshold['color']);
					}
				}

				if ($column_config['display'] == CWidgetFieldColumnsList::DISPLAY_BAR) {
					$cell->setAttribute('solid', 1);
				}

				if (array_key_exists('base_color', $column_config)) {
					$cell->setAttribute('fill', '#'.$column_config['base_color']);
				}

				if (array_key_exists('min', $column_config)) {
					$cell->setAttribute('min', $column_config['min']);
				}

				if (array_key_exists('max', $column_config)) {
					$cell->setAttribute('max', $column_config['max']);
				}
				break;
		}

		$color = array_key_exists('base_color', $column_config) ? $column_config['base_color'] : '';

		if (array_key_exists('thresholds', $column_config)
				&& array_key_exists('display', $column_config)
				&& $column_config['display'] == CWidgetFieldColumnsList::DISPLAY_AS_IS) {
			foreach ($column_config['thresholds'] as $threshold) {
				if ($column['value'] < $threshold['threshold']) {
					break;
				}

				$color = $threshold['color'];
			}
		}

		if (!is_a($cell, CBarGauge::class) && $color !== '') {
			$cell = (new CCol($cell))->addStyle('background-color: #'.$color);
		}

		$row[] = $cell;
	}

	$table->addRow($row);
}

$output = [
	'name' => $data['name'],
	'body' => (new CDiv($table))
		->addClass('dashboard-grid-widget-tophosts')
		->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
