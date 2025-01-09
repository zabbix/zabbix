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
 * Converter for converting import data from 6.2 to 6.4.
 */
class C62ImportConverter extends CConverter {

	private const DASHBOARD_WIDGET_TYPE = [
		CXmlConstantName::DASHBOARD_WIDGET_TYPE_CLOCK => CXmlConstantValue::DASHBOARD_WIDGET_TYPE_CLOCK,
		CXmlConstantName::DASHBOARD_WIDGET_TYPE_GRAPH_CLASSIC => CXmlConstantValue::DASHBOARD_WIDGET_TYPE_GRAPH_CLASSIC,
		CXmlConstantName::DASHBOARD_WIDGET_TYPE_GRAPH_PROTOTYPE => CXmlConstantValue::DASHBOARD_WIDGET_TYPE_GRAPH_PROTOTYPE,
		CXmlConstantName::DASHBOARD_WIDGET_TYPE_ITEM => CXmlConstantValue::DASHBOARD_WIDGET_TYPE_ITEM,
		CXmlConstantName::DASHBOARD_WIDGET_TYPE_PLAIN_TEXT => CXmlConstantValue::DASHBOARD_WIDGET_TYPE_PLAIN_TEXT,
		CXmlConstantName::DASHBOARD_WIDGET_TYPE_URL => CXmlConstantValue::DASHBOARD_WIDGET_TYPE_URL
	];

	/**
	 * Convert import data from 6.2 to 6.4 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert(array $data): array {
		$data['zabbix_export']['version'] = '6.4';

		unset($data['zabbix_export']['date']);

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = self::convertHosts($data['zabbix_export']['hosts']);
		}

		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = self::convertTemplates($data['zabbix_export']['templates']);
		}

		if (array_key_exists('media_types', $data['zabbix_export'])) {
			$data['zabbix_export']['media_types'] = self::convertMediaTypes($data['zabbix_export']['media_types']);
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

			if (array_key_exists('dashboards', $template)) {
				$template['dashboards'] = self::convertDashboards($template['dashboards']);
			}
		}
		unset($template);

		return $templates;
	}

	/**
	 * Convert discovery rules.
	 *
	 * @param array $discovery_rules
	 *
	 * @return array
	 */
	private static function convertDiscoveryRules(array $discovery_rules): array {
		foreach ($discovery_rules as &$discovery_rule) {
			if (array_key_exists('item_prototypes', $discovery_rule)) {
				$discovery_rule['item_prototypes'] = self::convertItemPrototypes($discovery_rule['item_prototypes']);
			}
		}
		unset($discovery_rule);

		return $discovery_rules;
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
			$item += ['type' => CXmlConstantName::ZABBIX_PASSIVE];

			if ($item['type'] === CXmlConstantName::CALCULATED && array_key_exists('params', $item)) {
				$item['params'] = self::convertCalcItemFormula($item['params']);
			}
		}
		unset($item);

		return $items;
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
			$item_prototype += ['type' => CXmlConstantName::ZABBIX_PASSIVE];

			if ($item_prototype['type'] === CXmlConstantName::CALCULATED
					&& array_key_exists('params', $item_prototype)) {
				$item_prototype['params'] = self::convertCalcItemFormula($item_prototype['params'], true);
			}

			if (array_key_exists('inventory_link', $item_prototype)) {
				unset($item_prototype['inventory_link']);
			}
		}
		unset($item_prototype);

		return $item_prototypes;
	}

	/**
	 * Removes useless 2nd parameter from last_foreach() functions if it is 0.
	 *
	 * @param string $formula
	 * @param bool   $prototype
	 *
	 * @return string
	 */
	private static function convertCalcItemFormula(string $formula, bool $prototype = false): string {
		$expression_parser = new CExpressionParser([
			'usermacros' => true,
			'lldmacros' => $prototype,
			'calculated' => true,
			'host_macro' => true,
			'empty_host' => true
		]);

		$simple_interval_parser = new CSimpleIntervalParser(['with_year' => true]);

		if ($expression_parser->parse($formula) != CParser::PARSE_SUCCESS) {
			return $formula;
		}

		$tokens = $expression_parser
			->getResult()
			->getTokensOfTypes([CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]);

		foreach (array_reverse($tokens) as $token) {
			if ($token['data']['function'] !== 'last_foreach' || count($token['data']['parameters']) != 2) {
				continue;
			}

			if ($token['data']['parameters'][1]['type'] != CHistFunctionParser::PARAM_TYPE_PERIOD) {
				continue;
			}

			$sec_num = $token['data']['parameters'][1]['data']['sec_num'];

			if ($simple_interval_parser->parse($sec_num) != CParser::PARSE_SUCCESS) {
				continue;
			}

			if (timeUnitToSeconds($sec_num, true) == 0) {
				$pos = $token['data']['parameters'][1]['pos'];
				$length = $token['data']['parameters'][1]['length'];
				for ($lpos = $pos; $formula[$lpos] !== ','; $lpos--)
					;
				$rpos = strpos($formula, ')', $pos + $length);

				$formula = substr_replace($formula, '', $lpos, $rpos - $lpos);
			}
		}

		return $formula;
	}

	/**
	 * Convert dashboards.
	 *
	 * @param array $dashboards
	 *
	 * @return array
	 */
	private static function convertDashboards(array $dashboards): array {
		foreach ($dashboards as &$dashboard) {
			if (!array_key_exists('pages', $dashboard)) {
				continue;
			}

			foreach ($dashboard['pages'] as &$dashboard_page) {
				if (!array_key_exists('widgets', $dashboard_page)) {
					continue;
				}

				foreach ($dashboard_page['widgets'] as &$widget) {
					$widget['type'] = self::DASHBOARD_WIDGET_TYPE[$widget['type']];
				}
				unset($widget);
			}
			unset($dashboard_page);
		}
		unset($dashboard);

		return $dashboards;
	}

	/**
	 * Convert media types.
	 *
	 * @param array $media_types
	 *
	 * @return array
	 */
	private static function convertMediaTypes(array $media_types): array {
		foreach ($media_types as &$media_type) {
			if ($media_type['type'] == CXmlConstantName::SCRIPT && array_key_exists('parameters', $media_type)) {
				$parameters = [];
				$sortorder = 0;

				foreach ($media_type['parameters'] as $value) {
					$parameters[] = ['sortorder' => (string) $sortorder++, 'value' => $value];
				}

				$media_type['parameters'] =  $parameters;
			}
		}
		unset($media_type);

		return $media_types;
	}
}
