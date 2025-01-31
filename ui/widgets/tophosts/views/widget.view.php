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
 * Top hosts widget view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\TopHosts\Widget;

use Widgets\TopHosts\Includes\CWidgetFieldColumnsList;

$table = (new CTableInfo())->addClass($data['show_thumbnail'] ? 'show-thumbnail' : null);

if ($data['error'] !== null) {
	$table->setNoDataMessage($data['error']);
}
else {
	$header = [];

	foreach ($data['configuration'] as $column_config) {
		if (shouldUseDoubleColumnHeader($column_config)) {
			$header[] = (new CColHeader(
				(new CSpan($column_config['name']))
					->setTitle($column_config['name'])
			))->setColSpan(2);
		}
		else {
			$header[] = new CColHeader($column_config['name']);
		}
	}

	$table->setHeader($header);

	foreach ($data['rows'] as ['columns' => $columns, 'context' => $context]) {
		$row = [];
		$is_empty_row = true;

		foreach ($columns as $i => $column) {
			$column_config = $data['configuration'][$i];

			if ($column === null) {
				$row[] = shouldUseDoubleColumnHeader($column_config) ? (new CCol(''))->setColSpan(2) : '';

				continue;
			}

			$is_empty_row = false;
			$color = $column_config['base_color'];

			// Create each column's cell display according to configuration and value type.
			switch ($column_config['data']) {
				case CWidgetFieldColumnsList::DATA_HOST_NAME:
					$row[] = (new CCol(
						(new CLinkAction($column['value']))->setMenuPopup(CMenuPopupHelper::getHost($column['hostid']))
					))
						->setAttribute('bgcolor', $color !== '' ? '#'.$color : null)
						->addItem($column['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON
							? makeMaintenanceIcon($column['maintenance_type'], $column['maintenance_name'],
								$column['maintenance_description']
							)
							: null
						);

					break;

				case CWidgetFieldColumnsList::DATA_TEXT:
					$row[] = (new CCol($column['value']))
						->setAttribute('bgcolor', $color !== '' ? '#'.$color : null);

					break;

				case CWidgetFieldColumnsList::DATA_ITEM_VALUE:
					if (array_key_exists('thresholds', $column_config) && array_key_exists('value', $column)
							&& ($column_config['display'] == CWidgetFieldColumnsList::DISPLAY_AS_IS
								|| $column_config['display'] == CWidgetFieldColumnsList::DISPLAY_SPARKLINE)
							) {
						$is_numeric_data = in_array($column['item']['value_type'],
							[ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]
						) || CAggFunctionData::isNumericResult($column_config['aggregate_function']);

						if ($is_numeric_data) {
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
					}

					$formatted_value = getFormattedValue($column, $column_config);

					if ($column_config['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_BINARY) {
						if ($column['item']['value_type'] == ITEM_VALUE_TYPE_BINARY
								&& $column_config['aggregate_function'] != AGGREGATE_COUNT) {
							$row[] = (new CCol(
								createBinaryShowButton($column_config['name'], $column_config['show_thumbnail'])
							))
								->setAttribute('bgcolor', $color !== '' ? '#'.$color : null)
								->setAttribute('data-itemid', $column['item']['itemid'])
								->setAttribute('data-clock', $column['value']['clock'].'.'.$column['value']['ns'])
								->setColSpan(2);
						}
						else {
							$value = $column_config['aggregate_function'] == AGGREGATE_COUNT
								? $column['value']
								: base64_encode($column['value']);

							$row[] = (new CCol(
								createNonBinaryShowButton($column_config['name'])
							))
								->setAttribute('bgcolor', $color !== '' ? '#'.$color : null)
								->setHint(
									(new CDiv($value))->addClass(ZBX_STYLE_HINTBOX_WRAP)
								)
								->setColSpan(2);
						}
					}
					elseif ($column_config['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_TEXT) {
						if (array_key_exists('highlights', $column_config)) {
							$value_to_check = $column['item']['value_type'] == ITEM_VALUE_TYPE_BINARY
								? $column['value']
								: $formatted_value;

							foreach ($column_config['highlights'] as $highlight) {
								if (@preg_match('('.$highlight['pattern'].')', $value_to_check)) {
									$color = $highlight['color'];
									break;
								}
							}
						}

						$row[] = createTextColumn($formatted_value, $column['value'], $color, true);
					}
					elseif ($column_config['display'] == CWidgetFieldColumnsList::DISPLAY_AS_IS) {
						$row[] = createTextColumn($formatted_value, $column['value'], $color);
					}
					elseif ($column_config['display'] == CWidgetFieldColumnsList::DISPLAY_SPARKLINE) {
						$row[] = new CCol((new CSparkline())
							->setHeight(20)
							->setColor('#'.$column_config['sparkline']['color'])
							->setLineWidth($column_config['sparkline']['width'])
							->setFill($column_config['sparkline']['fill'])
							->setValue($column['sparkline_value'] ?? [])
							->setTimePeriodFrom($column_config['sparkline']['time_period']['from_ts'])
							->setTimePeriodTo($column_config['sparkline']['time_period']['to_ts'])
						);
						$row[] = createTextColumn($formatted_value, $column['value'] ?? '', $color);
					}
					else {
						$bar_gauge = createBarGauge($column, $column_config, $color);

						$row[] = new CCol($bar_gauge);
						$row[] = (new CCol())
							->addStyle('width: 0;')
							->addItem(
								(new CDiv($formatted_value))
									->addClass(ZBX_STYLE_CURSOR_POINTER)
									->addClass(ZBX_STYLE_NOWRAP)
									->setHint((new CDiv($column['value']))->addClass(ZBX_STYLE_HINTBOX_WRAP))
							);
					}

					break;
			}
		}

		if (!$is_empty_row) {
			$table->addRow(
				(new CRow($row))->setAttribute('data-hostid', $context['hostid'])
			);
		}
	}
}

(new CWidgetView($data))
	->addItem($table)
	->show();

function shouldUseDoubleColumnHeader(array $column_config): bool {
	return $column_config['data'] == CWidgetFieldColumnsList::DATA_ITEM_VALUE
		&& ($column_config['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_BINARY
			|| ($column_config['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC
				&& $column_config['display'] != CWidgetFieldColumnsList::DISPLAY_AS_IS));
}

function getFormattedValue(array $column, array $column_config): string {
	if (!array_key_exists('value', $column)) {
		return '';
	}

	if (in_array($column['item']['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
		return formatAggregatedHistoryValue($column['value'], $column['item'], $column_config['aggregate_function'],
			false, true, [
				'decimals' => $column_config['decimal_places'],
				'decimals_exact' => true,
				'small_scientific' => false,
				'zero_as_zero' => false
			]
		);
	}

	if ($column['item']['value_type'] != ITEM_VALUE_TYPE_BINARY) {
		return formatAggregatedHistoryValue($column['value'], $column['item'], $column_config['aggregate_function']);
	}

	if ($column_config['display_value_as'] != CWidgetFieldColumnsList::DISPLAY_VALUE_AS_BINARY) {
		return substr($column['value'], 0, Widget::TEXT_MAX_LENGTH).
			(strlen($column['value']) > Widget::TEXT_MAX_LENGTH ? '...' : '');
	}

	return '';
}

function createBinaryShowButton(string $column_name, bool $show_thumbnail = false): CButton {
	return (new CButton(null, _('Show')))
		->addClass($show_thumbnail ? 'btn-thumbnail' : ZBX_STYLE_BTN_LINK)
		->addClass('js-show-binary')
		->setAttribute('data-alt', $column_name);
}

function createNonBinaryShowButton(string $column_name): CButton {
	return (new CButton(null, _('Show')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->setAttribute('data-alt', $column_name);
}

function createTextColumn(string $formatted_value, string $hint_value, string $color, bool $raw_data = false): CCol {
	$hint = (new CDiv($hint_value))->addClass(ZBX_STYLE_HINTBOX_WRAP);

	if ($raw_data) {
		$hint->addClass(ZBX_STYLE_HINTBOX_RAW_DATA);
	}

	return (new CCol())
		->setAttribute('bgcolor', $color !== '' ? '#'.$color : null)
		->addItem(
			(new CDiv($formatted_value))
				->addClass(ZBX_STYLE_CURSOR_POINTER)
				->setHint($hint)
		);
}

function createBarGauge(array $column, array $column_config, string $color): CBarGauge {
	$bar_gauge = (new CBarGauge())
		->setValue($column['value'])
		->setAttribute('fill', $color !== '' ? '#'.$color : Widget::DEFAULT_FILL)
		->setAttribute('min', $column['is_binary_units'] ? $column_config['min_binary'] : $column_config['min'])
		->setAttribute('max', $column['is_binary_units'] ? $column_config['max_binary'] : $column_config['max']);

	if ($column_config['display'] == CWidgetFieldColumnsList::DISPLAY_BAR) {
		$bar_gauge->setAttribute('solid', 1);
	}

	if (array_key_exists('thresholds', $column_config)) {
		foreach ($column_config['thresholds'] as $threshold) {
			$threshold_value = $column['is_binary_units'] ? $threshold['threshold_binary'] : $threshold['threshold'];

			$bar_gauge->addThreshold($threshold_value, '#'.$threshold['color']);
		}
	}

	return $bar_gauge;
}
