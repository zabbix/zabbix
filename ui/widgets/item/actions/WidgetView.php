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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


namespace Widgets\Item\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CMacrosResolverHelper,
	CSettingsHelper,
	CUrl,
	CValueMapHelper,
	Manager;

use Widgets\Item\Widget;

use Zabbix\Core\CWidget;

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'dynamic_hostid' => 'db hosts.hostid'
		]);
	}

	protected function doAction(): void {
		$name = $this->widget->getDefaultName();
		$cells = [];
		$url = null;
		$error = '';
		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		$description = '';
		$value = null;
		$change_indicator = null;
		$time = '';
		$units = '';
		$decimals = null;
		$last_value = null;

		$options = [
			'output' => ['value_type'],
			'selectValueMap' => ['mappings'],
			'itemids' => $this->fields_values['itemid'],
			'webitems' => true,
			'preservekeys' => true
		];

		$is_template_dashboard = $this->hasInput('templateid');
		$is_dynamic = ($this->hasInput('dynamic_hostid')
			&& ($is_template_dashboard || $this->fields_values['dynamic'] == CWidget::DYNAMIC_ITEM)
		);

		$tmp_items = [];

		if ($is_dynamic) {
			$tmp_items = API::Item()->get([
				'output' => ['key_'],
				'itemids' => $this->fields_values['itemid'],
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

		$show = array_flip($this->fields_values['show']);

		/*
		 * Select original item name in several cases: if user is in normal dashboards or in template dashboards when
		 * user is in view mode to display that item name in widget name. Item name should be select only if it is not
		 * overwritten. Host name can be attached to item name with delimiter when user is in normal dashboards.
		 */
		if ($this->getInput('name', '') === '') {
			if (!$is_template_dashboard || ($this->hasInput('dynamic_hostid') && $tmp_items)) {
				$options['output'] = array_merge($options['output'], ['name']);
			}

			if (!$is_template_dashboard) {
				$options['selectHosts'] = ['name'];
			}
		}

		// Add other fields in case current widget is set in dynamic mode, template dashboard or has a specified host.
		if (($is_dynamic && $tmp_items) || !$is_dynamic) {
			// If description contains user macros, we need "itemid" and "hostid" to resolve them.
			if (array_key_exists(Widget::SHOW_DESCRIPTION, $show)) {
				$options['output'] = array_merge($options['output'], ['itemid', 'hostid']);
			}

			if ($this->fields_values['units_show'] == 1 && $this->fields_values['units'] === '') {
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

			if ($this->fields_values['itemid']) {
				$itemid = $this->fields_values['itemid'][0];
			}
		}

		if ($items) {
			$item = $items[$itemid];

			$history_limit = array_key_exists(Widget::SHOW_CHANGE_INDICATOR, $show) ? 2 : 1;
			$history = Manager::History()->getLastValues($items, $history_limit, $history_period);

			$value_type = $item['value_type'];

			if ($history) {
				$last_value = $history[$itemid][0]['value'];
				$prev_value = array_key_exists(1, $history[$itemid]) ? $history[$itemid][1]['value'] : null;

				if (array_key_exists(Widget::SHOW_TIME, $show)) {
					$time = date(ZBX_FULL_DATE_TIME, (int) $history[$itemid][0]['clock']);
				}

				switch ($value_type) {
					case ITEM_VALUE_TYPE_FLOAT:
					case ITEM_VALUE_TYPE_UINT64:
						if ($this->fields_values['units_show'] == 1) {
							if ($this->fields_values['units'] !== '') {
								$item['units'] = $this->fields_values['units'];
							}
						}
						else {
							$item['units'] = '';
						}

						$formatted_value = formatHistoryValueRaw($last_value, $item, false, [
							'decimals' => $this->fields_values['decimal_places'],
							'decimals_exact' => true,
							'small_scientific' => false,
							'zero_as_zero' => false
						]);

						$value = $formatted_value['value'];
						$units = $formatted_value['units'];

						if (!$formatted_value['is_mapped']) {
							$numeric_formatting = getNumericFormatting();
							$decimal_pos = strrpos($value, $numeric_formatting['decimal_point']);

							if ($decimal_pos !== false) {
								$decimals = substr($value, $decimal_pos);
								$value = substr($value, 0, $decimal_pos);
							}
						}

						if (array_key_exists(Widget::SHOW_CHANGE_INDICATOR, $show) && $prev_value !== null) {
							if ($formatted_value['is_mapped']) {
								if ($last_value != $prev_value) {
									$change_indicator = Widget::CHANGE_INDICATOR_UP_DOWN;
								}
							}
							elseif ($last_value > $prev_value) {
								$change_indicator = Widget::CHANGE_INDICATOR_UP;
							}
							elseif ($last_value < $prev_value) {
								$change_indicator = Widget::CHANGE_INDICATOR_DOWN;
							}
						}
						break;

					case ITEM_VALUE_TYPE_STR:
					case ITEM_VALUE_TYPE_TEXT:
					case ITEM_VALUE_TYPE_LOG:
						$value = formatHistoryValue($last_value, $items[$itemid], false);

						if (array_key_exists(Widget::SHOW_CHANGE_INDICATOR, $show) && $prev_value !== null
								&& $last_value !== $prev_value) {
							$change_indicator = Widget::CHANGE_INDICATOR_UP_DOWN;
						}
						break;
				}
			}
			else {
				$value_type = ITEM_VALUE_TYPE_TEXT;

				// Since there is no value, we can still show time.
				if (array_key_exists(Widget::SHOW_TIME, $show)) {
					$time = date(ZBX_FULL_DATE_TIME);
				}
			}

			if ($this->getInput('name', '') === '') {
				if (!$is_template_dashboard || $this->hasInput('dynamic_hostid')) {
					// Resolve original item name when user is in normal dashboards or template dashboards view mode.
					$name = $items[$itemid]['name'];
				}

				if (!$is_template_dashboard) {
					$name = $items[$itemid]['hosts'][0]['name'].NAME_DELIMITER.$name;
				}
			}

			/*
			 * It doesn't matter if item has value or not, description can be resolved separately if needed. If item
			 * will have value, it will resolve, otherwise it will not.
			 */
			if (array_key_exists(Widget::SHOW_DESCRIPTION, $show)) {
				// Overwrite item name with the custom description.
				$items[$itemid]['name'] = $this->fields_values['description'];

				// Do not resolve macros if using template dashboard. Template dashboards only have edit mode.
				if (!$is_template_dashboard || $this->hasInput('dynamic_hostid')) {
					$items = CMacrosResolverHelper::resolveWidgetItemNames($items);
				}

				// All macros in item name are resolved here.
				$description = $items[$itemid]['name'];
			}

			$cells = self::arrangeByCells($this->fields_values, [
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

		$bg_color = $this->fields_values['bg_color'];

		if ($last_value !== null) {
			foreach ($this->fields_values['thresholds'] as $threshold) {
				if ($threshold['threshold_value'] > $last_value) {
					break;
				}

				$bg_color = $threshold['color'];
			}
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $name),
			'cells' => $cells,
			'url' => $url,
			'bg_color' => $bg_color,
			'error' => $error,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	/**
	 * Arrange all widget parts by cells, apply all related configuration settings to each part.
	 *
	 * @static
	 *
	 * @param array       $fields_values  Input fields from the form.
	 * @param array       $fields_values  ['show']              Flags to show description, value, time and change indicator.
	 * @param int         $fields_values  ['desc_v_pos']        Vertical position of the description.
	 * @param int         $fields_values  ['desc_h_pos']        Horizontal position of the description.
	 * @param int         $fields_values  ['desc_bold']         Font weight of the description (0 - normal, 1 - bold).
	 * @param int         $fields_values  ['desc_size']         Font size of the description.
	 * @param string      $fields_values  ['desc_color']        Font color of the description.
	 * @param int         $fields_values  ['value_v_pos']       Vertical position of the value.
	 * @param int         $fields_values  ['value_h_pos']       Horizontal position of the value.
	 * @param int         $fields_values  ['value_bold']        Font weight of the value (0 - normal, 1 - bold).
	 * @param int         $fields_values  ['value_size']        Font size of the value.
	 * @param string      $fields_values  ['value_color']       Font color of the value.
	 * @param int         $fields_values  ['units_show']        Display units or not (0 - hide, 1 - show).
	 * @param int         $fields_values  ['units_pos']         Position of the units.
	 * @param int         $fields_values  ['units_bold']        Font weight of the units (0 - normal, 1 - bold).
	 * @param int         $fields_values  ['units_size']        Font size of the units.
	 * @param string      $fields_values  ['units_color']       Font color of the units.
	 * @param int         $fields_values  ['decimal_size']      Font size of the fraction.
	 * @param int         $fields_values  ['time_v_pos']        Vertical position of the time.
	 * @param int         $fields_values  ['time_h_pos']        Horizontal position of the time.
	 * @param int         $fields_values  ['time_bold']         Font weight of the time (0 - normal, 1 - bold).
	 * @param int         $fields_values  ['time_size']         Font size of the time.
	 * @param string      $fields_values  ['time_color']        Font color of the time.
	 * @param array       $data           Array of pre-processed data that needs to be displayed.
	 * @param string      $data           ['description']       Item description with all macros resolved.
	 * @param string      $data           ['value_type']        Calculated value type. It can be integer or text.
	 * @param string      $data           ['units']             Units of the item. Can be empty string if nothing to show.
	 * @param string|null $data           ['value']             Value of the item or NULL if there is no value.
	 * @param string|null $data           ['decimals']          Decimal places or NULL if there is no decimals to show.
	 * @param int|null    $data           ['change_indicator']  Change indicator type or NULL if indicator should not be shown.
	 * @param string      $data           ['time']              Time when item received the value or current time if no data.
	 * @param array       $data           ['items']             The original array of items.
	 * @param string      $data           ['itemid']            Item ID from the host.
	 *
	 * @return array
	 */
	private static function arrangeByCells(array $fields_values, array $data): array {
		$cells = [];

		$show = array_flip($fields_values['show']);

		if (array_key_exists(Widget::SHOW_DESCRIPTION, $show)) {
			$cells[$fields_values['desc_v_pos']][$fields_values['desc_h_pos']] = [
				'item_description' => [
					'text' => $data['description'],
					'font_size' => $fields_values['desc_size'],
					'bold' => ($fields_values['desc_bold'] == 1),
					'color' => $fields_values['desc_color']
				]
			];
		}

		if (array_key_exists(Widget::SHOW_VALUE, $show)) {
			$item_value_cell = [
				'value_type' => $data['value_type']
			];

			if ($fields_values['units_show'] == 1 && $data['units'] !== '') {
				$item_value_cell['parts']['units'] = [
					'text' => $data['units'],
					'font_size' => $fields_values['units_size'],
					'bold' => ($fields_values['units_bold'] == 1),
					'color' => $fields_values['units_color']
				];
				$item_value_cell['units_pos'] = $fields_values['units_pos'];
			}

			$item_value_cell['parts']['value'] = [
				'text' => $data['value'],
				'font_size' => $fields_values['value_size'],
				'bold' => ($fields_values['value_bold'] == 1),
				'color' => $fields_values['value_color']
			];

			if ($data['decimals'] !== null) {
				$item_value_cell['parts']['decimals'] = [
					'text' => $data['decimals'],
					'font_size' => $fields_values['decimal_size'],
					'bold' => ($fields_values['value_bold'] == 1),
					'color' => $fields_values['value_color']
				];
			}

			$cells[$fields_values['value_v_pos']][$fields_values['value_h_pos']] = [
				'item_value' => $item_value_cell
			];
		}

		if (array_key_exists(Widget::SHOW_CHANGE_INDICATOR, $show) && $data['change_indicator'] !== null) {
			$colors = [
				Widget::CHANGE_INDICATOR_UP => $fields_values['up_color'],
				Widget::CHANGE_INDICATOR_DOWN => $fields_values['down_color'],
				Widget::CHANGE_INDICATOR_UP_DOWN => $fields_values['updown_color']
			];

			// Change indicator can be displayed with or without value.
			$cells[$fields_values['value_v_pos']][$fields_values['value_h_pos']]['item_value']['parts']['change_indicator'] = [
				'type' => $data['change_indicator'],
				'font_size' => ($data['decimals'] !== null)
					? max($fields_values['value_size'], $fields_values['decimal_size'])
					: $fields_values['value_size'],
				'color' => $colors[$data['change_indicator']]
			];
		}

		if (array_key_exists(Widget::SHOW_TIME, $show)) {
			$cells[$fields_values['time_v_pos']][$fields_values['time_h_pos']] = [
				'item_time' => [
					'text' => $data['time'],
					'font_size' => $fields_values['time_size'],
					'bold' => ($fields_values['time_bold'] == 1),
					'color' => $fields_values['time_color']
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
