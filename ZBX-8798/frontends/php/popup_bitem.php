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
	require_once 'include/config.inc.php';
	require_once 'include/triggers.inc.php';
	require_once 'include/js.inc.php';

	$dstfrm		= get_request('dstfrm',		0);	// destination form

	$page['title'] = "S_GRAPH_ITEM";
	$page['file'] = 'popup_bitem.php';

	define('ZBX_PAGE_NO_MENU', 1);

include_once 'include/page_header.php';

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'dstfrm'=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		null),

		'config'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1,2,3'),		NULL),
		'gid'=>			array(T_ZBX_INT, O_OPT,  P_SYS,	BETWEEN(0,65535),	null),

		'list_name'=>	array(T_ZBX_STR, O_OPT,  P_SYS,	NOT_EMPTY,			'isset({save})&&isset({gid})'),
		'caption'=>		array(T_ZBX_STR, O_OPT,  null,	null,			null),
		'itemid'=>		array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID.NOT_ZERO,	'isset({save})'),
		'color'=>		array(T_ZBX_CLR, O_OPT,  null,	null,				'isset({save})'),
		'calc_fnc'=>	array(T_ZBX_INT, O_OPT,	 null,	IN('0,1,2,4,7,9'),	'isset({save})'),
		'axisside'=>	array(T_ZBX_INT, O_OPT,	 null,	IN(GRAPH_YAXIS_SIDE_LEFT.','.GRAPH_YAXIS_SIDE_RIGHT),	null),

/* actions */
		'add'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
/* other */
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>array(T_ZBX_STR, O_OPT, null,	null,	null)
	);

	check_fields($fields);

	$_REQUEST['caption'] = get_request('caption','');
	$_REQUEST['axisside'] = get_request('axisside',	GRAPH_YAXIS_SIDE_LEFT);
	if(zbx_empty($_REQUEST['caption']) && isset($_REQUEST['itemid']) && ($_REQUEST['itemid'] > 0)){
		$_REQUEST['caption'] = item_description(get_item_by_itemid($_REQUEST['itemid']));
	}

	insert_js_function('add_bitem');
	insert_js_function('update_bitem');

	if(isset($_REQUEST['save']) && !isset($_REQUEST['gid'])){
		insert_js("add_bitem(".
			zbx_jsvalue($_REQUEST['dstfrm']).",".
			zbx_jsvalue($_REQUEST['caption']).",'".
			$_REQUEST['itemid']."','".
			$_REQUEST['color']."',".
			$_REQUEST['calc_fnc'].",".
			$_REQUEST['axisside'].");\n");
	}

	if(isset($_REQUEST['save']) && isset($_REQUEST['gid'])){
		insert_js("update_bitem(".
			zbx_jsvalue($_REQUEST['dstfrm']).",".
			zbx_jsvalue($_REQUEST['list_name']).",'".
			$_REQUEST['gid']."',".
			zbx_jsvalue($_REQUEST['caption']).",'".
			$_REQUEST['itemid']."','".
			$_REQUEST['color']."',".
			$_REQUEST['calc_fnc'].",".
			$_REQUEST['axisside'].");\n");
	}
	else{
		echo SBR;

		$frmGItem = new CFormTable(S_NEW_ITEM_FOR_THE_GRAPH);
		$frmGItem->setName('graph_item');
		$frmGItem->setHelp('web.graph.item.php');

		$frmGItem->addVar('dstfrm',$_REQUEST['dstfrm']);

		$config		= get_request('config',	 	1);
		$gid		= get_request('gid',	 	null);
		$list_name	= get_request('list_name', 	null);
		$caption	= get_request('caption', 	'');
		$itemid		= get_request('itemid', 	0);
		$color		= get_request('color', 		'009900');
		$calc_fnc	= get_request('calc_fnc',	2);
		$axisside	= get_request('axisside',	GRAPH_YAXIS_SIDE_LEFT);

		$frmGItem->addVar('gid',$gid);
		$frmGItem->addVar('config',$config);
		$frmGItem->addVar('list_name',$list_name);
		$frmGItem->addVar('itemid',$itemid);

		$frmGItem->addRow(array( new CVisibilityBox('caption_visible', !zbx_empty($caption), 'caption', S_DEFAULT),
			S_CAPTION), new CTextBox('caption',$caption,32));

		$txtCondVal = new CTextBox('description',$caption,50,'yes');

		$btnSelect = new CButton('btn1',S_SELECT,
				"return PopUp('popup.php?dstfrm=".$frmGItem->GetName().
				'&dstfld1=itemid&dstfld2=description&srctbl=items'.
				"&srcfld1=itemid&srcfld2=description&monitored_hosts=1');",
				'T');

		$frmGItem->addRow(S_PARAMETER ,array($txtCondVal,$btnSelect));

		$cmbFnc = new CComboBox('calc_fnc',$calc_fnc);
			$cmbFnc->addItem(CALC_FNC_MIN, S_MIN_SMALL);
			$cmbFnc->addItem(CALC_FNC_AVG, S_AVG_SMALL);
			$cmbFnc->addItem(CALC_FNC_MAX, S_MAX_SMALL);
			$cmbFnc->addItem(0, S_COUNT);

		$frmGItem->addRow(S_FUNCTION, $cmbFnc);

		if($config == 1){
			$cmbAxis = new CComboBox('axisside',$axisside);
				$cmbAxis->addItem(GRAPH_YAXIS_SIDE_LEFT, S_LEFT);
				$cmbAxis->addItem(GRAPH_YAXIS_SIDE_RIGHT, S_RIGHT);

			$frmGItem->addRow(S_AXIS_SIDE, $cmbAxis);
		}


		if($config == 1)
			$frmGItem->addRow(S_COLOR, new CColor('color',$color));
		else
			$frmGItem->addVar('color',$color);


		$frmGItem->addItemToBottomRow(new CButton('save', isset($gid)?S_SAVE:S_ADD));

		$frmGItem->addItemToBottomRow(new CButtonCancel(null,'close_window();'));
		$frmGItem->Show();
	}
?>
<?php

include_once 'include/page_footer.php';

?>
