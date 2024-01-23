<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


namespace Widgets\Honeycomb\Actions;

use API,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CMacrosResolverHelper,
	CNumberParser,
	CSettingsHelper,
	Manager;

use Widgets\Honeycomb\Includes\WidgetForm;

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'with_config' => 'in 1'
		]);
	}

	protected function doAction(): void {
		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'vars' => [
				'cells' => $this->getCells()
			]
		];

		if ($this->hasInput('with_config')) {
			$data['vars']['config'] = $this->getConfig();
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getCells(): array {
		if ($this->isTemplateDashboard() && !$this->fields_values['hostids']) {
			return [];
		}

		$groupids = !$this->isTemplateDashboard() && $this->fields_values['groupids']
			? getSubGroups($this->fields_values['groupids'])
			: null;

		$hosts = API::Host()->get([
			'output' => [],
			'groupids' => $groupids,
			'hostids' => $this->fields_values['hostids'] ?: null,
			'evaltype' => !$this->isTemplateDashboard() ? $this->fields_values['evaltype_host'] : null,
			'tags' => !$this->isTemplateDashboard() && $this->fields_values['host_tags']
				? $this->fields_values['host_tags']
				: null,
			'filter' => [
				'maintenance_status' => $this->fields_values['maintenance'] != 1
					? HOST_MAINTENANCE_STATUS_OFF
					: null
			],
			'monitored_hosts' => true,
			'preservekeys' => true
		]);

		if (!$hosts) {
			return [];
		}

		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'name', 'units', 'value_type'],
			'selectHosts' => ['name'],
			'webitems' => true,
			'hostids' => array_keys($hosts),
			'evaltype' => $this->fields_values['evaltype_item'],
			'tags' => $this->fields_values['item_tags'] ?: null,
			'selectValueMap' => ['mappings'],
			'search' => [
				'name' => in_array('*', $this->fields_values['items']) ? null : $this->fields_values['items']
			],
			'searchWildcardsEnabled' => true,
			'searchByAny' => true
		]);

		if (!$items) {
			return [];
		}

		foreach ($items as &$item) {
			$item['hostname'] = $item['hosts'][0]['name'];
		}
		unset($item);

		CArrayHelper::sort($items, ['hostname', 'name']);

		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		$history = Manager::History()->getLastValues($items, 1, $history_period);

		$show = array_flip($this->fields_values['show']);

		$cells = [];

		foreach ($items as $item) {
			if (!array_key_exists($item['itemid'], $history)) {
				continue;
			}

			$last_value = $history[$item['itemid']][0]['value'];

			$primary_label = array_key_exists(WidgetForm::SHOW_PRIMARY_LABEL, $show)
				? self::getCellLabel($item, $last_value, [
					'label' =>					$this->fields_values['primary_label'],
					'label_decimal_places' =>	$this->fields_values['primary_label_decimal_places'],
					'label_type' =>				$this->fields_values['primary_label_type'],
					'label_units' =>			$this->fields_values['primary_label_units'],
					'label_units_pos' =>		$this->fields_values['primary_label_units_pos'],
					'label_units_show' =>		$this->fields_values['primary_label_units_show']
				])
				: '';

			$secondary_label = array_key_exists(WidgetForm::SHOW_SECONDARY_LABEL, $show)
				? self::getCellLabel($item, $last_value, [
					'label' =>					$this->fields_values['secondary_label'],
					'label_decimal_places' =>	$this->fields_values['secondary_label_decimal_places'],
					'label_type' =>				$this->fields_values['secondary_label_type'],
					'label_units' =>			$this->fields_values['secondary_label_units'],
					'label_units_pos' =>		$this->fields_values['secondary_label_units_pos'],
					'label_units_show' =>		$this->fields_values['secondary_label_units_show']
				])
				: '';

			$cells[] = [
				'hostid' => $item['hostid'],
				'itemid' => $item['itemid'],
				'primary_label' => $primary_label,
				'secondary_label' => $secondary_label,
				'value' => $last_value,
				'is_numeric' => in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]),
				'is_binary_units' => isBinaryUnits($item['units'])
			];
		}

		return $cells;
	}

	private function getCellLabel(array $item, $last_value, array $context_fields_values): string {
		if ($context_fields_values['label_type'] == WidgetForm::LABEL_TYPE_TEXT) {
			$label = $context_fields_values['label'];

			if (!$this->isTemplateDashboard() || $this->fields_values['hostids']) {
				$resolved_label = CMacrosResolverHelper::resolveItemBasedWidgetMacros(
					[$item['itemid'] => $item + ['label' => $label]],
					['label' => 'label']
				);
				$label = $resolved_label[$item['itemid']]['label'];
			}

			return $label;
		}

		switch ($item['value_type']) {
			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
				if ($context_fields_values['label_units_show'] == 1) {
					if ($context_fields_values['label_units'] !== '') {
						$item['units'] = $context_fields_values['label_units'];
					}
				}
				else {
					$item['units'] = '';
				}

				$formatted_value = formatHistoryValueRaw($last_value, $item, false, [
					'decimals' => $context_fields_values['label_decimal_places'],
					'decimals_exact' => true,
					'small_scientific' => false,
					'zero_as_zero' => false
				]);

				if ($context_fields_values['label_units_show'] == 1) {
					return $context_fields_values['label_units_pos'] == WidgetForm::UNITS_POSITION_BEFORE
						? $formatted_value['units'].' '.$formatted_value['value']
						: $formatted_value['value'].' '.$formatted_value['units'];
				}

				return $formatted_value['value'];

			default:
				return formatHistoryValue($last_value, $item, false);
		}
	}

	private function getConfig(): array {
		$config = ['bg_color' => $this->fields_values['bg_color']];

		$show = array_flip($this->fields_values['show']);

		if (array_key_exists(WidgetForm::SHOW_PRIMARY_LABEL, $show)) {
			$config['primary_label'] = [
				'show' => true,
				'is_custom_size' => $this->fields_values['primary_label_size_type'] == WidgetForm::LABEL_SIZE_CUSTOM,
				'is_bold' => $this->fields_values['primary_label_bold'] == 1,
				'color' => $this->fields_values['primary_label_color']
			];

			if ($this->fields_values['primary_label_size_type'] == WidgetForm::LABEL_SIZE_CUSTOM) {
				$config['primary_label']['size'] = $this->fields_values['primary_label_size'];
			}
		}
		else {
			$config['primary_label']['show'] = false;
		}

		if (array_key_exists(WidgetForm::SHOW_SECONDARY_LABEL, $show)) {
			$config['secondary_label'] = [
				'show' => true,
				'is_custom_size' => $this->fields_values['secondary_label_size_type'] == WidgetForm::LABEL_SIZE_CUSTOM,
				'is_bold' => $this->fields_values['secondary_label_bold'] == 1,
				'color' => $this->fields_values['secondary_label_color']
			];

			if ($this->fields_values['secondary_label_size_type'] == WidgetForm::LABEL_SIZE_CUSTOM) {
				$config['secondary_label']['size'] = $this->fields_values['secondary_label_size'];
			}
		}
		else {
			$config['secondary_label']['show'] = false;
		}

		$config['apply_interpolation'] = $this->fields_values['interpolation'] == 1;
		$config['thresholds'] = $this->fields_values['thresholds'];

		$number_parser = new CNumberParser([
			'with_size_suffix' => true,
			'with_time_suffix' => true,
			'is_binary_size' => false
		]);

		$number_parser_binary = new CNumberParser([
			'with_size_suffix' => true,
			'with_time_suffix' => true,
			'is_binary_size' => true
		]);

		foreach ($config['thresholds'] as &$threshold) {
			$number_parser_binary->parse($threshold['threshold']);
			$threshold['threshold_binary'] = $number_parser_binary->calcValue();

			$number_parser->parse($threshold['threshold']);
			$threshold['threshold'] = $number_parser->calcValue();
		}
		unset($threshold);

		return $config;
	}
}
