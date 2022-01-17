<?php declare(strict_types = 1);
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


/**
 * Converter for converting import data from 5.4 to 6.0.
 */
class C54ImportConverter extends CConverter {

	/**
	 * Convert import data from 5.4 to 6.0 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert(array $data): array {
		$data['zabbix_export']['version'] = '6.0';

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = self::convertHosts($data['zabbix_export']['hosts']);
		}

		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = self::convertTemplates($data['zabbix_export']['templates']);
		}

		if (array_key_exists('graphs', $data['zabbix_export'])) {
			$data['zabbix_export']['graphs'] = self::convertGraphs($data['zabbix_export']['graphs']);
		}

		if (array_key_exists('maps', $data['zabbix_export'])) {
			$data['zabbix_export']['maps'] = self::convertMaps($data['zabbix_export']['maps']);
		}

		if (array_key_exists('media_types', $data['zabbix_export'])) {
			$data['zabbix_export']['media_types'] = self::convertMediaTypes($data['zabbix_export']['media_types']);
		}

		return $data;
	}

	/**
	 * Convert function macros "{host:key.func(<param>)}" to expression macros "{?func(/host/key<, param>)}".
	 *
	 * @static
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private static function convertFunctionMacros(string $text): string {
		return (new C54SimpleMacroConverter())->convert($text);
	}

	/**
	 * Convert hosts.
	 *
	 * @static
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
	 * @static
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
		}
		unset($template);

		return $templates;
	}

	/**
	 * Convert items.
	 *
	 * @static
	 *
	 * @param array       $items
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
	 * Convert preprocessing steps.
	 *
	 * @static
	 *
	 * @param array $preprocessing_steps
	 *
	 * @return array
	 */
	private static function convertPreprocessingSteps(array $preprocessing_steps): array {
		foreach ($preprocessing_steps as &$preprocessing_step) {
			if ($preprocessing_step['type'] === CXmlConstantName::PROMETHEUS_PATTERN
					&& count($preprocessing_step['parameters']) === 2) {
				$preprocessing_step['parameters'][2] = $preprocessing_step['parameters'][1];
				$preprocessing_step['parameters'][1] = ($preprocessing_step['parameters'][2] === '')
					? ZBX_PREPROC_PROMETHEUS_VALUE
					: ZBX_PREPROC_PROMETHEUS_LABEL;
			}
		}
		unset($preprocessing_step);

		return $preprocessing_steps;
	}

	/**
	 * Convert discover rules.
	 *
	 * @param array $discovery_rules
	 *
	 * @return array
	 */
	private static function convertDiscoveryRules(array $discovery_rules): array {
		foreach ($discovery_rules as &$discovery_rule) {
			if (array_key_exists('graph_prototypes', $discovery_rule)) {
				$discovery_rule['graph_prototypes'] = self::convertGraphs($discovery_rule['graph_prototypes']);
			}

			if (array_key_exists('item_prototypes', $discovery_rule)) {
				$discovery_rule['item_prototypes'] = self::convertItems($discovery_rule['item_prototypes']);
			}
		}
		unset($discovery_rule);

		return $discovery_rules;
	}

	/**
	 * Convert graphs.
	 *
	 * @static
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	private static function convertGraphs(array $graphs): array {
		foreach ($graphs as &$graph) {
			$graph['name'] = self::convertFunctionMacros($graph['name']);
		}
		unset($graph);

		return $graphs;
	}

	/**
	 * Convert maps.
	 *
	 * @static
	 *
	 * @param array $maps
	 *
	 * @return array
	 */
	private static function convertMaps(array $maps): array {
		foreach ($maps as &$map) {
			$map['label_string_host'] = self::convertFunctionMacros($map['label_string_host']);
			$map['label_string_map'] = self::convertFunctionMacros($map['label_string_map']);
			$map['label_string_trigger'] = self::convertFunctionMacros($map['label_string_trigger']);
			$map['label_string_hostgroup'] = self::convertFunctionMacros($map['label_string_hostgroup']);
			$map['label_string_image'] = self::convertFunctionMacros($map['label_string_image']);

			foreach ($map['selements'] as &$selement) {
				$selement['label'] = self::convertFunctionMacros($selement['label']);
			}
			unset($selement);

			foreach ($map['shapes'] as &$shape) {
				$shape['text'] = self::convertFunctionMacros($shape['text']);
			}
			unset($shape);

			foreach ($map['links'] as &$link) {
				$link['label'] = self::convertFunctionMacros($link['label']);
			}
			unset($link);
		}
		unset($map);

		return $maps;
	}

	/**
	 * Convert media types.
	 *
	 * @static
	 *
	 * @param array $media_types
	 *
	 * @return array
	 */
	private static function convertMediaTypes(array $media_types): array {
		foreach ($media_types as &$media_type) {
			if (array_key_exists('message_templates', $media_type)) {
				foreach ($media_type['message_templates'] as &$message_template) {
					if (array_key_exists('subject', $message_template)) {
						$message_template['subject'] = self::convertFunctionMacros($message_template['subject']);
					}
					if (array_key_exists('message', $message_template)) {
						$message_template['message'] = self::convertFunctionMacros($message_template['message']);
					}
				}
				unset($message_template);
			}
		}
		unset($media_type);

		return $media_types;
	}
}
