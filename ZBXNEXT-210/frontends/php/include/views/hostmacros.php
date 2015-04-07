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
	$table = new CTable(null, 'formElementTable');
	$table->setAttribute('id', 'tbl_macros');
	$actions_col = $data['readonly'] ? null : '';
	if ($data['show_inherited_macros']) {
		$table->addRow(array(_('Macro'), '', _('Value'), $actions_col, '', _('Template value'), '', _('Global value')));
	}
	else {
		$table->addRow(array(_('Macro'), '', _('Value'), $actions_col));
	}

	// fields
	foreach ($data['macros'] as $i => $macro) {
		$macro_input = new CTextBox('macros['.$i.'][macro]', $macro['macro'], 30, false, 64);
		$macro_input->setReadOnly(
			$data['readonly'] || ($data['show_inherited_macros'] && ($macro['type'] & 0x01))	/* 0x01 - INHERITED */
		);
		$macro_input->addClass('macro');
		$macro_input->setAttribute('placeholder', '{$MACRO}');

		$value_input = new CTextBox('macros['.$i.'][value]', $macro['value'], 40, false, 255);
		$value_input->setReadOnly(
			$data['readonly'] || ($data['show_inherited_macros'] && !($macro['type'] & 0x02))	/* 0x02 - HOSTMACRO */
		);
		$value_input->setAttribute('placeholder', _('value'));

		$button_cell = null;
		if (!$data['readonly']) {
			if ($data['show_inherited_macros']) {
				if ($macro['type'] & 0x01) {
					if ($macro['type'] & 0x02) {
						$button_cell = array(
							new CButton('macros['.$i.'][change]', _('Remove'), null, 'link_menu element-table-change')
						);
					}
					else {
						$button_cell = array(
							new CButton('macros['.$i.'][change]', _('Change'), null, 'link_menu element-table-change')
						);
					}
				}
				else {
					$button_cell = array(
						new CButton('macros['.$i.'][remove]', _('Remove'), null, 'link_menu element-table-remove')
					);
				}
			}
			else {
				$button_cell = array(
					new CButton('macros['.$i.'][remove]', _('Remove'), null, 'link_menu element-table-remove')
				);
			}
			if (array_key_exists('hostmacroid', $macro)) {
				$button_cell[] = new CVar('macros['.$i.'][hostmacroid]', $macro['hostmacroid']);
			}
			if ($data['show_inherited_macros']) {
				$button_cell[] = new CVar('macros['.$i.'][type]', $macro['type']);
			}
		}

		$row = array($macro_input, '&rArr;', $value_input, $button_cell);

		if ($data['show_inherited_macros']) {
			if (array_key_exists('template', $macro)) {
				$row[] = '&lArr;';
				$value = new CTextBox('macros['.$i.'][template][value]', $macro['template']['value'], 40, true, 64);
				if ($macro['template']['rights'] == PERM_READ_WRITE) {
					$value->setHint(new CLink($macro['template']['name'],
						'templates.php?form=update&templateid='.$macro['template']['templateid']
					));
				}
				else {
					$value->setHint($macro['template']['name']);
				}
				$row[] = $value;

			}
			else {
				$row[] = '';
				$row[] = '';
			}

			if (array_key_exists('global', $macro)) {
				$row[] = '&lArr;';
				$row[] = new CTextBox('macros['.$i.'][global][value]', $macro['global']['value'], 40, true, 64);
			}
			else {
				$row[] = '';
				$row[] = '';
			}
		}

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

	$show_inherited_macros_filter = array(
		new CRadioButton('show_inherited_macros', '0', null, 'hide_inherited_macros', !$data['show_inherited_macros'], 'submit()'),
		new CLabel(_('Host macros'), 'hide_inherited_macros'),
		new CRadioButton('show_inherited_macros', '1', null, 'show_inherited_macros', $data['show_inherited_macros'], 'submit()'),
		new CLabel(_('Inherited and host macros'), 'show_inherited_macros')
	);

	$macros_form_list->addRow(null, new CDiv($show_inherited_macros_filter, 'jqueryinputset radioset'));
	$macros_form_list->addRow(null, $table);
}

return $macros_form_list;
