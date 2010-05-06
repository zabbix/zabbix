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
	
	$expressionData = parseTriggerExpressions($expression, true);
	list($outline, $eHTMLTree) = analyze_expression($expression);

// test data (create table, create check fields)

	$data_table = new CTable();
	$data_table->setClass('tableinfo');
	$data_table->setAttribute('id', 'data_list');
	$data_table->setOddRowClass('even_row');
	$data_table->setEvenRowClass('even_row');
	$data_table->setHeader(array(S_ITEM_SLASH_FUNCTION, S_RESULT_TYPE, S_VALUE));

	$datas = array();
	$fields = array();
	$rplcts = Array();

	if(!isset($expressionData[$expression]['errors']) && isset($expressionData[$expression]['allMacros'])) {
		$macrosData = Array();
		foreach ($expressionData[$expression]['allMacros'] as $macros){
			$macroStr = zbx_substr($expression, $macros['openSymbolNum'], $macros['closeSymbolNum']-$macros['openSymbolNum']+1);
			//SDI($macroStr);
			
			$macrosId = md5($macroStr);
			$skip = isset($macrosData[$macrosId]);
			
			if(!isset($macrosData[$macrosId]) || !isset($macrosData[$macrosId]['pos']))
				$macrosData[$macrosId]['pos'] = Array();
			
			$rplcts[$macros['openSymbolNum'].'_'.$macros['closeSymbolNum']] = Array('start' => $macros['openSymbolNum'], 'end' => $macros['closeSymbolNum'], 'item' => &$macrosData[$macrosId]);
			
			if($skip) continue;

			$fname = 'test_data_'.$macrosId;
			$macrosData[$macrosId]['cValue'] = get_request($fname, '');
			$info = get_item_function_info($macroStr);
			//SDII($info);

			$validation = $info['validation'];

			if(substr($validation, 0, COMBO_PATTERN_LENGTH) == COMBO_PATTERN){
				$vals = explode(',', substr($validation, COMBO_PATTERN_LENGTH, zbx_strlen($validation) - COMBO_PATTERN_LENGTH - 4));

				$control = new CComboBox($fname, $macrosData[$macrosId]['cValue']);
				foreach ($vals as $v) $control->addItem($v, $v);
			}else
				$control = new CTextBox($fname, $macrosData[$macrosId]['cValue'], 30);

			$data_table->addRow(new CRow(array($macroStr, $info['value_type'], $control)));
			$fields[$fname] = array($info['type'], O_OPT, null, $validation, 'isset({test_expression})');
		}
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
	
	ksort($rplcts, SORT_NUMERIC);
	
	//$exprs = make_disp_tree($tree, $map);
	foreach($eHTMLTree as $e){
		//if(!isset($e['expression']))
			//continue;
		
		$result = '-';
		if($test && isset($e['expression'])){
			/*
			$evStr = zbx_substr($expression, $e['expression']['start']+($e['expression']['oSym'] !== NULL ? zbx_strlen($e['expression']['oSym']) : 0),
							 $e['expression']['end']-$e['expression']['start']-($e['expression']['cSym'] !== NULL ? zbx_strlen($e['expression']['cSym']) : 0));
			
			$chStart = $e['expression']['start']+($e['expression']['oSym'] !== NULL ? zbx_strlen($e['expression']['oSym']) : 0);
			if(is_array($rplcts)) {
				foreach($rplcts as $mKey => $mData) {
					if($mData['start'] >= $e['expression']['start'] && $mData['end'] <= $e['expression']['end']) {
						//SDII($cvItem);
						$vStart = $mData['start'] - $chStart;
						$vEnd = $mData['end'] - $chStart+1;
						$cValue = convert($mData['item']['cValue']);
						if(empty($cValue)) $cValue = "''";
						$chStart += ($mData['end']-$mData['start']+1)-zbx_strlen($cValue);
						//SDI(zbx_substr($expression, $mData['start'], $mData['end']-$mData['start']+1));
 						//SDI(zbx_substr($evStr, $vStart, $vEnd-$vStart));
						//SDI($evStr);
						$evStr = ($vStart > 0 ? zbx_substr($evStr, 0, $vStart) : '').$cValue.($vEnd < zbx_strlen($evStr) ? zbx_substr($evStr, $vEnd) : '');
						//SDI($evStr);
					}
				}
			}
			$evStr = str_replace('=', '==', $evStr);
			$evStr = str_replace('#', '!=', $evStr);
			$evStr = str_replace('&', '&&', $evStr);
			$evStr = str_replace('|', '||', $evStr);
			//SDI($evStr);
			*/
			$evStr = replaceExpressionTestData($expression, &$e, &$rplcts);
			eval('$result = '.$evStr.';');
			$result = !$result ? 'FALSE' : 'TRUE';
		}

		$style = 'text-align: center;';
		if($result != '-')
			$style = ($result == 'TRUE') ? 'background-color: #ccf; color: #00f;': 'background-color: #fcc; color: #f00;';

		$col = new CCol($result);
		$col->setAttribute('style', $style);
		$res_table->addRow(new CRow(array($e['list'], $col)));
	}

	$result = '-';
	if($test){
		$e['expression'] = Array('start' => 0, 'end' => zbx_strlen($expression), 'oSym' => NULL, 'cSym' => NULL);
		$evStr = replaceExpressionTestData($expression, &$e, &$rplcts);
		
		eval('$result = '.$evStr.';');
		$result = !$result ? 'FALSE':'TRUE';
	}

	$style = 'text-align: center;';
	if($result != '-')
		$style = ($result == 'TRUE') ? 'background-color: #ccf; color: #00f;': 'background-color: #fcc; color: #f00;';

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
