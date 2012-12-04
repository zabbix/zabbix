<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; ifnot, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
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
?>
<?php
//----------------------------------------------------------------------

// expression analyze
	$expression = get_request('expression', '');

	define('NO_LINK_IN_TESTING', true);
	list($outline, $eHTMLTree) = analyze_expression($expression);

// test data (create table, create check fields)

	$data_table = new CTable(null, 'tableinfo');
	$data_table->setAttribute('id', 'data_list');

	$data_table->setHeader(array(_('Expression Variable Elements'), _('Result type'), _('Value')));

	$octet = false;
	$datas = array();
	$fields = array();
	$rplcts = array();
	$allowedTesting = true;

	$expressionData = new CTriggerExpression();
	if ($expressionData->parse($expression)) {
		$macrosData = array();

		$expressions = array_merge($expressionData->expressions, $expressionData->macros, $expressionData->usermacros,
				$expressionData->lldmacros);

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
				if(substr($validation, 0, COMBO_PATTERN_LENGTH) == COMBO_PATTERN){
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

			$data_table->addRow(new CRow(array($exprPart['expression'], (is_array($info) || !isset($definedErrorPhrases[$info])) ? $info['value_type'] : new CCol($definedErrorPhrases[$info], 'disaster'), $control)));
		}
	}
//---------------------------------- CHECKS ------------------------------------

	$fields['test_expression'] = array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null);
	if(!check_fields($fields)) {
		$test = false;
	}

//------------------------ <ACTIONS> ---------------------------
	if(isset($_REQUEST['test_expression'])){
		show_messages();
		$test = true;
	}
	else $test = false;
//------------------------ </ACTIONS> --------------------------

//------------------------ <FORM> ---------------------------

	$frm_test = new CFormTable(_('Test'), 'tr_testexpr.php');
	$frm_test->setHelp('web.testexpr.service.php');
	$frm_test->setTableClass('formlongtable formtable');
	$frm_test->addVar('form_refresh', get_request('form_refresh', 1));
	$frm_test->addVar('expression', $expression);

/* test data */
	$frm_test->addRow(_('Test data'), $data_table);

/* result */
	$res_table = new CTable(null, 'tableinfo');
	$res_table->setAttribute('id', 'result_list');
	$res_table->setOddRowClass('even_row');
	$res_table->setEvenRowClass('even_row');
	$res_table->setHeader(array(_('Expression'), _('Result')));

	ksort($rplcts, SORT_NUMERIC);

	foreach($eHTMLTree as $e){
		$result = '-';
		if($allowedTesting && $test && isset($e['expression'])){
			$result = evalExpressionData($e['expression']['value'], $macrosData, $octet);
		}

		$style = 'text-align: center;';
		if($result != '-')
			$style = ($result == 'TRUE') ? 'background-color: #ccf; color: #00f;': 'background-color: #fcc; color: #f00;';

		$col = new CCol($result);
		$col->setAttribute('style', $style);
		$res_table->addRow(new CRow(array($e['list'], $col)));
	}

	$result = '-';
	if($allowedTesting && $test){
		$result = evalExpressionData($expression, $macrosData, $octet);
	}

	$style = 'text-align: center;';
	if($result != '-')
		$style = ($result == 'TRUE') ? 'background-color: #ccf; color: #00f;': 'background-color: #fcc; color: #f00;';

	$col = new CCol($result);
	$col->setAttribute('style', $style);
	$res_table->setFooter(array($outline, $col), $res_table->headerClass);

	$frm_test->addRow(_('Result'), $res_table);

// action buttons
	$btn_test = new CSubmit('test_expression', _('Test'));
	if(!$allowedTesting) $btn_test->setAttribute('disabled', 'disabled');
	$frm_test->addItemToBottomRow($btn_test);
	$frm_test->addItemToBottomRow(SPACE);

	$btn_close = new CButton('close', _('Close'),'javascript: self.close();');
	$frm_test->addItemToBottomRow($btn_close);

	$frm_test->show();

//------------------------ </FORM> ---------------------------
?>
<?php

require_once dirname(__FILE__).'/include/page_footer.php';

?>
