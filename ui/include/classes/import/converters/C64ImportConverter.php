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


/**
 * Converter for converting import data from 6.4 to 7.0.
 */
class C64ImportConverter extends CConverter {

	private static CExpressionParser $parser;

	/**
	 * Convert import data from 6.4 to 7.0 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert(array $data): array {
		$data['zabbix_export']['version'] = '7.0';

		class_alias(C10HistFunctionParser::class, 'CHistFunctionParser');

		self::$parser = new CExpressionParser(['usermacros' => true]);

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
			if (array_key_exists('triggers', $item)) {
				$item['triggers'] = self::convertTriggers($item['triggers']);
			}

			if ($item['type'] === 'CALCULATED' && array_key_exists('params', $item)) {
				$item['params'] = self::convertExpression($item['params']);
			}
		}
		unset($item);

		return $items;
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
	 * Convert item prototypes.
	 *
	 * @param array $item_prototypes
	 *
	 * @return array
	 */
	private static function convertItemPrototypes(array $item_prototypes): array {
		foreach ($item_prototypes as &$item_prototype) {
			if (array_key_exists('trigger_prototypes', $item_prototype)) {
				$item_prototype['trigger_prototypes'] = self::convertTriggers($item_prototype['trigger_prototypes']);
			}

			if ($item_prototype['type'] === 'CALCULATED' && array_key_exists('params', $item_prototype)) {
				$item_prototype['params'] = self::convertExpression($item_prototype['params']);
			}
		}
		unset($item_prototype);

		return $item_prototypes;
	}

	/**
	 * Convert triggers.
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	private static function convertTriggers(array $triggers): array {
		foreach ($triggers as &$trigger) {
			if (array_key_exists('expression', $trigger)) {
				$trigger['expression'] = self::convertExpression($trigger['expression']);
			}
		}
		unset($trigger);

		return $triggers;
	}

	/**
	 * Convert expression.
	 *
	 * @param string $expression
	 *
	 * @return string
	 */
	private static function convertExpression(string $expression): string {
		$convert_functions = ['count', 'countunique','find', 'logeventid', 'logsource'];

		if (self::$parser->parse($expression) != CParser::PARSE_SUCCESS) {
			return $expression;
		}

		$tokens = self::$parser->getResult()->getTokensOfTypes([CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]);
		$convert_params = [];

		foreach ($tokens as $token) {
			if (!in_array($token['data']['function'], $convert_functions)) {
				continue;
			}

			foreach ($token['data']['parameters'] as $parameter) {
				if ($parameter['type'] == C10HistFunctionParser::PARAM_TYPE_QUOTED
					&& strpos($parameter['match'], '\\') !== false) {
					$convert_params[] = $parameter;
				}
			}
		}

		if (!$convert_params) {
			return $expression;
		}

		foreach (array_reverse($convert_params) as $param) {
			$expression = substr_replace(
				$expression,
				strtr($param['match'], ['\\"' => '\\"', '\\' => '\\\\']),
				$param['pos'],
				$param['length']
			);
		}

		return $expression;
	}
}
