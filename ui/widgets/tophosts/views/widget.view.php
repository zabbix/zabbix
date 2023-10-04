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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Top hosts widget view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\TopHosts\Widget;

use Zabbix\Widgets\Fields\CWidgetFieldColumnsList;

$table = new CTableInfo();

if ($data['error'] !== null) {
	$table->setNoDataMessage($data['error']);
}
else {
	$header = [];

	foreach ($data['configuration'] as $column_config) {
		if ($column_config['data'] == CWidgetFieldColumnsList::DATA_ITEM_VALUE) {
			if ($column_config['display'] == CWidgetFieldColumnsList::DISPLAY_AS_IS) {
				$header[] = (new CColHeader($column_config['name']))->addClass(ZBX_STYLE_CENTER);
			}
			else {
				$header[] = (new CColHeader($column_config['name']))->setColSpan(2);
			}
		}
		else {
			$header[] = $column_config['name'];
		}
	}

	$table->setHeader($header);

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
					$threshold_value = $column['is_binary_units']
						? $threshold['threshold_binary']
						: $threshold['threshold'];

					if ($column['value'] < $threshold_value) {
						break;
					}

					$color = $threshold['color'];
				}
			}

			switch ($column_config['data']) {
				case CWidgetFieldColumnsList::DATA_HOST_NAME:
					$row[] = (new CCol(
						(new CLinkAction($column['value']))->setMenuPopup(CMenuPopupHelper::getHost($column['hostid']))
					))
						->addStyle($color !== '' ? 'background-color: #' . $color : null)
						->addItem($column['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON
							? makeMaintenanceIcon($column['maintenance_type'], $column['maintenance_name'],
								$column['maintenance_description']
							)
							: null
						);

					break;

				case CWidgetFieldColumnsList::DATA_TEXT:
					$row[] = (new CCol($column['value']))
						->addStyle($color !== '' ? 'background-color: #' . $color : null);

					break;

				case CWidgetFieldColumnsList::DATA_ITEM_VALUE:
					if ($column['item']['value_type'] == ITEM_VALUE_TYPE_BINARY) {
						$formatted_value = italic(_('binary value'))->addClass($color === '' ? ZBX_STYLE_GREY : null);
						$column['value'] = _('binary value');
					}
					else {
						$formatted_value = formatHistoryValue($column['value'], $column['item'], true, [
							'decimals' => $column_config['decimal_places'],
							'decimals_exact' => true,
							'small_scientific' => false,
							'zero_as_zero' => false
						]);
					}

					if ($column_config['display'] == CWidgetFieldColumnsList::DISPLAY_AS_IS) {
						$row[] = (new CCol())
							->addStyle($color !== '' ? 'background-color: #' . $color : null)
							->addItem(
								(new CDiv($formatted_value))
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
							? '#' . $column_config['base_color']
							: Widget::DEFAULT_FILL
						)
						->setAttribute('min', $column['is_binary_units']
							? $column_config['min_binary']
							: $column_config['min']
						)
						->setAttribute('max', $column['is_binary_units']
							? $column_config['max_binary']
							: $column_config['max']
						);

					if ($column_config['display'] == CWidgetFieldColumnsList::DISPLAY_BAR) {
						$bar_gauge->setAttribute('solid', 1);
					}

					if (array_key_exists('thresholds', $column_config)) {
						foreach ($column_config['thresholds'] as $threshold) {
							$threshold_value = $column['is_binary_units']
								? $threshold['threshold_binary']
								: $threshold['threshold'];

							$bar_gauge->addThreshold($threshold_value, '#'.$threshold['color']);
						}
					}

					$row[] = new CCol($bar_gauge);
					$row[] = (new CCol())
						->addStyle('width: 0;')
						->addItem(
							(new CDiv($formatted_value))
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
}

(new CWidgetView($data))
	->addItem($table)
	->show();
