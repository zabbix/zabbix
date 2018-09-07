<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
		CTriggerExpressionParserResult::TOKEN_TYPE_FUNCTION_MACRO => 1,
		CTriggerExpressionParserResult::TOKEN_TYPE_MACRO => 1,
		CTriggerExpressionParserResult::TOKEN_TYPE_USER_MACRO => 1,
		CTriggerExpressionParserResult::TOKEN_TYPE_LLD_MACRO => 1
	];

	protected function init() {
		$this->disableSIDvalidation();

		define('ZBX_PAGE_NO_MENU', 1);
		define('COMBO_PATTERN', 'str_in_array({},array(');
		define('COMBO_PATTERN_LENGTH', strlen(COMBO_PATTERN));
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

		$expression_data = new CTriggerExpression();
		$result = $expression_data->parse($this->expression);

		if ($result) {
			$this->macros_data = [];

			foreach ($result->getTokens() as $token) {
				if (!array_key_exists($token['type'], $this->supported_token_types)
						|| array_key_exists($token['value'], $this->macros_data)) {
					continue;
				}

				$row = (new CRow())->addItem($token['value']);
				$fname = 'test_data_'.md5($token['value']);
				$this->macros_data[$token['value']] = array_key_exists($fname, $_REQUEST) ? $_REQUEST[$fname] : '';
				$info = get_item_function_info($token['value']);

				if (!is_array($info) && array_key_exists($info, $this->defined_error_phrases)) {
					$this->allowed_testing = false;
					$row->addItem(
						(new CCol($this->defined_error_phrases[$info]))
							->addClass(ZBX_STYLE_RED)
							->setColspan(2)
					);
				}
				else {
					$validation = $info['validation'];

					if (substr($validation, 0, COMBO_PATTERN_LENGTH) == COMBO_PATTERN) {
						$end = strlen($validation) - COMBO_PATTERN_LENGTH - 4;
						$vals = explode(',', substr($validation, COMBO_PATTERN_LENGTH, $end));
						$control = new CComboBox($fname, $this->macros_data[$token['value']]);

						foreach ($vals as $v) {
							$control->addItem($v, $v);
						}
					}
					else {
						$control = (new CTextBox($fname, $this->macros_data[$token['value']]))
							->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
					}

					$this->fields[$fname] = [$info['type'], O_OPT, null, $info['validation'],
						'isset({test_expression})', $token['value']
					];

					$row->addItem($info['value_type']);
					$row->addItem($control);
				}

				$this->data_table_rows[] = $row;
			}
		}
	}

	protected function checkInput() {
		$fields = [
			'test_expression' => 'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		list($outline, $expression_html_tree) = analyzeExpression($this->expression, TRIGGER_EXPRESSION);

		$this->setResponse(new CControllerResponseData([
			'title' => _('Test'),
			'expression' => $this->expression,
			'allowed_testing' => $this->allowed_testing,
			'data_table_rows' => $this->data_table_rows,
			'supported_token_types' => $this->supported_token_types,
			'defined_error_phrases' => $this->defined_error_phrases,
			'eHTMLTree' => $expression_html_tree,
			'outline' => $outline,
			'test' => array_key_exists('test_expression', $_REQUEST),
			'message' => getMessages(),
			'macros_data' => $this->macros_data,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
