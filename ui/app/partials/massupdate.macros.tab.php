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

$table = (new CTable())
	->setId('tbl_macros')
	->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
	->setHeader([_('Macro'), _('Value'), _('Description')]);

foreach ($data['macros'] as $i => $macro) {
	$macro_input = (new CTextAreaFlexible('macros['.$i.'][macro]', $macro['macro']))
		->addClass('macro')
		->setAdaptiveWidth(ZBX_TEXTAREA_MACRO_WIDTH)
		->setAttribute('placeholder', '{$MACRO}');

	if ($i == 0) {
		$macro_input->setAttribute('autofocus', 'autofocus');
	}

	$macro_value = new CMacroValue($macro['type'], 'macros['.$i.']');

	if ($macro['type'] == ZBX_MACRO_TYPE_SECRET) {
		$macro_value->addRevertButton();
		$macro_value->setRevertButtonVisibility(array_key_exists('value', $macro)
			&& array_key_exists('globalmacroid', $macro)
		);
	}

	if (array_key_exists('value', $macro)) {
		$macro_value->setAttribute('value', $macro['value']);
	}

	$description_input = (new CTextAreaFlexible('macros['.$i.'][description]', $macro['description']))
		->setAdaptiveWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
		->setMaxlength(DB::getFieldLength('globalmacro', 'description'))
		->setAttribute('placeholder', _('description'));

	$button_cell = [
		(new CButton('macros['.$i.'][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
	];
	if (array_key_exists('globalmacroid', $macro)) {
		$button_cell[] = new CVar('macros['.$i.'][globalmacroid]', $macro['globalmacroid']);
	}

	$table->addRow([
		(new CCol($macro_input))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($macro_value))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($description_input))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($button_cell))
			->addClass(ZBX_STYLE_NOWRAP)
			->addClass(ZBX_STYLE_TOP)
	], 'form_row');
}

$table->setFooter(new CCol(
	(new CButton('macro_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
));

$checkbox_add = (new CDiv(
	(new CCheckBox('macros_add'))
		->setLabel(_('Update existing'))
		->setChecked($data['macros_checkbox'][ZBX_ACTION_ADD])
))
	->addClass(ZBX_STYLE_CHECKBOX_BLOCK)
	->setAttribute('data-type', ZBX_ACTION_ADD)
	->addStyle('display: block;');

$checkbox_update = (new CDiv(
	(new CCheckBox('macros_update'))
		->setLabel(_('Add missing'))
		->setChecked($data['macros_checkbox'][ZBX_ACTION_REPLACE])
))
	->addClass(ZBX_STYLE_CHECKBOX_BLOCK)
	->setAttribute('data-type', ZBX_ACTION_REPLACE);

$checkbox_remove = (new CDiv(
	(new CCheckBox('macros_remove'))
		->setLabel(_('Except selected'))
		->setChecked($data['macros_checkbox'][ZBX_ACTION_REMOVE])
))
	->addClass(ZBX_STYLE_CHECKBOX_BLOCK)
	->setAttribute('data-type', ZBX_ACTION_REMOVE);

$checkbox_remove_all = (new CDiv(
	(new CCheckBox('macros_remove_all'))
		->setLabel(_('I confirm to remove all macros'))
		->setChecked($data['macros_checkbox'][ZBX_ACTION_REMOVE_ALL])
))
	->addClass(ZBX_STYLE_CHECKBOX_BLOCK)
	->setAttribute('data-type', ZBX_ACTION_REMOVE_ALL);

$form_list = (new CFormList('macros-form-list'))
	->addRow(
		(new CVisibilityBox('visible[macros]', 'macros-div', _('Original')))
			->setLabel(_('Macros'))
			->setChecked(array_key_exists('macros', $data['visible'])),
		(new CDiv([
			(new CRadioButtonList('mass_update_macros', ZBX_ACTION_ADD))
				->addValue(_('Add'), ZBX_ACTION_ADD)
				->addValue(_('Update'), ZBX_ACTION_REPLACE)
				->addValue(_('Remove'), ZBX_ACTION_REMOVE)
				->addValue(_('Remove all'), ZBX_ACTION_REMOVE_ALL)
				->setModern(true)
				->addStyle('margin-bottom: 10px;'),
			$table,
			$checkbox_add,
			$checkbox_update,
			$checkbox_remove,
			$checkbox_remove_all
		]))->setId('macros-div')
	);

$form_list->show();
