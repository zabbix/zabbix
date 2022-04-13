<?php
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


require_once dirname(__FILE__).'/../../include/triggers.inc.php';

class CControllerPopupTestTriggerExpr extends CController {
	private $defined_error_phrases = [];
	private $expression = '';
	private $fields = [];
	private $macros_data = [];
	private $data_table_rows = [];
	private $allowed_testing = true;
	private $supported_token_types = [
		CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION,
		CExpressionParserResult::TOKEN_TYPE_MACRO,
		CExpressionParserResult::TOKEN_TYPE_USER_MACRO,
		CExpressionParserResult::TOKEN_TYPE_LLD_MACRO,
		CExpressionParserResult::TOKEN_TYPE_STRING
	];

	protected function init() {
		$this->disableSIDvalidation();

		define('ZBX_PAGE_NO_MENU', true);
		define('NO_LINK_IN_TESTING', true);

		$this->defined_error_phrases = [
			EXPRESSION_HOST_UNKNOWN => _('Unknown host, no such host present in system'),
			EXPRESSION_HOST_ITEM_UNKNOWN => _('Unknown host item, no such item in selected host'),
			EXPRESSION_NOT_A_MACRO_ERROR => _('Given expression is not a macro'),
			EXPRESSION_FUNCTION_UNKNOWN => _('Incorrect function is used'),
			EXPRESSION_UNSUPPORTED_VALUE_TYPE => _('Incorrect item value type')
		];

		// Must be done before input validation, because validated fields depends on tokens included in the expression.
		if (array_key_exists('expression', $_REQUEST)) {
			$this->expression = $_REQUEST['expression'];
		}

		$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);

		if ($expression_parser->parse($this->expression) == CParser::PARSE_SUCCESS) {
			$this->macros_data = [];

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
					if (array_key_exists($macro, $this->macros_data)) {
						continue;
					}

					$row = (new CRow())->addItem(
						(new CCol($macro))
							->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
							->addStyle('max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
					);
					$fname = 'test_data_'.md5($macro);
					$this->macros_data[$macro] = array_key_exists($fname, $_REQUEST) ? $_REQUEST[$fname] : '';
					$info = get_item_function_info($macro);

					if (!is_array($info) && array_key_exists($info, $this->defined_error_phrases)) {
						$this->allowed_testing = false;
						$row->addItem(
							(new CCol($this->defined_error_phrases[$info]))
								->addClass(ZBX_STYLE_RED)
								->setColspan(2)
						);
					}
					else {
						if ($info['values'] !== null) {
							$control = (new CSelect($fname))
								->addOptions(CSelect::createOptionsFromArray($info['values']))
								->setValue($this->macros_data[$macro]);
						}
						else {
							$control = (new CTextBox($fname, $this->macros_data[$macro]))
								->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
						}

						$this->fields[$fname] = 'string';

						$row->addItem($info['value_type']);
						$row->addItem($control);
					}

					$this->data_table_rows[] = $row;
				}
			}
		}
	}

	protected function checkInput() {
		$ret = $this->validateInput(['test_expression' => 'string'] + $this->fields);

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
		return true;
	}

	protected function doAction() {
		list($outline, $expression_html_tree) = analyzeExpression($this->expression, TRIGGER_EXPRESSION);

		$message_title = null;
		$results = [];

		if ($this->allowed_testing && $this->hasInput('test_expression')) {
			$mapping = [];
			$expressions = [];

			$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);

			foreach ($expression_html_tree as $e) {
				$original_expression = $e['expression']['value'];

				if ($expression_parser->parse($original_expression) == CParser::PARSE_SUCCESS) {
					$tokens = $expression_parser->getResult()->getTokensOfTypes($this->supported_token_types);
					$expression = $original_expression;

					for ($token = end($tokens); $token; $token = prev($tokens)) {
						if ($token['type'] == CExpressionParserResult::TOKEN_TYPE_STRING) {
							$value = strtr(CExpressionParser::unquoteString($token['match']), $this->macros_data);
							$value = CExpressionParser::quoteString($value, false, true);
						}
						else {
							$value = strtr($token['match'], $this->macros_data);
							$value = CExpressionParser::quoteString($value, false);
						}

						$expression = substr_replace($expression, $value, $token['pos'], $token['length']);
					}

					$mapping[$expression][] = $original_expression;
					$expressions[] = $expression;
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
				foreach ($response as $expression) {
					foreach ($mapping[$expression['expression']] as $original_expression) {
						unset($expression['expression']);
						$results[$original_expression] = $expression;
					}
				}
			}
		}

		$this->setResponse(new CControllerResponseData([
			'title' => _('Test'),
			'expression' => $this->expression,
			'allowed_testing' => $this->allowed_testing,
			'data_table_rows' => $this->data_table_rows,
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
