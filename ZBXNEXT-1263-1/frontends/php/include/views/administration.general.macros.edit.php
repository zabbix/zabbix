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


require_once dirname(__FILE__).'/js/administration.general.macros.edit.js.php';

$widget = (new CWidget())->setTitle(_('Macros'));

$header_form = (new CForm())->cleanItems();

$controls = new CList();
$controls->addItem(new CComboBox('configDropDown', 'adm.macros.php',
	'redirect(this.options[this.selectedIndex].value);',
	[
		'adm.gui.php' => _('GUI'),
		'adm.housekeeper.php' => _('Housekeeping'),
		'adm.images.php' => _('Images'),
		'adm.iconmapping.php' => _('Icon mapping'),
		'adm.regexps.php' => _('Regular expressions'),
		'adm.macros.php' => _('Macros'),
		'adm.valuemapping.php' => _('Value mapping'),
		'adm.workingtime.php' => _('Working time'),
		'adm.triggerseverities.php' => _('Trigger severities'),
		'adm.triggerdisplayoptions.php' => _('Trigger displaying options'),
		'adm.other.php' => _('Other')
	]
));

$header_form->addItem($controls);

$widget->setControls($header_form);

$table = (new CTable())->
	addClass('formElementTable')->
	setAttribute('id', 'tbl_macros')->
	addRow([
		_('Macro'), '',
		_('Value'),
		''
	]);

// fields
foreach ($data['macros'] as $i => $macro) {
	$macro_input = new CTextBox('macros['.$i.'][macro]', $macro['macro'], 30, false, 64);
	$macro_input->addClass('macro');
	$macro_input->setAttribute('placeholder', '{$MACRO}');

	$value_input = new CTextBox('macros['.$i.'][value]', $macro['value'], 40, false, 255);
	$value_input->setAttribute('placeholder', _('value'));

	$button_cell = [new CButton('macros['.$i.'][remove]', _('Remove'), null, 'link_menu element-table-remove')];
	if (array_key_exists('globalmacroid', $macro)) {
		$button_cell[] = new CVar('macros['.$i.'][globalmacroid]', $macro['globalmacroid']);
	}

	$table->addRow([$macro_input, '&rArr;', $value_input, $button_cell], 'form_row');
}

// buttons
$buttons_column = new CCol(new CButton('macro_add', _('Add'), null, 'link_menu element-table-add'));
$buttons_column->setAttribute('colspan', 5);

$table->addRow(new CRow($buttons_column, null, 'row_new_macro'));

// form list
$macros_form_list = new CFormList('macrosFormList');
$macros_form_list->addRow($table);

$tab_view = new CTabView();
$tab_view->addTab('macros', _('Macros'), $macros_form_list);

$saveButton = new CSubmit('update', _('Update'));
$saveButton->setAttribute('data-removed-count', 0);
$saveButton->main();

$tab_view->setFooter(makeFormFooter(null, [$saveButton]));

$form = new CForm();
$form->setName('macrosForm');
$form->addItem($tab_view);

$widget->addItem($form);

return $widget;
