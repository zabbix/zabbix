<?php
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
 * Converter for converting import data from 4.0 to 4.2
 */
class C40ImportConverter extends CConverter {

	public function convert($data) {
		$data['zabbix_export']['version'] = '4.2';

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = $this->convertHosts($data['zabbix_export']['hosts']);
		}

		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = $this->convertHosts($data['zabbix_export']['templates']);
		}

		return $data;
	}

	/**
	 * Convert hosts.
	 *
	 * @param array $hosts
	 *
	 * @return array
	 */
	protected function convertHosts(array $hosts) {
		foreach ($hosts as &$host) {
			$host['tags'] = [];

			if (array_key_exists('items', $host)) {
				$host['items'] = $this->convertItems($host['items']);
			}

			if (array_key_exists('discovery_rules', $host)) {
				$host['discovery_rules'] = $this->convertDiscoveryRules($host['discovery_rules']);
			}
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Convert item elements.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	protected function convertItems(array $items) {
		foreach ($items as &$item) {
			if (array_key_exists('preprocessing', $item)) {
				$item['preprocessing'] = $this->convertItemPreprocessingSteps($item['preprocessing']);
			}
		}
		unset($item);

		return $items;
	}

	/**
	 * Convert discovery rule elements.
	 *
	 * @param array $discovery_rules
	 *
	 * @return array
	 */
	protected function convertDiscoveryRules(array $discovery_rules) {
		$default = $this->getDiscoveryRuleDefaultFields();

		foreach ($discovery_rules as &$discovery_rule) {
			$discovery_rule += $default;
			$discovery_rule['item_prototypes'] = $this->convertItems($discovery_rule['item_prototypes']);
			$discovery_rule['master_item'] = [];
		}
		unset($discovery_rule);

		return $discovery_rules;
	}

	/**
	 * Convert item preprocessing step elements.
	 *
	 * @param array $preprocessing_steps
	 *
	 * @return array
	 */
	protected function convertItemPreprocessingSteps(array $preprocessing_steps) {
		$default = [
			'error_handler' => DB::getDefault('item_preproc', 'error_handler'),
			'error_handler_params' => DB::getDefault('item_preproc', 'error_handler_params')
		];

		foreach ($preprocessing_steps as &$preprocessing_step) {
			$preprocessing_step += $default;
		}
		unset($preprocessing_step);

		return $preprocessing_steps;
	}

	/**
	 * Return associative array of LLD rule default fields.
	 *
	 * @return array
	 */
	protected function getDiscoveryRuleDefaultFields() {
		return [
			'lld_macro_paths' => [],
			'preprocessing' => []
		];
	}
}
