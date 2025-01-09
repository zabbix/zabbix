<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CPartial $this
 */
$change_container = new CDiv(new CPartial('configuration.valuemap', [
	'context' => $data['context'],
	'valuemaps' => [],
	'readonly' => false,
	'form' => 'massupdate',
	'table_id' => 'valuemap-table'
]));

$update_existing = (new CDiv(
	(new CCheckBox('valuemap_update_existing'))->setLabel(_('Update existing'))
))->addClass(ZBX_STYLE_CHECKBOX_BLOCK);

$add_missing = (new CDiv(
	(new CCheckBox('valuemap_add_missing'))->setLabel(_('Add missing'))
))->addClass(ZBX_STYLE_CHECKBOX_BLOCK);

$rename_container = (new CTable())
	->setId('valuemap-rename-table')
	->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
	->addStyle('width:100%;')
	->setHeader([
		_('From'),
		_('To'),
		''
	])
	->setFooter(new CCol(
		(new CButtonLink(_('Add')))->addClass('element-table-add')
	));

$remove_container = (new CDiv())->addClass('valuemap-remove');

$remove_container->addItem([
	(new CMultiSelect([
		'name' => 'valuemap_remove[]',
		'object_name' => 'valuemap_names',
		'data' => [],
		'popup' => [
			'parameters' => [
				'srctbl' => 'valuemap_names',
				'srcfld1' => 'valuemapid',
				'dstfrm' => 'massupdate-form',
				'dstfld1' => 'valuemap_remove_',
				'hostids' => $data['hostids'],
				'context' => $data['context'],
				'editable' => true
			]
		]
	]))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	(new CDiv(
		(new CCheckBox('valuemap_remove_except'))
			->setLabel(_('Except selected'))
			->setUncheckedValue(0)
	))->addClass(ZBX_STYLE_VALUEMAP_CHECKBOX)
]);

$remove_all_container = (new CDiv())->addItem((new CDiv(
	(new CCheckBox('valuemap_remove_all'))
		->setLabel(_('I confirm to remove all value mappings'))
)));

$form_list = (new CFormList('valuemap-form-list'))
	->addRow(
		(new CVisibilityBox('visible[valuemaps]', 'valuemap-field', _('Original')))
			->setLabel(_('Value mapping'))
			->setChecked(array_key_exists('valuemaps', $data['visible'])),
		(new CDiv([
			(new CRadioButtonList('valuemap_massupdate', ZBX_ACTION_ADD))
				->addValue(_('Add'), ZBX_ACTION_ADD)
				->addValue(_('Update'), ZBX_ACTION_REPLACE)
				->addValue(_('Rename'), ZBX_ACTION_RENAME)
				->addValue(_('Remove'), ZBX_ACTION_REMOVE)
				->addValue(_('Remove all'), ZBX_ACTION_REMOVE_ALL)
				->setModern(true)
				->addStyle('margin-bottom: 10px;'),
			$change_container->setAttribute('data-type', ZBX_ACTION_ADD.','.ZBX_ACTION_REPLACE),
			$update_existing->setAttribute('data-type', ZBX_ACTION_ADD),
			$add_missing->setAttribute('data-type', ZBX_ACTION_REPLACE),
			$rename_container->setAttribute('data-type', ZBX_ACTION_RENAME),
			$remove_container->setAttribute('data-type', ZBX_ACTION_REMOVE),
			$remove_all_container->setAttribute('data-type', ZBX_ACTION_REMOVE_ALL)
		]))->setId('valuemap-field')
	);

$form_list->show();
