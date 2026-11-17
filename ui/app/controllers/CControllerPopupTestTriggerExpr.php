<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/triggers.inc.php';

class CControllerPopupTestTriggerExpr extends CController {
	private $defined_error_phrases = [];
	private $supported_token_types = [
		CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION,
		CExpressionParserResult::TOKEN_TYPE_MACRO,
		CExpressionParserResult::TOKEN_TYPE_USER_MACRO,
		CExpressionParserResult::TOKEN_TYPE_LLD_MACRO,
		CExpressionParserResult::TOKEN_TYPE_STRING
	];

	protected function init() {
		$this->disableCsrfValidation();

		define('ZBX_PAGE_NO_MENU', true);
		define('NO_LINK_IN_TESTING', true);

		$this->defined_error_phrases = [
			EXPRESSION_HOST_UNKNOWN => _('Unknown host, no such host present in system'),
			EXPRESSION_HOST_ITEM_UNKNOWN => _('Unknown host item, no such item in selected host'),
			EXPRESSION_NOT_A_MACRO_ERROR => _('Given expression is not a macro'),
			EXPRESSION_FUNCTION_UNKNOWN => _('Incorrect function is used'),
			EXPRESSION_UNSUPPORTED_VALUE_TYPE => _('Incorrect item value type')
		];
	}

	protected function checkInput() {
		$fields = [
			'expression' =>			'string',
			'test_expression' =>	'string',
			'test_data' =>			'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			foreach ($this->getInput('test_data', []) as $test_data) {
				if (!is_array($test_data) || !array_key_exists('key', $test_data) || !is_string($test_data['key'])
						|| !array_key_exists('value', $test_data) || !is_string($test_data['value'])) {
					error(_s('Incorrect value for "%1$s" field.', 'test_data'));

					$ret = false;

					break;
				}
			}
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			|| $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction() {
		$expression = $this->getInput('expression', '');
		$test_data = array_column($this->getInput('test_data', []), 'value', 'key');
		$test_data_rows = [];
		$macros_data = [];
		$allowed_testing = true;

		$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);

		if ($expression_parser->parse($expression) == CParser::PARSE_SUCCESS) {
			foreach ($expression_parser->getResult()->getTokensOfTypes($this->supported_token_types) as $token) {
				if ($token['type'] == CExpressionParserResult::TOKEN_TYPE_STRING) {
					$matched_macros = CMacrosResolverGeneral::extractMacros(
						[CExpressionParser::unquoteString($token['match'])],
						[
							'macros' => [
								'trigger' => ['{TRIGGER.VALUE}']
							],
							'lldmacros' => true,
							'usermacros' => true
						]
					);

					$macros = array_merge(array_keys($matched_macros['usermacros'] + $matched_macros['lldmacros']),
						$matched_macros['macros']['trigger']
					);
				}
				else {
					$macros = [$token['match']];
				}

				foreach ($macros as $macro) {
					if (array_key_exists($macro, $macros_data)) {
						continue;
					}

					$key = md5($macro);
					$macros_data[$macro] = array_key_exists($key, $test_data) ? $test_data[$key] : '';
					$info = get_item_function_info($macro);

					if (!is_array($info) && array_key_exists($info, $this->defined_error_phrases)) {
						$allowed_testing = false;
						$test_data_rows[] = [
							'macro' => $macro,
							'key' => $key,
							'value' => $macros_data[$macro],
							'error' => $this->defined_error_phrases[$info]
						];
					}
					else {
						$test_data_rows[] = [
							'macro' => $macro,
							'key' => $key,
							'value' => $macros_data[$macro],
							'value_type' => $info['value_type'],
							'values' => $info['values'],
							'error' => null
						];
					}
				}
			}
		}

		list($outline, $expression_html_tree) = analyzeExpression($expression, TRIGGER_EXPRESSION);

		$message_title = null;
		$results = [];

		if ($allowed_testing && $this->hasInput('test_expression')) {
			$mapping = [];
			$expressions = [];

			foreach ($expression_html_tree as $e) {
				$original_expression = $e['expression']['value'];

				if ($expression_parser->parse($original_expression) == CParser::PARSE_SUCCESS) {
					$tokens = $expression_parser->getResult()->getTokensOfTypes($this->supported_token_types);
					$substituted_expression = $original_expression;

					for ($token = end($tokens); $token; $token = prev($tokens)) {
						if ($token['type'] == CExpressionParserResult::TOKEN_TYPE_STRING) {
							$value = strtr(CExpressionParser::unquoteString($token['match']), $macros_data);
							$value = CExpressionParser::quoteString($value, false, true);
						}
						else {
							$value = strtr($token['match'], $macros_data);
							$value = CExpressionParser::quoteString($value, false);
						}

						$substituted_expression = substr_replace($substituted_expression, $value, $token['pos'],
							$token['length']
						);
					}

					$mapping[$substituted_expression][] = $original_expression;
					$expressions[] = $substituted_expression;
				}
			}

			$data = ['expressions' => array_values(array_unique($expressions))];

			global $ZBX_SERVER, $ZBX_SERVER_PORT;

			$zabbix_server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
				timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
				timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::SOCKET_TIMEOUT)), 0
			);
			$response = $zabbix_server->expressionsEvaluate($data, CSessionHelper::getId());

			if ($zabbix_server->getError()) {
				error($zabbix_server->getError());
				$message_title = _('Cannot evaluate expression');
			}
			else {
				foreach ($response as $result) {
					foreach ($mapping[$result['expression']] as $original_expression) {
						unset($result['expression']);
						$results[$original_expression] = $result;
					}
				}
			}
		}

		$this->setResponse(new CControllerResponseData([
			'title' => _('Test'),
			'expression' => $expression,
			'allowed_testing' => $allowed_testing,
			'test_data_rows' => $test_data_rows,
			'eHTMLTree' => $expression_html_tree,
			'results' => $results,
			'outline' => $outline,
			'messages' => getMessages(false, $message_title),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
