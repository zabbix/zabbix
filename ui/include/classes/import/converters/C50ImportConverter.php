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


/**
 * Converter for converting import data from 5.0 to 5.2.
 */
class C50ImportConverter extends CConverter {

	/**
	 * Convert import data from 5.0 to 5.2 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert($data): array {
		$data['zabbix_export']['version'] = '5.2';

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = self::convertHosts($data['zabbix_export']['hosts']);
		}

		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = self::convertTemplates($data['zabbix_export']['templates']);
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
	private static function convertHosts(array $hosts): array {
		foreach ($hosts as &$host) {
			if (array_key_exists('items', $host)) {
				$host['items'] = self::convertItems($host['items']);
			}

			if (array_key_exists('discovery_rules', $host)) {
				$host['discovery_rules'] = self::convertDiscoveryRules($host['discovery_rules']);
			}
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Convert templates.
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	private static function convertTemplates(array $templates): array {
		foreach ($templates as &$template) {
			if (array_key_exists('items', $template)) {
				$template['items'] = self::convertItems($template['items']);
			}

			if (array_key_exists('discovery_rules', $template)) {
				$template['discovery_rules'] = self::convertDiscoveryRules($template['discovery_rules']);
			}

			if (array_key_exists('screens', $template)) {
				$template['dashboards'] = self::convertScreensToDashboards($template['screens']);
				unset($template['screens']);
			}
		}
		unset($template);

		return $templates;
	}

	/**
	 * Convert items.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	private static function convertItems(array $items): array {
		foreach ($items as &$item) {
			if (array_key_exists('preprocessing', $item)) {
				$item['preprocessing'] = self::convertPreprocessingSteps($item['preprocessing']);
			}
		}
		unset($item);

		return $items;
	}

	/**
	 * Convert discovery rules.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	private static function convertDiscoveryRules(array $discovery_rules): array {
		foreach ($discovery_rules as &$discovery_rule) {
			if (array_key_exists('preprocessing', $discovery_rule)) {
				$discovery_rule['preprocessing'] = self::convertPreprocessingSteps($discovery_rule['preprocessing']);
			}

			if (array_key_exists('item_prototypes', $discovery_rule)) {
				$discovery_rule['item_prototypes'] = self::convertItemPrototypes($discovery_rule['item_prototypes']);
			}
		}
		unset($discovery_rule);

		return $discovery_rules;
	}

	/**
	 * Convert item prototypes.
	 *
	 * @param array $item_prototypes
	 *
	 * @return array
	 */
	private static function convertItemPrototypes(array $item_prototypes): array {
		foreach ($item_prototypes as &$item_prototype) {
			if (array_key_exists('preprocessing', $item_prototype)) {
				$item_prototype['preprocessing'] = self::convertPreprocessingSteps($item_prototype['preprocessing']);
			}
		}
		unset($item_prototype);

		return $item_prototypes;
	}

	/**
	 * Convert preprocessing steps.
	 *
	 * @param array $preprocessing_steps
	 *
	 * @return array
	 */
	protected static function convertPreprocessingSteps(array $preprocessing_steps): array {
		foreach ($preprocessing_steps as &$preprocessing_step) {
			$preprocessing_step['parameters'] = ($preprocessing_step['type'] === CXmlConstantName::JAVASCRIPT)
				? [$preprocessing_step['params']]
				: explode("\n", $preprocessing_step['params']);
			unset($preprocessing_step['params']);
		}
		unset($preprocessing_step);

		return $preprocessing_steps;
	}

	/**
	 * Convert template screens to template dashboards.
	 *
	 * @param array $screens
	 *
	 * @return array
	 */
	private static function convertScreensToDashboards(array $screens): array {
		$converter = new CTemplateScreenConverter();

		foreach ($screens as &$screen) {
			$screen = $converter->convert($screen);
		}
		unset($screen);

		return $screens;
	}
}
