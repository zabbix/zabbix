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
	->setId('valuemap-table')
	->addClass(ZBX_STYLE_VALUEMAP_LIST_TABLE)
	->setColumns([
		(new CTableColumn(_('Name')))
			->addStyle('width: '.ZBX_TEXTAREA_MAPPING_VALUE_WIDTH.'px;')
			->addClass('table-col-handle'),
		(new CTableColumn(_('Value')))
			->addStyle('width: '.ZBX_TEXTAREA_MAPPING_NEWVALUE_WIDTH.'px;')
			->addClass('table-col-handle'),
		(new CTableColumn(_('Action')))
			->addClass('table-col-handle')
	]);

$buttons = [
	(new CButton('valuemap_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
		->setEnabled(!$data['readonly'])
];

if ($data['form'] === 'massupdate') {
	$buttons[] = (new CButton(null, _('Add from')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-addfrom');
}

$table->addItem((new CTag('tfoot', true))->addItem([new CCol($buttons)]));

$table->show();

$this->includeJsFile('configuration.valuemap.js.php', ['valuemaps' => $data['valuemaps']]);
