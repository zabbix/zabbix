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
$change_container = new CDiv(new CPartial('configuration.valuemap', [
	'source' => 'template',
	'valuemaps' => [],
	'readonly' => false,
	'form' => 'massupdate'
]));

$rename_container = (new CTable())
	->setId('valuemap-rename-table')
	->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
	->setHeader([_('From'), _('To'), _('Action')])
	->addItem((new CTag('tfoot', true))->addItem([
		new CCol([
			(new CButton(null, _('Add')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-add')
		])
]));

$remove_container = (new CDiv())->addClass('valuemap-remove');

$remove_container->addItem([
	(new CMultiSelect([
		'name' => 'valuemap[]',
		'object_name' => 'valuemaps',
		'data' => [],
		'popup' => [
			'parameters' => [
				'srctbl' => 'valuemaps',
				'srcfld1' => 'valuemapid',
				'dstfrm' => 'massupdate-form',
				'dstfld1' => 'valuemap_',
				'hostids' => $data['hostids'],
				'editable' => true
			]
		]
	]))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	(new CDiv(
		(new CCheckBox('valuemap_checkbox'))
			->setLabel(_('Except selected'))
			->setUncheckedValue(0)
	))->addClass('valuemap-checkbox')
]);

$remove_all_container = (new CDiv())->addItem((new CDiv(
	(new CCheckBox('valuemap_remove_all'))
		->setLabel(_('I confirm to remove all macros'))
)));

$form_list = (new CFormList('valuemap-form-list'))
	->addRow(
		(new CVisibilityBox('visible[valuemaps]', 'valuemap-div', _('Original')))
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
			$change_container->setAttribute('data-type', ZBX_ACTION_ADD),
			$rename_container->setAttribute('data-type', ZBX_ACTION_RENAME),
			$remove_container->setAttribute('data-type', ZBX_ACTION_REMOVE),
			$remove_all_container->setAttribute('data-type', ZBX_ACTION_REMOVE_ALL)
		]))->setId('valuemap-div')
	);

$form_list->show();
