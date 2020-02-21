<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

$data['macros'] = [['macro' => '', 'type' => 0, 'value' => '', 'description' => '']];

foreach ($data['macros'] as $i => $macro) {
	$macro_input = (new CTextAreaFlexible('macros['.$i.'][macro]', $macro['macro']))
		->addClass('macro')
		->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
		->setAttribute('placeholder', '{$MACRO}');

	if ($i == 0) {
		$macro_input->setAttribute('autofocus', 'autofocus');
	}

	// Macro value input group.
	$value_input_group = (new CDiv())
		->addClass(ZBX_STYLE_INPUT_GROUP)
		->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH);

	$value_input = ($macro['type'] == ZBX_MACRO_TYPE_TEXT)
		? (new CTextAreaFlexible('macros['.$i.'][value]', CMacrosResolverGeneral::getMacroValue($macro)))
			->setAttribute('placeholder', _('value'))
		: (new CInputSecret('macros['.$i.'][value]', ZBX_MACRO_SECRET_MASK, _('value')));

	$dropdown_options = [
		'title' => _('Change type'),
		'active_class' => ($macro['type'] == ZBX_MACRO_TYPE_TEXT) ? ZBX_STYLE_ICON_TEXT : ZBX_STYLE_ICON_SECRET_TEXT,
		'items' => [
			['label' => _('Text'), 'value' => ZBX_MACRO_TYPE_TEXT, 'class' => ZBX_STYLE_ICON_TEXT],
			['label' => _('Secret text'), 'value' => ZBX_MACRO_TYPE_SECRET, 'class' => ZBX_STYLE_ICON_SECRET_TEXT]
		]
	];

	$value_input_group->addItem([
		$value_input,
		($macro['type'] == ZBX_MACRO_TYPE_SECRET)
			? (new CButton(null))
				->setAttribute('title', _('Revert changes'))
			->addClass(ZBX_STYLE_BTN_ALT . ' ' . ZBX_STYLE_BTN_UNDO)
			: null,
		new CButtonDropdown('macros['.$i.'][type]', $macro['type'], $dropdown_options)
	]);

	$description_input = (new CTextAreaFlexible('macros['.$i.'][description]', $macro['description']))
		->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
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
		(new CCol($value_input_group))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($description_input))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($button_cell))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');
}

$table->setFooter(new CCol(
	(new CButton('macro_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
));

$checkbox_add = (new CDiv((new CCheckBox('add_checkbox'))->setLabel('Update existing')))
	->addClass(ZBX_STYLE_CHECKBOX_BLOCK)
	->setAttribute('data-type', ZBX_ACTION_ADD)
	->addStyle('display: block;');

$checkbox_update = (new CDiv((new CCheckBox('add_checkbox'))->setLabel('Add missing')))
	->addClass(ZBX_STYLE_CHECKBOX_BLOCK)
	->setAttribute('data-type', ZBX_ACTION_REPLACE);

$checkbox_remove = (new CDiv((new CCheckBox('add_checkbox'))->setLabel('Except selected')))
	->addClass(ZBX_STYLE_CHECKBOX_BLOCK)
	->setAttribute('data-type', ZBX_ACTION_REMOVE);

$checkbox_remove_all = (new CDiv((new CCheckBox('add_checkbox'))->setLabel('I confirm to remove all macros')))
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

$this->includeJsFile('massupdate.macros.tab.js.php');
