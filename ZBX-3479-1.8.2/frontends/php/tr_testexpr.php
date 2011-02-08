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

define('VALUE_TYPE_UNKNOWN', '#ERROR#');
define('COMBO_PATTERN', 'str_in_array({},array(');
define('COMBO_PATTERN_LENGTH', zbx_strlen(COMBO_PATTERN));

include_once('include/page_header.php');

//----------------------------------------------------------------------

// expression analyze
	$expression = urldecode(get_request('expression', ''));

	list($outline, $node, $map) = analyze_expression($expression);

	$tree = array();
	create_node_list($node, $tree);

// test data (create table, create check fields)

	$data_table = new CTable();
	$data_table->setClass('tableinfo');
	$data_table->setAttribute('id', 'data_list');
	$data_table->setOddRowClass('even_row');
	$data_table->setEvenRowClass('even_row');
	$data_table->setHeader(array('#', S_ITEM_FUNCTION, S_RESULT_TYPE, S_VALUE));

	$datas = array();
	$fields = array();
	foreach ($map as $key => $val){
		$expr = $val['expression'];
		if(isset($datas[$expr])) continue;

		$num = count($datas) + 1;
		$fname = 'test_data_n'.$num;
		$datas[$expr] = get_request($fname, '');
		$info = get_item_function_info($expr);

		$validation = $info['validation'];

		if(substr($validation, 0, COMBO_PATTERN_LENGTH) == COMBO_PATTERN){
			$vals = explode(',', substr($validation, COMBO_PATTERN_LENGTH, zbx_strlen($validation) - COMBO_PATTERN_LENGTH - 4));

			$control = new CComboBox($fname, $datas[$expr]);
			foreach ($vals as $v) $control->addItem($v, $v);
		}
		else $control = new CTextBox($fname, $datas[$expr], 30);

		$data_table->addRow(new CRow(array($num, $expr, $info['value_type'], $control)));
		$fields[$fname] = array($info['type'], O_OPT, null, $validation, 'isset({test_expression})');
	}

//---------------------------------- CHECKS ------------------------------------

	$fields['test_expression'] = array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null);
	check_fields($fields);


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

	$exprs = make_disp_tree($tree, $map);
	foreach($exprs as $e){
		$result = '-';
		if($test && $e['key']){
			$i = &$map[$e['key']];
			$value = convert($datas[$i['expression']]);

			if(empty($value)) $value = "''";

			eval("\$result = ".$value.($i['sign'] == '=' ? '==' : ($i['sign'] == '#' ? '!=' : $i['sign'])).convert($i['value']).';');
			$i['result'] = $result = $result == 1 ? 'TRUE' : 'FALSE';
		}

		$style = 'text-align: center;';
		if($result != '-')
			$style = ($result == 'TRUE')?'background-color: #ccf; color: #00f;': 'background-color: #fcc; color: #f00;';

		$col = new CCol($result);
		$col->setAttribute('style', $style);
		$res_table->addRow(new CRow(array($e['expr'], $col)));
	}

	$result = '-';
	if($test){
		$combine_expr = $outline;
		foreach ($map as $key => $val){
			$combine_expr = str_replace($key, zbx_strtolower($val['result']), $combine_expr);
		}

		eval("\$result = ".$combine_expr.';');
		$result = $result == 1 ? 'TRUE' : 'FALSE';
	}

	$style = 'text-align: center;';
	if($result != '-')
		$style = ($result == 'TRUE')?'background-color: #ccf; color: #00f;': 'background-color: #fcc; color: #f00;';

	$col = new CCol($result);
	$col->setAttribute('style', $style);
	$res_table->setFooter(array($outline, $col), $res_table->headerClass);

	$frm_test->addRow(S_RESULT, $res_table);

// action buttons
	$frm_test->addItemToBottomRow(new CButton('test_expression', S_TEST));
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
