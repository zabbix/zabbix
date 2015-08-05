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

include dirname(__FILE__).'/js/common.macros.js.php';

$macrosTable = new CTable(SPACE, 'formElementTable');
$macrosTable->setAttribute('id', 'tbl_macros');
$macrosTable->addRow(array(_('Macro'), SPACE, _('Value'), SPACE));

// fields
$macros = array_values($this->data['macros']);
foreach ($macros as $macroid => $macro) {
	$text1 = new CTextBox('macros['.$macroid.'][macro]', $macro['macro'], 30, 'no', 64);
	$text1->setAttribute('placeholder', '{$MACRO}');
	$text1->setAttribute('style', 'text-transform:uppercase;');
	$text2 = new CTextBox('macros['.$macroid.'][value]', $macro['value'], 40, 'no', 255);
	$text2->setAttribute('placeholder', _('value'));
	$span = new CSpan(RARR);
	$span->addStyle('vertical-align:top;');

	$deleteButtonCell = array(new CButton('macros_'.$macroid.'_remove', _('Remove'), null, 'link_menu macroRemove'));
	if (isset($macro['globalmacroid'])) {
		$deleteButtonCell[] = new CVar('macros['.$macroid.'][globalmacroid]', $macro['globalmacroid'], 'macros_'.$macroid.'_id');
	}
	if (isset($macro['hostmacroid'])) {
		$deleteButtonCell[] = new CVar('macros['.$macroid.'][hostmacroid]', $macro['hostmacroid'], 'macros_'.$macroid.'_id');
	}

	$row = array($text1, $span, $text2, $deleteButtonCell);
	$macrosTable->addRow($row, 'form_row');
}

// buttons
$addButton = new CButton('macro_add', _('Add'), null, 'link_menu');

$buttonColumn = new CCol($addButton);
$buttonColumn->setAttribute('colspan', 5);

$buttonRow = new CRow();
$buttonRow->setAttribute('id', 'row_new_macro');
$buttonRow->addItem($buttonColumn);

$macrosTable->addRow($buttonRow);

// form list
$macrosFormList = new CFormList('macrosFormList');
$macrosFormList->addRow($macrosTable);

return $macrosFormList;
