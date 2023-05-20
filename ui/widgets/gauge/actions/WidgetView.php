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
	CMacrosResolverHelper,
	CNumberParser,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CSettingsHelper,
	Manager;

use Zabbix\Core\CWidget;

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'dynamic_hostid' => 'db hosts.hostid',
			'with_config' => 'in 1'
		]);
	}

	protected function doAction(): void {
		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$item = $this->getItem();

		if ($item === null) {
			$this->setResponse(new CControllerResponseData($data + [
				'error' => _('No permissions to referred object or it does not exist!')
			]));

			return;
		}

		if ($this->getInput('name', '') === '') {
			$data['name'] = $this->isTemplateDashboard()
				? $item['name']
				: $item['hosts'][0]['name'].NAME_DELIMITER.$item['name'];
		}

		$data['vars'] = $this->getValueData($item);

		if ($this->hasInput('with_config')) {
			$data['vars']['config'] = $this->getConfig($item);
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getItem(): ?array {
		if (($this->isTemplateDashboard() || $this->fields_values['dynamic'] == CWidget::DYNAMIC_ITEM)
				&& $this->hasInput('dynamic_hostid')) {
			$dynamic_hostid = $this->getInput('dynamic_hostid');
		}
		else {
			$dynamic_hostid = null;
		}

		$item_options = [
			'output' => ['itemid', 'hostid', 'name', 'value_type', 'units'],
			'selectHosts' => !$this->isTemplateDashboard() ? ['name'] : null,
			'selectValueMap' => ['mappings'],
			'filter' => [
				'value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]
			],
			'webitems' => true
		];

		if ($dynamic_hostid !== null) {
			$src_items = API::Item()->get([
				'output' => ['key_'],
				'itemids' => $this->fields_values['itemid'],
				'webitems' => true
			]);

			if (!$src_items) {
				return null;
			}

			$item_options['hostids'] = $dynamic_hostid;
			$item_options['filter']['key_'] = $src_items[0]['key_'];
		}
		else {
			$item_options['itemids'] = $this->fields_values['itemid'];
		}

		$items = API::Item()->get($item_options);

		if (!$items) {
			return null;
		}

		return $items[0];
	}

	private function getConfig(array $item): array {
		$config = [
			'angle' => $this->fields_values['angle'],
			'empty_color' => $this->fields_values['empty_color'],
			'bg_color' => $this->fields_values['bg_color']
		];

		$number_parser = new CNumberParser([
			'with_size_suffix' => true,
			'with_time_suffix' => true
		]);

		$number_parser->parse($this->fields_values['min']);
		$config['min'] = $number_parser->calcValue();

		$number_parser->parse($this->fields_values['max']);
		$config['max'] = $number_parser->calcValue();

		if ($this->fields_values['minmax_show'] == 1) {
			$config['minmax'] = [
				'show' => true,
				'size' => $this->fields_values['minmax_size']
			];

			if ($this->fields_values['units_show'] == 1 && $this->fields_values['minmax_show_units'] == 1) {
				$minmax_units = $this->fields_values['units'] !== '' ? $this->fields_values['units'] : $item['units'];
			}
			else {
				$minmax_units = '';
			}

			$labels = $this->makeValueLabels(['units' => $minmax_units] + $item, $config['min']);
			$config['minmax']['min_text'] = $labels['value'].($labels['units'] !== '' ? ' '.$labels['units'] : '');

			$labels = $this->makeValueLabels(['units' => $minmax_units] + $item, $config['max']);
			$config['minmax']['max_text'] = $labels['value'].($labels['units'] !== '' ? ' '.$labels['units'] : '');
		}
		else {
			$config['minmax']['show'] = false;

			$minmax_units = '';
		}

		$widget_description = $this->fields_values['description'];

		if (!$this->isTemplateDashboard() || $this->hasInput('dynamic_hostid')) {
			[[
				'widget_description' => $widget_description
			]] = CMacrosResolverHelper::resolveItemWidgetDescriptions([$item + [
				'widget_description' => $widget_description
			]]);
		}

		$config['description'] = [
			'text' => $widget_description,
			'position' => $this->fields_values['desc_v_pos'],
			'size' => $this->fields_values['desc_size'],
			'is_bold' => $this->fields_values['desc_bold'] == 1,
			'color' => $this->fields_values['desc_color']
		];

		$config['value'] = [
			'size' => $this->fields_values['value_size'],
			'is_bold' => $this->fields_values['value_bold'] == 1,
			'color' => $this->fields_values['value_color'],
			'arc' => $this->fields_values['value_arc'] == 1
				? [
					'show' => true,
					'size' => $this->fields_values['value_arc_size']
				]
				: [
					'show' => false
				]
		];

		$config['units'] = $this->fields_values['units_show'] == 1
			? [
				'show' => true,
				'position' => $this->fields_values['units_pos'],
				'size' => $this->fields_values['units_size'],
				'is_bold' => $this->fields_values['units_bold'] == 1,
				'color' => $this->fields_values['units_color']
			]
			: [
				'show' => false
			];

		$config['thresholds'] = [
			'show_labels' => $this->fields_values['th_show_labels'] == 1,
			'arc' => $this->fields_values['th_show_arc'] == 1
				? [
					'show' => true,
					'size' => $this->fields_values['th_arc_size']
				]
				: [
					'show' => false
				],
			'data' => []
		];

		$config['needle'] = $this->fields_values['needle_show'] == 1
			? [
				'show' => true,
				'color' => $this->fields_values['needle_color']
			]
			: [
				'show' => false
			];

		foreach ($this->fields_values['thresholds'] as $threshold) {
			$labels = $this->makeValueLabels(['units' => $minmax_units] + $item, $threshold['threshold_value']);

			$config['thresholds']['data'][] = [
				'color' => $threshold['color'],
				'value' => $threshold['threshold_value'],
				'text' => $labels['value'].($labels['units'] !== '' ? ' '.$labels['units'] : '')
			];
		}

		return $config;
	}

	private function getValueData(array $item): array {
		$no_data = [
			'value' => null,
			'value_text' => _('No data'),
			'units_text' => ''
		];

		if ($this->isTemplateDashboard() && !$this->hasInput('dynamic_hostid')) {
			return $no_data;
		}

		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		$history = Manager::History()->getLastValues([$item], 1, $history_period);

		if (!$history) {
			return $no_data;
		}

		$value = $history[$item['itemid']][0]['value'];

		if ($this->fields_values['units_show'] == 1) {
			if ($this->fields_values['units'] !== '') {
				$item['units'] = $this->fields_values['units'];
			}
		}
		else {
			$item['units'] = '';
		}

		$labels = $this->makeValueLabels($item, $value);

		return [
			'value' => (float) $value,
			'value_text' => $labels['value'],
			'units_text' => $labels['units']
		];
	}

	private function makeValueLabels(array $item, $value): array {
		return formatHistoryValueRaw($value, $item, false, [
			'decimals' => $this->fields_values['decimal_places'],
			'decimals_exact' => true,
			'small_scientific' => false,
			'zero_as_zero' => false
		]);
	}
}
