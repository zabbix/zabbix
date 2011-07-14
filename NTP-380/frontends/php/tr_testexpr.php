<?php
/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
include_once "include/config.inc.php";
require_once "include/triggers.inc.php";

$page['title'] = S_TEST;
$page['file'] = 'tr_testexpr.php';

define('ZBX_PAGE_NO_MENU', 1);
define('S_0_OR_1', '0 or 1');
define('VALUE_TYPE_UNKNOWN', '#ERROR#');
define('COMBO_PATTERN', 'in_array({},array(');
define('COMBO_PATTERN_LENGTH', strlen(COMBO_PATTERN));
define('RESULT_STYLE', 'text-align: center;');
define('R_TRUE_STYLE', 'background-color: #ccf; color: #00f;');
define('R_FALSE_STYLE', 'text-align: center; background-color: #fcc; color: #f00;');

$value_type = array(
	ITEM_VALUE_TYPE_UINT64	=> S_NUMERIC_UINT64,
	ITEM_VALUE_TYPE_FLOAT	=> S_NUMERIC_FLOAT,
	ITEM_VALUE_TYPE_STR		=> S_CHARACTER,
	ITEM_VALUE_TYPE_LOG		=> S_LOG,
	ITEM_VALUE_TYPE_TEXT	=> S_TEXT
	);

$type_of_value_type = array(
	ITEM_VALUE_TYPE_UINT64	=> T_ZBX_INT,
	ITEM_VALUE_TYPE_FLOAT	=> T_ZBX_DBL,
	ITEM_VALUE_TYPE_STR		=> T_ZBX_STR,
	ITEM_VALUE_TYPE_LOG		=> T_ZBX_STR,
	ITEM_VALUE_TYPE_TEXT	=> T_ZBX_STR
	);

$function_info = array(
	'abschange' => array(
		'value_type'	=> $value_type,
		'type'			=> $type_of_value_type,
		'validation'	=> NOT_EMPTY
		),
	'avg' => array(
		'value_type'	=> $value_type,
		'type'			=> $type_of_value_type,
		'validation'	=> NOT_EMPTY
		),
	'delta' => array(
		'value_type'	=> $value_type,
		'type'			=> $type_of_value_type,
		'validation'	=> NOT_EMPTY
		),
	'change' => array(
		'value_type'	=> $value_type,
		'type'			=> $type_of_value_type,
		'validation'	=> NOT_EMPTY
		),
	'count' => array(
		'value_type'	=> S_NUMERIC_UINT64,
		'type'			=> T_ZBX_INT,
		'validation'	=> NOT_EMPTY
		),
	'date' => array(
		'value_type'	=> 'YYYYMMDD',
		'type'			=> T_ZBX_INT,
		'validation'	=> '{}>=19700101&&{}<=99991231'
		),
	'dayofweek' => array(
		'value_type'	=> '1-7',
		'type'			=> T_ZBX_INT,
		'validation'	=> IN('1,2,3,4,5,6,7')
		),
	'diff' => array(
		'value_type'	=> S_0_OR_1,
		'type'			=> T_ZBX_INT,
		'validation'	=> IN('0,1')
		),
	'fuzzytime' => array(
		'value_type'	=> S_0_OR_1,
		'type'			=> T_ZBX_INT,
		'validation'	=> IN('0,1')
		),
	'iregexp' => array(
		'value_type'	=> S_0_OR_1,
		'type'			=> T_ZBX_INT,
		'validation'	=> IN('0,1')
		),
	'last' => array(
		'value_type'	=> $value_type,
		'type'			=> $type_of_value_type,
		'validation'	=> NOT_EMPTY
		),
	'logseverity' => array(
		'value_type'	=> S_NUMERIC_UINT64,
		'type'			=> T_ZBX_INT,
		'validation'	=> NOT_EMPTY
		),
	'logsource' => array(
		'value_type'	=> S_0_OR_1,
		'type'			=> T_ZBX_INT,
		'validation'	=> IN('0,1')
		),
	'max' => array(
		'value_type'	=> $value_type,
		'type'			=> $type_of_value_type,
		'validation'	=> NOT_EMPTY
		),
	'min' => array(
		'value_type'	=> $value_type,
		'type'			=> $type_of_value_type,
		'validation'	=> NOT_EMPTY
		),
	'nodata' => array(
		'value_type'	=> S_0_OR_1,
		'type'			=> T_ZBX_INT,
		'validation'	=> IN('0,1')
		),
	'now' => array(
		'value_type'	=> S_NUMERIC_UINT64,
		'type'			=> T_ZBX_INT,
		'validation'	=> NOT_EMPTY
		),
	'prev' => array(
		'value_type'	=> $value_type,
		'type'			=> $type_of_value_type,
		'validation'	=> NOT_EMPTY
		),
	'regexp' => array(
		'value_type'	=> S_0_OR_1,
		'type'			=> T_ZBX_INT,
		'validation'	=> IN('0,1')
		),
	'str' => array(
		'value_type'	=> S_0_OR_1,
		'type'			=> T_ZBX_INT,
		'validation'	=> IN('0,1')
		),
	'sum' => array(
		'value_type'	=> $value_type,
		'type'			=> $type_of_value_type,
		'validation'	=> NOT_EMPTY
		),
	'time' => array(
		'value_type'	=> 'HHMMSS',
		'type'			=> T_ZBX_INT,
		'validation'	=> 'strlen({})==6'
		)
	);

include_once "include/page_header.php";

//----------------------------------------------------------------------

$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_WRITE,PERM_MODE_LT);

/* expression analyze */
$expression = get_request('expression', '');
list($outline, $node, $map) = analyze_expression($expression);
$tree = array();
create_node_list($node, $tree);

/* test data (create table, create check fields) */
$data_table = new CTable();
$data_table->SetClass('tableinfo');
$data_table->AddOption('id', 'data_list');
$data_table->oddRowClass = 'even_row';
$data_table->evenRowClass = 'even_row';
$data_table->options['cellpadding'] = 3;
$data_table->options['cellspacing'] = 1;
$data_table->headerClass = 'header';
$data_table->footerClass = 'footer';
$data_table->SetHeader(array('#', S_ITEM_FUNCTION, S_RESULT_TYPE, S_VALUE));

$datas = array();
$fields = array();
foreach ($map as $key => $val)
{
	$expr = $val['expression'];
	if (isset($datas[$expr])) continue;

	$num = count($datas) + 1;
	$fname = 'test_data_#' . $num;
	$datas[$expr] = get_request($fname, '');
	$info = get_item_function_info($expr);
	$validation = $info['validation'];

	if (substr($validation, 0, COMBO_PATTERN_LENGTH) == COMBO_PATTERN)
	{
		$vals = explode(',', substr($validation, COMBO_PATTERN_LENGTH, strlen($validation) - COMBO_PATTERN_LENGTH - 4));

		$control = new CComboBox($fname, $datas[$expr]);
		foreach ($vals as $v) $control->AddItem($v, $v);
	}
	else $control = new CTextBox($fname, $datas[$expr], 30);

	$data_table->AddRow(new CRow(array($num, $expr, $info['value_type'], $control)));
	$fields[$fname] = array($info['type'], O_OPT, null, $validation, 'isset({test_expression})');
}

//---------------------------------- CHECKS ------------------------------------

$fields['test_expression'] = array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null);
check_fields($fields);

validate_group_with_host(PERM_READ_WRITE,
						 array('always_select_first_host', 'only_current_node'),
						 'web.last.conf.groupid', 
						 'web.last.conf.hostid'
	);

//------------------------ <ACTIONS> ---------------------------
if (isset($_REQUEST['test_expression']))
{
	show_messages();
	$test = true;
}
else $test = false;
//------------------------ </ACTIONS> --------------------------

//------------------------ <FORM> ---------------------------

$frm_test = new CFormTable(S_TEST, 'tr_testexpr.php', 'POST');
$frm_test->SetHelp('web.testexpr.service.php');
$frm_test->SetTableClass('formlongtable');
$frm_test->AddVar('form_refresh', get_request('form_refresh', 1));
$frm_test->AddVar('expression', $expression);

/* test data */
$frm_test->AddRow(S_TEST_DATA, $data_table);

/* result */
$res_table = new CTable();
$res_table->SetClass('tableinfo');
$res_table->AddOption('id', 'result_list');
$res_table->oddRowClass = 'even_row';
$res_table->evenRowClass = 'even_row';
$res_table->options['cellpadding'] = 3;
$res_table->options['cellspacing'] = 1;
$res_table->headerClass = 'header';
$res_table->footerClass = 'footer';
$res_table->SetHeader(array(S_EXPRESSION, S_RESULT));

$exprs = make_disp_tree($tree, $map);
foreach ($exprs as $e)
{
	$result = '-';
	if ($test && $e['key'])
	{
		$i = &$map[$e['key']];
		$value = convert($datas[$i['expression']]);
		if (empty($value)) $value = "''";
		eval("\$result = " . $value . ($i['sign'] == '=' ? '==' : ($i['sign'] == '#' ? '!=' : $i['sign'])) .
			 convert($i['value']) . ';');
		$i['result'] = $result = $result == 1 ? 'TRUE' : 'FALSE';
	}
	$col = new CCol($result);
	$col->AddOption('style', RESULT_STYLE . ($result == '-' ? '' : ($result == 'TRUE' ? R_TRUE_STYLE : R_FALSE_STYLE)));
	$res_table->AddRow(new CRow(array($e['expr'], $col)));
}

$result = '-';
if ($test)
{
	$combine_expr = $outline;
	foreach ($map as $key => $val) $combine_expr = str_replace($key, strtolower($val['result']), $combine_expr);
	eval("\$result = " . $combine_expr . ';');
	$result = $result == 1 ? 'TRUE' : 'FALSE';
}
$col = new CCol($result);
$col->AddOption('style', RESULT_STYLE . ($result == '-' ? '' : ($result == 'TRUE' ? R_TRUE_STYLE : R_FALSE_STYLE)));
$res_table->SetFooter(array($outline, $col), $res_table->headerClass);

$frm_test->AddRow(S_RESULT, $res_table);

/* action buttons */
$frm_test->AddItemToBottomRow(new CButton('test_expression', S_TEST));
$frm_test->AddItemToBottomRow(SPACE);

$btn_close = new CButton('close', S_CLOSE);
$btn_close->SetType('button');
$btn_close->SetAction('javascript: self.close();');
$frm_test->AddItemToBottomRow($btn_close);

$frm_test->Show();

//------------------------ </FORM> ---------------------------

function get_item_function_info($expr)
{
	global $USER_DETAILS, $function_info, $denyed_hosts, $ZBX_TR_EXPR_ALLOWED_MACROS;

	if (isset($ZBX_TR_EXPR_ALLOWED_MACROS[$expr]))
	{
		$result = array(
			'value_type'	=> S_0_OR_1,
			'type'			=> T_ZBX_INT,
			'validation'	=> IN('0,1')
			);
	}
	else
	{
		$item_id = $function = null;
		if (mb_ereg('^' . ZBX_EREG_SIMPLE_EXPRESSION_FORMAT_MB, $expr, $expr_res))
		{
			$db_res = DBfetch(DBselect('select i.itemid from items i, hosts h ' .
									   ' where i.hostid=h.hostid and h.host=' .
									   zbx_dbstr($expr_res[ZBX_SIMPLE_EXPRESSION_HOST_ID]) .
									   ' and i.key_=' .
									   zbx_dbstr($expr_res[ZBX_SIMPLE_EXPRESSION_KEY_ID])));
			if ($db_res) $item_id = $db_res['itemid'];

			$function = $expr_res[ZBX_SIMPLE_EXPRESSION_FUNCTION_NAME_ID];
		}
		unset($expr_res);
		if ($item_id == null) return VALUE_TYPE_UNKNOWN;

		$result = $function_info[$function];
		if (is_array($result['value_type']))
		{
			$value_type = null;
			if ($item_data = DBfetch(DBselect('select distinct i.value_type from hosts h,items i ' .
											  ' where h.hostid=i.hostid and h.hostid not in (' . $denyed_hosts . ')' .
											  ' and i.itemid=' . $item_id)))
			{
				$value_type = $item_data['value_type'];
			}
			if ($value_type == null) return VALUE_TYPE_UNKNOWN;

			$result['value_type'] = $result['value_type'][$value_type];
			$result['type'] = $result['type'][$value_type];

			if ($result['type'] == T_ZBX_INT || $result['type'] == T_ZBX_DBL)
			{
				$result['type'] = T_ZBX_STR;
				$result['validation'] = 'mb_ereg("^' . ZBX_EREG_NUMBER . '$",{})';
			}
		}
	}

	return $result;
}

function convert($value)
{
	$val = trim($value);
	if (!mb_ereg(ZBX_EREG_NUMBER, $val)) return $value;

	$last = strtolower($val{strlen($val)-1});
	switch($last)
	{
	case 't':
		$val *= 1024 * 1024 * 1024 * 1024;
	case 'g':
		$val *= 1024 * 1024 * 1024;
	case 'm':
		$val *= 1024 * 1024;
	case 'k':
		$val *= 1024;
	}

	return $val;
}
?>
<?php

include_once "include/page_footer.php";

?>
