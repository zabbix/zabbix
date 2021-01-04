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
	->setId('valuemap-table')
	->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
	->addStyle('width:100%;')
	->setHeader([_('Name'), _('Value'), _('Action')]);

$table->addItem([
	(new CTag('tfoot', true))->addItem([
		new CCol(
			(new CButton('valuemap_add', _('Add')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-add')
				->onClick('return PopUp("popup.valuemap.edit", jQuery.extend('.
					json_encode([]).', null), null, this);'
				)
		)
	])
]);

$remove_div = (new CDiv())->addClass('valuemap-remove');

$remove_div
	->addItem([
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

$form_list = (new CFormList('valuemap-form-list'))
	->addRow(
		(new CVisibilityBox('visible[valuemaps]', 'valuemap-div', _('Original')))
			->setLabel(_('Value mapping'))
			->setChecked(array_key_exists('valuemaps', $data['visible'])),
		(new CDiv([
			(new CRadioButtonList('valuemap_massupdate', ZBX_ACTION_ADD))
				->addValue(_('Add'), ZBX_ACTION_ADD)
				->addValue(_('Update'), ZBX_ACTION_REPLACE)
				->addValue(_('Remove'), ZBX_ACTION_REMOVE)
				->setModern(true)
				->addStyle('margin-bottom: 10px;'),
			$table,
			$remove_div
		]))->setId('valuemap-div')
	);

$form_list->addItem(new CJsScript($this->readJsFile('configuration.valuemap.js.php')));

$form_list->show();
