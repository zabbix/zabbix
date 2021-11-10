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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CControllerWidgetItemView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_ITEM);
		$this->setValidationRules([
			'edit_mode' => 'in 0,1',
			'name' => 'string',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$error = '';
		$fields = $this->getForm()->getFieldsData();

		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));

		$description = '';
		$value = '';
		$change_indicator = [];
		$time = '';
		$units = '';
		$decimals = '';

		$options = [
			'output' => [],
			'selectValueMap' => ['mappings'],
			'itemids' => $fields['itemid'],
			'webitems' => true,
			'filter' => [
				'status' => [ITEM_STATUS_ACTIVE],
				// Log items are not supported.
				'value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64,
					ITEM_VALUE_TYPE_TEXT
				]
			],
			'preservekeys' => true
		];

		$show = array_flip($fields['show']);

		// If there is a need to show description and description is not overridden, select it from item.
		if (array_key_exists(WIDGET_ITEM_SHOW_DESCRIPTION, $show)) {
			if ($fields['description'] === '') {
				$options['output'][] = 'name';
			}

			/*
			 * If name or description contains user macros, we need item "key_" and "hostid" to resolve them. If it
			 * contains HOST.IP, DNS etc macros, we need "hostid" and "interfaceid". In case of ITEM.* macros, we need
			 * "itemid" and "value_type" to get value from history.
			 */
			$options['output'] = array_merge($options['output'], ['key_', 'hostid', 'interfaceid', 'itemid',
				'value_type'
			]);
		}

		if (array_key_exists(WIDGET_ITEM_SHOW_VALUE, $show)) {
			// In case description is not shown, we still need value_type for history data.
			if (!in_array('value_type', $options['output'])) {
				$options['output'][] = 'value_type';
			}

			if ($fields['units_show'] == 1) {
				$options['output'][] = 'units';
			}
		}

		$items = API::Item()->get($options);

		if ($items) {
			// In case we want to show only value or only time or only change indicator, we need to search history data.
			$items_with_values = Manager::History()->getItemsHavingValues($items, $history_period);

			if ($items_with_values) {
				// Selecting data from history does not depend on "Show" checkboxes.
				$history = Manager::History()->getLastValues($items_with_values, 2, $history_period);

				if (array_key_exists($fields['itemid'][0], $history)
						&& array_key_exists(0, $history[$fields['itemid'][0]])) {
					// Process value only if needs to be shown.
					if (array_key_exists(WIDGET_ITEM_SHOW_VALUE, $show)) {
						$last_value = $history[$fields['itemid'][0]][0]['value'];

						// The view will determine when to show ellipsis for text values.
						$value_type = $items_with_values[$fields['itemid'][0]]['value_type'];

						// Override item units if needed.
						$units = ($fields['units'] === '')
							? ($fields['units_show'] == 1)
								? $items_with_values[$fields['itemid'][0]]['units']
								: ''
							: $fields['units'];
					}

					// Time can be shown independently.
					if (array_key_exists(WIDGET_ITEM_SHOW_TIME, $show)) {
						$time = date(ZBX_FULL_DATE_TIME, $history[$fields['itemid'][0]][0]['clock']);
					}

					switch ($value_type) {
						case ITEM_VALUE_TYPE_FLOAT:
						case ITEM_VALUE_TYPE_UINT64:
							// Apply unit conversion always because it will also convert values to scientific notation.
							$value = convertUnits([
								'value' => $last_value,
								'units' => $units,
								'decimals' => $fields['decimal_places']
							]);

							/*
							 * Perform splitting units and value only if conversion actually happened. In case there is
							 * a thousands separator space, find the last space that could be the units separator.
							 */
							if ($value != $last_value) {
								$pos = strrpos($value, ' ');

								if ($pos !== false) {
									$units = substr($value, $pos + 1);
									$value = substr($value, 0, $pos);
								}
							}

							/*
							 * Regardless of unit conversion, separate the decimals from value. In case of scientific
							 * notation, use the whole string after decimal separator.
							 */
							$numeric_formatting = localeconv();
							$pos = strrpos($value, $numeric_formatting['decimal_point']);

							if ($pos !== false) {
								// Include the dot as part of decimal, so it can be shown in different font size.
								$decimals = substr($value, $pos);
								$value = substr($value, 0, $pos);
							}

							if ($items_with_values[$fields['itemid'][0]]['valuemap']) {
								// Apply value mapping if it is set in item configuration.
								$value = CValueMapHelper::applyValueMap($value_type, $value,
									$items_with_values[$fields['itemid'][0]]['valuemap']
								);

								// Show of hide change indicator for mapped value.
								if (array_key_exists(WIDGET_ITEM_SHOW_CHANGE_INDICATOR, $show)) {
									$change_indicator = ['up' => true, 'down' => true,
										'fill_color' => $fields['updown_color']
									];
								}
							}
							elseif (array_key_exists(1, $history[$fields['itemid'][0]])
									&& array_key_exists(WIDGET_ITEM_SHOW_CHANGE_INDICATOR, $show)) {
								/*
								 * If there is no value mapping and there is more than one value, add up or down change
								 * indicator. Do not show change indicator if value is the same.
								 */
								$prev_value = $history[$fields['itemid'][0]][1]['value'];

								if ($last_value > $prev_value) {
									$change_indicator = ['up' => true, 'fill_color' => $fields['up_color']];
								}
								elseif ($last_value < $prev_value) {
									$change_indicator = ['down' => true, 'fill_color' => $fields['down_color']];
								}
							}
							break;

						case ITEM_VALUE_TYPE_STR:
						case ITEM_VALUE_TYPE_TEXT:
							// Apply value mapping to string type values (same as in Latest Data).
							$mapping = CValueMapHelper::getMappedValue($value_type, $value,
								$items_with_values[$fields['itemid'][0]]['valuemap']
							);

							if ($mapping !== false) {
								// To do: same as in latest data, but could be that original value should not be shown.
								$value = $mapping.' ('.$value.')';
							}

							if (array_key_exists(1, $history[$fields['itemid'][0]])
									&& array_key_exists(WIDGET_ITEM_SHOW_CHANGE_INDICATOR, $show)) {
								$prev_value = $history[$fields['itemid'][0]][1]['value'];

								if ($last_value !== $prev_value) {
									$change_indicator = ['up' => true, 'down' => true,
										'fill_color' => $fields['updown_color']
									];
								}
							}
							break;
					}
				}
			}
			else {
				$value = _('No data.');

				// The value automatically becomes string type, so it can be truncated if necessary.
				$value_type = ITEM_VALUE_TYPE_TEXT;

				// Since there no value, we can still show time.
				if (array_key_exists(WIDGET_ITEM_SHOW_TIME, $show)) {
					$time = date(ZBX_FULL_DATE_TIME);
				}
			}

			/*
			 * It doesn't matter if item has value or not, description can be resolved separately if needed. If item
			 * will have value it will resolve, otherwise it will not.
			 */
			if (array_key_exists(WIDGET_ITEM_SHOW_DESCRIPTION, $show)) {
				// Overwrite item name with the custom description.
				if ($fields['description'] !== '') {
					$items[$fields['itemid'][0]]['name'] = $fields['description'];
				}

				// Do not resolve macros if using template dashboard. Template dashboards only have edit mode.
				if ($this->getContext() === CWidgetConfig::CONTEXT_DASHBOARD) {
					$items = CMacrosResolverHelper::resolveWidgetItemNames($items);
				}

				// All macros in item name are resolved here.
				$description = $items[$fields['itemid'][0]]['name'];
			}
		}
		else {
			$error = _('No permissions to referred object or it does not exist!');
		}

		// Calculate what to show and where.
		$data = [];

		if (array_key_exists(WIDGET_ITEM_SHOW_DESCRIPTION, $show)) {
			$classes = ['description', self::trVPos($fields['desc_v_pos']), self::trHPos($fields['desc_h_pos'])];
			if ($fields['desc_bold'] == 1) {
				$classes[] = 'bold';
			}

			$styles = ['--item-font' => number_format($fields['desc_size'] / 100, 2)];
			if ($fields['desc_color'] !== '') {
				$styles['color'] = '#'.$fields['desc_color'];
			}

			$data[$fields['desc_v_pos']][$fields['desc_h_pos']] = [
				'data' => $description,
				'classes' => $classes,
				'styles' => $styles
			];
		}

		if (array_key_exists(WIDGET_ITEM_SHOW_VALUE, $show)) {
			$classes = ['item-value', self::trVPos($fields['value_v_pos']), self::trHPos($fields['value_h_pos'])];

			$data[$fields['value_v_pos']][$fields['value_h_pos']] = [
				'data' => [],
				'classes' => $classes
			];

			if ($fields['units_show'] == 1 && $units !== '') {
				$units_classes = ['units', self::trUPos($fields['units_pos'])];
				if ($fields['units_bold'] == 1) {
					$units_classes[] = 'bold';
				}

				$units_styles = ['--item-font' => number_format($fields['units_size'] / 100, 2)];
				if ($fields['units_color'] !== '') {
					$units_styles['color'] = '#'.$fields['units_color'];
				}

				if (in_array($fields['units_pos'], [WIDGET_ITEM_POS_BEFORE, WIDGET_ITEM_POS_ABOVE])) {
					$data[$fields['value_v_pos']][$fields['value_h_pos']]['data'][] = [
						'data' => $units,
						'classes' => $units_classes,
						'styles' => $units_styles
					];
				}
			}

			$classes = ['value'];
			if ($fields['value_bold'] == 1) {
				$classes[] = 'bold';
			}

			$styles = ['--item-font' => number_format($fields['value_size'] / 100, 2)];
			if ($fields['value_color'] !== '') {
				$styles['color'] = '#'.$fields['value_color'];
			}

			$data[$fields['value_v_pos']][$fields['value_h_pos']]['data'][] = [
				'data' => $value,
				'value_type' => $value_type,
				'classes' => $classes,
				'styles' => $styles
			];

			if ($decimals !== '' && in_array($value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
				$classes = ['decimals'];
				if ($fields['value_bold'] == 1) {
					$classes[] = 'bold';
				}

				$styles = ['--item-font' => number_format($fields['decimal_size'] / 100, 2)];
				if ($fields['value_color'] !== '') {
					$styles['color'] = '#'.$fields['value_color'];
				}

				$data[$fields['value_v_pos']][$fields['value_h_pos']]['data'][] = [
					'data' => $decimals,
					'classes' => $classes,
					'styles' => $styles
				];
			}

			if (array_key_exists(WIDGET_ITEM_SHOW_CHANGE_INDICATOR, $show) && $change_indicator) {
				$data[$fields['value_v_pos']][$fields['value_h_pos']]['data'][] = [
					'data' => $change_indicator,
					'classes' => ['change-indicator'],
					'styles' => ['--item-font' => number_format($fields['value_size'] / 100, 2)]
				];
			}

			if ($fields['units_show'] == 1 && $units !== ''
					&& in_array($fields['units_pos'], [WIDGET_ITEM_POS_AFTER, WIDGET_ITEM_POS_BELOW])) {
				$data[$fields['value_v_pos']][$fields['value_h_pos']]['data'][] = [
					'data' => $units,
					'classes' => $units_classes,
					'styles' => $units_styles
				];
			}
		}
		elseif (array_key_exists(WIDGET_ITEM_SHOW_CHANGE_INDICATOR, $show) && $change_indicator) {
			// Show only change indicator without value, but do get the position of value where it should be.
			$classes = ['item-value', self::trVPos($fields['value_v_pos']), self::trHPos($fields['value_h_pos'])];

			$data[$fields['value_v_pos']][$fields['value_h_pos']] = [
				'data' => [[
					'data' => $change_indicator,
					'classes' => ['change-indicator'],
					'styles' => ['--item-font' => number_format($fields['value_size'] / 100, 2)]
				]],
				'classes' => $classes
			];
		}

		if (array_key_exists(WIDGET_ITEM_SHOW_TIME, $show)) {
			$classes = ['time', self::trVPos($fields['time_v_pos']), self::trHPos($fields['time_h_pos'])];
			if ($fields['time_bold'] == 1) {
				$classes[] = 'bold';
			}

			$styles = ['--item-font' => number_format($fields['time_size'] / 100, 2)];
			if ($fields['time_color'] !== '') {
				$styles['color'] = '#'.$fields['time_color'];
			}

			$data[$fields['time_v_pos']][$fields['time_h_pos']] = [
				'data' => $time,
				'classes' => $classes,
				'styles' => $styles
			];
		}

		// Sort data in order - top, middle, bottom and left, center, right.
		foreach ($data as $row) {
			ksort($row);
		}
		ksort($data);

		$data['bg_color'] = $fields['bg_color'];

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultName()),
			'data' => $data,
			'error' => $error,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	/**
	 * Translate horizontal posittion to CSS class.
	 *
	 * @static
	 *
	 * @param int $pos  Position of element.
	 *
	 * @return string
	 */
	private static function trHPos(int $pos): string {
		switch ($pos) {
			case WIDGET_ITEM_POS_LEFT:
				return 'left';

			case WIDGET_ITEM_POS_CENTER:
				return 'center';

			case WIDGET_ITEM_POS_RIGHT:
				return 'right';
		}
	}

	/**
	 * Translate vertical posittion to CSS class.
	 *
	 * @static
	 *
	 * @param int $pos  Position of element.
	 *
	 * @return string
	 */
	private static function trVPos(int $pos): string {
		switch ($pos) {
			case WIDGET_ITEM_POS_TOP:
				return 'top';

			case WIDGET_ITEM_POS_MIDDLE:
				return 'middle';

			case WIDGET_ITEM_POS_BOTTOM:
				return 'bottom';

		}
	}

	/**
	 * Translate units posittion to CSS class.
	 *
	 * @static
	 *
	 * @param int $pos  Position of element.
	 *
	 * @return string
	 */
	private static function trUPos(int $pos): string {
		switch ($pos) {
			case WIDGET_ITEM_POS_ABOVE:
				return 'top';

			case WIDGET_ITEM_POS_BEFORE:
				return 'left';

			case WIDGET_ITEM_POS_AFTER:
				return 'right';

			case WIDGET_ITEM_POS_BELOW:
				return 'bottom';
		}
	}
}
