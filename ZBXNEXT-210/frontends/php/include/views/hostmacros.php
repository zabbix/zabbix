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

if (!$data['readonly']) {
	require_once dirname(__FILE__).'/js/hostmacros.js.php';
}

// form list
$macros_form_list = new CFormList('macrosFormList');

if ($data['readonly'] && !$data['macros']) {
	$macros_form_list->addRow(_('No macros found.'));
}
else {
	$table = new CTable(SPACE, 'formElementTable');
	$table->setAttribute('id', 'tbl_macros');
	$table->addRow(array(_('Macro'), SPACE, _('Value'), SPACE));

	// fields
	foreach ($data['macros'] as $i => $macro) {
		$macro_input = new CTextBox('macros['.$i.'][macro]', $macro['macro'], 30, $data['readonly'], 64);
		$macro_input->addClass('macro');
		$macro_input->setAttribute('placeholder', '{$MACRO}');

		$value_input = new CTextBox('macros['.$i.'][value]', $macro['value'], 40, $data['readonly'], 255);
		$value_input->setAttribute('placeholder', _('value'));

		$remove_button = null;
		if (!$data['readonly']) {
			$remove_button = array(
				new CButton('macros['.$i.'][remove]', _('Remove'), null, 'link_menu element-table-remove')
			);
			if (array_key_exists('hostmacroid', $macro)) {
				$remove_button[] = new CVar('macros['.$i.'][hostmacroid]', $macro['hostmacroid'],
					'macros_'.$i.'_hostmacroid'
				);
			}
		}

		$row = array($macro_input, '&rArr;', $value_input, $remove_button);
		$table->addRow($row, 'form_row');
	}

	// buttons
	if (!$data['readonly']) {
		$buttons_column = new CCol(new CButton('macro_add', _('Add'), null, 'link_menu element-table-add'));
		$buttons_column->setAttribute('colspan', 5);

		$buttons_row = new CRow();
		$buttons_row->setAttribute('id', 'row_new_macro');
		$buttons_row->addItem($buttons_column);

		$table->addRow($buttons_row);
	}

	$macros_form_list->addRow($table);
}

return $macros_form_list;
