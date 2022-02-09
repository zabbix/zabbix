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
	if ($column_config['data'] == CWidgetFieldColumnsList::DATA_ITEM_VALUE) {
		if ($column_config['display'] == CWidgetFieldColumnsList::DISPLAY_AS_IS) {
			$headers[] = (new CColHeader($column_config['name']))->addClass(ZBX_STYLE_CENTER);
		}
		else {
			$headers[] = (new CColHeader($column_config['name']))->setColSpan(2);
		}
	}
	else {
		$headers[] = $column_config['name'];
	}
}

$table = (new CTableInfo())->setHeader($headers);

foreach ($data['rows'] as $columns) {
	$row = [];

	foreach ($columns as $i => $column) {
		$column_config = $data['configuration'][$i];

		if ($column === null) {
			if ($column_config['data'] == CWidgetFieldColumnsList::DATA_ITEM_VALUE
					&& $column_config['display'] != CWidgetFieldColumnsList::DISPLAY_AS_IS) {
				$row[] = (new CCol(''))->setColSpan(2);
			}
			else {
				$row[] = '';
			}

			continue;
		}

		$color = $column_config['base_color'];

		if ($column_config['data'] == CWidgetFieldColumnsList::DATA_ITEM_VALUE
				&& $column_config['display'] == CWidgetFieldColumnsList::DISPLAY_AS_IS
				&& array_key_exists('thresholds', $column_config)) {
			foreach ($column_config['thresholds'] as $threshold) {
				if ($column['value'] < $threshold['threshold']) {
					break;
				}

				$color = $threshold['color'];
			}
		}

		switch ($column_config['data']) {
			case CWidgetFieldColumnsList::DATA_HOST_NAME:
				$row[] = (new CCol(
					(new CLinkAction($column['value']))->setMenuPopup(CMenuPopupHelper::getHost($column['hostid']))
				))->addStyle($color !== '' ? 'background-color: #'.$color : null);

				break;

			case CWidgetFieldColumnsList::DATA_TEXT:
				$row[] = (new CCol($column['value']))
					->addStyle($color !== '' ? 'background-color: #'.$color : null);

				break;

			case CWidgetFieldColumnsList::DATA_ITEM_VALUE:
				if ($column_config['display'] == CWidgetFieldColumnsList::DISPLAY_AS_IS) {
					$row[] = (new CCol())
						->addStyle($color !== '' ? 'background-color: #'.$color : null)
						->addItem(
							(new CDiv(formatHistoryValue($column['value'], $column['item'])))
								->addClass(ZBX_STYLE_CENTER)
								->addClass(ZBX_STYLE_CURSOR_POINTER)
								->setHint(
									(new CDiv($column['value']))->addClass(ZBX_STYLE_HINTBOX_WRAP)
								)
						);

					break;
				}

				$bar_gauge = (new CBarGauge())
					->setValue($column['value'])
					->setAttribute('fill', $column_config['base_color'] !== ''
						? '#'.$column_config['base_color']
						: ZBX_WIDGET_TOP_HOSTS_DEFAULT_FILL
					)
					->setAttribute('min', $column_config['min'])
					->setAttribute('max', $column_config['max']);

				if ($column_config['display'] == CWidgetFieldColumnsList::DISPLAY_BAR) {
					$bar_gauge->setAttribute('solid', 1);
				}

				if (array_key_exists('thresholds', $column_config)) {
					foreach ($column_config['thresholds'] as $threshold) {
						$bar_gauge->addThreshold($threshold['threshold'], '#'.$threshold['color']);
					}
				}

				$row[] = new CCol($bar_gauge);
				$row[] = (new CCol())
					->addStyle('width: 0;')
					->addItem(
						(new CDiv(formatHistoryValue($column['value'], $column['item'])))
							->addClass(ZBX_STYLE_CURSOR_POINTER)
							->addClass(ZBX_STYLE_NOWRAP)
							->setHint(
								(new CDiv($column['value']))->addClass(ZBX_STYLE_HINTBOX_WRAP)
							)
					);

				break;
		}
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
