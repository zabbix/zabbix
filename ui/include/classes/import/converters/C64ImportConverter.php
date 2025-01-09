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
 * Converter for converting import data from 6.4 to 7.0.
 */
class C64ImportConverter extends CConverter {

	/**
	 * Convert import data from 6.4 to 7.0 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert(array $data): array {
		$data['zabbix_export']['version'] = '7.0';

		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = self::convertTemplates($data['zabbix_export']['templates']);
		}

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = self::convertHosts($data['zabbix_export']['hosts']);
		}

		if (array_key_exists('triggers', $data['zabbix_export'])) {
			$data['zabbix_export']['triggers'] = self::convertTriggers($data['zabbix_export']['triggers']);
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
				$items = array_key_exists('items', $template) ? $template['items'] : [];
				$template['dashboards'] = self::convertDashboards($template['dashboards'], $items);
			}
		}
		unset($template);

		return $templates;
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
			if (array_key_exists('proxy', $host)) {
				$host['monitored_by'] = CXmlConstantName::PROXY;
			}

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
	 * Convert maps.
	 *
	 * @param array $maps
	 *
	 * @return array
	 */
	private static function convertMaps(array $maps): array {
		foreach ($maps as &$map) {
			$map['selements'] = self::convertMapSelements($map['selements']);
			$map['links'] = self::convertMapLinks($map['links']);
		}
		unset($map);

		return $maps;
	}

	/**
	 * Convert map selements.
	 *
	 * @param array $selements
	 *
	 * @return array
	 */
	private static function convertMapSelements(array $selements): array {
		foreach ($selements as &$selement) {
			if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
				$selement['elements'] = self::convertTriggers($selement['elements']);
			}
		}
		unset($selement);

		return $selements;
	}

	/**
	 * Convert map links.
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	private static function convertMapLinks(array $links): array {
		foreach ($links as &$link) {
			foreach ($link['linktriggers'] as &$linktrigger) {
				$linktrigger['trigger'] = self::convertTriggers([$linktrigger['trigger']])[0];
			}
			unset($linktrigger);
		}
		unset($link);

		return $links;
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
			if (array_key_exists('parameters', $media_type)) {
				foreach ($media_type['parameters'] as &$parameter) {
					if ($media_type['type'] === CXmlConstantName::WEBHOOK) {
						$parameter['name'] = self::convertExpressionMacros($parameter['name']);
					}

					if (array_key_exists('value', $parameter)) {
						$parameter['value'] = self::convertExpressionMacros($parameter['value']);
					}
				}
				unset($parameter);
			}

			if (array_key_exists('message_templates', $media_type)) {
				foreach ($media_type['message_templates'] as &$message_template) {
					foreach (['subject', 'message'] as $field) {
						if (array_key_exists($field, $message_template)) {
							$message_template[$field] = self::convertExpressionMacros($message_template[$field]);
						}
					}
				}
				unset($message_template);
			}

			$media_type = CArrayHelper::renameKeys($media_type, ['content_type' => 'message_format']);
		}
		unset($media_type);

		return $media_types;
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
				$item['params'] = self::convertExpression($item['params'], true);
				$item['params'] = self::convertCalcItemFormula($item['params']);
			}

			if ($item['type'] !== CXmlConstantName::HTTP_AGENT && $item['type'] !== CXmlConstantName::SCRIPT) {
				unset($item['timeout']);
			}

			self::convertPreprocessing($item);

			if (array_key_exists('triggers', $item)) {
				$item['triggers'] = self::convertTriggers($item['triggers']);
			}

			if (!array_key_exists('history', $item)) {
				$item['history'] = '90d';
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
			$discovery_rule += ['type' => CXmlConstantName::ZABBIX_PASSIVE];

			if ($discovery_rule['type'] !== CXmlConstantName::HTTP_AGENT
					&& $discovery_rule['type'] !== CXmlConstantName::SCRIPT) {
				unset($discovery_rule['timeout']);
			}

			$discovery_rule['enabled_lifetime_type'] = CXmlConstantName::LLD_DISABLE_NEVER;

			if (array_key_exists('lifetime', $discovery_rule)) {
				if ($discovery_rule['lifetime'][0] !== '{') {
					$converted_lifetime = timeUnitToSeconds($discovery_rule['lifetime']);

					if ($converted_lifetime !== null && $converted_lifetime == 0) {
						$discovery_rule['lifetime_type'] = CXmlConstantName::LLD_DELETE_IMMEDIATELY;
						$discovery_rule['enabled_lifetime_type'] = CXmlConstantName::LLD_DISABLE_IMMEDIATELY;
					}
				}
			}
			else {
				$discovery_rule['lifetime'] = '30d';
			}

			if (array_key_exists('item_prototypes', $discovery_rule)) {
				$discovery_rule['item_prototypes'] = self::convertItemPrototypes($discovery_rule['item_prototypes']);
			}

			if (array_key_exists('trigger_prototypes', $discovery_rule)) {
				$discovery_rule['trigger_prototypes'] = self::convertTriggers($discovery_rule['trigger_prototypes']);
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
			$item_prototype += ['type' => CXmlConstantName::ZABBIX_PASSIVE];

			if ($item_prototype['type'] === CXmlConstantName::CALCULATED
					&& array_key_exists('params', $item_prototype)) {
				$item_prototype['params'] = self::convertExpression($item_prototype['params'], true);
				$item_prototype['params'] = self::convertCalcItemFormula($item_prototype['params']);
			}

			if ($item_prototype['type'] !== CXmlConstantName::HTTP_AGENT
					&& $item_prototype['type'] !== CXmlConstantName::SCRIPT) {
				unset($item_prototype['timeout']);
			}

			self::convertPreprocessing($item_prototype);

			if (array_key_exists('trigger_prototypes', $item_prototype)) {
				$item_prototype['trigger_prototypes'] = self::convertTriggers($item_prototype['trigger_prototypes']);
			}
		}
		unset($item_prototype);

		return $item_prototypes;
	}

	/**
	 * @param array $item Item or item prototype.
	 */
	private static function convertPreprocessing(array &$item): void {
		if (!array_key_exists('preprocessing', $item)) {
			return;
		}

		foreach ($item['preprocessing'] as &$step) {
			if ($step['type'] == CXmlConstantName::CHECK_NOT_SUPPORTED) {
				$step['parameters'] = [(string) ZBX_PREPROC_MATCH_ERROR_ANY];
			}
		}
		unset($step);
	}

	/**
	 * Convert dashboards.
	 *
	 * @param array $dashboards
	 *
	 * @return array
	 */
	private static function convertDashboards(array $dashboards, array $items): array {
		$items_index = array_flip(array_column($items, 'key'));

		foreach ($dashboards as &$dashboard) {
			if (!array_key_exists('pages', $dashboard)) {
				continue;
			}

			$reference = 'AAAAA';

			foreach ($dashboard['pages'] as &$dashboard_page) {
				if (!array_key_exists('widgets', $dashboard_page)) {
					continue;
				}

				foreach ($dashboard_page['widgets'] as &$widget) {
					if (array_key_exists('x', $widget) && is_numeric($widget['x'])) {
						$widget['x'] = (string) ((int) $widget['x'] * 3);
					}

					if (array_key_exists('width', $widget)) {
						if (is_numeric($widget['width'])) {
							$widget['width'] = (string) ((int) $widget['width'] * 3);
						}
					}
					else {
						$widget['width'] = '3';
					}

					if (array_key_exists('fields', $widget)) {
						foreach ($widget['fields'] as &$field) {
							$field['name'] = preg_replace('/^([a-z]+)\.([a-z_]+)\.(\d+)\.(\d+)$/',
								'$1.$3.$2.$4', $field['name']
							);
							$field['name'] = preg_replace('/^([a-z]+)\.([a-z_]+)\.(\d+)$/',
								'$1.$3.$2', $field['name']
							);
						}
						unset($field);
					}

					if (in_array($widget['type'], ['graph', 'graphprototype'])) {
						if (!array_key_exists('fields', $widget)) {
							$widget['fields'] = [];
						}

						$widget['fields'][] = [
							'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_STRING,
							'name' => 'reference',
							'value' => $reference++
						];
					}

					if ($widget['type'] === 'plaintext') {
						$widget['type'] = 'itemhistory';

						$old_fields = array_key_exists('fields', $widget) ? $widget['fields'] : [];
						$old_fields_by_name = array_column($old_fields, 'name');

						$new_fields = [
							[
								'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
								'name' => 'show_timestamp',
								'value' => '1'
							],
							[
								'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_STRING,
								'name' => 'reference',
								'value' => $reference++
							]
						];

						foreach ($old_fields as $field) {
							switch ($field['name']) {
								case 'itemids':
									if (array_key_exists($field['value']['key'], $items_index)) {
										$item = $items[$items_index[$field['value']['key']]];

										$new_fields[] = [
											'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_STRING,
											'name' => 'columns.'.$items_index[$field['value']['key']].'.name',
											'value' => $item['name']
										];

										$new_fields[] = [
											'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_ITEM,
											'name' => 'columns.'.$items_index[$field['value']['key']].'.itemid',
											'value' => $field['value']
										];

										$is_textual = array_key_exists('value_type', $item)
											&& in_array(
												$item['value_type'],
												[CXmlConstantName::CHAR, CXmlConstantName::LOG, CXmlConstantName::TEXT]
											);

										$show_as_html = array_key_exists('show_as_html', $old_fields_by_name)
											&& $old_fields_by_name['show_as_html'] === '1';

										if ($is_textual) {
											$new_fields[] = [
												'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
												'name' => 'columns.'.$items_index[$field['value']['key']].'.monospace_font',
												'value' => '1'
											];

											if ($show_as_html) {
												$new_fields[] = [
													'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
													'name' => 'columns.'.$items_index[$field['value']['key']].'.display',
													'value' => '4'
												];
											}
										}
									}
									break;

								case 'style':
									$new_fields[] = ['name' => 'layout'] + $field;
									break;

								default:
									$new_fields[] = $field;
							}
						}

						$widget['fields'] = $new_fields;
					}

					if (array_key_exists('fields', $widget)) {
						// Fields must be sorted not to trigger diff view.
						usort($widget['fields'],
							static function (array $widget_field_a, array $widget_field_b): int {
								return strnatcasecmp($widget_field_a['name'], $widget_field_b['name']);
							}
						);
					}
				}
				unset($widget);
			}
			unset($dashboard_page);
		}
		unset($dashboard);

		return $dashboards;
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
			$trigger['expression'] = self::convertExpression($trigger['expression']);

			if (array_key_exists('recovery_expression', $trigger)) {
				$trigger['recovery_expression'] = self::convertExpression($trigger['recovery_expression']);
			}

			if (array_key_exists('event_name', $trigger)) {
				$trigger['event_name'] = self::convertExpressionMacros($trigger['event_name']);
			}

			if (array_key_exists('dependencies', $trigger)) {
				$trigger['dependencies'] = self::convertTriggers($trigger['dependencies']);
			}
		}
		unset($trigger);

		return $triggers;
	}

	/**
	 * Escaping backslashes in string parameters of history functions.
	 *
	 * @param CExpressionParser $expression_parser
	 * @param string            $expression
	 *
	 * @return string
	 */
	private static function escapeBackslashes(CExpressionParser $expression_parser, string $expression): string {
		$tokens = $expression_parser
			->getResult()
			->getTokensOfTypes([CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]);
		$convert_parameters = [];

		foreach ($tokens as $token) {
			foreach ($token['data']['parameters'] as $parameter) {
				if ($parameter['type'] == CHistFunctionParser::PARAM_TYPE_QUOTED
						&& strpos($parameter['match'], '\\') !== false) {
					$convert_parameters[] = $parameter;
				}
			}
		}

		foreach (array_reverse($convert_parameters) as $parameter) {
			$parameter['match'] = CHistFunctionParser::unquoteParam($parameter['match'],
				['unescape_backslashes' => false]
			);
			$parameter['match'] = CHistFunctionParser::quoteParam($parameter['match']);

			$expression = substr_replace($expression, $parameter['match'], $parameter['pos'], $parameter['length']);
		}

		return $expression;
	}

	/**
	 * @param string $text
	 *
	 * @return string
	 */
	private static function convertExpressionMacros(string $text): string {
		$expression_macro_parser = new CExpressionMacroParser([
			'usermacros' => true,
			'lldmacros' => true,
			'host_macro_n' => true,
			'empty_host' => true,
			'escape_backslashes' => false
		]);

		for ($p = strpos($text, '{'); $p !== false; $p = strpos($text, '{', $p + 1)) {
			if ($expression_macro_parser->parse($text, $p) != CParser::PARSE_FAIL) {
				$text = self::escapeBackslashes($expression_macro_parser->getExpressionParser(), $text);
				$p += $expression_macro_parser->getLength() - 1;
			}
		}

		return $text;
	}

	/**
	 * Convert expression.
	 *
	 * @param string $expression
	 * @param bool   $is_calc_item_formula
	 *
	 * @return string
	 */
	private static function convertExpression(string $expression, bool $is_calc_item_formula = false): string {
		$options = [
			'usermacros' => true,
			'lldmacros' => true,
			'escape_backslashes' => false
		];

		if ($is_calc_item_formula) {
			$options += [
				'calculated' => true,
				'host_macro' => true,
				'empty_host' => true
			];
		}

		$expression_parser = new CExpressionParser($options);

		return $expression_parser->parse($expression) == CParser::PARSE_SUCCESS
			? self::escapeBackslashes($expression_parser, $expression)
			: $expression;
	}

	/**
	 * Removes useless 2nd parameter from last_foreach() functions.
	 *
	 * @param string $formula
	 *
	 * @return string
	 */
	private static function convertCalcItemFormula(string $formula): string {
		$expression_parser = new CExpressionParser([
			'usermacros' => true,
			'lldmacros' => true,
			'calculated' => true,
			'host_macro' => true,
			'empty_host' => true
		]);

		if ($expression_parser->parse($formula) != CParser::PARSE_SUCCESS) {
			return $formula;
		}

		$tokens = $expression_parser
			->getResult()
			->getTokensOfTypes([CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]);

		foreach (array_reverse($tokens) as $token) {
			if ($token['data']['function'] === 'last_foreach') {
				if (count($token['data']['parameters']) == 2) {
					$pos = $token['data']['parameters'][1]['pos'];
					$length = $token['data']['parameters'][1]['length'];
					for ($lpos = $pos; $formula[$lpos] !== ','; $lpos--)
						;
					$rpos = strpos($formula, ')', $pos + $length);

					$formula = substr_replace($formula, '', $lpos, $rpos - $lpos);
				}
			}
		}

		return $formula;
	}
}
