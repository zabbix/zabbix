<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
include_once('include/config.inc.php');
require_once('include/triggers.inc.php');

$page['title'] = S_TEST;
$page['file'] = 'tr_testexpr.php';

define('ZBX_PAGE_NO_MENU', 1);
define('COMBO_PATTERN', 'str_in_array({},array(');
define('COMBO_PATTERN_LENGTH', zbx_strlen(COMBO_PATTERN));

$definedErrorPhrases = array(
			EXPRESSION_VALUE_TYPE_UNKNOWN => S_EXPRESSION_VALUE_TYPE_UNKNOWN,
			EXPRESSION_HOST_UNKNOWN => S_EXPRESSION_HOST_UNKNOWN,
			EXPRESSION_HOST_ITEM_UNKNOWN => S_EXPRESSION_HOST_ITEM_UNKNOWN,
			EXPRESSION_NOT_A_MACRO_ERROR => S_EXPRESSION_NOT_A_MACRO_ERROR,
			EXPRESSION_FUNCTION_UNKNOWN => S_INCORRECT_FUNCTION_IS_USED
);

include_once('include/page_header.php');
?>
<?php
//----------------------------------------------------------------------

// expression analyze
	$expression = urldecode(get_request('expression', ''));

	define('NO_LINK_IN_TESTING', true);
	list($outline, $eHTMLTree) = analyze_expression($expression);

// test data (create table, create check fields)

	$data_table = new CTable(null, 'tableinfo');
	$data_table->setAttribute('id', 'data_list');

	$data_table->setHeader(array(S_EXPRESSION_VARIABLE_ELEMENTS, S_RESULT_TYPE, S_VALUE));

	$octet = false;
	$datas = array();
	$fields = array();
	$rplcts = array();
	$allowedTesting = true;

	$expressionData = new CTriggerExpression();
	if ($expressionData->parse($expression)) {
		$macrosData = array();

		$expressions = array_merge($expressionData->expressions, $expressionData->macros, $expressionData->usermacros);

		foreach ($expressions as $exprPart) {
			if (isset($macrosData[$exprPart['expression']])) {
				continue;
			}

			$fname = 'test_data_'.md5($exprPart['expression']);
			$macrosData[$exprPart['expression']] = get_request($fname, '');

			$info = get_item_function_info($exprPart['expression']);

			$octet = ($info['value_type'] == 'HHMMSS');

			$validation = $info['validation'];
			if(substr($validation, 0, COMBO_PATTERN_LENGTH) == COMBO_PATTERN){
				$vals = explode(',', substr($validation, COMBO_PATTERN_LENGTH, zbx_strlen($validation) - COMBO_PATTERN_LENGTH - 4));

				$control = new CComboBox($fname, $macrosData[$exprPart['expression']]);
				foreach ($vals as $v) $control->addItem($v, $v);
			}
			else
				$control = new CTextBox($fname, $macrosData[$exprPart['expression']], 30);

			if(!is_array($info) && isset($definedErrorPhrases[$info])) {
				$control->setAttribute('disabled', 'disabled');
				$allowedTesting = false;
			}

			$data_table->addRow(new CRow(array($exprPart['expression'], (is_array($info) || !isset($definedErrorPhrases[$info])) ? $info['value_type'] : new CCol($definedErrorPhrases[$info], 'disaster'), $control)));
			$fields[$fname] = array($info['type'], O_OPT, null, $validation, 'isset({test_expression})', $exprPart['expression']);
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

	$frm_test = new CFormTable(S_TEST, 'tr_testexpr.php');
	$frm_test->setHelp('web.testexpr.service.php');
	$frm_test->setTableClass('formlongtable formtable');
	$frm_test->addVar('form_refresh', get_request('form_refresh', 1));
	$frm_test->addVar('expression', urlencode($expression));

/* test data */
	$frm_test->addRow(S_TEST_DATA, $data_table);

/* result */
	$res_table = new CTable();
	$res_table->setClass('tableinfo');
	$res_table->setAttribute('id', 'result_list');
	$res_table->setOddRowClass('even_row');
	$res_table->setEvenRowClass('even_row');
	$res_table->setHeader(array(S_EXPRESSION, S_RESULT));

	ksort($rplcts, SORT_NUMERIC);

	//$exprs = make_disp_tree($tree, $map);
	foreach($eHTMLTree as $e){
		//if(!isset($e['expression']))
			//continue;
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

	$frm_test->addRow(S_RESULT, $res_table);

// action buttons
	$btn_test = new CButton('test_expression', S_TEST);
	if(!$allowedTesting) $btn_test->setAttribute('disabled', 'disabled');
	$frm_test->addItemToBottomRow($btn_test);
	$frm_test->addItemToBottomRow(SPACE);

	$btn_close = new CButton('close', S_CLOSE);
	$btn_close->setType('button');
	$btn_close->setAction('javascript: self.close();');
	$frm_test->addItemToBottomRow($btn_close);

	$frm_test->show();

//------------------------ </FORM> ---------------------------
?>
<?php

include_once('include/page_footer.php');

?>
