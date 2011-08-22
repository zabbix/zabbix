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
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
include('include/views/js/administration.general.macros.js.php');

$macrosForm = new CForm();
$macrosForm->setName('macrosForm');
$macrosForm->addVar('form', $this->data['form']);
$macrosForm->addVar('form_refresh', $this->data['form_refresh'] + 1);
$macrosForm->addVar('config', get_request('config', 11));

$macrosTable = new CTable(SPACE, 'macrosTable');
$macrosTable->setAttribute('id', 'tbl_macros');
$macrosTable->addRow(array(SPACE, _('Macro'), SPACE, _('Value')));

// fields
$macros = array_values($this->data['macros']);
foreach ($macros as $macroid => $macro) {
	$text1 = new CTextBox('macros['.$macroid.'][macro]', $macro['macro'], 30);
	$text1->setAttribute('placeholder', '{$MACRO}');
	$text2 = new CTextBox('macros['.$macroid.'][value]', $macro['value'], 40);
	$text2->setAttribute('placeholder', '<'._('Value').'>');
	$span = new CSpan(RARR);
	$span->addStyle('vertical-align:top;');

	$macrosTable->addRow(array(new CCheckBox(), $text1, $span, $text2));
}

// buttons
$deleteButton = new CButton('macros_del', _('Delete selected'), '$$("#tbl_macros input:checked").each(function(obj){ $(obj.parentNode.parentNode).remove(); if (typeof(deleted_macro_cnt) == \'undefined\') deleted_macro_cnt=1; else deleted_macro_cnt++; });');
$addButton = new CButton('macro_add', _('Add'), 'javascript: addMacroRow()');

$buttonColumn = new CCol(array($addButton, SPACE, $deleteButton));
$buttonColumn->setAttribute('colspan', 4);

$buttonRow = new CRow();
$buttonRow->setAttribute('id', 'row_new_macro');
$buttonRow->addItem($buttonColumn);

$macrosTable->addRow($buttonRow);

// tab
$macrosTab = new CTabView();
$macrosTab->addTab('macros', _('Macros'), $macrosTable);

$macrosForm->addItem($macrosTab);
$macrosForm->addItem(makeFormFooter(array(new CSubmit('save', _('Save'), "if (deleted_macro_cnt > 0) return confirm('"._('Are you sure you want to delete')." '+deleted_macro_cnt+' "._('macro(s)')."?');"))));

return $macrosForm;
?>
