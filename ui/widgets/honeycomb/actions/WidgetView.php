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


namespace Widgets\Honeycomb\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CMacrosResolverHelper,
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
			'vars' => []
		];

		if ($this->isTemplateDashboard() && !$this->fields_values['hostids']) {
			$data['vars']['cells']['no_data'] = true;
		}
		else {
			$data['vars']['cells'] = $this->getCellData();
		}

		if ($this->hasInput('with_config')) {
			$data['vars']['config'] = $this->getConfig();
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getCellData(): array {
		$cells = [];

		$groupids = !$this->isTemplateDashboard() && $this->fields_values['groupids']
			? getSubGroups($this->fields_values['groupids'])
			: null;

		$hostids = $this->fields_values['hostids'] ?: null;

		$host_tags_exist = array_key_exists('host_tags', $this->fields_values);

		$maintenance_status = $this->fields_values['maintenance'] == HOST_MAINTENANCE_STATUS_OFF
			? HOST_MAINTENANCE_STATUS_OFF
			: null;

		$hosts = API::Host()->get([
			'output' => [],
			'groupids' => $groupids,
			'hostids' => $hostids,
			'evaltype' => $host_tags_exist ? $this->fields_values['evaltype_host'] : null,
			'tags' => $host_tags_exist ? $this->fields_values['host_tags'] : null,
			'filter' => ['maintenance_status' => $maintenance_status],
			'monitored_hosts' => true,
			'preservekeys' => true
		]);

		$hostids = array_keys($hosts);

		if (!$hostids) {
			$cells['no_data'] = true;

			return $cells;
		}

		$item_tags_exist = array_key_exists('item_tags', $this->fields_values);

		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'units', 'value_type'],
			'selectHosts' => !$this->isTemplateDashboard() ? ['name'] : null,
			'webitems' => true,
			'hostids' => $hostids,
			'evaltype' => $item_tags_exist ? $this->fields_values['evaltype_item'] : null,
			'tags' => $item_tags_exist ? $this->fields_values['item_tags'] : null,
			'selectValueMap' => ['mappings'],
			'search' => [
				'name' => self::processItemPattern($this->fields_values['items'])
			],
			'searchWildcardsEnabled' => true,
			'searchByAny' => true,
			'sortfield' => 'name',
			'sortorder' => ZBX_SORT_UP
		]);

		if (!$items) {
			$cells['no_data'] = true;

			return $cells;
		}

		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		$history = Manager::History()->getLastValues($items, 1, $history_period);

		foreach ($items as &$item) {
			$item['value'] = array_key_exists($item['itemid'], $history) ? $history[$item['itemid']][0]['value'] : null;

			$primary_label = $this->fields_values['primary_label'];
			$secondary_label = $this->fields_values['secondary_label'];

			if ($item['value'] !== null && $item['value'] != 0) {
				if (!$this->isTemplateDashboard() || $this->fields_values['hostids']) {
					[[
						'primary_label' => $primary_label
					]] = CMacrosResolverHelper::resolveLabels([$item + [
						'primary_label' => $primary_label
					]], 'primary_label');

					[[
						'secondary_label' => $secondary_label
					]] = CMacrosResolverHelper::resolveLabels([$item + [
						'secondary_label' => $secondary_label
					]], 'secondary_label');
				}

				$cells[] = [
					'hostid' => $item['hostid'],
					'itemid' => $item['itemid'],
					'primary_label' => $primary_label,
					'secondary_label' => $secondary_label,
					'value' => $item['value'],
					'is_numeric' => $item['value_type'] == ITEM_VALUE_TYPE_FLOAT
						|| $item['value_type'] == ITEM_VALUE_TYPE_UINT64
				];
			}
		}

		return $cells;
	}

	private function getConfig(): array {
		$config = ['bg_color' => $this->fields_values['bg_color']];

		$show = array_flip($this->fields_values['show']);

		if (array_key_exists(WidgetForm::SHOW_PRIMARY, $show)) {
			$config['primary_label'] = [
				'show' => true,
				'is_custom_size' => $this->fields_values['primary_label_size_type'] == WidgetForm::SIZE_CUSTOM,
				'is_bold' => $this->fields_values['primary_label_bold'] == WidgetForm::BOLD_ON,
				'color' => $this->fields_values['primary_label_color']
			];

			if ($this->fields_values['primary_label_size_type'] == WidgetForm::SIZE_CUSTOM) {
				$config['primary_label']['size'] = $this->fields_values['primary_label_size'];
			}
		}
		else {
			$config['primary_label']['show'] = false;
		}

		if (array_key_exists(WidgetForm::SHOW_SECONDARY, $show)) {
			$config['secondary_label'] = [
				'show' => true,
				'is_custom_size' => $this->fields_values['secondary_label_size_type'] == WidgetForm::SIZE_CUSTOM,
				'is_bold' => $this->fields_values['secondary_label_bold'] == WidgetForm::BOLD_ON,
				'color' => $this->fields_values['secondary_label_color']
			];

			if ($this->fields_values['secondary_label_size_type'] == WidgetForm::SIZE_CUSTOM) {
				$config['secondary_label']['size'] = $this->fields_values['secondary_label_size'];
			}
		}
		else {
			$config['primary_label']['show'] = false;
		}

		$config['apply_interpolation'] = $this->fields_values['interpolation'] == WidgetForm::INTERPOLATION_ON;
		$config['thresholds'] = $this->fields_values['thresholds'];

		return $config;
	}

	/**
	 * Prepare an array to be used for items filtering.
	 *
	 * @param array  $patterns  Array of strings containing item patterns.
	 *
	 * @return array|mixed  Returns array of patterns.
	 *                      Returns NULL if array contains '*' (so any possible item search matches).
	 */
	private static function processItemPattern(array $patterns): ?array {
		return in_array('*', $patterns, true) ? null : $patterns;
	}
}
