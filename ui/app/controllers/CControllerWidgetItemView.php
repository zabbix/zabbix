<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

	public const CHANGE_INDICATOR_UP = 1;
	public const CHANGE_INDICATOR_DOWN = 2;
	public const CHANGE_INDICATOR_UP_DOWN = 3;

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_ITEM);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json',
			'dynamic_hostid' => 'db hosts.hostid'
		]);
	}

	protected function doAction() {
		$name = $this->getDefaultName();
		$cells = [];
		$url = null;
		$error = '';
		$fields = $this->getForm()->getFieldsData();
		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		$description = '';
		$value = null;
		$change_indicator = null;
		$time = '';
		$units = '';
		$decimals = null;
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

		/*
		 * Select original item name in several cases: if user is in normal dashboards or in template dashboards when
		 * user is in view mode to display that item name in widget name. Item name should be select only if it is not
		 * overwritten. Host name can be attached to item name with delimiter when user is in normal dashboards.
		 */
		if ($this->getInput('name', '') === '') {
			if ($this->getContext() === CWidgetConfig::CONTEXT_DASHBOARD
					|| $this->getContext() === CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
					&& $this->hasInput('dynamic_hostid') && $tmp_items) {
				$options['output'] = array_merge($options['output'], ['name']);
			}

			if ($this->getContext() === CWidgetConfig::CONTEXT_DASHBOARD) {
				$options['selectHosts'] = ['name'];
			}
		}

		// Add other fields in case current widget is set in dynamic mode, template dashboard or has a specified host.
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

				// Time can be shown independently.
				if (array_key_exists(WIDGET_ITEM_SHOW_TIME, $show)) {
					$time = date(ZBX_FULL_DATE_TIME, (int) $history[$itemid][0]['clock']);
				}

				switch ($value_type) {
					case ITEM_VALUE_TYPE_FLOAT:
					case ITEM_VALUE_TYPE_UINT64:
						// Override item units if needed.
						if (array_key_exists(WIDGET_ITEM_SHOW_VALUE, $show) && $fields['units_show'] == 1) {
							$units = ($fields['units'] === '')
								? $items[$itemid]['units']
								: $fields['units'];
						}

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
						 * number. So we convert the value again which results in 0.00. In case decimal places are set
						 * to 10 (maximum), the value will be converted to 0.0012340000.
						 */
						if ($raw_units['is_numeric']) {
							$value = self::convertNumeric($value, $fields['decimal_places'], $value_type);
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

						if ($items[$itemid]['valuemap']) {
							// Apply value mapping if it is set in item configuration.
							$value = CValueMapHelper::applyValueMap($value_type, $value,
								$items[$itemid]['valuemap']
							);

							// Show of hide change indicator for mapped value.
							if (array_key_exists(WIDGET_ITEM_SHOW_CHANGE_INDICATOR, $show)) {
								$change_indicator = self::CHANGE_INDICATOR_UP_DOWN;
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
								$change_indicator = self::CHANGE_INDICATOR_UP;
							}
							elseif ($last_value < $prev_value) {
								$change_indicator = self::CHANGE_INDICATOR_DOWN;
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
								$change_indicator = self::CHANGE_INDICATOR_UP_DOWN;
							}
						}
						break;
				}
			}
			else {
				$value_type = ITEM_VALUE_TYPE_TEXT;

				// Since there is no value, we can still show time.
				if (array_key_exists(WIDGET_ITEM_SHOW_TIME, $show)) {
					$time = date(ZBX_FULL_DATE_TIME);
				}
			}

			if ($this->getInput('name', '') === '') {
				if ($this->getContext() === CWidgetConfig::CONTEXT_DASHBOARD
						|| $this->getContext() === CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
						&& $this->hasInput('dynamic_hostid')) {
					// Resolve original item name when user is in normal dashboards or template dashboards view mode.
					$name = $items[$itemid]['name'];
				}

				if ($this->getContext() === CWidgetConfig::CONTEXT_DASHBOARD) {
					$name = $items[$itemid]['hosts'][0]['name'].NAME_DELIMITER.$name;
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

			$cells = self::arrangeByCells($fields, [
				'description' => $description,
				'value_type' => $value_type,
				'units' => $units,
				'value' => $value,
				'decimals' => $decimals,
				'change_indicator' => $change_indicator,
				'time' => $time,
				'items' => $items,
				'itemid' => $itemid
			]);

			// Use the real item value type.
			$url = (new CUrl('history.php'))
				->setArgument('action',
					($items[$itemid]['value_type'] == ITEM_VALUE_TYPE_FLOAT
							|| $items[$itemid]['value_type'] == ITEM_VALUE_TYPE_UINT64)
						? HISTORY_GRAPH
						: HISTORY_VALUES
				)
				->setArgument('itemids[]', $itemid);
		}
		else {
			$error = _('No permissions to referred object or it does not exist!');
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $name),
			'cells' => $cells,
			'url' => $url,
			'bg_color' => $fields['bg_color'],
			'error' => $error,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	/**
	 * Convert numeric value using precise decimal points.
	 *
	 * @param string $value       Value to convert.
	 * @param int    $decimals    Number of decimal places.
	 * @param string $value_type  Item value type.
	 *
	 * @return string
	 */
	private static function convertNumeric(string $value, int $decimals, string $value_type): string {
		if ($value >= pow(10, ZBX_FLOAT_DIG)) {
			return sprintf('%.'.ZBX_FLOAT_DIG.'E', $value);
		}

		if ($value_type == ITEM_VALUE_TYPE_FLOAT) {
			$numeric_formatting = localeconv();

			return number_format((float) $value, $decimals, $numeric_formatting['decimal_point'],
				$numeric_formatting['thousands_sep']
			);
		}

		return $value;
	}

	/**
	 * Arrange all widget parts by cells, apply all related configuration settings to each part.
	 *
	 * @static
	 *
	 * @param array       $fields                      Input fields from the form.
	 * @param array       $fields['show']              Flags to show description, value, time and change indicator.
	 * @param int         $fields['desc_v_pos']        Vertical position of the description.
	 * @param int         $fields['desc_h_pos']        Horizontal position of the description.
	 * @param int         $fields['desc_bold']         Font weight of the description (0 - normal, 1 - bold).
	 * @param int         $fields['desc_size']         Font size of the description.
	 * @param string      $fields['desc_color']        Font color of the description.
	 * @param int         $fields['value_v_pos']       Vertical position of the value.
	 * @param int         $fields['value_h_pos']       Horizontal position of the value.
	 * @param int         $fields['value_bold']        Font weight of the value (0 - normal, 1 - bold).
	 * @param int         $fields['value_size']        Font size of the value.
	 * @param string      $fields['value_color']       Font color of the value.
	 * @param int         $fields['units_show']        Display units or not (0 - hide, 1 - show).
	 * @param int         $fields['units_pos']         Position of the units.
	 * @param int         $fields['units_bold']        Font weight of the units (0 - normal, 1 - bold).
	 * @param int         $fields['units_size']        Font size of the units.
	 * @param string      $fields['units_color']       Font color of the units.
	 * @param int         $fields['decimal_size']      Font size of the fraction.
	 * @param int         $fields['time_v_pos']        Vertical position of the time.
	 * @param int         $fields['time_h_pos']        Horizontal position of the time.
	 * @param int         $fields['time_bold']         Font weight of the time (0 - normal, 1 - bold).
	 * @param int         $fields['time_size']         Font size of the time.
	 * @param string      $fields['time_color']        Font color of the time.
	 * @param array       $values                      Array of pre-processed data that needs to be displayed.
	 * @param string      $values['description']       Item description with all macros resolved.
	 * @param string      $values['value_type']        Calculated value type. It can be integer or text.
	 * @param string      $values['units']             Units of the item. Can be empty string if nothing to show.
	 * @param string|null $values['value']             Value of the item or NULL if there is no value.
	 * @param string|null $values['decimals']          Decimal places or NULL if there is no decimals to show.
	 * @param int|null    $values['change_indicator']  Change indicator type or NULL if indicator should not be shown.
	 * @param string      $values['time']              Time when item received the value or current time if no data.
	 * @param array       $values['items']             The original array of items.
	 * @param string      $values['itemid']            Item ID from the host.
	 *
	 * @return array
	 */
	private static function arrangeByCells(array $fields, array $values): array {
		$cells = [];

		$show = array_flip($fields['show']);

		if (array_key_exists(WIDGET_ITEM_SHOW_DESCRIPTION, $show)) {
			$cells[$fields['desc_v_pos']][$fields['desc_h_pos']] = [
				'item_description' => [
					'text' => $values['description'],
					'font_size' => $fields['desc_size'],
					'bold' => ($fields['desc_bold'] == 1),
					'color' => $fields['desc_color']
				]
			];
		}

		if (array_key_exists(WIDGET_ITEM_SHOW_VALUE, $show)) {
			$item_value_cell = [
				'value_type' => $values['value_type']
			];

			if ($fields['units_show'] == 1 && $values['units'] !== '') {
				$item_value_cell['parts']['units'] = [
					'text' => $values['units'],
					'font_size' => $fields['units_size'],
					'bold' => ($fields['units_bold'] == 1),
					'color' => $fields['units_color']
				];
				$item_value_cell['units_pos'] = $fields['units_pos'];
			}

			$item_value_cell['parts']['value'] = [
				'text' => $values['value'],
				'font_size' => $fields['value_size'],
				'bold' => ($fields['value_bold'] == 1),
				'color' => $fields['value_color']
			];

			if ($values['decimals'] !== null) {
				$item_value_cell['parts']['decimals'] = [
					'text' => $values['decimals'],
					'font_size' => $fields['decimal_size'],
					'bold' => ($fields['value_bold'] == 1),
					'color' => $fields['value_color']
				];
			}

			$cells[$fields['value_v_pos']][$fields['value_h_pos']] = [
				'item_value' => $item_value_cell
			];
		}

		if (array_key_exists(WIDGET_ITEM_SHOW_CHANGE_INDICATOR, $show) && $values['change_indicator'] !== null) {
			$colors = [
				self::CHANGE_INDICATOR_UP => $fields['up_color'],
				self::CHANGE_INDICATOR_DOWN => $fields['down_color'],
				self::CHANGE_INDICATOR_UP_DOWN => $fields['updown_color']
			];

			// Change indicator can be displayed with or without value.
			$cells[$fields['value_v_pos']][$fields['value_h_pos']]['item_value']['parts']['change_indicator'] = [
				'type' => $values['change_indicator'],
				'font_size' => ($values['decimals'] !== null)
					? max($fields['value_size'], $fields['decimal_size'])
					: $fields['value_size'],
				'color' => $colors[$values['change_indicator']]
			];
		}

		if (array_key_exists(WIDGET_ITEM_SHOW_TIME, $show)) {
			$cells[$fields['time_v_pos']][$fields['time_h_pos']] = [
				'item_time' => [
					'text' => $values['time'],
					'font_size' => $fields['time_size'],
					'bold' => ($fields['time_bold'] == 1),
					'color' => $fields['time_color']
				]
			];
		}

		// Sort data column blocks in order - left, center, right.
		foreach ($cells as &$row) {
			ksort($row);
		}
		unset($row);

		return $cells;
	}
}
