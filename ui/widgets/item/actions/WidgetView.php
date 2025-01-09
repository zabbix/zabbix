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


namespace Widgets\Item\Actions;

use API,
	CAggFunctionData,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CItemHelper,
	CMacrosResolverHelper,
	CNumberParser,
	CSettingsHelper,
	CSvgGraph,
	CUrl,
	Manager;

use Widgets\Item\Widget;

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'contents_width'			=> 'int32',
			'has_custom_time_period'	=> 'in 1'
		]);
	}

	protected function doAction(): void {
		$item = $this->getItem();

		$output = [
			'name' => $this->getName($item),
			'info' => $this->makeWidgetInfo(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'sparkline' => []
		];

		if ($item !== null) {
			$show = array_flip($this->fields_values['show']);
			$show_change_indicator = array_key_exists(Widget::SHOW_CHANGE_INDICATOR, $show);

			[$data_last, $data_prev] = $this->getItemValues($item, $show_change_indicator);

			$output += [
				'cells' => $this->arrangeAndConfigure(
					$this->getElements($item, $data_last, $data_prev, $this->fields_values['aggregate_function'])
				),
				'url' => $this->getUrl($item),
				'bg_color' => $this->getBgColor($item, $data_last, $this->fields_values['aggregate_function'])
			];

			if (array_key_exists(Widget::SHOW_SPARKLINE, $show) && $item) {
				$sparkline_item = [
					'itemid'		=> $item['itemid'],
					'value_type'	=> $item['value_type'],
					'history'		=> $item['history'],
					'trends'		=> $item['trends']
				];
				$output['sparkline'] = $this->getSparkline($sparkline_item, [
					'width'		=> $this->fields_values['sparkline']['width'],
					'fill'		=> $this->fields_values['sparkline']['fill'],
					'color'		=> $this->fields_values['sparkline']['color'],
					'history'	=> $this->fields_values['sparkline']['history'],
					'from'		=> $this->fields_values['sparkline']['time_period']['from_ts'],
					'to'		=> $this->fields_values['sparkline']['time_period']['to_ts'],
					'contents_width'	=> $this->getInput('contents_width', 0)
				]);
			}
		}
		else {
			$output['error'] = _('No permissions to referred object or it does not exist!');
		}

		$this->setResponse(new CControllerResponseData($output));
	}

	protected function getSparkline(array $sparkline_item, array $options): array {
		$sparkline = [
			'width'		=> $options['width'],
			'fill'		=> $options['fill'],
			'color'		=> $options['color'],
			'history'	=> $options['history'],
			'from'		=> $options['from'],
			'to'		=> $options['to'],
			'value'		=> []
		];

		if (!in_array($sparkline_item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
			return $sparkline;
		}

		if ($options['history'] == Widget::HISTORY_DATA_AUTO) {
			[$sparkline_item] = CItemHelper::addDataSource([$sparkline_item], $options['from']);
		}
		else {
			$sparkline_item['source'] = $options['history'] == Widget::HISTORY_DATA_TRENDS ? 'trends' : 'history';
		}

		$data = Manager::History()->getGraphAggregationByWidth([$sparkline_item], $options['from'], $options['to'],
			$options['contents_width']
		);

		if ($data) {
			$points = array_column(reset($data)['data'], 'avg', 'clock');
			/**
			 * Postgres may return entries in mixed 'clock' order, getMissingData for calculations
			 * requires order by 'clock'.
			 */
			ksort($points);
			$points += CSvgGraph::getMissingData($points, SVG_GRAPH_MISSING_DATA_NONE);
			ksort($points);

			foreach ($points as $ts => $value) {
				$sparkline['value'][] = [$ts, $value];
			}
		}

		return $sparkline;
	}

	private function getItem(): ?array {
		$resolve_macros = !$this->isTemplateDashboard() || $this->fields_values['override_hostid'];

		$item_options = [
			'output' => ['itemid', 'hostid', $resolve_macros ? 'name_resolved' : 'name', 'history', 'trends',
				'value_type', 'units'
			],
			'selectValueMap' => ['mappings'],
			'webitems' => true
		];

		if ($this->fields_values['override_hostid']) {
			$src_items = API::Item()->get([
				'output' => ['key_'],
				'itemids' => $this->fields_values['itemid'],
				'webitems' => true
			]);

			if (!$src_items) {
				return null;
			}

			$item_options['hostids'] = $this->fields_values['override_hostid'];
			$item_options['filter']['key_'] = $src_items[0]['key_'];
		}
		else {
			$item_options['itemids'] = $this->fields_values['itemid'];
		}

		$items = API::Item()->get($item_options);

		if (!$items) {
			return null;
		}

		return $resolve_macros ? CArrayHelper::renameKeys($items[0], ['name_resolved' => 'name']) : $items[0];
	}

	private function getItemValues(array $item, bool $with_data_prev): array {
		$history = Manager::History();
		$function = $this->fields_values['aggregate_function'];

		if ($function != AGGREGATE_NONE) {
			$time_from = $this->fields_values['time_period']['from_ts'];
			$time_to = $this->fields_values['time_period']['to_ts'];

			$item_last = $this->addDataSource($item, $time_from);
			$data_last = $history->getAggregatedValues([$item_last], $function, $time_from, $time_to);
			$data_last = $data_last ? reset($data_last) : null;

			if ($with_data_prev && $data_last !== null) {
				$time_from_prev = $time_from - ($time_to - $time_from) - 1;
				$time_to_prev = $time_from - 1;

				$item_prev = $this->addDataSource($item, $time_from_prev);
				$data_prev = $history->getAggregatedValues([$item_prev], $function, $time_from_prev, $time_to_prev);
				$data_prev = $data_prev ? reset($data_prev) : null;
			}
			else {
				$data_prev = null;
			}

			return [$data_last, $data_prev];
		}

		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));

		$item = $this->addDataSource($item, time() - $history_period);

		if ($item['source'] === 'trends') {
			$data_last = $history->getAggregatedValues([$item], AGGREGATE_LAST, time() - $history_period);
			$data_last = $data_last ? reset($data_last) : null;

			if ($with_data_prev && $data_last !== null) {
				$time_to_prev = $data_last['clock'] - 1;
				$time_from_prev = $time_to_prev - $history_period;

				$data_prev = $history->getAggregatedValues([$item], AGGREGATE_LAST, $time_from_prev, $time_to_prev);
				$data_prev = $data_prev ? reset($data_prev) : null;
			}
			else {
				$data_prev = null;
			}

			return [$data_last, $data_prev];
		}

		$history_limit = $with_data_prev ? 2 : 1;
		$history = $history->getLastValues([$item], $history_limit, $history_period);

		if ($history) {
			$item_history = reset($history);

			$data_last = count($item_history) > 0 ? $item_history[0] : null;
			$data_prev = count($item_history) > 1 ? $item_history[1] : null;
		}
		else {
			$data_last = null;
			$data_prev = null;
		}

		return [$data_last, $data_prev];
	}

	private function addDataSource(array $item, int $time): array {
		switch ($this->fields_values['history']) {
			case Widget::HISTORY_DATA_AUTO:
				[$item] = CItemHelper::addDataSource([$item], $time);
				break;

			case Widget::HISTORY_DATA_TRENDS:
				$item['source'] = 'trends';
				break;

			default:
				$item['source'] = 'history';
				break;
		}

		if (!in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
			$item['source'] = 'history';
		}

		return $item;
	}

	private function getElements(array $item, ?array $data_last, ?array $data_prev, int $function): array {
		$elements = [
			'description' => '',
			'units' => '',
			'value' => null,
			'decimals' => null,
			'change_indicator' => null,
			'time' => '',
			'is_numeric' => false
		];

		$show = array_flip($this->fields_values['show']);

		if (array_key_exists(Widget::SHOW_DESCRIPTION, $show)) {
			$item['widget_description'] = $this->fields_values['description'];

			if (!$this->isTemplateDashboard() || $this->fields_values['override_hostid']) {
				[
					$item['itemid'] => $item
				] = CMacrosResolverHelper::resolveItemBasedWidgetMacros(
					[$item['itemid'] => $item],
					['widget_description' => 'widget_description']
				);
			}

			$elements['description'] = $item['widget_description'];
		}

		if ($data_last === null) {
			if (array_key_exists(Widget::SHOW_TIME, $show)) {
				$elements['time'] = date(DATE_TIME_FORMAT_SECONDS);
			}

			return $elements;
		}

		if (array_key_exists(Widget::SHOW_TIME, $show)) {
			$elements['time'] = date(DATE_TIME_FORMAT_SECONDS, (int) $data_last['clock']);
		}

		$is_numeric_item = in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
		$is_numeric_data = $is_numeric_item || CAggFunctionData::isNumericResult($function);

		$force_units = false;

		if ($this->fields_values['units_show'] == 1) {
			if ($this->fields_values['units'] !== '') {
				$item['units'] = $this->fields_values['units'];
				$force_units = true;
			}
		}
		else {
			$item['units'] = '';
		}

		$formatted_value = formatAggregatedHistoryValueRaw($data_last['value'], $item, $function, $force_units, false,
				$is_numeric_data
			? [
				'decimals' => $this->fields_values['decimal_places'],
				'decimals_exact' => true,
				'small_scientific' => false,
				'zero_as_zero' => false
			]
			: []
		);

		$elements['value'] = $formatted_value['value'];
		$elements['units'] = $formatted_value['units'];

		if ($is_numeric_data && !$formatted_value['is_mapped']) {
			$numeric_formatting = getNumericFormatting();
			$decimal_pos = strrpos($elements['value'], $numeric_formatting['decimal_point']);

			if ($decimal_pos !== false) {
				$elements['decimals'] = substr($elements['value'], $decimal_pos);
				$elements['value'] = substr($elements['value'], 0, $decimal_pos);
			}

			$elements['is_numeric'] = true;
		}

		if (array_key_exists(Widget::SHOW_CHANGE_INDICATOR, $show) && $data_prev !== null) {
			if ($is_numeric_data) {
				if ($formatted_value['is_mapped']) {
					if ($data_last['value'] != $data_prev['value']) {
						$elements['change_indicator'] = Widget::CHANGE_INDICATOR_UP_DOWN;
					}
				}
				elseif ($data_last['value'] > $data_prev['value']) {
					$elements['change_indicator'] = Widget::CHANGE_INDICATOR_UP;
				}
				elseif ($data_last['value'] < $data_prev['value']) {
					$elements['change_indicator'] = Widget::CHANGE_INDICATOR_DOWN;
				}
			}
			else {
				if ($data_last['value'] !== $data_prev['value']) {
					$elements['change_indicator'] = Widget::CHANGE_INDICATOR_UP_DOWN;
				}
			}
		}

		return $elements;
	}

	/**
	 * Arrange and configure widget elements as defined in widget configuration.
	 *
	 * @param array       $elements                      Pre-processed elements for displaying.
	 *        string      $elements['description']       Item description with all macros resolved.
	 *        string      $elements['units']             Item units.
	 *        string|null $elements['value']             Item value without decimal part.
	 *        string|null $elements['decimals']          Decimal part of item value.
	 *        int|null    $elements['change_indicator']  Change indicator type.
	 *        string      $elements['time']              Time related to the item value, or current time if no data.
	 *
	 * @return array
	 */
	private function arrangeAndConfigure(array $elements): array {
		$cells = [];

		$config = $this->fields_values;

		$show = array_flip($config['show']);

		if (array_key_exists(Widget::SHOW_DESCRIPTION, $show)) {
			$cells[$config['desc_v_pos']][$config['desc_h_pos']] = [
				'item_description' => [
					'text' => $elements['description'],
					'font_size' => $config['desc_size'],
					'bold' => $config['desc_bold'] == 1,
					'color' => $config['desc_color']
				]
			];
		}

		if (array_key_exists(Widget::SHOW_VALUE, $show)) {
			$item_value_cell = [
				'is_numeric' => $elements['is_numeric']
			];

			if ($config['units_show'] == 1 && $elements['units'] !== '') {
				$item_value_cell['parts']['units'] = [
					'text' => $elements['units'],
					'font_size' => $config['units_size'],
					'bold' => $config['units_bold'] == 1,
					'color' => $config['units_color']
				];
				$item_value_cell['units_pos'] = $config['units_pos'];
			}

			$item_value_cell['parts']['value'] = [
				'text' => $elements['value'],
				'font_size' => $config['value_size'],
				'bold' => $config['value_bold'] == 1,
				'color' => $config['value_color']
			];

			if ($elements['decimals'] !== null) {
				$item_value_cell['parts']['decimals'] = [
					'text' => $elements['decimals'],
					'font_size' => $config['decimal_size'],
					'bold' => $config['value_bold'] == 1,
					'color' => $config['value_color']
				];
			}

			$cells[$config['value_v_pos']][$config['value_h_pos']] = [
				'item_value' => $item_value_cell
			];
		}

		if (array_key_exists(Widget::SHOW_CHANGE_INDICATOR, $show) && $elements['change_indicator'] !== null) {
			$colors = [
				Widget::CHANGE_INDICATOR_UP => $config['up_color'],
				Widget::CHANGE_INDICATOR_DOWN => $config['down_color'],
				Widget::CHANGE_INDICATOR_UP_DOWN => $config['updown_color']
			];

			$cells[$config['value_v_pos']][$config['value_h_pos']]['item_value']['parts']['change_indicator'] = [
				'type' => $elements['change_indicator'],
				'font_size' => $elements['decimals'] !== null
					? max($config['value_size'], $config['decimal_size'])
					: $config['value_size'],
				'color' => $colors[$elements['change_indicator']]
			];
		}

		if (array_key_exists(Widget::SHOW_TIME, $show)) {
			$cells[$config['time_v_pos']][$config['time_h_pos']] = [
				'item_time' => [
					'text' => $elements['time'],
					'font_size' => $config['time_size'],
					'bold' => $config['time_bold'] == 1,
					'color' => $config['time_color']
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

	private function getName(?array $item): string {
		if ($this->getInput('name', '') !== '') {
			return $this->getInput('name');
		}

		if ($this->isTemplateDashboard() && !$this->fields_values['override_hostid']) {
			return $this->widget->getDefaultName();
		}

		$name = $item !== null ? $item['name'] : $this->widget->getDefaultName();

		if (!$this->isTemplateDashboard()) {
			if ($this->fields_values['override_hostid']) {
				$hosts = API::Host()->get([
					'output' => ['name'],
					'hostids' => $this->fields_values['override_hostid']
				]);
			}
			elseif ($item !== null) {
				$hosts = API::Host()->get([
					'output' => ['name'],
					'itemids' => $item['itemid']
				]);
			}
			else {
				$hosts = [];
			}

			if ($hosts) {
				$name = $hosts[0]['name'].NAME_DELIMITER.$name;
			}
		}

		return $name;
	}

	/**
	 * Make widget specific info to show in widget's header.
	 *
	 * @return array Returns an array containing icon data, or an empty array if the conditions are not met.
	 */
	private function makeWidgetInfo(): array {
		$info = [];

		if ($this->hasInput('has_custom_time_period')) {
			$info[] = [
				'icon' => ZBX_ICON_TIME_PERIOD,
				'hint' => relativeDateToText($this->fields_values['time_period']['from'],
					$this->fields_values['time_period']['to']
				)
			];
		}

		return $info;
	}

	private function getUrl(array $item): string {
		return (new CUrl('history.php'))
			->setArgument('action',
				$item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64
					? HISTORY_GRAPH
					: HISTORY_VALUES
			)
			->setArgument('itemids[]', $item['itemid'])
			->getUrl();
	}

	public function getBgColor(array $item, ?array $data_last, int $function): string {
		$bg_color = $this->fields_values['bg_color'];

		$is_numeric_data = in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
			|| CAggFunctionData::isNumericResult($function);

		if ($data_last === null || !$is_numeric_data) {
			return $bg_color;
		}

		$units = $this->fields_values['units_show'] == 1 && $this->fields_values['units'] !== ''
			? $this->fields_values['units']
			: $item['units'];

		$number_parser = new CNumberParser([
			'with_size_suffix' => true,
			'with_time_suffix' => true,
			'is_binary_size' => isBinaryUnits($units)
		]);

		foreach ($this->fields_values['thresholds'] as $threshold) {
			$number_parser->parse($threshold['threshold']);

			$threshold_value = $number_parser->calcValue();

			if ($threshold_value > $data_last['value']) {
				break;
			}

			$bg_color = $threshold['color'];
		}

		return $bg_color;
	}
}
