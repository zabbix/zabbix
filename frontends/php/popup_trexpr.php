<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
	require_once('include/config.inc.php');
	require_once('include/users.inc.php');

	$page['title'] = 'S_CONDITION';
	$page['file'] = 'popup_trexpr.php';

	define('ZBX_PAGE_NO_MENU', 1);

	include_once('include/page_header.php');

?>
<?php
	$operators = array(
		'<' => '<',
		'>' => '>',
		'=' => '=',
		'#' => 'NOT');
	$limited_operators = array(
		'=' => '=',
		'#' => 'NOT');

	$metrics = array(
		PARAM_TYPE_SECONDS => S_SECONDS,
		PARAM_TYPE_COUNTS => S_COUNT);

	$param1_sec_count = array(
			array(
				'C' => S_LAST_OF.' (T)',	/* caption */
				'T' => T_ZBX_INT,	/* type */
				'M' => $metrics		/* metrcis */
			     ),
			array(
				'C' => S_TIME_SHIFT.' ',	/* caption */
				'T' => T_ZBX_INT,	/* type */
			));
	$param1_sec_count_no_timeshift = array(
		array(
				'C' => S_LAST_OF.' (T)',	/* caption */
				'T' => T_ZBX_INT,	/* type */
				'M' => $metrics		/* metrcis */
			     )
	);

	$param1_sec = array(
			array(
				'C' => S_LAST_OF.' (T)',	/* caption */
				'T' => T_ZBX_INT,	/* type */
			     ));

	$param1_str = array(
			array(
				'C' => 'T',		/* caption */
				'T' => T_ZBX_STR,
			     ));

	$param2_sec_val = array(
		array(
			'C' => S_LAST_OF.' (T)',	/* caption */
			'T' => T_ZBX_INT,
			 ),
		array(
			'C' => 'V',		/* caption */
			'T' => T_ZBX_STR,
			 ));

	$param2_val_sec = array(
		array(
			'C' => 'V', /* caption */
			'T' => T_ZBX_STR,
		),
		array(
			'C' => S_LAST_OF . ' (T)', /* caption */
			'T' => T_ZBX_INT,
		)
	);

	$allowed_types_any = array(
		ITEM_VALUE_TYPE_FLOAT => 1,
		ITEM_VALUE_TYPE_STR => 1,
		ITEM_VALUE_TYPE_LOG => 1,
		ITEM_VALUE_TYPE_UINT64 => 1,
		ITEM_VALUE_TYPE_TEXT => 1
	);
	$allowed_types_numeric = array(
		ITEM_VALUE_TYPE_FLOAT => 1,
		ITEM_VALUE_TYPE_UINT64 => 1
	);
	$allowed_types_str = array(
		ITEM_VALUE_TYPE_STR => 1,
		ITEM_VALUE_TYPE_LOG => 1,
		ITEM_VALUE_TYPE_TEXT => 1
	);
	$allowed_types_log = array(
		ITEM_VALUE_TYPE_LOG => 1
	);

	$functions = array(
		'abschange'	=> array(
			'description'	=> 'Absolute difference between last and previous value {OP} N',
			'operators'	=> $operators,
			'allowed_types' => $allowed_types_any
			),
		'avg'		=> array(
			'description'	=> 'Average value for period of T times {OP} N',
			'operators'	=> $operators,
			'params'	=> $param1_sec_count,
			'allowed_types' => $allowed_types_numeric
			),
		'delta'		=> array(
			'description'	=> 'Difference between MAX and MIN value of T times {OP} N',
			'operators'	=> $operators,
			'params'	=> $param1_sec_count,
			'allowed_types' => $allowed_types_numeric
			),
		'change'	=> array(
			'description'	=> 'Difference between last and previous value of T times {OP} N.',
			'operators'	=> $operators,
			'allowed_types' => $allowed_types_any
			),
		'count'		=> array(
			'description'	=> 'Number of successfully retrieved values V for period of time T {OP} N.',
			'operators'     => $operators,
			'params'	=> $param2_sec_val,
			'allowed_types' => $allowed_types_any
			),
		'diff'		=> array(
			'description'	=> 'N {OP} X, where X is 1 - if last and previous values differs, 0 - otherwise.',
			'operators'     => $limited_operators,
			'allowed_types' => $allowed_types_any
			),
		'last'	=> array(
			'description'	=> 'Last value {OP} N',
			'operators'	=> $operators,
			'params'	=> $param1_sec_count,
			'allowed_types' => $allowed_types_any
			),
		'max'		=> array(
			'description'	=> 'Maximal value for period of time T {OP} N.',
			'operators'     => $operators,
			'params'	=> $param1_sec_count,
			'allowed_types' => $allowed_types_numeric
			),
		'min'		=> array(
			'description'	=> 'Minimal value for period of time T {OP} N.',
			'operators'     => $operators,
			'params'	=> $param1_sec_count,
			'allowed_types' => $allowed_types_numeric
			),
		'prev'		=> array(
			'description'	=> 'Previous value {OP} N.',
			'operators'     => $operators,
			'allowed_types' => $allowed_types_any
			),
		'str'		=> array(
			'description'	=> 'Find string T last value. N {OP} X, where X is 1 - if found, 0 - otherwise',
			'operators'     => $limited_operators,
			'params'	=> $param1_str,
			'allowed_types' => $allowed_types_str
			),
		'strlen'		=> array(
			'description'	=> 'Find if string T length {OP} N',
			'operators'     => $operators,
			'params'	=> $param1_sec_count,
			'allowed_types' => $allowed_types_str
			),
		'sum'		=> array(
			'description'	=> 'Sum of values for period of time T {OP} N',
			'operators'     => $operators,
			'params'	=> $param1_sec_count,
			'allowed_types' => $allowed_types_numeric
			),
		'date' => array(
			'description' => 'Current date is {OP} N.',
			'operators' => $operators,
			'allowed_types' => $allowed_types_any
		),
		'dayofweek' => array(
			'description' => 'Day of week is {OP} N.',
			'operators' => $operators,
			'allowed_types' => $allowed_types_any
		),
		'dayofmonth' => array(
			'description' => 'Day of month is {OP} N.',
			'operators' => $operators,
			'allowed_types' => $allowed_types_any
		),
		'fuzzytime' => array(
			'description' => 'N {OP} X, where X is 1 - if timestamp is equal with Zabbix server time for T seconds, 0 - otherwise',
			'operators' => $limited_operators,
			'params' => $param1_sec_count_no_timeshift,
			'allowed_types' => $allowed_types_numeric
		),
		'regexp' => array(
			'description' => 'N {OP} X, where X is 1 - last value matches regular expression V for last T seconds, 0 - otherwise.',
			'operators' => $limited_operators,
			'params' => $param2_val_sec,
			'allowed_types' => $allowed_types_str
		),
		'iregexp' => array(
			'description' => 'N {OP} X, where X is 1 - last value matches regular expression V for last T seconds, 0 - otherwise. (non case-sensitive)',
			'operators' => $limited_operators,
			'params' => $param2_val_sec,
			'allowed_types' => $allowed_types_str
		),
		'logeventid' => array(
			'description' => 'N {OP} X, where X is 1 - last Event ID matches regular expression T, 0 - otherwise.',
			'operators' => $limited_operators,
			'params' => $param1_str,
			'allowed_types' => $allowed_types_log
		),
		'logseverity' => array(
			'description' => 'Log severity of the last log entry is {OP} N',
			'operators' => $operators,
			'allowed_types' => $allowed_types_log
		),
		'logsource' => array(
			'description' => 'N {OP} X, where X is 1 - last log source of the last log entry matches T, 0 - otherwise.',
			'operators' => $limited_operators,
			'params' => $param1_str,
			'allowed_types' => $allowed_types_log
		),
		'now' => array(
			'description' => 'Number of seconds since the Epoch is {OP} N.',
			'operators' => $operators,
			'allowed_types' => $allowed_types_any
		),
		'time' => array(
			'description' => 'Current time is {OP} N.',
			'operators' => $operators,
			'allowed_types' => $allowed_types_any
		),
		'nodata' => array(
			'description' => 'N {OP} X, where X is 1 - no data received during period of T seconds, 0 - otherwise',
			'operators' => $operators,
			'params' => $param1_sec,
			'allowed_types' => $allowed_types_any
		),
	);


//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'dstfrm'=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	null),
		'dstfld1'=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	null),

		'expression'=>	array(T_ZBX_STR, O_OPT, null,	null,		null),

		'itemid'=>	array(T_ZBX_INT, O_OPT,	null,	null,						'isset({insert})'),
		'expr_type'=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,					'isset({insert})'),
		'param'=>	array(T_ZBX_STR, O_OPT,	null,	0,						'isset({insert})'),
		'paramtype'=>	array(T_ZBX_INT, O_OPT, null,	IN(PARAM_TYPE_SECONDS.','.PARAM_TYPE_COUNTS),	'isset({insert})'),
		'value'=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,					'isset({insert})'),

		'insert'=>	array(T_ZBX_STR,	O_OPT,	P_SYS|P_ACT,	null,	null)
	);

	check_fields($fields);

	if(isset($_REQUEST['expression']) && $_REQUEST['dstfld1'] == 'expr_temp'){
		$_REQUEST['expression'] = utf8RawUrlDecode($_REQUEST['expression']);

		$trigExpr = new CTriggerExpression(array('expression' => $_REQUEST['expression']));

		if(empty($trigExpr->errors) && !empty($trigExpr->expressions)){
			preg_match('/\}([=><#]{1})([0-9]+)$/', $_REQUEST['expression'], $match);
			$exprSymbols = $match;
			$expr = reset($trigExpr->expressions);
			if(isset($expr['functionName']) && isset($exprSymbols[1])) $_REQUEST['expr_type'] = $expr['functionName'].'['.$exprSymbols[1].']';
			if(isset($expr['functionParamList'])){
				$_REQUEST['param'] = $expr['functionParamList'];
				$_REQUEST['paramtype'] = 0;
			}
			if(isset($exprSymbols[2])) $_REQUEST['value'] = $exprSymbols[2];
			if(isset($expr['host']) && isset($expr['item'])){
				$_REQUEST['description'] = $expr['host'] .':'. $expr['item'];
				$options = array(
						'filter' => array('host' => $expr['host'], 'key_' => $expr['item']),
						'output'=>API_OUTPUT_EXTEND,
						'webitems' => 1,
				);

				$myItem = CItem::get($options);
				$myItem = reset($myItem);
				if(isset($myItem['itemid'])) $_REQUEST['itemid'] = $myItem['itemid'];
			}
		}
	}

	$expr_type	= get_request('expr_type', 'last[=]');
	if(preg_match('/^([a-z]+)\[(['.implode('',array_keys($operators)).'])\]$/i', $expr_type, $expr_res)){
		$function = $expr_res[1];
		$operator = $expr_res[2];

		if(!isset($functions[$function])) unset($function);
	}

	$dstfrm = get_request('dstfrm', 0);
	$dstfld1 = get_request('dstfld1', '');
	$itemid = get_request('itemid', 0);
	$value = get_request('value', 0);
	$param = get_request('param', 0);
	$paramtype = get_request('paramtype');

	if(!isset($function)) $function = 'last';
	if(!isset($functions[$function]['operators'][$operator])) $operator = '=';

	$expr_type = $function.'['.$operator.']';

	if($itemid){
		$item_data = CItem::get(array(
			'output' => API_OUTPUT_EXTEND,
			'itemids' => $itemid,
			'nodeids' => id2nodeid($itemid),
			'webitems' => 1,
			'select_hosts' => API_OUTPUT_EXTEND
		));
		$item_data = reset($item_data);
		$item_key = $item_data['key_'];

		$item_host = reset($item_data['hosts']);
		$item_host = $item_host['host'];

		$description = $item_host.':'.item_description($item_data);
	}
	else{
		$item_key = $item_host = $description = '';
	}


	if(is_null($paramtype) && isset($functions[$function]['params']['M'])){
		$paramtype = is_array($functions[$function]['params']['M'])
				? reset($functions[$function]['params']['M'])
				: $functions[$function]['params']['M'];
	}
	else if(is_null($paramtype)){
		$paramtype = PARAM_TYPE_SECONDS;
	}

	if(!is_array($param)){
		if(isset($functions[$function]['params'])){
			$param = explode(',', $param, count($functions[$function]['params']));
		}
		else{
			$param = array($param);
		}
	}

?>
<script language="JavaScript" type="text/javascript">
<!--
function add_var_to_opener_obj(obj,name,value){
        new_variable = window.opener.document.createElement('input');
        new_variable.type = 'hidden';
        new_variable.name = name;
        new_variable.value = value;

        obj.appendChild(new_variable);
}

function InsertText(obj, value){
    <?php if ($dstfld1 == 'expression') { ?>
	if(IE){
		obj.focus();
		var s = window.opener.document.selection.createRange();
		s.text = value;
	}
	else if (obj.selectionStart || obj.selectionStart == '0') {
		var s = obj.selectionStart;
		var e = obj.selectionEnd;
		obj.value = obj.value.substring(0, s) + value + obj.value.substring(e, obj.value.length);
	}
	else {
		obj.value += value;
	}
    <?php } else { ?>
	obj.value = value;
	<?php } ?>
}
-->
</script>
<?php

	if(isset($_REQUEST['insert'])){
		$expression = sprintf('{%s:%s.%s(%s%s)}%s%s',
			$item_host,
			$item_key,
			$function,
			$paramtype == PARAM_TYPE_COUNTS ? '#' : '',
			rtrim(implode(',', $param),','),
			$operator,
			$value
		);
?>

<script language="JavaScript" type="text/javascript">
<!--
var form = window.opener.document.forms['<?php echo $dstfrm; ?>'];

if(form){
	var el = form.elements['<?php echo $dstfld1; ?>'];

	if(el){
		InsertText(el, <?php echo zbx_jsvalue($expression); ?>);
		close_window();
	}
}
-->
</script>
<?php
	}

	echo SBR;

	$form = new CFormTable(S_CONDITION);
	$form->SetName('expression');
	$form->addVar('dstfrm', $dstfrm);
	$form->addVar('dstfld1', $dstfld1);
	$form->addVar('itemid',$itemid);

	$form->addRow(S_ITEM, array(
		new CTextBox('description', $description, 50, 'yes'),
		new CButton('select', S_SELECT, "return PopUp('popup.php?dstfrm=".$form->GetName().
				"&dstfld1=itemid&dstfld2=description&submitParent=1&".
				"srctbl=items&srcfld1=itemid&srcfld2=description',0,0,'zbx_popup_item');")
		));

	$cmbFnc = new CComboBox('expr_type', $expr_type	, 'submit()');
	$cmbFnc->addStyle('width: auto;');


	//If user has already selected an item
	$selectedItemId = get_request('itemid');
	if ($selectedItemId) {
		//getting type of return value for the item user selected
		$selectedItems = CItem::get(array(
			'itemids' => array($selectedItemId),
			'nodeids' => id2nodeid($selectedItemId),
			'output' => API_OUTPUT_EXTEND
		));
		if($selectedItem = reset($selectedItems)) {
			$itemValueType = $selectedItem['value_type'];
		}
	}

	foreach($functions as  $id => $f){
		foreach($f['operators'] as $op => $txt_op){
			//if user has selected an item, we are filtering out the triggers that can't work with it
			if(!isset($itemValueType) || isset($f['allowed_types'][$itemValueType])){
				$cmbFnc->addItem($id.'['.$op.']', str_replace('{OP}', $txt_op, $f['description']));
			}
		}
	}
	$form->addRow(S_FUNCTION, $cmbFnc);

	if(isset($functions[$function]['params'])){
		foreach($functions[$function]['params'] as $pid => $pf ){
			$pv = (isset($param[$pid])) ? $param[$pid] : null;

			if($pf['T'] == T_ZBX_INT){
				if(0 == $pid){
					if(isset($pf['M'])){
						if(is_array($pf['M'])){
							$cmbParamType = new CComboBox('paramtype', $paramtype);
							foreach( $pf['M'] as $mid => $caption ){
								$cmbParamType->addItem($mid, $caption);
							}
						}
						else if($pf['M'] == PARAM_TYPE_SECONDS){
							$form->addVar('paramtype', PARAM_TYPE_SECONDS);
							$cmbParamType = S_SECONDS;
						}
						else if($pf['M'] == PARAM_TYPE_COUNTS){
							$form->addVar('paramtype', PARAM_TYPE_COUNTS);
							$cmbParamType = S_COUNT;
						}
					}
					else{
						$form->addVar('paramtype', PARAM_TYPE_SECONDS);
						$cmbParamType = S_SECONDS;
					}
				}
				else if(1 == $pid){
					$cmbParamType = S_SECONDS;
				}
				else{
					$cmbParamType = null;
				}


				$form->addRow($pf['C'].' ', array(
					new CNumericBox('param['.$pid.']', $pv, 10),
					$cmbParamType
				));
			}
			else{
				$form->addRow($pf['C'], new CTextBox('param['.$pid.']', $pv, 30));
				$form->addVar('paramtype', PARAM_TYPE_SECONDS);
			}
		}
	}
	else{
		$form->addVar('paramtype', PARAM_TYPE_SECONDS);
		$form->addVar('param', 0);
	}

	$form->addRow('N', new CTextBox('value', $value, 10));

	$form->addItemToBottomRow(new CButton('insert',S_INSERT));
	$form->show();


include_once('include/page_footer.php');
?>
