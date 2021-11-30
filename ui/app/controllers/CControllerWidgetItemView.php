<?php declare(strict_types = 1);
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
			'fields' => 'json',
			'dynamic_hostid' => 'db hosts.hostid'
		]);
	}

	protected function doAction() {
		$data = [];
		$error = '';
		$fields = $this->getForm()->getFieldsData();
		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		$description = '';
		$value = '';
		$change_indicator = [];
		$time = '';
		$units = '';
		$decimals = '';
		$is_dynamic = ($this->hasInput('dynamic_hostid')
				&& ($this->getContext() === CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
					|| $fields['dynamic'] == WIDGET_DYNAMIC_ITEM)
		);

		if ($is_dynamic) {
			$tmp_items = API::Item()->get([
				'output' => ['key_'],
				'itemids' => $fields['itemid'],
				'webitems' => true
			]);

			if ($tmp_items) {
				$options = [
					'output' => ['value_type'],
					'selectValueMap' => ['mappings'],
					'hostids' => [$this->getInput('dynamic_hostid')],
					'webitems' => true,
					'filter' => [
						'key_' => $tmp_items[0]['key_']
					],
					'preservekeys' => true
				];
			}
		}
		else {
			$options = [
				'output' => ['value_type'],
				'selectValueMap' => ['mappings'],
				'itemids' => $fields['itemid'],
				'webitems' => true,
				'preservekeys' => true
			];
		}

		$show = array_flip($fields['show']);

		if ($is_dynamic && $tmp_items || !$is_dynamic) {
			// If description contains user macros, we need "itemid" and "hostid" to resolve them.
			if (array_key_exists(WIDGET_ITEM_SHOW_DESCRIPTION, $show)) {
				$options['output'] = array_merge($options['output'], ['itemid', 'hostid']);
			}

			if (array_key_exists(WIDGET_ITEM_SHOW_VALUE, $show) && $fields['units_show'] == 1) {
				$options['output'][] = 'units';
			}
		}

		if ($is_dynamic) {
			if ($tmp_items) {
				$items = API::Item()->get($options);
				$itemid = key($items);
			}
			else {
				$items = [];
			}
		}
		else {
			$items = API::Item()->get($options);

			if ($fields['itemid']) {
				$itemid = $fields['itemid'][0];
			}
		}

		if ($items) {
			// Selecting data from history does not depend on "Show" checkboxes.
			$history = Manager::History()->getLastValues($items, 2, $history_period);
			$value_type = $items[$itemid]['value_type'];

			if ($history) {
				// Get values regardless of show status, since change indicator can be shown independently.
				$last_value = $history[$itemid][0]['value'];

				// Override item units if needed.
				if (array_key_exists(WIDGET_ITEM_SHOW_VALUE, $show) && $fields['units_show'] == 1) {
					$units = ($fields['units'] === '')
						? $items[$itemid]['units']
						: $fields['units'];
				}

				// Time can be shown independently.
				if (array_key_exists(WIDGET_ITEM_SHOW_TIME, $show)) {
					$time = date(ZBX_FULL_DATE_TIME, (int) $history[$itemid][0]['clock']);
				}

				switch ($value_type) {
					case ITEM_VALUE_TYPE_FLOAT:
					case ITEM_VALUE_TYPE_UINT64:
						// Apply unit conversion always because it will also convert values to scientific notation.
						$raw_units = convertUnitsRaw([
							'value' => $last_value,
							'units' => $units,
							'decimals' => $fields['decimal_places']
						]);
						// Get the converted value (this is not the final value).
						$value = $raw_units['value'];

						/*
						 * Get the converted units. If resulting units are empty, this could also mean value was
						 * converted to time.
						 */
						$units = $raw_units['units'];

						/*
						 * In case there is a numeric value, for example 0.001234 and decimal places are set to 2,
						 * convertUnitsRaw would return 0.0012, however in this widget we need to show the exact
						 * number. So we convert the value again which results in 0.
						 */
						if ($raw_units['is_numeric']) {
							$value = self::convertNumeric($value, $fields['decimal_places']);
						}

						// In order to split the number into value and fraction, we need to convert it to string.
						$value = (string) $value;

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

						if ($items[$itemid]['valuemap']) {
							// Apply value mapping if it is set in item configuration.
							$value = CValueMapHelper::applyValueMap($value_type, $value,
								$items[$itemid]['valuemap']
							);

							// Show of hide change indicator for mapped value.
							if (array_key_exists(WIDGET_ITEM_SHOW_CHANGE_INDICATOR, $show)) {
								$change_indicator = ['up' => true, 'down' => true,
									'fill_color' => $fields['updown_color']
								];
							}
						}
						elseif (array_key_exists(1, $history[$itemid])
								&& array_key_exists(WIDGET_ITEM_SHOW_CHANGE_INDICATOR, $show)) {
							/*
							 * If there is no value mapping and there is more than one value, add up or down change
							 * indicator. Do not show change indicator if value is the same.
							 */
							$prev_value = $history[$itemid][1]['value'];

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
					case ITEM_VALUE_TYPE_LOG:
						$value = $last_value;

						// Apply value mapping to string type values (same as in Latest Data).
						$mapping = CValueMapHelper::getMappedValue($value_type, $value,
							$items[$itemid]['valuemap']
						);

						if ($mapping !== false) {
							// Currently it is same as in latest data with original value in parenthesis.
							$value = $mapping.' ('.$value.')';
						}

						/*
						 * Even though \n does not affect HTML and would be shown in one line anyway, it is still
						 * better to process this and convert to empty space.
						 */
						$value = str_replace("\n", " ", $value);

						if (array_key_exists(1, $history[$itemid])
								&& array_key_exists(WIDGET_ITEM_SHOW_CHANGE_INDICATOR, $show)) {
							$prev_value = $history[$itemid][1]['value'];

							if ($last_value !== $prev_value) {
								$change_indicator = ['up' => true, 'down' => true,
									'fill_color' => $fields['updown_color']
								];
							}
						}
						break;
				}
			}
			else {
				$value = _('No data');
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
				$items[$itemid]['name'] = $fields['description'];

				// Do not resolve macros if using template dashboard. Template dashboards only have edit mode.
				if ($this->getContext() === CWidgetConfig::CONTEXT_DASHBOARD
						|| $this->getContext() === CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
						&& $this->hasInput('dynamic_hostid')) {
					$items = CMacrosResolverHelper::resolveWidgetItemNames($items);
				}

				// All macros in item name are resolved here.
				$description = $items[$itemid]['name'];
			}

			$data = $this->prepareData($fields, [
				'description' => $description,
				'value_type' => $value_type,
				'units' => $units,
				'history' => $history,
				'value' => $value,
				'decimals' => $decimals,
				'change_indicator' => $change_indicator,
				'time' => $time,
				'items' => $items,
				'itemid' => $itemid
			]);
		}
		else {
			$error = _('No permissions to referred object or it does not exist!');
		}

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
	 * Convert numeric value using precice decimal points.
	 *
	 * @param string $value     Value to convert.
	 * @param int    $decimals  Numer of decimal places.
	 *
	 * @return double|string  Return string if number is very large. Otherwise returns double (float).
	 */
	private static function convertNumeric(string $value, int $decimals) {
		if ($value >= pow(10, ZBX_FLOAT_DIG)) {
			return sprintf('%.'.ZBX_FLOAT_DIG.'E', $value);
		}
		else {
			return round((float) $value, $decimals);
		}
	}

	/*
	 * Calculate what to show and where, and prepare the output $data for view that contains classes, styles all the
	 * values and structure in order that the blocks should be displayed.
	 *
	 * @param array  $fields                      Input fields from the form.
	 * @param array  $fields['show']              Show checkboxes for description, value, time and change indicator.
	 * @param int    $fields['desc_v_pos']        Vertical position of the description.
	 * @param int    $fields['desc_h_pos']        Horizontal position of the description.
	 * @param int    $fields['desc_bold']         Font weight of the description (0 - normal, 1 - bold).
	 * @param int    $fields['desc_size']         Font size of the description.
	 * @param string $fields['desc_color']        Font color of the description.
	 * @param int    $fields['value_v_pos']       Vertical position of the value.
	 * @param int    $fields['value_h_pos']       Horizontal position of the value.
	 * @param int    $fields['value_bold']        Font weight of the value (0 - normal, 1 - bold).
	 * @param int    $fields['value_size']        Font size of the value.
	 * @param string $fields['value_color']       Font color of the value.
	 * @param int    $fields['units_show']        Display units or not (0 - hide, 1 - show).
	 * @param int    $fields['units_pos']         Position of the units.
	 * @param int    $fields['units_bold']        Font weight of the units (0 - normal, 1 - bold).
	 * @param int    $fields['units_size']        Font size of the units.
	 * @param string $fields['units_color']       Font color of the units.
	 * @param int    $fields['decimal_size']      Font size of the fraction.
	 * @param int    $fields['time_v_pos']        Vertical position of the time.
	 * @param int    $fields['time_h_pos']        Horizontal position of the time.
	 * @param int    $fields['time_bold']         Font weight of the time (0 - normal, 1 - bold).
	 * @param int    $fields['time_size']         Font size of the time.
	 * @param string $fields['time_color']        Font color of the time.
	 * @param string $fields['bg_color']          Background color of the widget (not including the header).
	 * @param array  $values                      Array of pre-processed data that needs to be displayed.
	 * @param string $values['description']       Item description with all macros resolved. Outgoing description can be
	 *                                            string or array if it contains multiple lines.
	 * @param string $values['value_type']        Calculated value type. It can be integer or text.
	 * @param string $values['units']             Units of the item. Can be empty string if nothing to show.
	 * @param array  $values['history']           History data. Can empty if item has no values.
	 * @param string $values['value']             Value of the item or "No data" string.
	 * @param string $values['decimals']          Decimal places.
	 * @param array  $values['change_indicator']  Change indicator data (arrow up, arrow down and color).
	 * @param string $values['time']              Time when item received the value or current time of no data.
	 * @param array  $values['items']             The original array of items.
	 * @param string $values['itemid']            Item ID from the host.
	 *
	 * @return array
	 */
	private function prepareData(array $fields, array $values): array {
		$data = [];

		$show = array_flip($fields['show']);

		if (array_key_exists(WIDGET_ITEM_SHOW_DESCRIPTION, $show)) {
			$v = $fields['desc_v_pos'];
			$h = $fields['desc_h_pos'];

			$classes = ['item-description', self::trVPos($v), self::trHPos($h)];
			if ($fields['desc_bold'] == 1) {
				$classes[] = 'bold';
			}

			if (strpos($values['description'], "\n") !== false) {
				$classes[] = 'multiline';
				$values['description'] = zbx_nl2br($values['description']);
			}

			$styles = ['--widget-item-font' => number_format($fields['desc_size'] / 100, 2)];
			// If advanced configuration is off, the color is null. Otherwise if default color is used, it is empty.
			if ($fields['desc_color'] !== null && $fields['desc_color'] !== '') {
				$styles['color'] = '#'.$fields['desc_color'];
			}

			$data[$v][$h] = [
				'item_description' => [
					// Description can be array or string.
					'data' => $values['description'],
					'classes' => $classes,
					'styles' => $styles
				]
			];
		}

		if (array_key_exists(WIDGET_ITEM_SHOW_VALUE, $show)) {
			$v = $fields['value_v_pos'];
			$h = $fields['value_h_pos'];

			// Wrap value, decimals, change indicator and units in "item-value" DIV.
			$classes = ['item-value', self::trVPos($v), self::trHPos($h),
				($values['value_type'] == ITEM_VALUE_TYPE_FLOAT || $values['value_type'] == ITEM_VALUE_TYPE_UINT64)
					? 'type-number'
					: 'type-text'
			];

			$data[$v][$h] = [
				'item_value' => [
					'item_value_content' => [
						'data' => [],
						'classes' => ['item-value-content']
					],
					'classes' => $classes
				]
			];

			if ($fields['units_show'] == 1 && $values['units'] !== '') {
				// Append "data" either before or after the wrapper if above or below value. Otherwise units are inside.
				if ($fields['units_pos'] == WIDGET_ITEM_POS_ABOVE) {
					$data[$v][$h]['item_value'] = [
						'data' => []
					] + $data[$v][$h]['item_value'];
				}
				elseif ($fields['units_pos'] == WIDGET_ITEM_POS_BELOW) {
					$data[$v][$h]['item_value']['data'] = [];
				}

				$units_classes = ['units'];
				if ($fields['units_bold'] == 1) {
					$units_classes[] = 'bold';
				}

				$units_styles = ['--widget-item-font' => number_format($fields['units_size'] / 100, 2)];
				// No need to check for null, since displaying units depend on value checkbox.
				if ($fields['units_color'] !== '') {
					$units_styles['color'] = '#'.$fields['units_color'];
				}

				if ($fields['units_pos'] == WIDGET_ITEM_POS_BEFORE) {
					$data[$v][$h]['item_value']['item_value_content']['data'][] = [
						'units' => [
							'data' => $values['units'],
							'classes' => $units_classes,
							'styles' => $units_styles
						]
					];
				}
				elseif ($fields['units_pos'] == WIDGET_ITEM_POS_ABOVE) {
					$data[$v][$h]['item_value']['data'][] = [
						'units' => [
							'data' => $values['units'],
							'classes' => $units_classes,
							'styles' => $units_styles
						]
					];
				}
			}

			$classes = ['value'];
			if ($fields['value_bold'] == 1) {
				$classes[] = 'bold';
			}

			if (!$values['history']) {
				$classes[] = 'item-value-no-data';
			}

			$styles = ['--widget-item-font' => number_format($fields['value_size'] / 100, 2)];
			if ($fields['value_color'] !== null && $fields['value_color'] !== '') {
				$styles['color'] = '#'.$fields['value_color'];
			}

			$data[$v][$h]['item_value']['item_value_content']['data'][] = [
				'value' => [
					'data' => $values['value'],
					'classes' => $classes,
					'styles' => $styles
				]
			];

			if ($values['decimals'] !== '' && ($values['value_type'] == ITEM_VALUE_TYPE_FLOAT
							|| $values['value_type'] == ITEM_VALUE_TYPE_UINT64)) {
				$classes = ['decimals'];
				if ($fields['value_bold'] == 1) {
					$classes[] = 'bold';
				}

				$styles = ['--widget-item-font' => number_format($fields['decimal_size'] / 100, 2)];
				if ($fields['value_color'] !== null && $fields['value_color'] !== '') {
					$styles['color'] = '#'.$fields['value_color'];
				}

				$data[$v][$h]['item_value']['item_value_content']['data'][] = [
					'decimals' => [
						'data' => $values['decimals'],
						'classes' => $classes,
						'styles' => $styles
					]
				];
			}

			if (array_key_exists(WIDGET_ITEM_SHOW_CHANGE_INDICATOR, $show) && $values['change_indicator']) {
				$data[$v][$h]['item_value']['item_value_content']['data'][] = [
					'change_indicator' => [
						'data' => $values['change_indicator'],
						'classes' => ['change-indicator'],
						'styles' => [
							'--widget-item-font' => number_format(
								max($fields['value_size'], $fields['decimal_size']) / 100, 2
							)
						]
					]
				];
			}

			if ($fields['units_show'] == 1 && $values['units'] !== '') {
				if ($fields['units_pos'] == WIDGET_ITEM_POS_AFTER) {
					$data[$v][$h]['item_value']['item_value_content']['data'][] = [
						'units' => [
							'data' => $values['units'],
							'classes' => $units_classes,
							'styles' => $units_styles
						]
					];
				}
				elseif ($fields['units_pos'] == WIDGET_ITEM_POS_BELOW) {
					$data[$v][$h]['item_value']['data'][] = [
						'units' => [
							'data' => $values['units'],
							'classes' => $units_classes,
							'styles' => $units_styles
						]
					];
				}
			}
		}
		elseif (array_key_exists(WIDGET_ITEM_SHOW_CHANGE_INDICATOR, $show) && $values['change_indicator']) {
			$v = $fields['value_v_pos'];
			$h = $fields['value_h_pos'];

			/*
			 * Show only change indicator without value, but do get the position of value where it should be.
			 * If change indicator is alone, it doesn't matter if type is text or number.
			 */
			$classes = ['item-value', self::trVPos($v), self::trHPos($h)];

			$data[$v][$h] = [
				'item_value' => [
					'item_value_content' => [
						'data' => [[
							'change_indicator' => [
								'data' => $values['change_indicator'],
								'classes' => ['change-indicator'],
								'styles' => ['--widget-item-font' => number_format($fields['value_size'] / 100, 2)]
							]
						]],
						'classes' => ['item-value-content']
					],
					'classes' => $classes
				]
			];
		}

		if (array_key_exists(WIDGET_ITEM_SHOW_TIME, $show)) {
			$v = $fields['time_v_pos'];
			$h = $fields['time_h_pos'];

			$classes = ['item-time', self::trVPos($v), self::trHPos($h)];
			if ($fields['time_bold'] == 1) {
				$classes[] = 'bold';
			}

			$styles = ['--widget-item-font' => number_format($fields['time_size'] / 100, 2)];
			if ($fields['time_color'] !== null && $fields['time_color'] !== '') {
				$styles['color'] = '#'.$fields['time_color'];
			}

			$data[$v][$h] = [
				'item_time' => [
					'data' => $values['time'],
					'classes' => $classes,
					'styles' => $styles
				]
			];
		}

		// Sort data column blocks in order - left, center, right.
		foreach ($data as &$row) {
			ksort($row);
		}
		unset($row);

		// Sort data row blocks in order - top, middle, bottom.
		ksort($data);

		// Use the real item value type.
		$data['url'] = (new CUrl('history.php'))
			->setArgument('action',
				($values['items'][$values['itemid']]['value_type'] == ITEM_VALUE_TYPE_FLOAT
					|| $values['items'][$values['itemid']]['value_type'] == ITEM_VALUE_TYPE_UINT64)
				? HISTORY_GRAPH
				: HISTORY_VALUES
			)
			->setArgument('itemids[]', $values['itemid']);

		$data['bg_color'] = ($fields['bg_color'] !== null && $fields['bg_color'] !== '') ? $fields['bg_color'] : '';

		return $data;
	}
}
