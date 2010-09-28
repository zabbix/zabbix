<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	require_once "include/config.inc.php";
	require_once "include/users.inc.php";

	$page["title"] = "S_CONDITION";
	$page["file"] = "popup_trexpr.php";

	define('ZBX_PAGE_NO_MENU', 1);
    define('ITEM_VALUE_TYPE_ANY', -99);
	
include_once "include/page_header.php";

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

    $param1_sec = array(
		    array(
				'C' => S_LAST_OF.' T',  /* caption */
				'T' => T_ZBX_INT
				));

	$param1_sec_count = array(
			array(
				'C' => S_LAST_OF.' T',	/* caption */
				'T' => T_ZBX_INT,	/* type */
				'M' => $metrics		/* metrcis */
			     ));
	
	$param1_str = array(
			array(
				'C' => 'V',		/* caption */
				'T' => T_ZBX_STR,
			     ));

    $param1_regexp = array(
		    array(
				'C' => 'R',     /* caption */
				'T' => T_ZBX_STR,
				));

	$param2_sec_val = array(
			array(
				'C' => S_LAST_OF.' T',	/* caption */
				'T' => T_ZBX_INT,
			     ),
			array(
				'C' => 'V',		/* caption */
				'T' => T_ZBX_STR,
			     ));

	$functions = array(
		'abschange'	=> array(
			'description'	=> S_FNC_DESCRIPTION_ABSCHANGE,
			'value_types'   => array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT),
			'operators'	=> $operators
			),
		'avg'		=> array(
			'description'	=> S_FNC_DESCRIPTION_AVG,
			'value_types'   => array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64),
			'operators'	=> $operators,
			'params'	=> $param1_sec_count
			),
		'delta'		=> array(
			'description'	=> S_FNC_DESCRIPTION_DELTA,
            'value_types'   => array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64),
			'operators'	=> $operators,
			'params'	=> $param1_sec_count
			),
		'change'	=> array(
			'description'	=> S_FNC_DESCRIPTION_CHANGE,
            'value_types'   => array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT),
			'operators'	=> $operators
			),
		'count'		=> array(
			'description'	=> S_FNC_DESCRIPTION_COUNT,
            'value_types'   => array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64),
			'operators'     => $operators,
			'params'	=> $param2_sec_val
			),
		'diff'		=> array(
			'description'	=> S_FNC_DESCRIPTION_DIFF,
            'value_types'   => array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT),
			'operators'     => $limited_operators
			),
		'last'	=> array(
			'description'	=> S_FNC_DESCRIPTION_LAST,
            'value_types'   => array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64),
			'operators'	=> $operators
			),
		'max'		=> array(
			'description'	=> S_FNC_DESCRIPTION_MAX,
            'value_types'   => array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64),
			'operators'     => $operators,
			'params'	=> $param1_sec_count
			),
		'min'		=> array(
			'description'	=> S_FNC_DESCRIPTION_MIN,
            'value_types'   => array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64),
			'operators'     => $operators,
			'params'	=> $param1_sec_count
			),
		'prev'		=> array(
			'description'	=> S_FNC_DESCRIPTION_PREV,
            'value_types'   => array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64),
			'operators'     => $operators
			),
		'str'		=> array(
			'description'	=> S_FNC_DESCRIPTION_STR,
            'value_types'   => array(ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG),
			'operators'     => $limited_operators,
			'params'	=> $param1_str
			),
		'sum'		=> array(
			'description'	=> S_FNC_DESCRIPTION_SUM,
            'value_types'   => array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64),
			'operators'     => $operators,
			'params'	=> $param1_sec_count
			),
        'date'  => array(
            'description'   => S_FNC_DESCRIPTION_DATE,
            'value_types'   => array(ITEM_VALUE_TYPE_ANY),
            'operators' => $operators
            ),
        'dayofweek' => array(
            'description'   => S_FNC_DESCRIPTION_DAYOFWEEK,
            'value_types'   => array(ITEM_VALUE_TYPE_ANY),
            'operators' => $operators
            ),
        'fuzzytime' => array(
            'description'   => S_FNC_DESCRIPTION_FUZZYTIME,
            'value_types'   => array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64),
            'operators' => $limited_operators,
            'params'    => $param1_sec
            ),
        'iregexp'   => array(
            'description'   => S_FNC_DESCRIPTION_IREGEXP,
            'value_types'   => array(ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT),
            'operators' => $limited_operators,
            'params'    => $param1_regexp
            ),
        'logseverity'  => array(
            'description'   => S_FNC_DESCRIPTION_LOGSEVERITY,
            'value_types'   => array(ITEM_VALUE_TYPE_LOG),
            'operators' => $operators
            ),
        'logsource' => array(
            'description'   => S_FNC_DESCRIPTION_LOGSOURCE,
            'value_types'   => array(ITEM_VALUE_TYPE_LOG),
            'operators' => $limited_operators,
            'params'    => $param1_str
            ),
        'nodata'    => array(
            'description'   => S_FNC_DESCRIPTION_NODATA,
            'value_types'   => array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT),
            'operators' => $limited_operators,
            'params'    => $param1_sec
            ),
        'now'   => array(
            'description'   => S_FNC_DESCRIPTION_NOW,
            'value_types'   => array(ITEM_VALUE_TYPE_ANY),
            'operators' => $operators
            ),
        'regexp'    => array(
            'description'   => S_FNC_DESCRIPTION_REGEXP,
            'value_types'   => array(ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT),
            'operators' => $limited_operators,
            'params'    => $param1_regexp
            ),
        'time'  => array(
            'description'   => S_FNC_DESCRIPTION_TIME,
            'value_types'   => array(ITEM_VALUE_TYPE_ANY),
            'operators' => $operators
            )

	);
    $special_functions = array();
    foreach ($functions as  $id => $f) if (in_array(ITEM_VALUE_TYPE_ANY, $f['value_types'])) array_push($special_functions, $id);	
		

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"dstfrm"=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	null),
		"dstfld1"=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	null),
		
		"expression"=>	array(T_ZBX_STR, O_OPT, null,	null,		null),

		"itemid"=>	array(T_ZBX_INT, O_OPT,	null,	null,						'isset({insert})'),
		"expr_type"=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,					'isset({insert})'),
		"param"=>	array(T_ZBX_STR, O_OPT,	null,	0,						'isset({insert})'),
		"paramtype"=>	array(T_ZBX_INT, O_OPT, null,	IN(PARAM_TYPE_SECONDS.','.PARAM_TYPE_COUNTS),	'isset({insert})'),
		"value"=>	array(T_ZBX_STR, O_OPT,	null,	'mb_ereg("^'.ZBX_EREG_NUMBER.'$",{})',		'isset({insert})'),

		"insert"=>	array(T_ZBX_STR,	O_OPT,	P_SYS|P_ACT,	null,	null)
	);

	check_fields($fields);

	if(isset($_REQUEST['expression']))
	{

		if( ($res = mb_ereg(
			'^'.ZBX_EREG_SIMPLE_EXPRESSION_FORMAT.'(['.implode('',array_keys($operators)).'])'.'([[:print:]]{1,})',
			$_REQUEST['expression'],
			$expr_res))
		)
		{
			$itemid = DBfetch(DBselect('select i.itemid from items i, hosts h '.
					' where i.hostid=h.hostid and h.host='.zbx_dbstr($expr_res[ZBX_SIMPLE_EXPRESSION_HOST_ID]).
					' and i.key_='.zbx_dbstr($expr_res[ZBX_SIMPLE_EXPRESSION_KEY_ID])));

			$_REQUEST['itemid'] = $itemid['itemid'];
			
			$_REQUEST['paramtype'] = PARAM_TYPE_SECONDS;
			$_REQUEST['param'] = $expr_res[ZBX_SIMPLE_EXPRESSION_FUNCTION_PARAM_ID];
			if($_REQUEST['param'][0] == '#')
			{
				$_REQUEST['paramtype'] = PARAM_TYPE_COUNTS;
				$_REQUEST['param'] = ltrim($_REQUEST['param'],'#');
			}
				
			$operator = $expr_res[count($expr_res) - 2];
			
			$_REQUEST['expr_type'] = $expr_res[ZBX_SIMPLE_EXPRESSION_FUNCTION_NAME_ID].'['.$operator.']';
				
			
			$_REQUEST['value'] = $expr_res[count($expr_res) - 1];
			
		}
	}
	unset($expr_res);

	$dstfrm		= get_request("dstfrm",		0);	// destination form
	$dstfld1	= get_request("dstfld1",	'');	// destination field
	$itemid		= get_request("itemid",		0);

	$denyed_hosts	= get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_MODE_LT);
	
	if($item_data = DBfetch(DBselect("select distinct h.host,i.* from hosts h,items i ".
		" where h.hostid=i.hostid and h.hostid not in (".$denyed_hosts.")".
		" and i.itemid=".$itemid)))
	{
		$description = $item_data['host'].':'.item_description($item_data["description"],$item_data["key_"]);
        $item_key = $item_data["key_"];
        $value_type = $item_data['value_type'];
	}
	else
	{
		$itemid = 0;
		$description = '';
        $item_key = '';
        $value_type = -1;
	}

    $expr_type  = get_request("expr_type",  $value_type == ITEM_VALUE_TYPE_LOG ? 'regexp[=]' : 'last[=]');
	if(eregi('^([a-z]{1,})\[(['.implode('',array_keys($operators)).'])\]$',$expr_type,$expr_res))
	{
		$function = $expr_res[1];
		$operator = $expr_res[2];

		if(!in_array($function, array_keys($functions)))	unset($function);
	}
	unset($expr_res);

    if(!isset($function))   $function = $value_type == ITEM_VALUE_TYPE_LOG ? 'regexp' : 'last';
		
	if(!in_array($operator, array_keys($functions[$function]['operators'])))	unset($operator);
	if(!isset($operator))	$operator = '=';
	
	$expr_type = $function.'['.$operator.']';
	
	$param		= get_request('param',	0);
	$paramtype	= get_request('paramtype',	PARAM_TYPE_SECONDS);
	$value		= get_request('value',		0);

	if( !is_array($param) )
	{
		if( isset($functions[$function]['params']) )
		{
			$param = split(',', $param, count($functions[$function]['params']));
		}
		else
		{
			$param = array($param);
		}
	}

?>
<script language="JavaScript" type="text/javascript">
<!--
function add_var_to_opener_obj(obj,name,value)
{
        new_variable = window.opener.document.createElement('input');
        new_variable.type = 'hidden';
        new_variable.name = name;
        new_variable.value = value;

        obj.appendChild(new_variable);
}

function InsertText(obj, value)
{
    <?php if ($dstfld1 == 'expression') { ?>
	if (navigator.appName == "Microsoft Internet Explorer") {
		obj.focus();
		var s = window.opener.document.selection.createRange();
		s.text = value;
	} else if (obj.selectionStart || obj.selectionStart == '0') {
		var s = obj.selectionStart;
		var e = obj.selectionEnd;
		obj.value = obj.value.substring(0, s) + value + obj.value.substring(e, obj.value.length);
	} else {
		obj.value += value;
	}
    <?php } else { ?>
	obj.value = value;
	<?php } ?>
}
-->
</script>
<?php

	if(isset($_REQUEST['insert']))
	{

		$expression = sprintf("{%s:%s.%s(%s%s)}%s%s", 
			$item_data['host'],
			$item_data['key_'],
			$function,
			$paramtype == PARAM_TYPE_COUNTS ? '#' : '',
			rtrim(implode(',', $param),','),
			$operator,
			$value);

?>
<script language="JavaScript" type="text/javascript">
<!--
var form = window.opener.document.forms['<?php echo $dstfrm; ?>'];

if(form)
{
	var el = form.elements['<?php echo $dstfld1; ?>'];

	if(el)
	{
		InsertText(el, <?php echo zbx_jsvalue($expression); ?>);
		close_window();
	}
}
-->
</script>
<?php
	}

	echo BR;

	$form = new CFormTable(S_CONDITION);
	$form->SetHelp('config_triggers.php');
	$form->SetName('expression');
	$form->AddVar('dstfrm', $dstfrm);
	$form->AddVar('dstfld1', $dstfld1);

	$form->AddVar('itemid',$itemid);
	$form->AddRow(S_ITEM, array(
		new CTextBox('description', $description, 50, 'yes'),
		new CButton('select', S_SELECT, "return PopUp('popup.php?dstfrm=".$form->GetName().
				"&dstfld1=itemid&dstfld2=description&".
                "srctbl=items&srcfld1=itemid&srcfld2=description&".
				"dstsubmit=1',0,0,'zbx_popup_item');"),
        new CButton('clear', S_CLEAR, "document.getElementsByName('description')[0].value = ''; document.getElementsByName('itemid')[0].value = 0;")
		));

	$cmbFnc = new CComboBox('expr_type', $expr_type	, 'submit()');
	foreach($functions as  $id => $f)
	{
        if (!in_array($value_type, $f['value_types']) && !(in_array($id, $special_functions) && $item_key == 'status'))
        {
            if ($id == substr($expr_type, 0, strlen($id)))
            {
                $expr_type = 'last[=]';
                $cmbFnc->SetValue($expr_type);
                $function = 'last';
            }
            continue;
        }
		foreach($f['operators'] as $op => $txt_op)
		{
			$cmbFnc->AddItem($id.'['.$op.']', str_replace('{OP}', $txt_op, $f['description']));
		}
	}
	$form->AddRow(S_FUNCTION, $cmbFnc);

	if(isset($functions[$function]['params']))
	{
		foreach($functions[$function]['params'] as $pid => $pf )
		{
			$pv = (isset($param[$pid])) ? $param[$pid] : null;

			if($pf['T'] == T_ZBX_INT)
			{
				if( 0 == $pid) 
				{
					if( isset($pf['M']) && is_array($pf['M']))
					{
						$cmbParamType = new CComboBox('paramtype', $paramtype);
						foreach( $pf['M'] as $mid => $caption )
						{
							$cmbParamType->AddItem($mid, $caption);
						}
					} else {
						$form->AddVar('paramtype', PARAM_TYPE_SECONDS);
						$cmbParamType = S_SECONDS;
					}
				}
				else
				{
					$cmbParamType = null;
				}
					
				
				$form->AddRow(S_LAST_OF.' ', array(
					new CNumericBox('param['.$pid.']', $pv, 10),
					$cmbParamType
					)); 
			}
			else
			{
				$form->AddRow($pf['C'], new CTextBox('param['.$pid.']', $pv, 30));
				$form->AddVar('paramtype', PARAM_TYPE_SECONDS);
			}
		}
	}
	else
	{
		$form->AddVar('paramtype', PARAM_TYPE_SECONDS);
		$form->AddVar('param', 0);
	}

	$form->AddRow('N', new CTextBox('value', $value, 10));
	
	$form->AddItemToBottomRow(new CButton('insert',S_INSERT));
	$form->Show();
?>
<?php

include_once "include/page_footer.php";

?>
