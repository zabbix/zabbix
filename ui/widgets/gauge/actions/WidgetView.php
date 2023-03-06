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


namespace Widgets\Gauge\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CMacrosResolverHelper,
	CSettingsHelper,
	CUrl,
	CValueMapHelper,
	Manager,
	CNumberParser,
	CParser;

use Widgets\Gauge\Widget;

use Zabbix\Core\CWidget;

class WidgetView extends CControllerDashboardWidgetView {

	/**
	 * Array of items.
	 *
	 * @var array
	 */
	private $items = [];

	/**
	 * Item ID.
	 *
	 * @var string
	 */
	private $itemid = null;

	/**
	 * If user is in template dashboards or not.
	 *
	 * @var bool
	 */
	private $is_template_dashboard = false;

	/**
	 * If host is currently dynamically selected.
	 *
	 * @var bool
	 */
	private $is_dynamic = false;

	/**
	 * If user is selecting host dynamically, set temporary items to later calculate real itemm by key.
	 * @var array
	 */
	private $tmp_items = [];

	/**
	 * Set base units string from either item or custom units in widget configuration.
	 *
	 * @var string
	 */
	private $unit_base = '';

	/**
	 * Set value defaults. Use text type for errors or no data as default.
	 *
	 * @var array
	 */
	private $value = [
		'type' => ITEM_VALUE_TYPE_TEXT,
		'units' => '',
		'power' => null
	];

	/**
	 * Previous item value.
	 *
	 * @var int|float
	 */
	private $prev_value = null;

	/**
	 * Array of gauge min values. Contains raw value and converted value with units.
	 *
	 * @var array
	 */
	private $min = [];

	/**
	 * Array of gauge max values. Contains raw value and converted value with units.
	 *
	 * @var array
	 */
	private $max = [];

	/**
	 * Array of threshold values and colors.
	 *
	 * @var array
	 */
	private $thresholds;

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'initial_load' => 'in 0,1',
			'dynamic_hostid' => 'db hosts.hostid'
		]);
	}

	protected function doAction(): void {
		$error = '';
		// Set default text in case there is no data for item.
		$this->value['text'] = _('No data.');

		$this->is_template_dashboard = $this->hasInput('templateid');
		$this->is_dynamic = ($this->hasInput('dynamic_hostid')
			&& ($this->is_template_dashboard || $this->fields_values['dynamic'] == CWidget::DYNAMIC_ITEM)
		);

		$this->setItems();
		$this->setItemUnits();
		$this->setUnitBase();

		$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

		if ($this->items) {
			$history = $this->getItemHistory();
			$this->setValue($history);
			$this->setPreviousValue($history);

			$is_binary = false || in_array($this->unit_base, ['B', 'Bps']);
			$calc_power = false || $this->unit_base === '' || $this->unit_base[0] !== '!';

			$this->min['raw'] = $number_parser->parse($this->fields_values['min']) == CParser::PARSE_SUCCESS
				? $number_parser->calcValue()
				: null;

			$this->max['raw'] = $number_parser->parse($this->fields_values['max']) == CParser::PARSE_SUCCESS
				? $number_parser->calcValue()
				: null;

			// At this point the Min/Max should be floatin point values. If not, then that is a critical system error.
			$minmax_power = $calc_power
				? (int) min(8, max(0, floor(log(max(
					abs(truncateFloat($this->min['raw'])),
					abs(truncateFloat($this->max['raw']))
				), $is_binary ? ZBX_KIBIBYTE : 1000))))
				: 0;

			$power = $minmax_power;

			if ($this->fields_values['minmax_show_units'] == 0) {
				$power = $this->value['power'];
			}

			$this->setMin($is_binary, $power);
			$this->setMax($is_binary, $power);
			$this->setThresholds($is_binary, $power);

			// Create and URL to history. Widget will be placed inside <a>.
			$url = (new CUrl('history.php'))
				->setArgument('action', HISTORY_GRAPH)
				->setArgument('itemids[]', $this->itemid);
		}
		else {
			$error = _('No permissions to referred object or it does not exist!');
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getWidgetName(),
			'error' => $error,
			'url' => $url,
			'data' => [
				'description' => [
					'text' => $this->getDescription($this->items),
					'font_size' => $this->fields_values['desc_size'],
					'pos' => $this->fields_values['desc_v_pos'],
					'is_bold' => ($this->fields_values['desc_bold'] == 1),
					'color' => $this->fields_values['desc_color']
				],
				'value' => [
					'type' => $this->value['type'],
					'text' => $this->value['text'],
					'font_size' => $this->fields_values['value_size'],
					'is_bold' => ($this->fields_values['value_bold'] == 1),
					'color' => $this->fields_values['value_color'],
					'show_arc' => ($this->fields_values['value_arc'] == 1),
					'arc_size' => $this->fields_values['value_arc_size'],
					'prev_value' => $this->prev_value
				],
				'units' => [
					'text' => $this->value['units'],
					'pos' => $this->fields_values['units_pos'],
					'show' => ($this->fields_values['units_show'] == 1),
					'font_size' => $this->fields_values['units_size'],
					'is_bold' => ($this->fields_values['units_bold'] == 1),
					'color' => $this->fields_values['units_color']
				],
				'needle' => [
					'show' => ($this->fields_values['needle_show'] == 1),
					'color' => $this->fields_values['needle_color']
				],
				'minmax' => [
					'min' => $this->min,
					'max' => $this->max,
					'show' => ($this->fields_values['minmax_show'] == 1),
					'size' => $this->fields_values['minmax_size']
				],
				'empty_color' => $this->fields_values['empty_color'],
				'bg_color' => $this->fields_values['bg_color'],
				'thresholds' => [
					'data' => $this->thresholds,
					'show_arc' => ($this->fields_values['th_show_arc'] == 1),
					'arc_size' => $this->fields_values['th_arc_size'],
					'show_labels' => ($this->fields_values['th_show_labels'] == 1)
				]
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	/**
	 * Get widget name based on default settings, custom name and item name. If a custom name is set, use that as widget
	 * name. Show "Gauge" as widget name if user is in template dashboards or item does not exist and custom name is not
	 * set. If item exists and user is in normal dashboards, use both host name and item name using name delimiter.
	 *
	 * @return string
	 */
	private function getWidgetName(): string {
		$item = $this->items[$this->itemid] ?? null;
		$name = $this->widget->getDefaultName();

		if ($this->getInput('name', '') === '' && $item !== null) {
			if (!$this->hasInput('templateid') || $this->hasInput('dynamic_hostid')) {
				$name = $item['name'];
			}

			if (!$this->hasInput('templateid')) {
				$name = $item['hosts'][0]['name'].NAME_DELIMITER.$name;
			}
		}

		return $this->getInput('name', $name);
	}

	/**
	 * Get description. Resolve macros in description if item exists and user is not templated dashboards or dynamic
	 * host is requested.
	 *
	 * @param array $items  Array of items with keys as item IDs. So that macros can be resolved in item name.
	 *
	 * $items = [
	 *     <itemid> => [
	 *         'name' => (string) Item name.
	 *     ]
	 * ]
	 *
	 * @return string
	 */
	private function getDescription(array $items): string {
		$description = $this->fields_values['description'];

		if ($items) {
			// Overwrite item name with the custom description.
			$items[$this->itemid]['name'] = $description;

			// Do not resolve macros if using template dashboard. Template dashboards only have edit mode.
			if (!$this->hasInput('templateid') || $this->hasInput('dynamic_hostid')) {
				$items = CMacrosResolverHelper::resolveWidgetItemNames($items);
			}

			// All macros in item name are resolved here.
			$description = $items[$this->itemid]['name'];
		}

		return $description;
	}

	/**
	 * Compose and return array options for item.get API. In case of dynamic host selection, set temporary items.
	 *
	 * @return array
	 */
	private function getItemOptions(): array {
		$options = [
			'output' => ['value_type'],
			'selectValueMap' => ['mappings'],
			'itemids' => $this->fields_values['itemid'],
			'webitems' => true,
			'preservekeys' => true
		];

		if ($this->is_dynamic) {
			$this->tmp_items = API::Item()->get([
				'output' => ['key_'],
				'itemids' => $this->fields_values['itemid'],
				'webitems' => true
			]);

			if ($this->tmp_items) {
				$options = [
					'output' => ['value_type'],
					'selectValueMap' => ['mappings'],
					'hostids' => [$this->getInput('dynamic_hostid')],
					'webitems' => true,
					'filter' => [
						'key_' => $this->tmp_items[0]['key_']
					],
					'preservekeys' => true
				];
			}
		}

		/*
		 * Select original item name in several cases: if user is in normal dashboards or in template dashboards when
		 * user is in view mode to display that item name in widget name. Item name should be select only if it is not
		 * overwritten. Host name can be attached to item name with delimiter when user is in normal dashboards.
		 */
		if ($this->getInput('name', '') === '') {
			if (!$this->is_template_dashboard || ($this->hasInput('dynamic_hostid') && $this->tmp_items)) {
				$options['output'] = array_merge($options['output'], ['name']);
			}

			if (!$this->is_template_dashboard) {
				$options['selectHosts'] = ['name'];
			}
		}

		// Add other fields in case current widget is set in dynamic mode, template dashboard or has a specified host.
		if (($this->is_dynamic && $this->tmp_items) || !$this->is_dynamic) {
			// If description contains user macros, we need "itemid" and "hostid" to resolve them.
			$options['output'] = array_merge($options['output'], ['itemid', 'hostid']);

			// Get units from item.
			if ($this->fields_values['units_show'] == 1 && $this->fields_values['units'] === '') {
				$options['output'][] = 'units';
			}
		}

		return $options;
	}

	/**
	 * Set the data to items array and item ID depending on options and wheter there were previously temporary items set
	 * in case of dynamic host selection.
	 */
	private function setItems(): void {
		$options = $this->getItemOptions();

		if ($this->is_dynamic) {
			if ($this->tmp_items) {
				$this->items = API::Item()->get($options);
				$this->itemid = key($this->items);
			}
		}
		else {
			$this->items = API::Item()->get($options);

			if ($this->fields_values['itemid']) {
				$this->itemid = $this->fields_values['itemid'][0];
			}
		}
	}

	/**
	 * Set units to items array if units are supposed to be shown and overwritten.
	 */
	private function setItemUnits(): void {
		if ($this->fields_values['units_show'] == 1) {
			if ($this->fields_values['units'] !== '') {
				// Overwrite units for item.
				$this->items[$this->itemid]['units'] = $this->fields_values['units'];
			}
		}
		else {
			// Do not make any unit conversions if units are supposed to be hidden.
			$this->items[$this->itemid]['units'] = '';
		}
	}

	/**
	 * Set base unit string depending on units set in widget configuration or item. Base units can be custom, can be
	 * binary or any other supported units. For example if units are Bps, after item value is formatted, units that
	 * widget will display can change to MBps, but this unit base string is the same - Bps.
	 */
	private function setUnitBase(): void {
		// Try to show units either ones that are custom set by widget or get them from the item.
		if ($this->fields_values['units_show'] == 1) {
			if ($this->fields_values['units'] === '') {
				// Get units from item.
				$this->unit_base = $this->items[$this->itemid]['units'] ?? '';
			}
			else {
				// Get units from widget configuration.
				$this->unit_base = $this->fields_values['units'];
			}
		}
	}

	/**
	 * Get history data. If widget was previously loaded, get two values from history so that there is a transition from
	 * old value to new value with a fancy animation. If this is the initial load (for example manual refresh of the
	 * page), get only one value from history.
	 *
	 * @return array
	 */
	private function getItemHistory(): array {
		$history_limit = $this->getInput('initial_load', 1) ? 1 : 2;
		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		$history = Manager::History()->getLastValues($this->items, $history_limit, $history_period);

		return $history;
	}

	/**
	 * Set the value to display based on history data. If there is history data, format the value according to widget
	 * configuration: set exact decimals and units. Set raw value from database, so that it can be used to draw the SVG.
	 * Also set the power by which the value was converted which later can be used to change Min/Max units. For example
	 * if item unit base string is B and raw value in database is "1000000". The value will be "976.56", units are "KB"
	 * and the power by which the converion happened is "1".
	 *
	 * @param array $history  Array of history data.
	 *
	 * $history = [
	 *     <itemid> => [
	 *         0 => 'value' (string)  Item value from the history data.
	 *     ]
	 * ]
	 */
	private function setValue(array $history): void {
		$this->value['type'] = $this->items[$this->itemid]['value_type'];

		if ($history) {
			$this->value['raw'] = $history[$this->itemid][0]['value'];

			$formatted_value = formatHistoryValueRaw($this->value['raw'], $this->items[$this->itemid], false, [
				'decimals' => $this->fields_values['decimal_places'],
				'decimals_exact' => true,
				'small_scientific' => false,
				'zero_as_zero' => false
			]);

			$value = $formatted_value['value'];
			$value_units = $formatted_value['units'];
			$value_power = $formatted_value['power'];

			if (!$formatted_value['is_mapped']) {
				$numeric_formatting = getNumericFormatting();
				$decimal_pos = strrpos($value, $numeric_formatting['decimal_point']);

				if ($decimal_pos !== false) {
					$decimals = substr($value, $decimal_pos);
					$value = substr($value, 0, $decimal_pos);
				}
			}

			$this->value['text'] = $value.$decimals;
			$this->value['units'] = $value_units;
			$this->value['power'] = $value_power;
		}
		// Otherwise the "No data." text is displayed instead of value. Which is not the same as "0".
	}

	/**
	 * Set the previous value to display animation based on history data.
	 *
	 * @param array $history  Array of history data.
	 *
	 * $history = [
	 *     <itemid> => [
	 *         1 => 'value' (string)  Item previous value from the history data. Which may or may not exist.
	 *     ]
	 * ]
	 */
	private function setPreviousValue(array $history) {
		if ($history && array_key_exists(1, $history[$this->itemid])) {
			$this->prev_value = $history[$this->itemid][1]['value'];
		}
	}

	/**
	 * Get the unit conversion options.
	 *
	 * @param bool     $is_binary  If true, use 1024 as base. Use 1000 otherwise.
	 * @param int|null $power      Convert to the specific power (0 => '', 1 => K, 2 => M, ...)
	 *
	 * @return array
	 */
	private function getConvertUnitsOptions(bool $is_binary, ?int $power): array {
		return [
			'units' => $this->unit_base,
			'unit_base' => $is_binary ? ZBX_KIBIBYTE : 1000,
			'power' => $power,
			'ignore_milliseconds' => ($this->min['raw'] <= -1 || $this->max['raw'] >= 1),
			'decimals' => $this->fields_values['decimal_places'],
			'decimals_exact' => true,
		];
	}

	/**
	 * Set the Min values. If a mapped value is found, return that value. Otherwise use the converted value and add
	 * units if needed.
	 *
	 * @param bool     $is_binary  If true, use 1024 as base. Use 1000 otherwise.
	 * @param int|null $power      Convert to the specific power (0 => '', 1 => K, 2 => M, ...)
	 */
	private function setMin(bool $is_binary, ?int $power): void {
		$mapped_value = CValueMapHelper::getMappedValue(ITEM_VALUE_TYPE_UINT64, $this->min['raw'],
			$this->items[$this->itemid]['valuemap']
		);

		if ($mapped_value !== false) {
			$this->min['text'] = $mapped_value;
		}
		else {
			$min = convertUnitsRaw(['value' => $this->min['raw']] + $this->getConvertUnitsOptions($is_binary, $power));

			$this->min['text'] = $min['value'];
			$this->min['text'] .= ($this->fields_values['minmax_show_units'] == 1) ? ' '.$min['units'] : '';
		}
	}

	/**
	 * Set the Max values. If a mapped value is found, return that value. Otherwise use the converted value and add
	 * units if needed.
	 *
	 * @param bool     $is_binary  If true, use 1024 as base. Use 1000 otherwise.
	 * @param int|null $power      Convert to the specific power (0 => '', 1 => K, 2 => M, ...)
	 */
	private function setMax(bool $is_binary, ?int $power): void {
		$mapped_value = CValueMapHelper::getMappedValue(ITEM_VALUE_TYPE_UINT64, $this->max['raw'],
			$this->items[$this->itemid]['valuemap']
		);

		if ($mapped_value !== false) {
			$this->max['text'] = $mapped_value;
		}
		else {
			$max = convertUnitsRaw(['value' => $this->max['raw']] + $this->getConvertUnitsOptions($is_binary, $power));

			$this->max['text'] = $max['value'];
			$this->max['text'] .= ($this->fields_values['minmax_show_units'] == 1) ? ' '.$max['units'] : '';
		}
	}

	/**
	 * Set the threshold values. If a mapped value is found, return that value. Otherwise use the converted value and
	 * add units if needed. Thresholds values depend on Min/Max units. In case Min/Max units are hidden, thresholds will
	 * use units from item. Meaning it will use the same power by which items were converted.
	 *
	 * @param bool     $is_binary  If true, use 1024 as base. Use 1000 otherwise.
	 * @param int|null $power      Convert to the specific power (0 => '', 1 => K, 2 => M, ...)
	 */
	private function setThresholds(bool $is_binary, ?int $power): void {
		foreach ($this->fields_values['thresholds'] as &$threshold) {
			$mapped_value = CValueMapHelper::getMappedValue(ITEM_VALUE_TYPE_FLOAT,
				$threshold['threshold_value'], $this->items[$this->itemid]['valuemap']
			);

			if ($mapped_value !== false) {
				$threshold['text'] = $mapped_value;
			}
			else {
				$converted = convertUnitsRaw(
					['value' => $threshold['threshold_value']] + $this->getConvertUnitsOptions($is_binary, $power)
				);
				$threshold['text'] = $converted['value'];
			}
		}
		unset($threshold);

		$this->thresholds = $this->fields_values['thresholds'];
	}
}
