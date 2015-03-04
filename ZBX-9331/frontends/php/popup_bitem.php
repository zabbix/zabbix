<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/js.inc.php';

$dstfrm = getRequest('dstfrm', 0);

$page['title'] = _('Graph item');
$page['file'] = 'popup_bitem.php';

define('ZBX_PAGE_NO_MENU', 1);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'dstfrm' =>			array(T_ZBX_STR, O_MAND, P_SYS,	NOT_EMPTY,			null),
	'config' =>			array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0,1,2,3'),		null),
	'gid' =>			array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID.'({} != 0)',	null),
	'list_name' =>		array(T_ZBX_STR, O_OPT,	 P_SYS,	NOT_EMPTY,			'(isset({add}) || isset({update})) && isset({gid})'),
	'caption' =>		array(T_ZBX_STR, O_OPT,	 null,	null,				null),
	'itemid' =>			array(T_ZBX_INT, O_OPT,	 P_SYS, DB_ID.'({} != 0)', 'isset({add}) || isset({update})', _('Parameter')),
	'color' =>			array(T_ZBX_CLR, O_OPT,	 null,	null, 'isset({add}) || isset({update})', _('Colour')),
	'calc_fnc' =>		array(T_ZBX_INT, O_OPT,	 null,	IN('0,1,2,4,7,9'),	'isset({add}) || isset({update})'),
	'axisside' =>		array(T_ZBX_INT, O_OPT,	 null,	IN(GRAPH_YAXIS_SIDE_LEFT.','.GRAPH_YAXIS_SIDE_RIGHT), null),
	// actions
	'add' =>			array(T_ZBX_STR, O_OPT,	 P_SYS|P_ACT,	null,	null),
	'update' =>			array(T_ZBX_STR, O_OPT,	 P_SYS|P_ACT,	null,	null),
	// other
	'form' =>			array(T_ZBX_STR, O_OPT,	 P_SYS,	null,	null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT,	 null,	null,	null),
	'host' =>			array(T_ZBX_STR, O_OPT,	 null,	null,	null),
	'name' =>			array(T_ZBX_STR, O_OPT,	 null,	null,	null),
	'name_expanded' =>	array(T_ZBX_STR, O_OPT,	 null,	null,	null)
);
check_fields($fields);

$caption = getRequest('caption', '');
$autoCaption = '';
$_REQUEST['axisside'] = getRequest('axisside',	GRAPH_YAXIS_SIDE_LEFT);

if (getRequest('itemid') > 0) {
	$items = CMacrosResolverHelper::resolveItemNames(array(get_item_by_itemid(getRequest('itemid'))));
	$item = reset($items);

	$autoCaption = $item['name_expanded'];

	if (!hasRequest('caption') || getRequest('caption') === $item['name']) {
		$caption = $item['name_expanded'];
	}
}

insert_js_function('add_bitem');
insert_js_function('update_bitem');

if (hasRequest('add') && !hasRequest('gid')) {
	insert_js("add_bitem(".
		zbx_jsvalue(getRequest('dstfrm')).",".
		zbx_jsvalue($caption).",'".
		getRequest('itemid')."','".
		getRequest('color')."',".
		getRequest('calc_fnc').",".
		getRequest('axisside').");\n"
	);
}

if (hasRequest('update') && hasRequest('gid')) {
	insert_js("update_bitem(".
		zbx_jsvalue(getRequest('dstfrm')).",".
		zbx_jsvalue(getRequest('list_name')).",'".
		getRequest('gid')."',".
		zbx_jsvalue($caption).",'".
		getRequest('itemid')."','".
		getRequest('color')."',".
		getRequest('calc_fnc').",".
		getRequest('axisside').");\n"
	);
}
else {
	echo BR();

	$frmGItem = new CFormTable(_('New item for the graph'));
	$frmGItem->setName('graph_item');
	$frmGItem->addVar('dstfrm', $_REQUEST['dstfrm']);

	$config	= getRequest('config', 1);
	$gid = getRequest('gid');
	$list_name = getRequest('list_name');
	$itemid = getRequest('itemid', 0);
	$color = getRequest('color', '009900');
	$calc_fnc = getRequest('calc_fnc', 2);
	$axisside = getRequest('axisside', GRAPH_YAXIS_SIDE_LEFT);

	$frmGItem->addVar('gid', $gid);
	$frmGItem->addVar('config', $config);
	$frmGItem->addVar('list_name', $list_name);
	$frmGItem->addVar('itemid', $itemid);
	$frmGItem->addRow(
		array(
			new CVisibilityBox('caption_visible', hasRequest('caption') && $caption != $autoCaption, 'caption',
				_('Default')
			),
			_('Caption')
		),
		new CTextBox('caption', $caption, 50)
	);

	$host = getRequest('host');
	$itemName = getRequest('name_expanded');
	if ($host && $itemName) {
		$caption = $host['name'].NAME_DELIMITER.$itemName;
	}

	$txtCondVal = new CTextBox('name', $caption, 50, true);

	$btnSelect = new CButton('btn1', _('Select'),
		'return PopUp("popup.php?'.
			'dstfrm='.$frmGItem->GetName().
			'&dstfld1=itemid'.
			'&dstfld2=name'.
			'&srctbl=items'.
			'&srcfld1=itemid'.
			'&srcfld2=name'.
			'&monitored_hosts=1'.
			'&numeric=1");',
		'button-plain'
	);

	$frmGItem->addRow(_('Parameter'), array($txtCondVal, $btnSelect));

	$cmbFnc = new CComboBox('calc_fnc', $calc_fnc);
	$cmbFnc->addItem(CALC_FNC_MIN, _('min'));
	$cmbFnc->addItem(CALC_FNC_AVG, _('avg'));
	$cmbFnc->addItem(CALC_FNC_MAX, _('max'));
	$cmbFnc->addItem(0, _('Count'));

	$frmGItem->addRow(_('Function'), $cmbFnc);

	if ($config == 1) {
		$cmbAxis = new CComboBox('axisside', $axisside);
		$cmbAxis->addItem(GRAPH_YAXIS_SIDE_LEFT, _('Left'));
		$cmbAxis->addItem(GRAPH_YAXIS_SIDE_RIGHT, _('Right'));

		$frmGItem->addRow(_('Axis side'), $cmbAxis);
	}

	if ($config == 1) {
		$frmGItem->addRow(_('Colour'), new CColor('color', $color));
	}
	else {
		$frmGItem->addVar('color', $color);
	}

	if (isset($gid)) {
		$frmGItem->addItemToBottomRow(new CSubmit('update', _('Update')));
	} else {
		$frmGItem->addItemToBottomRow(new CSubmit('add', _('Add')));
	}

	$frmGItem->addItemToBottomRow(new CButtonCancel(null, 'close_window();'));
	$frmGItem->Show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
