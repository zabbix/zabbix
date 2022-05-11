<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CPartial $this
 */

if ($data['readonly'] && !$data['macros']) {
	$table = new CObject(_('No macros found.'));
}
else {
	$table = (new CTable())
		->setId('tbl_macros')
		->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
		->addClass('host-macros-table')
		->setColumns([
			(new CTableColumn(_('Macro')))->addClass('table-col-macro'),
			(new CTableColumn(_('Value')))->addClass('table-col-value'),
			(new CTableColumn(_('Description')))->addClass('table-col-description'),
			$data['readonly'] ? null : (new CTableColumn())->addClass('table-col-action')
		]);

	foreach ($data['macros'] as $i => $macro) {
		$macro_input = (new CTextAreaFlexible('macros['.$i.'][macro]', $macro['macro']))
			->setReadonly($data['readonly'])
			->addClass('macro')
			->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
			->setAttribute('placeholder', '{$MACRO}');
		$macro_value = (new CMacroValue($macro['type'], 'macros['.$i.']', null, false))
			->setReadonly($data['readonly']);
		$macro_cell = [$macro_input];

		if (!$data['readonly']) {
			if (array_key_exists('hostmacroid', $macro)) {
				$macro_cell[] = new CVar('macros['.$i.'][hostmacroid]', $macro['hostmacroid']);
			}

			$action_btn = (new CButton('macros['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove');
		}
		else {
			$action_btn = null;
		}

		if ($macro['type'] == ZBX_MACRO_TYPE_SECRET) {
			$macro_value->addRevertButton();
			$macro_value->setRevertButtonVisibility(array_key_exists('value', $macro)
				&& array_key_exists('hostmacroid', $macro)
			);
		}

		if (array_key_exists('value', $macro)) {
			$macro_value->setAttribute('value', $macro['value']);
		}

		$table->addRow([
			(new CCol($macro_cell))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol($macro_value))
				->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)
				->addClass(ZBX_STYLE_NOWRAP),
			(new CCol(
				(new CTextAreaFlexible('macros['.$i.'][description]', $macro['description']))
					->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					->setMaxlength(DB::getFieldLength('hostmacro', 'description'))
					->setReadonly($data['readonly'])
					->setAttribute('placeholder', _('description'))
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			$action_btn ? (new CCol($action_btn))->addClass(ZBX_STYLE_NOWRAP) : null
		], 'form_row');
	}

	// buttons
	if (!$data['readonly']) {
		$table->setFooter(new CCol(
			(new CButton('macro_add', _('Add')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-add')
		));
	}
}

$table->show();

// Initializing input secret and macro value init script separately.
(new CScriptTag("jQuery('.input-secret').inputSecret();"))->show();
(new CScriptTag("jQuery('.macro-input-group').macroValue();"))->show();
