<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
** along with this program; ifnot, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';

$page['title'] = _('Test');
$page['file'] = 'tr_testexpr.php';

define('ZBX_PAGE_NO_MENU', 1);
define('COMBO_PATTERN', 'str_in_array({},array(');
define('COMBO_PATTERN_LENGTH', zbx_strlen(COMBO_PATTERN));

$definedErrorPhrases = array(
	EXPRESSION_VALUE_TYPE_UNKNOWN => _('Unknown variable type, testing not available'),
	EXPRESSION_HOST_UNKNOWN => _('Unknown host, no such host present in system'),
	EXPRESSION_HOST_ITEM_UNKNOWN => _('Unknown host item, no such item in selected host'),
	EXPRESSION_NOT_A_MACRO_ERROR => _('Given expression is not a macro'),
	EXPRESSION_FUNCTION_UNKNOWN => _('Incorrect function is used')
);

require_once dirname(__FILE__).'/include/page_header.php';

// expression analyze
$expression = get_request('expression', '');

define('NO_LINK_IN_TESTING', true);
list($outline, $eHTMLTree) = analyzeExpression($expression);

// test data (create table, create check fields)
$dataTable = new CTable(null, 'tableinfo');
$dataTable->setAttribute('id', 'data_list');
$dataTable->setHeader(array(_('Expression Variable Elements'), _('Result type'), _('Value')));

$octet = false;
$datas = array();
$fields = array();
$rplcts = array();
$allowedTesting = true;

$expressionData = new CTriggerExpression();
if ($expressionData->parse($expression)) {
	$macrosData = array();

	$expressions = array_merge($expressionData->expressions, $expressionData->macros, $expressionData->usermacros, $expressionData->lldmacros);

	foreach ($expressions as $exprPart) {
		if (isset($macrosData[$exprPart['expression']])) {
			continue;
		}

		$fname = 'test_data_'.md5($exprPart['expression']);
		$macrosData[$exprPart['expression']] = get_request($fname, '');

		$info = get_item_function_info($exprPart['expression']);

		if (!is_array($info) && isset($definedErrorPhrases[$info])) {
			$allowedTesting = false;
			$control = new CTextBox($fname, $macrosData[$exprPart['expression']], 30);
			$control->setAttribute('disabled', 'disabled');
		}
		else {
			$octet = ($info['value_type'] == 'HHMMSS');
			$validation = $info['validation'];

			if (substr($validation, 0, COMBO_PATTERN_LENGTH) == COMBO_PATTERN) {
				$vals = explode(',', substr($validation, COMBO_PATTERN_LENGTH, zbx_strlen($validation) - COMBO_PATTERN_LENGTH - 4));
				$control = new CComboBox($fname, $macrosData[$exprPart['expression']]);

				foreach ($vals as $v) {
					$control->addItem($v, $v);
				}
			}
			else {
				$control = new CTextBox($fname, $macrosData[$exprPart['expression']], 30);
			}

			$fields[$fname] = array($info['type'], O_OPT, null, $validation, 'isset({test_expression})', $exprPart['expression']);
		}

		$resultType = (is_array($info) || !isset($definedErrorPhrases[$info]))
			? $info['value_type']
			: new CCol($definedErrorPhrases[$info], 'disaster');

		$dataTable->addRow(new CRow(array($exprPart['expression'], $resultType, $control)));
	}
}

// checks
$fields['test_expression'] = array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null);
if (!check_fields($fields)) {
	$test = false;
}

// actions
if (isset($_REQUEST['test_expression'])) {
	show_messages();
	$test = true;
}
else {
	$test = false;
}

// form
$testForm = new CFormTable(_('Test'), 'tr_testexpr.php');
$testForm->setHelp('web.testexpr.service.php');
$testForm->setTableClass('formlongtable formtable');
$testForm->addVar('form_refresh', get_request('form_refresh', 1));
$testForm->addVar('expression', $expression);
$testForm->addRow(_('Test data'), $dataTable);

$resultTable = new CTable(null, 'tableinfo');
$resultTable->setAttribute('id', 'result_list');
$resultTable->setOddRowClass('even_row');
$resultTable->setEvenRowClass('even_row');
$resultTable->setHeader(array(_('Expression'), _('Result')));

ksort($rplcts, SORT_NUMERIC);

foreach ($eHTMLTree as $e) {
	$result = array('result' => '-', 'error' => '');

	if ($allowedTesting && $test && isset($e['expression'])) {
		$result = evalExpressionData($e['expression']['value'], $macrosData, $octet);
	}

	$style = 'text-align: center;';
	if ($result['result'] != '-') {
		$style = ($result['result'] == 'TRUE')
			? 'background-color: #ccf; color: #00f;'
			: 'background-color: #fcc; color: #f00;';
	}

	$col = new CCol(array($result['result'], SPACE, $result['error']));
	$col->setAttribute('style', $style);

	$resultTable->addRow(new CRow(array($e['list'], $col)));
}

$result = array('result' => '-', 'error' => '');

if ($allowedTesting && $test) {
	$result = evalExpressionData($expression, $macrosData, $octet);
}

$style = 'text-align: center;';
if ($result['result'] != '-') {
	$style = ($result['result'] == 'TRUE')
		? 'background-color: #ccf; color: #00f;'
		: 'background-color: #fcc; color: #f00;';
}

$col = new CCol(array($result['result'], SPACE, $result['error']));
$col->setAttribute('style', $style);

$resultTable->setFooter(array($outline, $col), $resultTable->headerClass);

$testForm->addRow(_('Result'), $resultTable);

// action buttons
$testButton = new CSubmit('test_expression', _('Test'));
if (!$allowedTesting) {
	$testButton->setAttribute('disabled', 'disabled');
}

$testForm->addItemToBottomRow($testButton);
$testForm->addItemToBottomRow(SPACE);
$testForm->addItemToBottomRow(new CButton('close', _('Close'), 'javascript: self.close();'));
$testForm->show();

require_once dirname(__FILE__).'/include/page_footer.php';
